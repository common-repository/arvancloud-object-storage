<?php
namespace WP_Arvan\OBS;

use WP_Encryption\Encryption;
use Aws\S3\MultipartUploader;
/**
 * The file that defines the plugin helper functions
 *
 * A class definition that includes functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       khorshidlab.com
 * @since      1.0.0
 *
 * @package    Wp_Arvancloud_Storage
 * @subpackage Wp_Arvancloud_Storage/includes
 */
class Helper {
    
    public static function get_storage_settings() {
        $credentials         = false;
        $acs_settings_option = get_option( 'arvan-cloud-storage-settings' );

        if( !empty( $acs_settings_option ) ) {    
            $acs_settings_option = json_decode( (new Encryption)->decrypt( $acs_settings_option ), true );

            if( $acs_settings_option['config-type'] != 'snippet' ) {
                $credentials = $acs_settings_option;
            } else {
                if( defined( 'ARVANCLOUD_STORAGE_SETTINGS' ) ) {
                    $settings = json_decode( \ARVANCLOUD_STORAGE_SETTINGS, true );
                    $settings['config-type'] = $acs_settings_option['config-type'];
                    
                    $credentials = $settings;
                }
            }
        }
        if(!empty($credentials['endpoint-url'] ))
            $credentials['endpoint-url'] = self::check_for_http_protocol($credentials['endpoint-url']);
        return $credentials;

    }

    public static function check_for_http_protocol($url){

        $url = strtolower($url);
        if( str_starts_with($url, 'http://') )
            return $url;
        if( str_starts_with($url, 'https://') )
            return $url;

        return 'https://' . $url;
    }


    public static function get_bucket_name() {

        $bucket_name = esc_attr( get_option( 'arvan-cloud-storage-bucket-name', false ) );

        return $bucket_name;

    }

    public static function get_storage_url() {

        $credentials  = self::get_storage_settings();
        $bucket_name  = self::get_bucket_name();
        $endpoint_url = $credentials['endpoint-url'] . "/";

        return esc_url( substr_replace( $endpoint_url, $bucket_name . ".", 8, 0 ) );
        
    }

    public static function get_storage_url_by_bucket_name($bucketname) {

        $credentials  = self::get_storage_settings();
        $bucket_name  = $bucketname;
        $endpoint_url = $credentials['endpoint-url'] . "/";

        return esc_url( substr_replace( $endpoint_url, $bucket_name . ".", 8, 0 ) );

    }

    /**
     * Recursive sanitation for an array
     * 
     * @param $array
     *
     * @return mixed
     */
    public static function acs_recursive_sanitize( $array ) {
        foreach ( $array as $key => &$value ) {
            if ( is_array( $value ) ) {
                $value = self::acs_recursive_sanitize( $value );
            } else {
                $value = \sanitize_text_field( $value );
            }
        }

        return $array;
    }

    public static function show_admin_notice($message, $type='notice-error'){

        add_action('admin_notices', function () use ($message, $type) {
            echo wp_kses_post('<div class="notice ' .$type. ' is-dismissible">
                    <p>' . $message . '</p>
                </div>');
        });

    }

    public static function check_secret_key($default_args=null, $check_type = 'db'){


        if('snippet'==$check_type) {
            $default_args = json_decode(ARVANCLOUD_STORAGE_SETTINGS, true);
        }

        $s3client = S3Singletone::get_instance();
        $args = $s3client->args;
        $args['endpoint'] = $default_args['endpoint-url'];
        $args['credentials']['secret'] = $default_args['secret-key'];
        $args['credentials']['key'] =  $default_args['access-key'];

        $s3client->set_args($args);


        try{

            if(empty($default_args['bucket_name']))
            $buckets = $s3client->get_s3client()->listBuckets();
            else{
                $result = $s3client->get_s3client()->headBucket([
                    'Bucket' =>$default_args['bucket_name'],
                ]);
            }

            return 'TRUE';


        }catch (\Exception $exception){

            return 'FALSE';
        }

    }




    public static function log_to_file($var){
        file_put_contents(ACS_PLUGIN_ROOT . 'log.txt', print_r($var, true) . "\n", FILE_APPEND);
    }

    public static function sleep( $micro_seconds = 1000 ){
        usleep($micro_seconds);
    }

    public static function check_bulk_ops_nonce(){
        if(!check_admin_referer('obs-bulk-ops-nonce','obs_bulk_ops_nonce')){
            wp_send_json_error(array(
                'success'=>'false',
                'message'=>'Cheating huh?'
            ),403);
            exit;
        }
    }

    public static function check_generic_nonce($action, $arg){
        check_admin_referer($action,$arg);
    }

    public static function check_user_authorization(){
        if(!current_user_can('manage_options')){
            wp_send_json_error(array(
                'success'=>'false',
                'message'=>'Forbidden'
            ),403);
            exit;
        }
    }
    
    public static function check_machine_bucket($default_args=null, $check_type = 'db'){
        
        if('snippet'==$check_type) {
            $default_args = json_decode(ARVANCLOUD_STORAGE_SETTINGS, true);
        }

        $s3client = S3Singletone::get_instance();
        //$args = $s3client->args;
        //$args['endpoint'] = $default_args['endpoint-url'];
        //$args['credentials']['secret'] = $default_args['secret-key'];
        //$args['credentials']['key'] =  $default_args['access-key'];

        //$s3client->set_args($args);
        $file = ACS_PLUGIN_ROOT_URL.'assets/img/1-1.png';
        $uploader = new MultipartUploader( $s3client->get_s3client(), $file, [
						'bucket' => $default_args['bucket_name'],
						'key'    => basename( $file ),
						'ACL' 	 => 'public-read', // or private
        ]);
        try {
            $result = $uploader->upload();
            return true;
        } catch ( \Throwable $e ) {
            return false;
        }
    }
}
