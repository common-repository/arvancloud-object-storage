<?php

use WP_Arvan\OBS\Helper;
use WP_Arvan\OBS\Admin\Admin;

if ( ! defined( 'WPINC' ) ) {
	die;
}



if( $acs_settings_option = Helper::get_storage_settings() ) {
    $config_type         = $acs_settings_option['config-type'];
    $snippet_defined     = defined( 'ARVANCLOUD_STORAGE_SETTINGS' );
    $db_defined          = $config_type != 'snippet' && ! empty( $acs_settings_option['access-key'] ) && ! empty( $acs_settings_option['secret-key'] ) && ! empty( $acs_settings_option['endpoint-url'] ) ? true : false;
    $bucket_selected     = Helper::get_bucket_name();
    $acs_settings	     = get_option( 'acs_settings' );

}


$client = \WP_Arvan\OBS\S3Singletone::get_instance()->get_s3client();
try {
    $result = $client->headBucket([
        'Bucket' => $bucket_selected,
    ]);

    //$list_response = $client->listBuckets();
    //$buckets       = $list_response[ 'Buckets' ];

} catch (Aws\Exception\AwsException $e) {
    echo __('There is an error in ArvanCloud service connection:','arvancloud-object-storage') . '<br/>' . $e->getMessage();
}

if ( $result ) {
    $used = Admin::formatBytes($result['@metadata']['headers']['x-rgw-bytes-used']);
    $object_count = $result['@metadata']['headers']['x-rgw-object-count'];
}

?>
<style type="text/css">
.tagify__tag{
    background-color:#D3E2E2;
}
.tagify{
    padding-right: 5px;
}
tags.tagify{
    width: 310px;
    border-radius: 4px;
    border: 1px solid #8c8f94;
}

</style>
<div class="obs-box-outline-title mb-4">
    <?php _e( 'Settings', 'arvancloud-object-storage' ) ?>
</div>

<form method="post">
    <input type="hidden" name="obs_general_nonce_data" value="<?php echo wp_create_nonce('obs_general_nonce'); ?>">
    <div class="obs-box-outline d-flex align-items-center justify-content-between">
        <div>
            <div class="obs-box-outline-title"><?php _e( 'Provider', 'arvancloud-object-storage' ) ?></div>
            <div class="obs-box-outline-desc"><?php echo Helper::get_storage_url() ?></div>
        </div>
        <div>
            <a class="obs-btn-primary-outline"
                href="<?php echo admin_url( '/admin.php?page=wp-arvancloud-storage&action=change-access-option' ) ?>"><?php _e( 'Change Provider', 'arvancloud-object-storage' ) ?></a>
        </div>
    </div>

    <div class="obs-box-outline d-flex align-items-center justify-content-between">
        <div>
            <div class="obs-box-outline-title"><?php _e( 'Bucket: ', 'arvancloud-object-storage' ) ?></div>
            <div class="obs-box-outline-desc"><?php echo Helper::get_bucket_name() ?></div>
        </div>
        <div>
            <?php
            if ( $result ) {
                ?>
            <span class="obs-badge obs-badge-gray me-2">
                <?php echo $used ?>
            </span>
            <span class="obs-badge obs-badge-gray me-3">
                <?php echo $object_count . ' ' . __('Objects', 'arvancloud-object-storage') ?>
            </span>
            <?php
            }
            if(empty($acs_settings_option['bucket_name'])){
            ?>
            <a class="obs-btn-primary-outline"
                href="<?php echo admin_url( '/admin.php?page=wp-arvancloud-storage&action=change-bucket' ) ?>"><?php echo __( "Change Bucket", 'arvancloud-object-storage' ) ?></a>
            <?php } ?>
        </div>
    </div>

    <div class="obs-box-outline d-flex align-items-center justify-content-between">
        <div>
            <div class="obs-box-outline-title"><?php _e( "Keep local files", 'arvancloud-object-storage' ) ?>
            </div>
            <div class="obs-box-outline-desc">
                <?php  _e( 'Keep local files after uploading them to storage.', 'arvancloud-object-storage' ) ?>
            </div>
        </div>
        <div>
            <div class="obs-form-toggle">
                <input class="obs-input" type="checkbox" name="keep-local-files" id="keep-local-files" value="1"
                    <?php echo ( !isset($acs_settings['keep-local-files']) || $acs_settings['keep-local-files']) ? 'checked' : '' ?>>
                <div class="obs-custom-input"></div>
            </div>
        </div>
    </div>
    <div class="obs-box-outline d-flex align-items-center justify-content-between">
        <div>
            <div class="obs-box-outline-title"><?php _e( "file filter", 'arvancloud-object-storage' ) ?>
            </div>
            <div class="obs-box-outline-desc">
                <?php  _e( 'select file type and size limit send to storage.', 'arvancloud-object-storage' ) ?>
            </div>
        </div>
        <div>
            <div>
                <textarea name='file_ext' class="tagify" placeholder='File Extension'>
                <?php echo isset($acs_settings['file_ext'])?$acs_settings['file_ext']:' '; ?>
                </textarea>
                <div class="obs-custom-input"><?php  _e( 'Press [Enter] after writing each file extension without dot (.) and lowercase like (jpg jpeg gif).', 'arvancloud-object-storage' ) ?></div>
            </div>
            <br />
            <div>
                <input class="obs-input" type="number" name="file_f_size" id="keep-local-files" value="<?php echo isset($acs_settings['file_f_size']) ? $acs_settings['file_f_size']: ''; ?>" size="10" placeholder="<?php  _e( 'From size', 'arvancloud-object-storage' ) ?>"  pattern="[0-9.]+"/> 
                <input class="obs-input" type="number" name="file_t_size" id="keep-local-files" value="<?php echo isset($acs_settings['file_t_size']) ? $acs_settings['file_t_size']: '' ?>" size="10" placeholder="<?php  _e( 'To size', 'arvancloud-object-storage' ) ?>"  pattern="[0-9.]+"/>
                <div class="obs-custom-input"><?php  _e( 'Enter limit file size as MB.', 'arvancloud-object-storage' ) ?></div>
            </div>
        </div>
    </div>
    <div class="obs-box-outline d-flex align-items-center justify-content-between">
        <div>
            <div class="obs-box-outline-title"><?php _e( "Sync attachment deletion", 'arvancloud-object-storage' ) ?>
            </div>
            <div class="obs-box-outline-desc">
                <?php  _e( 'Delete item from storage when it deleted from local', 'arvancloud-object-storage' ) ?>
            </div>
        </div>
        <div>
            <div class="obs-form-toggle">
                <input class="obs-input" type="checkbox" name="sync-attachment-deletion" id="sync-attachment-deletion" value="1"
                    <?php echo ( !isset($acs_settings['sync-attachment-deletion']) || $acs_settings['sync-attachment-deletion']) ? 'checked' : '' ?>>
                <div class="obs-custom-input"></div>
            </div>
        </div>
    </div>
<?php if ( is_plugin_active( 'woocommerce/woocommerce.php' ) ) { ?>
    <div class="obs-box-outline d-flex align-items-center justify-content-between">
        <div>
            <div class="obs-box-outline-title"><?php _e( "WooCommerce downloadable products", 'arvancloud-object-storage' ) ?>
            </div>
            <div class="obs-box-outline-desc">
                <?php  _e( 'Do not upload downloadable product files to object storage.', 'arvancloud-object-storage' ) ?>
            </div>
        </div>
        <div>
            <div class="obs-form-toggle">
                <input class="obs-input" type="checkbox" name="wooc_prev_upload_product" id="wooc_prev_upload_product" value="1"
                    <?php echo ( empty($acs_settings) || isset($acs_settings['wooc_prev_upload_product'])) ? 'checked' : '' ?>>
                <div class="obs-custom-input"></div>
            </div>
        </div>
    </div>
<?php } ?>
    <div class="d-flex justify-left mt-4">
        <button type="submit" class="obs-btn-primary" name="acs-settings" value="1">
            <?php  _e( 'Save', 'arvancloud-object-storage' ) ?>
        </button>
    </div>

</form>
<br />
<script type="text/javascript">
var input = document.querySelector('textarea[name=file_ext]');
new Tagify(input);
</script>


