<?php

namespace WP_Arvan\OBS\Admin\Controllers;

use WP_Arvan\OBS\Admin\Partials;
use WP_Arvan\OBS\Helper;
use WP_Arvar\OBS;
use WP_Arvan\OBS\S3Singletone;
use WP_Arvan\OBS\CustomDB;
use WP_Arvan\OBS\Kueue\KueueCore;

class RemoveLocalFilesController
{
    private static $instance;
    private $s3client;
    private const PAGE_LIMIT = 100;
    private static $force_stop = false;

    public function __construct()
    {
        if (!defined('WPINC')) {
            die;
        }
        $this->s3_client = (S3Singletone::get_instance())->get_s3client();
        add_action('obs_do_bulk_remove', array($this, 'do_bulk_remove'), 10, 1);
    }

    public static function get_instance()
    {

        if (null == self::$instance) {
            self::$instance = new RemoveLocalFilesController();
        }
        return self::$instance;
    }

    public function control()
    {

        if (('post' == strtolower($_SERVER['REQUEST_METHOD']))) {
            Helper::check_bulk_ops_nonce();
            Helper::check_user_authorization();
            $kueue_scheduler = KueueCore::get_instance();
            if ($kueue_scheduler->has_pending_job('obs_do_bulk_remove')) {
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
                'operation' => 'DELETE_PROGRESS'
            ));

            $memory_db->delete_operation(array(
                'operation' => 'STOP_DELETE_PROCESS',

            ));

            $posts = $this->get_deletable_posts();



            $upload_dir = wp_upload_dir()['basedir'] . DIRECTORY_SEPARATOR;



            $total_source_files = [];
            foreach ($posts as $post) {


                if (!$post->should_delete)
                    continue;

                if (is_array($post->attachments) && isset($post->attachments['file'])) {

                    $file_name = $post->attachments['file'];

                    $sub_attachment_path = $upload_dir . dirname($file_name) . DIRECTORY_SEPARATOR;

                    if (file_exists($upload_dir . $file_name)) {


                        $total_source_files[] = $upload_dir . $file_name;

                    }

                    if (is_array($post->attachments['sizes'])) {

                        foreach ($post->attachments['sizes'] as $key => $value) {

                            if (file_exists($sub_attachment_path . $value['file'])) {


                                $total_source_files[] = $sub_attachment_path . $value['file'];
                            }

                        }
                    }

                }
            }


            if(empty($total_source_files)){
                wp_send_json_success( array(
                    'success'=>'false',
                    'message'=> __('No data to process' ,'arvancloud-object-storage' )
                ),200);
                wp_die();
            }

            if (isset($_POST['reschedule'])) {

                /*
                 * Load
                 */
                $total_source_files = $memory_db->get_option_by_fields(array(
                    'operation' => 'DELETE',
                    'status' => 'pending'
                ));


            } else {

                $memory_db->delete_operation(array(
                    'operation' => 'DELETE'
                ));




                foreach ($total_source_files as $source_file) {

                    $is_already_pending = $memory_db->get_option_by_fields(array(
                        'operation' => 'DELETE',
                        'key' => $source_file,
                        'status' => 'pending'
                    ));

                    if (is_array($is_already_pending) && (count($is_already_pending) > 0))
                        continue;


                    $memory_db->create_operation(array(
                        'operation' => 'DELETE',
                        'source' => '',
                        'destination' => '',
                        'key' => $source_file,
                        'status' => 'pending'
                    ));


                }
            }




            $files_count = count($total_source_files);

            $task_status = array(
                'operation' => 'DELETE_PROGRESS',
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
                    $kueue->add_job(time() + ($time * MINUTE_IN_SECONDS)+1, 0, 'obs_do_bulk_remove', array($i));
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

    public function render_view()
    {

        Partials::remove_local_files();
        wp_die();

    }


    function get_deletable_posts()
    {
        $args = array(
            'post_type' => 'attachment',
            'post_status' => 'any',
            'orderby' => 'ID',
            'posts_per_page'=>-1
        );

        $result = new \WP_Query($args);



// Print last SQL query string




        $posts = [];

        if ($result->have_posts()) {
            while ($result->have_posts()) {
                $result->the_post();
                $post_id = get_the_ID();

                $post = new \stdClass();
                $post->id = $post_id;


                $should_delete = get_metadata('post', $post_id, 'acs_storage_file_url', true);

                $post->should_delete = empty($should_delete) ? false : true;

                $post->attachments = wp_get_attachment_metadata($post_id);
                $posts[] = $post;

            }
        }


        wp_reset_postdata();

        return $posts;
    }

    public function do_bulk_remove()
    {

        ini_set('max_execution_time', MINUTE_IN_SECONDS * 3);


        $memory_db = CustomDB::get_instance();

        $all_pending_files = $memory_db->get_option_by_fields(array(
            'operation' => 'DELETE',
            'status' => 'pending'
        ));


        if (!is_array($all_pending_files) || count($all_pending_files) <= 0)
            return false;


        foreach ($all_pending_files as $pending_file) {
            $result = $memory_db::get_single_item($memory_db->get_option_by_fields(array('operation' => 'STOP_DELETE_PROCESS')));
            $should_stop = ($result['status'] ?? 'false');

            if ('true' == $should_stop) {

                $current_status = $memory_db->get_option_by_fields(array(
                    'operation' => 'DELETE_PROGRESS'
                ));
                $current_status = $memory_db::get_single_item($current_status);
                $key = maybe_unserialize($current_status['key']);

                $key['task_status'] = 'stop';


                $memory_db->update_option(array(
                    'key' => maybe_serialize($key)
                ), array(
                    'operation' => 'DELETE_PROGRESS'
                ));

                break;

            }

            unlink($pending_file['key']);

            $id = $pending_file['id'];

            $memory_db->update_option(array(
                'status' => 'done'
            ), array(
                'id' => $id
            ));

            $progress_status = $memory_db::get_single_item($memory_db->get_option_by_fields(array(
                'operation' => 'DELETE_PROGRESS'
            )));

            $done_files = $memory_db->get_option_by_fields(array(
                'operation' => 'DELETE',
                'status' => 'done'
            ));

            $status = maybe_unserialize($progress_status['key']);

            $memory_db->update_option(array(

                'key' => maybe_serialize([
                    'files_count' => $status['files_count'],
                    'processed_files_count' => count($done_files),
                    'task_status' => 'processing'
                ])

            ), array(
                'operation' => 'DELETE_PROGRESS'
            ));


        }
        return true;
    }


    public function stop_current_bulk_remove_task()
    {

        if ('post' == strtolower($_SERVER['REQUEST_METHOD'])) {

            $result = (new KueueCore())->stop_process('obs_do_bulk_remove');
            $memory_db = CustomDB::get_instance();
            $memory_db->create_operation(array(
                'operation' => 'STOP_DELETE_PROCESS',
                'status' => 'true'));

            $current_status = $memory_db->get_option_by_fields(array(
                'operation' => 'DELETE_PROGRESS'
            ));
            $current_status = $memory_db::get_single_item($current_status);
            $key = maybe_unserialize($current_status['key']);

            $key['task_status'] = 'stop';


            $memory_db->update_option(array(
                'key' => maybe_serialize($key)
            ), array(
                'operation' => 'DELETE_PROGRESS'
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


    public function get_bulk_remove_task_status()
    {

        $memory_db = CustomDB::get_instance();

        $result = $memory_db::get_single_item($memory_db->get_option_by_fields(array(
            'operation' => 'DELETE_PROGRESS'
        )));

        $option_values = maybe_unserialize($result['key']);

        if (!$option_values) {
            wp_send_json_success(array(
                'success' => 'false',
                'response' => array(
                    'operation' => 'DELETE_PROGRESS',
                    'key' => [
                        'files_count' => 0,
                        'processed_files_count' => 0,
                        'task_status' => 'processing'
                    ]
                )
            ), 200);
            wp_die();
        }

        $option_values['files_done'] = sprintf("%d " . __('of', 'arvancloud-object-storage') . " %d", $option_values['processed_files_count'], $option_values['files_count']);


        if ('stop' == $option_values['task_status']) {

            wp_send_json_success(array(
                'success' => 'true',
                'response' => $option_values
            ), 200);
            wp_die();

        } else {

            if (!(KueueCore::get_instance())->has_pending_job('obs_do_bulk_remove')) {
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
