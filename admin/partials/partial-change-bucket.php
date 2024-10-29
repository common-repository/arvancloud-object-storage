<?php 
use WP_Arvan\OBS\Helper;
use Aws\Exception\AwsException;
// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

if( $acs_settings_option = Helper::get_storage_settings() ) {
    $config_type         = $acs_settings_option['config-type'];
    $snippet_defined     = defined( 'ARVANCLOUD_STORAGE_SETTINGS' );
    $db_defined          = $config_type == 'db' && ! empty( $acs_settings_option['access-key'] ) && ! empty( $acs_settings_option['secret-key'] ) && ! empty( $acs_settings_option['endpoint-url'] ) ? true : false;
    $bucket_selected     = Helper::get_bucket_name();
    $acs_settings	     = get_option( 'acs_settings' );

}

?>
<div class="arvan-wrapper">
    <div class="arvan-card">
        <div class="obs-box-outline-title mb-4 d-flex items-center">
            <?php
                if ( empty( get_option( 'arvan-cloud-storage-bucket-name' ) ) ) {
                    echo '
                    <a class="acs-back-btn" href="'. admin_url( '/admin.php?page=wp-arvancloud-storage&action=change-access-option' ) .'">
                        <img src="' . ACS_PLUGIN_ROOT_URL . 'assets/img/arrow-right.svg' . '" />
                    </a>';
                }
            ?>
            <?php echo __( "Select bucket", 'arvancloud-object-storage' ) ?>
        </div>

    <form class="arvancloud-storage-select-bucket-form" method="post">
        <input type="hidden" name="obs_general_nonce_data" value="<?php echo wp_create_nonce('obs_general_nonce'); ?>">
    <ul class="acs-bucket-list">
        <?php
        try {

            $client = \WP_Arvan\OBS\S3Singletone::get_instance()->get_s3client();
            $list_response = $client->listBuckets();
            $buckets       = $list_response[ 'Buckets' ];  

            if( count($buckets) == 0 ) {
                echo __( "You have not any bucket in ArvanCloud, please create a bucket in ArvanCloud storage panel then refresh this page!", 'arvancloud-object-storage' );
            } else {
                $selected_bucket = get_option( 'arvan-cloud-storage-bucket-name', false );

                foreach ( $buckets as $bucket ) {
                    $selected = $selected_bucket == $bucket['Name'] ? 'checked="checked"' : '';
                    try {
                        $resp = $client->getBucketAcl([
                            'Bucket' => $bucket['Name']
                        ]);
                        $acl = 'private';

                        foreach($resp['Grants'] as $Grants) {
                            if( $Grants['Grantee']['Type'] == 'Group' && $Grants['Grantee']['URI'] == 'http://acs.amazonaws.com/groups/global/AllUsers' ) {
                                $acl = 'public';
                            }
                        }
                        print_bucket_li( $bucket, $acl, $selected );
                    } catch (AwsException $e) {
                        print_bucket_li( $bucket, '', $selected );
                    }
                }

                ?>
                <button class="obs-btn-add-bucket" onclick="ar_open_modal(event, '#modal_create_bucket');">
                    <svg class="icon" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M8 3.3335V12.6668" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                    <path d="M3.33325 8H12.6666" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                    </svg>
                    <?php _e('Create New Bucket', 'arvancloud-object-storage'); ?>
                </button>
                <?php
            }
        } catch ( Exception $e ) {
            $error = $e->getMessage();
            //$url   = admin_url( "?page=wp-arvancloud-storage&action=change-access-option&error_message=" . urlencode( $error ) );

            //echo '<div class="notice notice-error is-dismissible"><p>'. esc_html( $error ) .'</p></div>';
            //echo '<script>window.location="' . esc_attr( $url ) . '"</script>';
        }
        ?>
    </ul>
    <div class="d-flex justify-left mt-4">
        <?php
        if ( ! empty( get_option( 'arvan-cloud-storage-bucket-name' ) ) ) {
            echo '<a class="obs-btn-primary-outline me-4" href="'. admin_url( 'admin.php?page=wp-arvancloud-storage' ) .'">'. __( "Cancel", 'arvancloud-object-storage' ) .'</a>';
        }
        ?>
        <button type="submit" id="acs-bucket-select-save" class="obs-btn-primary" value="1" <?php echo isset( $error ) ? 'disabled' : '' ?>>
            <?php echo __( "Next", 'arvancloud-object-storage' ) ?>
        </button>
    </div>
</form>

    </div>
</div>
<div class="obs-modal-wrapper">
    <div class="obs-modal obs-modal-successful" id="modal_create_bucket" style="display: none;">
        <div class="obs-modal-title"><?php _e( "Create bucket", 'arvancloud-object-storage' ) ?></div>
        <div class="d-flex flex-col">
            <label for="acs-new-bucket-name"><?php _e( 'Bucket name', 'arvancloud-object-storage' ) ?></label>
            <input type="text" name="acs-new-bucket-name" minlength="3" pattern="[A-Za-z]{3,}" id="acs-new-bucket-name" placeholder="<?php _e( 'The name should be unique', 'arvancloud-object-storage' ) ?>" value="" title="<?php _e('The bucket name should be in English letters only.', 'arvancloud-object-storage') ?>" />
            <div class="d-flex mt-4 mb-6 bg-gray-25 p-6 rounded-xl justify-content-between align-items-center">
                <label for="acs-new-bucket-public" style="font-weight: 700; font-size: 14px; line-height: 22px;">
                    <?php _e( 'Public read access', 'arvancloud-object-storage' ) ?>
                </label>
                <div class="obs-form-toggle">
                    <input class="obs-input" type="checkbox" name="acs-new-bucket-public" id="acs-new-bucket-public" value="0" checked="">
                    <div class="obs-custom-input"></div>
                </div>
            </div>
            <input type="hidden" name="bucket_nonce" value="<?php echo wp_create_nonce( 'create-bucket' ) ?>" />

        </div>
        <div class="obs-modal-actions">
            <button class="obs-btn-secondary-outline ar-close-modal"><?php _e( "Cancel", 'arvancloud-object-storage' ) ?></button>
            <button class="obs-btn-primary px-6"><?php _e( "Create", 'arvancloud-object-storage' ) ?></button>
        </div>
    </div>
</div>


<?php
function print_bucket_li( $bucket, $acl, $selected = '' ) {
    $acl_text = $acl == 'public' ? __( 'Public', 'arvancloud-object-storage' ) : __( 'Private', 'arvancloud-object-storage' );
    $acl_text = empty( $acl_text ) ? __( 'Unknown', 'arvancloud-object-storage' ) : $acl_text;
    echo '<li class="obs-box-outline d-flex align-items-center justify-content-between">
    <div class="d-flex items-center flex-wrap">
        <div class="obs-form-radio d-flex">
            <input type="radio" id="' . esc_attr( $bucket['Name'] ) . '" name="acs-bucket-select-name" value="' . esc_attr( $bucket['Name'] ) . '" class="obs-input no-compare" ' . esc_attr( $selected ) . '>
            <div class="obs-custom-input"></div>
        </div>
        <label class="mis-4 p-0" for="' . esc_attr( $bucket['Name'] ) . '">' . esc_html( $bucket['Name'] ) .'</label>
    </div>
    <div>
      <span class="obs-badge obs-badge-gray">
      '. $acl_text .'
      </span>
    </div>
  </li>';
}