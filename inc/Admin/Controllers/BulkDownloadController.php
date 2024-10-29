<?php

namespace WP_Arvan\OBS\Admin\Controllers;

use PHPUnit\TextUI\Help;
use WP_Arvan\OBS\Admin\Partials;
use WP_Arvan\OBS\CustomDB;
use WP_Arvan\OBS\Helper;
use WP_Arvan\OBS\Kueue\KueueCore;
use WP_Arvan\OBS\S3Singletone;
use function JmesPath\search;

class BulkDownloadController
{
    private static $instance;
    private $s3client;
    private const PAGE_LIMIT = 100;
    private static $force_stop = false;

    public function __construct(){

        if ( ! defined( 'WPINC' ) ) {
            die;
        }
        $this->s3_client = (S3Singletone::get_instance())->get_s3client();
        add_action('obs_do_bulk_download', array($this, 'do_bulk_download'),10,1);
    }
    public static function get_instance()
    {

        if (null == self::$instance) {
            self::$instance = new BulkDownloadController();
        }
        return self::$instance;
    }


    public function control(){



        if(('post' == strtolower($_SERVER['REQUEST_METHOD'])) ){
            Helper::check_bulk_ops_nonce();
            Helper::check_user_authorization();
            $kueue_scheduler = KueueCore::get_instance();
            if( $kueue_scheduler->has_pending_job('obs_do_bulk_download') )
            {
                wp_send_json_error( array(
                    'success'=>'false',
                    'message'=>__('Already has pending task', 'arvancloud-object-storage')
                ),200);
                return;

            }

            $memory_db = CustomDB::get_instance();


            $memory_db->delete_operation(array(

                'status'=>'done'
            ));
            $memory_db->delete_operation(array(
                'operation'=>'DOWNLOADING_PROGRESS'
            ));

            $memory_db->delete_operation(array(
                'operation'=>'STOP_DOWNLOADING_PROCESS',

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

            if( isset($_POST['reschedule']) ){

                /*
                 * Load
                 */
                $total_source_files = $memory_db->get_option_by_fields(array(
                    'operation'=>'DOWNLOAD',
                    'status'=>'pending'
                ));



            }else{

                $memory_db->delete_operation(array(
                    'operation'=>'DOWNLOAD'
                ));

                $s3client = (S3Singletone::get_instance())->get_s3client();


                foreach ($all_downloadable_file_names as $source_file) {

                    $is_already_pending = $memory_db->get_option_by_fields(array(
                        'operation'=>'DOWNLOAD',
                        'key'=>     $source_file,
                        'status'=>'pending'
                    ));

                    if( is_array($is_already_pending) && (count($is_already_pending) >0) )
                        continue;

                    $result = $s3client->getObjectTagging([
                        'Bucket' => $bucket,
                        'Key' => $source_file,
                    ]);

                    if(!isset($result['TagSet'][0]['Key']))
                        continue;

                    $memory_db->create_operation(array(
                        'operation'=>'DOWNLOAD',
                        'source'=>'',
                        'destination'=>'',
                        'key'=>$source_file,
                        'status'=>'pending'
                    ));
                    $total_source_files[] = $source_file;

                }
            }

            $files_count = count($total_source_files);

            $task_status = array(
                'operation'=>'DOWNLOADING_PROGRESS',
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
                    $kueue->add_job(time() + ($time * MINUTE_IN_SECONDS), 0, 'obs_do_bulk_download', array($i));
                    $time++;
                }

            }


            $kueue_scheduler->schedule_jobs();

            wp_send_json_success( array(
                'success'=>'true',
                'message'=>__('Successfully scheduled, Please wait a second', 'arvancloud-object-storage')
            ),200);
            return;


        }
    }

    public function render_view()
    {

        Partials::bulk_download_modal();
        wp_die();

    }

    public  function do_bulk_download(){

        ini_set('max_execution_time', MINUTE_IN_SECONDS * 3);




        $memory_db = CustomDB::get_instance();
        $bucket_name = Helper::get_bucket_name();
        $all_pending_files =  $memory_db->get_option_by_fields(array(
            'operation'=>'DOWNLOAD',
            'status'=>'pending'
        ));


        if(!is_array($all_pending_files) || count($all_pending_files) <=0)
            return false;

        $storage_url = Helper::get_storage_url();
        foreach ($all_pending_files as $pending_file){
            $result = $memory_db::get_single_item($memory_db->get_option_by_fields(array('operation'=>'STOP_DOWNLOADING_PROCESS')));
            $should_stop =  ($result['status']??'false');

            if(  'true' == $should_stop ){

                $current_status = $memory_db->get_option_by_fields(array(
                    'operation' => 'DOWNLOADING_PROGRESS'
                ));
                $current_status = $memory_db::get_single_item($current_status);
                $key = maybe_unserialize($current_status['key']);

                $key['task_status'] = 'stop';


                $memory_db->update_option(array(
                    'key'=>maybe_serialize($key)
                ), array(
                    'operation' => 'DOWNLOADING_PROGRESS'
                ));

                break;

            }

            $this->attach_files($storage_url.$pending_file['key']);

            $id = $pending_file['id'];

            $memory_db->update_option(array(
                'status' => 'done'
            ), array(
                'id'=>$id
            ));

            $progress_status = $memory_db::get_single_item($memory_db->get_option_by_fields(array(
                'operation'=>'DOWNLOADING_PROGRESS'
            )));

            $done_files = $memory_db->get_option_by_fields(array(
                'operation'=>'DOWNLOAD',
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
                'operation'=>'DOWNLOADING_PROGRESS'
            ));




        }
        return true;
    }


    public function attach_files($image_url){

        $bucket = Helper::get_bucket_name();


        $upload_dir = wp_upload_dir();

        $image_data = file_get_contents( $image_url );

        $filename = basename( $image_url );
        $slash_position = strpos($filename, '/');
        $filename = substr($filename, $slash_position );



        if ( wp_mkdir_p( $upload_dir['path'] ) ) {
            $file = $upload_dir['path'] . '/' . $filename;
        }
        else {
            $file = $upload_dir['basedir'] . '/' . $filename;
        }

        file_put_contents( $file, $image_data );

        $wp_filetype = wp_check_filetype( $filename, null );

        $attachment = array(
            'post_mime_type' => $wp_filetype['type'],
            'post_title' => sanitize_file_name( $filename ),
            'post_content' => '',
            'post_status' => 'inherit'
        );

        $attach_id = wp_insert_attachment( $attachment, $file );
        require_once( ABSPATH . 'wp-admin/includes/image.php' );
        $attach_data = wp_generate_attachment_metadata( $attach_id, $file );
        wp_update_attachment_metadata( $attach_id, $attach_data );
        update_post_meta( $attach_id, 'acs_storage_file_url', Helper::get_storage_url() );
    }




    public function get_full_file_list($bucket){
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


    public function stop_current_bulk_download_task(){

        if ('post' == strtolower($_SERVER['REQUEST_METHOD']) ) {

            $result = (new KueueCore())->stop_process('obs_do_bulk_download');
            $memory_db = CustomDB::get_instance();
            $memory_db->create_operation(array(
                'operation'=>'STOP_DOWNLOADING_PROCESS',
                'status'=>'true'));

            $current_status = $memory_db->get_option_by_fields(array(
                'operation' => 'DOWNLOADING_PROGRESS'
            ));
            $current_status = $memory_db::get_single_item($current_status);
            $key = maybe_unserialize($current_status['key']);

            $key['task_status'] = 'stop';


            $memory_db->update_option(array(
                'key'=>maybe_serialize($key)
            ), array(
                'operation' => 'DOWNLOADING_PROGRESS'
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


//        if ('post' == strtolower($_SERVER['REQUEST_METHOD']) ) {
//            $result = (new KueueCore())->stop_process('obs_do_bulk_download');
//            self::$force_stop = true;
//            if(!$result){
//
//                wp_send_json_success( array(
//                    'success'=>'false',
//                    'response'=> 'Failed'
//                ),200);
//            }else{
//
//                wp_send_json_success( array(
//                    'success'=>'true',
//                    'response'=> __('Successfully stopped','arvancloud-object-storage')
//                ),200);
//            }
//        }

    }


    public function get_bulk_download_task_status(){

        $memory_db = CustomDB::get_instance();

        $result = $memory_db::get_single_item( $memory_db->get_option_by_fields(array(
            'operation'=> 'DOWNLOADING_PROGRESS'
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

            if( !(KueueCore::get_instance())->has_pending_job('obs_do_bulk_download')){
                $option_values['task_status'] = 'done';
            }

        }

        wp_send_json_success( array(
            'success'=>'true',
            'response'=> $option_values
        ),200);
        wp_die();


//        $default_values = [
//            'files_count'=>0,
//            'current_file_index'=>0,
//            'task_status'=>'done',
//            'files_done'=>"%d from %d"
//        ];
//        $option_values = get_option('obs_do_bulk_download', $default_values);
//        $option_values['files_done'] = sprintf("%d ". __('of' ,'arvancloud-object-storage' ). " %d", $option_values['current_file_index'], $option_values['files_count']);
//        if( !(KueueCore::get_instance())->has_pending_job('obs_do_bulk_download')){
//            $option_values['task_status'] = 'done';
//        }
//        wp_send_json_success( array(
//            'success'=>'true',
//            'response'=> $option_values
//        ),200);
//        wp_die();
    }

}

