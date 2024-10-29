<?php

namespace WP_Arvan\OBS\Admin\Controllers;

use WP_Arvan\OBS\CustomDB;
use WP_Arvan\OBS\Helper;
use WP_Arvan\OBS\Admin\Partials;
use WP_Arvan\OBS\Kueue\KueueCore;
use WP_Arvan\OBS\S3Singletone;

class EmptyCurrentBucketController
{
    private static $instance;
    private $s3client;
    private const PAGE_LIMIT = 100;
    private static $force_stop = false;

    public function __construct()
    {
        if ( ! defined( 'WPINC' ) ) {
            die;
        }
        $this->s3_client = (S3Singletone::get_instance())->get_s3client();
        add_action('obs_do_empty_current_bucket', [$this, 'do_empty_current_bucket']);
    }

    public static function get_instance()
    {

        if (null == self::$instance) {
            self::$instance = new EmptyCurrentBucketController();
        }
        return self::$instance;
    }

    public function render_view()
    {

        Partials::empty_current_bucket_modal();
        wp_die();

    }
    public function control(){
        if ('post' == strtolower($_SERVER['REQUEST_METHOD']) ) {
            Helper::check_bulk_ops_nonce();
            Helper::check_user_authorization();
            $kueue_scheduler = KueueCore::get_instance();
            if ($kueue_scheduler->has_pending_job('obs_do_empty_current_bucket')) {
                wp_send_json_error(array(
                    'success' => 'false',
                    'message' => __('Already has pending task', 'arvancloud-object-storage')
                ), 200);
                return;

            }

            $memory_db = CustomDB::get_instance();


            $memory_db->delete_operation(array(

                'status' => 'done'
            ));
            $memory_db->delete_operation(array(
                'operation' => 'PURGING_PROGRESS'
            ));

            $memory_db->delete_operation(array(
                'operation' => 'STOP_PURGING_PROCESS',

            ));


            $bucket = Helper::get_bucket_name();
            $all_downloadable_file_names = $this->get_full_file_list($bucket);

            if(empty($all_downloadable_file_names)){
                wp_send_json_success( array(
                    'success'=>'false',
                    'message'=> __('No data to process' ,'arvancloud-object-storage' )
                ),200);
                wp_die();
            }

            $total_source_files = [];
            if (isset($_POST['reschedule'])) {

                /*
                 * Load
                 */
                $total_source_files = $memory_db->get_option_by_fields(array(
                    'operation' => 'PURGE',
                    'status' => 'pending'
                ));


            } else {

                $memory_db->delete_operation(array(
                    'operation' => 'PURGE'
                ));

                $s3client = (S3Singletone::get_instance())->get_s3client();


                foreach ($all_downloadable_file_names as $source_file) {

                    $is_already_pending = $memory_db->get_option_by_fields(array(
                        'operation' => 'PURGE',
                        'key' => $source_file,
                        'status' => 'pending'
                    ));

                    if (is_array($is_already_pending) && (count($is_already_pending) > 0))
                        continue;


                    $memory_db->create_operation(array(
                        'operation' => 'PURGE',
                        'source' => '',
                        'destination' => '',
                        'key' => $source_file,
                        'status' => 'pending'
                    ));
                    $total_source_files[] = $source_file;

                }
            }

            $files_count = count($total_source_files);

            $task_status = array(
                'operation' => 'PURGING_PROGRESS',
                'key' => [
                    'files_count' => $files_count,
                    'processed_files_count' => 0,
                    'task_status' => 'processing'
                ]
            );

            $memory_db->create_operation($task_status);


            $kueue = KueueCore::get_instance();

            $time = 0;

            for ($i = 0; $i < $files_count; $i++) {

                if (0 == ($i % self::PAGE_LIMIT)) {
                    $kueue->add_job(time() + ($time * MINUTE_IN_SECONDS), 0, 'obs_do_empty_current_bucket', array($i));
                    $time++;
                }

            }


            $kueue_scheduler->schedule_jobs();

            wp_send_json_success(array(
                'success' => 'true',
                'message' => __('Successfully scheduled, Please wait a second', 'arvancloud-object-storage')
            ), 200);
            return;
        }
    }
    public function do_empty_current_bucket(){
        ini_set('max_execution_time', MINUTE_IN_SECONDS * 3);




        $memory_db = CustomDB::get_instance();
        $bucket_name = Helper::get_bucket_name();
        $all_pending_files =  $memory_db->get_option_by_fields(array(
            'operation'=>'PURGE',
            'status'=>'pending'
        ));


        if(!is_array($all_pending_files) || count($all_pending_files) <=0)
            return false;


        foreach ($all_pending_files as $pending_file){
            $result = $memory_db::get_single_item($memory_db->get_option_by_fields(array('operation'=>'STOP_PURGING_PROCESS')));
            $should_stop =  ($result['status']??'false');

            if(  'true' == $should_stop ){

                $current_status = $memory_db->get_option_by_fields(array(
                    'operation' => 'PURGING_PROGRESS'
                ));
                $current_status = $memory_db::get_single_item($current_status);
                $key = maybe_unserialize($current_status['key']);

                $key['task_status'] = 'stop';


                $memory_db->update_option(array(
                    'key'=>maybe_serialize($key)
                ), array(
                    'operation' => 'PURGING_PROGRESS'
                ));

                break;

            }

            $this->s3_client->deleteObject(array(
                    'Bucket' => $bucket_name,
                    'Key' => $pending_file['key'])
            );

            $id = $pending_file['id'];

            $memory_db->update_option(array(
                'status' => 'done'
            ), array(
                'id'=>$id
            ));

            $progress_status = $memory_db::get_single_item($memory_db->get_option_by_fields(array(
                'operation'=>'PURGING_PROGRESS'
            )));

            $done_files = $memory_db->get_option_by_fields(array(
                'operation'=>'PURGE',
                'status'=>'done'
            ));

            $status = maybe_unserialize($progress_status['key']);

            $memory_db->update_option(array(

                'key'=> maybe_serialize( [
                    'files_count' => $status['files_count'],
                    'processed_files_count' => count($done_files),
                    'task_status' => 'processing'
                ])

            ), array(
                'operation'=>'PURGING_PROGRESS'
            ));




        }
        return true;

    }
    public function stop_current_bucket_emptying_task()
    {

        if ('post' == strtolower($_SERVER['REQUEST_METHOD'])) {

            $result = (new KueueCore())->stop_process('obs_do_empty_current_bucket');
            $memory_db = CustomDB::get_instance();
            $memory_db->create_operation(array(
                'operation' => 'STOP_PURGING_PROCESS',
                'status' => 'true'));

            $current_status = $memory_db->get_option_by_fields(array(
                'operation' => 'PURGING_PROGRESS'
            ));
            $current_status = $memory_db::get_single_item($current_status);
            $key = maybe_unserialize($current_status['key']);

            $key['task_status'] = 'stop';


            $memory_db->update_option(array(
                'key' => maybe_serialize($key)
            ), array(
                'operation' => 'PURGING_PROGRESS'
            ));

            if (!$result) {

                wp_send_json_success(array(
                    'success' => 'false',
                    'response' => 'Failed'
                ), 200);
            } else {

                wp_send_json_success(array(
                    'success' => 'true',
                    'response' => __('Successfully stopped', 'arvancloud-object-storage')
                ), 200);
            }
        }

    }
        public
        function get_full_file_list($bucket)
        {
            $s3client = (S3Singletone::get_instance())->get_s3client();
            $results = $s3client->getPaginator('ListObjectsV2', [
                'Bucket' => $bucket
            ]);

            $keys = array();

            foreach ($results->search('Contents[].Key') as $key) {
                $keys[] = $key;
            }

            return $keys;
        }


        public function get_bucket_all_files_count($bucket)
        {
            $s3client = (S3Singletone::get_instance())->get_s3client();
            $result = $s3client->headBucket([
                'Bucket' => $bucket,
            ]);
            return $result['@metadata']['headers']['x-rgw-object-count'] ?? 0;

        }

        public
        function get_task_status()
        {
            $memory_db = CustomDB::get_instance();

            $result = $memory_db::get_single_item($memory_db->get_option_by_fields(array(
                'operation' => 'PURGING_PROGRESS'
            )));

            $option_values = maybe_unserialize($result['key']);
            $option_values['files_done'] = sprintf("%d " . __('of', 'arvancloud-object-storage') . " %d", $option_values['processed_files_count'], $option_values['files_count']);


            if ('stop' == $option_values['task_status']) {

                wp_send_json_success(array(
                    'success' => 'true',
                    'response' => $option_values
                ), 200);
                wp_die();

            } else {

                if (!(KueueCore::get_instance())->has_pending_job('obs_do_empty_current_bucket')) {
                    $option_values['task_status'] = 'done';
                }

            }

            wp_send_json_success(array(
                'success' => 'true',
                'response' => $option_values
            ), 200);
            wp_die();


        }
    }