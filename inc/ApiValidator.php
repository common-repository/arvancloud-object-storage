<?php

namespace WP_Arvan\OBS;

use Aws\S3\Exception\S3Exception;
use Aws\S3\MultipartUploader;


class ApiValidator
{
    private $kueue;
    private const  interval = 60*60*24; // A Day

    public function __construct(){

        add_action('obs_periodic_validate_api', [$this, 'periodicValidateApi']);
        $this->kueue = new Kueue\KueueCore();
    }

    public function setup(){
        if(!$this->kueue->has_pending_job('obs_periodic_validate_api')) {
            $this->kueue->add_job(time(), self::interval, 'obs_periodic_validate_api', array(), null);
            $this->kueue->schedule_jobs();
        }

        if(get_option('OBS_INVALID_API_KEY')){
            Helper::show_admin_notice('Invalid API key, Please provide a valid access and secret key ' . "<a href='".admin_url('admin.php?page=wp-arvancloud-storage')."'> ".__("Setting page", 'arvancloud-object-storage' )." </a>");
        };
    }
    public function periodicValidateApi(){
        $s3client = S3Singletone::get_instance();
        $acs_settings_option = Helper::get_storage_settings();

        if(empty($acs_settings_option['bucket_name'])){
            try {
                $buckets = $s3client->get_s3client()->listBuckets();
                update_option('OBS_INVALID_API_KEY', false);
            }catch (S3Exception $ex){
                if($ex->getStatusCode() == 403)
                {
                    update_option('OBS_INVALID_API_KEY', true);
                    delete_option('arvan-cloud-storage-settings');

                }
            }
        }else{
            $file = ACS_PLUGIN_ROOT_URL.'assets/img/1-1.png';
            $uploader = new MultipartUploader( $s3client->get_s3client(), $file, [
						'bucket' => $acs_settings_option['bucket_name'],
						'key'    => basename( $file ),
						'ACL' 	 => 'public-read', // or private
            ]);
            try {
                $result = $uploader->upload();
                update_option('OBS_INVALID_API_KEY', false);
            } catch ( \Throwable $e ) {
                update_option('OBS_INVALID_API_KEY', true);
                delete_option('arvan-cloud-storage-settings');
            }
        }

    }
}