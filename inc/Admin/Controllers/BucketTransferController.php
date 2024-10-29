<?php

namespace WP_Arvan\OBS\Admin\Controllers;

use WP_Arvan\OBS\Admin\Partials;
use WP_Arvan\OBS\CustomDB;
use WP_Arvan\OBS\Helper;
use WP_Arvan\OBS\Kueue\KueueCore;
use WP_Arvan\OBS\S3Singletone;

class BucketTransferController
{
    public  $s3_client;
    private $wp_upload_folder;
    const PAGE_LIMIT = 100;
    private static $force_stop = false;
    public function __construct()
    {
        $this->s3_client = (S3Singletone::get_instance())->get_s3client();

        add_action('obs_do_transfer_from_source_to_destination', array($this, 'do_transfer_from_source_to_destination'),10,1);
        add_action('obs_rewrite_file_urls', array($this, 'rewrite_file_urls'),10,1);


    }

    public function render_view(){
        Partials::migrate_to_new_bucket_modal();
        wp_die();
    }


    public function get_bucket_list()
    {

        $list_response = $this->s3_client->listBuckets();
        return $list_response[ 'Buckets' ];
    }



    public function control(){

        if(('post' == strtolower($_SERVER['REQUEST_METHOD'])) ) {
            Helper::check_bulk_ops_nonce();
            Helper::check_user_authorization();
            $from = sanitize_text_field($_POST['bucket-files-transfer-from']) ?? null;
            $to = sanitize_text_field($_POST['bucket-files-transfer-to']) ?? null;

            $key_count = $this->get_bucket_all_files_count($from);

            if($key_count<=0){
                wp_send_json_success( array(
                    'success'=>'false',
                    'message'=> __('No data to process' ,'arvancloud-object-storage' )
                ),200);
                wp_die();
            }

            if (empty($from) || empty($to)) {
                wp_send_json_error(array(
                    'success' => 'false',
                    'message' => __('Source and Destination bucket should be selected', 'arvancloud-object-storage')
                ), 200);
                return;
            }


            if ('null' == $from || 'null' == $to) {
                wp_send_json_error(array(
                    'success' => 'false',
                    'message' => __('Source and Destination bucket should be selected', 'arvancloud-object-storage')
                ), 200);
                return;
            }

            if ($from == $to) {
                wp_send_json_error(array(
                    'success' => 'false',
                    'message' => __('Source and destination could not be same', 'arvancloud-object-storage')
                ), 200);

                return;
            }

            if ((KueueCore::get_instance())->has_pending_job('obs_do_transfer_from_source_to_destination')) {
                wp_send_json_error(array(
                    'success' => 'false',
                    'message' => __('Already has pending task', 'arvancloud-object-storage')
                ), 200);
                return;

            }




            $memory_db = CustomDB::get_instance();

            $memory_db->delete_operation(array(
                'operation'=>'MIGRATE',

                'status'=>'done'
            ));
            $memory_db->delete_operation(array(
                'operation'=>'MIGRATE_PROGRESS'
            ));

            $memory_db->delete_operation(array(
                'operation'=>'STOP_MIGRATION_PROCESS',

            ));



            $total_source_files = [];

            if( isset($_POST['reschedule']) ){

                $total_source_files = $memory_db->get_option_by_fields(array(
                    'operation'=>'MIGRATE',
                    'status'=>'pending'
                ));

            }else{
                $memory_db->delete_operation(array(
                    'operation'=>'MIGRATE'
                ));
                $total_source_files = $this->get_bucket_all_files($from);

                foreach ($total_source_files as $source_file) {

                    $is_already_pending = $memory_db->get_option_by_fields(array(
                        'operation'=>'MIGRATE',
                        'source'=>$from,
                        'destination'=>$to,
                        'key'=>$source_file,
                        'status'=>'pending'
                    ));

                    if( is_array($is_already_pending) && (count($is_already_pending) >0) )
                        continue;

                    $memory_db->create_operation(array(
                        'operation'=>'MIGRATE',
                        'source'=>$from,
                        'destination'=>$to,
                        'key'=>$source_file,
                        'status'=>'pending'
                    ));

                }
            }



            $files_count = count($total_source_files);

            $task_status = array(
                'operation'=>'MIGRATE_PROGRESS',
                'key'=>[
                    'files_count' => $files_count,
                    'processed_files_count' => 0,
                    'task_status' => 'processing'
                ]
            );

            $memory_db->create_operation($task_status);


            $kueue = KueueCore::get_instance();

            $time = 0;
            for ( $i =0 ;$i < $files_count; $i++) {

                if( 0 == ($i % self::PAGE_LIMIT) ) {
                    $kueue->add_job(time() + ($time * MINUTE_IN_SECONDS), 0, 'obs_do_transfer_from_source_to_destination', array($i));
                    $time++;
                }

            }
            $kueue->add_job(time() + (MINUTE_IN_SECONDS * $time), 0, 'obs_rewrite_file_urls', array($to));

            $kueue->schedule_jobs();

            wp_send_json_success(array(
                'success' => 'true',
                'message' => __('Successfully scheduled, Please wait a second', 'arvancloud-object-storage')
            ), 200);
            return;
        }
    }


    public  function do_transfer_from_source_to_destination($index){

        ini_set('max_execution_time', MINUTE_IN_SECONDS * 3);

        $bucket_name = Helper::get_bucket_name();

        $memory_db = CustomDB::get_instance();




        $items = $memory_db->get_option_by_fields(array(
            'operation' => 'MIGRATE',
            'status'=>'pending'
        ), self::PAGE_LIMIT);

        if(!$items)
            return false;

        foreach ($items as $item){

            $result = $memory_db::get_single_item($memory_db->get_option_by_fields(array('operation'=>'STOP_MIGRATION_PROCESS')));
            $should_stop =  ($result['status']??'false');

            if(  'true' == $should_stop ){

                $current_status = $memory_db->get_option_by_fields(array(
                    'operation' => 'MIGRATE_PROGRESS'
                ));
                $current_status = $memory_db::get_single_item($current_status);
                $key = maybe_unserialize($current_status['key']);

                $key['task_status'] = 'stop';


                $memory_db->update_option(array(
                    'key'=>maybe_serialize($key)
                ), array(
                    'operation' => 'MIGRATE_PROGRESS'
                ));

                break;

            }


            $id = $item['id'];
            $from = $item['source'];
            $to = $item['destination'];
            $file = $item['key'];

            try {

                $this->s3_client->copyObject([
                    'Bucket' => $to,
                    'CopySource' => "$from/$file",
                    'Key' => "$file",
                    'ACL' => 'public-read'
                ]);

                $memory_db->update_option(array(
                    'status' => 'done'
                ), array(
                    'id'=>$id
                ));

                $progress_status = $memory_db::get_single_item($memory_db->get_option_by_fields(array(
                    'operation'=>'MIGRATE_PROGRESS'
                )));

                $done_files = $memory_db->get_option_by_fields(array(
                    'operation'=>'MIGRATE',
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
                    'operation'=>'MIGRATE_PROGRESS'
                ));
            }catch (\Exception $ex){
                Helper::log_to_file($ex);
            }
        }
        return true;
    }



    public function rewrite_file_urls($bucketname){


        $args = array(
            'post_type'=> 'attachment',
            'post_status' => 'any',
            'orderby'=>'ID'
        );

        $result = new \WP_Query( $args );
        $posts = [];
        $bucket_url = Helper::get_storage_url_by_bucket_name($bucketname);

        if($result-> have_posts()){
            while ($result->have_posts()){
                $result->the_post();
                $guid = get_the_guid();
                $post = new \stdClass();
                $post->id = get_the_ID();
                $post->guid = wp_basename($guid);
                $post->url = mb_substr( $guid, 0, mb_strrpos($guid, '/')  ) . '/';
                $posts[] = $post;
                update_post_meta(get_the_ID(), 'acs_storage_file_url', $bucket_url);

            }
        }
        wp_reset_postdata();


    }

    public function get_bucket_all_files_count($bucket){
        $s3client = (S3Singletone::get_instance())->get_s3client();
        $result = $s3client->headBucket([
            'Bucket' => $bucket,
        ]);
        return $result['@metadata']['headers']['x-rgw-object-count']??0;

    }

    public function get_bucket_files_list($bucket, $separator_key, $index){

        if($index == 0)
            $args = ['Bucket'=>$bucket, 'list-type'=>2, 'MaxKeys'=> self::PAGE_LIMIT];
        else
            $args = ['Bucket'=>$bucket, 'list-type'=>2, 'StartAfter'=> $separator_key, 'MaxKeys'=> self::PAGE_LIMIT];

        $result = $this->s3_client->listObjectsV2( $args );

        $data = $result['Contents']??null;

        if(!$data)
            return false;

        $file_names = [];
        foreach($data as $file){
            $file_names[] = $file['Key'];
        }

        return $file_names;
    }

    public function get_bucket_all_files($bucket){
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


    public function stop_migrate_to_new_bucket_task(){

        if ('post' == strtolower($_SERVER['REQUEST_METHOD']) ) {
            $result = (new KueueCore())->stop_process('obs_do_transfer_from_source_to_destination');
            $memory_db = CustomDB::get_instance();
            $memory_db->create_operation(array(
                'operation'=>'STOP_MIGRATION_PROCESS',
                'status'=>'true'));

            $current_status = $memory_db->get_option_by_fields(array(
                'operation' => 'MIGRATE_PROGRESS'
            ));
            $current_status = $memory_db::get_single_item($current_status);
            $key = maybe_unserialize($current_status['key']);

            $key['task_status'] = 'stop';


            $memory_db->update_option(array(
                'key'=>maybe_serialize($key)
            ), array(
                'operation' => 'MIGRATE_PROGRESS'
            ));

            if(!$result){

                wp_send_json_success( array(
                    'success'=>'false',
                    'response'=> 'Failed'
                ),200);
            }else{

                wp_send_json_success( array(
                    'success'=>'true',
                    'response'=> __('Successfully stopped','arvancloud-object-storage')
                ),200);
            }
        }

    }

    function get_migrate_to_new_bucket_task_status(){

        $memory_db = CustomDB::get_instance();



        $result = $memory_db::get_single_item( $memory_db->get_option_by_fields(array(
            'operation'=> 'MIGRATE_PROGRESS'
        )));

        $option_values = maybe_unserialize( $result['key'] );
        $option_values['files_done'] = sprintf("%d ". __('of' ,'arvancloud-object-storage' ). " %d", $option_values['processed_files_count'], $option_values['files_count']);



        if( 'stop'  == $option_values['task_status'] ){

            wp_send_json_success( array(
                'success'=>'true',
                'response'=> $option_values
            ),200);
            wp_die();

        }else{

            if( !(KueueCore::get_instance())->has_pending_job('obs_do_transfer_from_source_to_destination')){
                $option_values['task_status'] = 'done';
            }

        }

        wp_send_json_success( array(
            'success'=>'true',
            'response'=> $option_values
        ),200);
        wp_die();
    }

    public function has_pending_task(){
        return ( true == (KueueCore::get_instance())->has_pending_job()) ? __('Has pending task', 'arvancloud-object-storage') : __('No pending task','arvancloud-object-storage');
    }

    public function get_pending_files_cound(){

        $memory_db = CustomDB::get_instance();
        $pending_files = $memory_db->get_option_by_fields(array(
            'operation'=>'MIGRATE',

            'status'=>'pending'
        ));
        return count($pending_files);
    }
}
