<?php

namespace WP_Arvan\OBS;

use Aws\S3\S3Client;
use WP_Arvan\OBS\Helper;

class S3Singletone
{
    private static  $instance;
    private         $s3_client;
    public $args;
    public function __construct($credentials=null){

        if(!$credentials)
            $credentials = Helper::get_storage_settings();

        $this->args = [
            'region'   => isset($credentials['region'])?$credentials['region']:'region',
            'version'  => '2006-03-01',
            'endpoint' => isset($credentials['endpoint-url'])?$credentials['endpoint-url']:'https://s3.ir-thr-at1.arvanstorage.com',
            'use_aws_shared_config_files' => false,
            'credentials' => [
                'key'     => isset($credentials['access-key'])?$credentials['access-key']:'key',
                'secret'  => isset($credentials['secret-key'])?$credentials['secret-key']:'secret'
            ],
            // Set the S3 class to use objects. arvanstorage.com/bucket
            // instead of bucket.objects. arvanstorage.com
            'use_path_style_endpoint' => true
        ];




    }
    public function set_args($args){
        $this->args = $args;
    }
    public static function get_instance(){

        if( !self::$instance )
            self::$instance = new S3Singletone();

        return self::$instance;

    }

    public function get_s3client(){
        $this->s3_client = new S3Client($this->args);
        return $this->s3_client;
    }
}
