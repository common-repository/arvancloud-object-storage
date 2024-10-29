<?php
use WP_Arvan\OBS\Helper;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

$config_type = null;
$snippet_defined = null;
$db_defined = null;
$bucket_selected = null;
$acs_settings = null;
if( $acs_settings_option = Helper::get_storage_settings() ) {
    $config_type         = $acs_settings_option['config-type'];
    $snippet_defined     = defined( 'ARVANCLOUD_STORAGE_SETTINGS' );
    $db_defined          = $config_type != 'snippet' && ! empty( $acs_settings_option['access-key'] ) && ! empty( $acs_settings_option['secret-key'] ) && ! empty( $acs_settings_option['endpoint-url'] ) ? true : false;
    $bucket_selected     = Helper::get_bucket_name();
    $acs_settings	     = get_option( 'acs_settings' );

}

if( isset( $_GET['error_message'] ) ) {
    echo '<div class="notice notice-error is-dismissible"><p>'. esc_html( $_GET['error_message'] ) .'</p></div>';
}
?>
<div class="arvan-wrapper">
    <div class="arvan-card">
        <div class="obs-box-outline-title mb-4">
            <?php echo __( 'Configure Cloud Storage', 'arvancloud-object-storage' ) ?>
        </div>

        <form class="arvancloud-storage-config-form" method="post"
            action="<?php echo admin_url( '/admin.php?page=wp-arvancloud-storage' ) ?>">

            <input type="hidden" name="obs_general_nonce_data" value="<?php echo wp_create_nonce('obs_general_nonce'); ?>">

            <div class="obs-box-outline d-flex items-center flex-wrap">
                <div class="obs-form-radio d-flex">
                    <input type="radio" name="config-type" value="snippet" class="obs-input" id="config-type-snippet"
                        <?php echo $config_type == 'snippet' ? 'checked' : '' ?> />
                    <div class="obs-custom-input"></div>
                </div>
                <div class="mis-4 d-flex flex-col" style="flex: 1;">
                    <label for="config-type-snippet"
                        class="mb-2"><?php echo __( "Define access keys in wp-config.php", 'arvancloud-object-storage' ) ?>
                        <?php
                            if ( $snippet_defined ) {
                                echo '<span class="acs-defined-in-config">' . __( 'defined in wp-config.php', 'arvancloud-object-storage' ) . '</span>';
                            }
                        ?>
                    </label>
                    <span>
                        <?php 
                        if ( $snippet_defined ) {
                            _e( "You've defined your access keys in your wp-config.php. To select a different option here, simply comment out or remove the Access Keys defined in your wp-config.php.", 'arvancloud-object-storage' );
                            
                            if ( $config_type == 'snippet' && ! $snippet_defined ) {
                                ?>
                                <div class="notice-error notice">
                                    <p>
                                        <?php _e( 'Please check your wp-config.php file as it looks like one of your access key defines is missing or incorrect.', 'amazon-s3-and-cloudfront' ) ?>
                                    </p>
                                </div>
                        <?php
                            }
                        } else {
                            _e( "Copy the following snippet <strong>near the top</strong> of your wp-config.php and replace the stars with the keys.", 'arvancloud-object-storage' );
                        }
                        ?>
                    </span>
                </div>
                <div class="obs-box-details mt-4 full-width <?php echo $config_type == 'snippet' ? 'active' : '' ?>">
                    <textarea rows="7" class="as3cf-define-snippet code clear" readonly="">
define( 'ARVANCLOUD_STORAGE_SETTINGS', json_encode( array(
'access-key' =&gt; '********************',
'secret-key' =&gt; '**************************************',
'endpoint-url' =&gt; '*********************',
'region' =&gt; '*********************',
'bucket_name' =&gt; '*********************',
) ) );
                    </textarea>
                </div>
            </div>


            <div class="obs-box-outline d-flex items-center flex-wrap">
                <div class="obs-form-radio d-flex <?php echo $snippet_defined ? 'disabled' : '' ?>">
                    <input type="radio" name="config-type" value="normal_db" class="obs-input" id="config-type-db"
                        <?php echo $config_type == 'normal_db' ? 'checked' : '' ?>
                        <?php echo $snippet_defined ? 'disabled="disabled"' : '' ?> />
                    <div class="obs-custom-input"></div>
                </div>
                <div class="mis-4 d-flex flex-col">
                    <p><strong><?php _e('Normal connection','arvancloud-object-storage') ?></strong></p>
                    <label for="config-type-db" class="mb-2">
                        <?php echo __( "I understand the risks but I'd like to store access keys in the database anyway (not recommended)", 'arvancloud-object-storage' ) ?>
                    </label>
                    <span>
                        <?php _e( "Storing your access keys in the database is less secure than the options above, but if you're ok with that, go ahead and enter your keys in the form below.", 'arvancloud-object-storage' ) ?>
                    </span>
                </div>


                <div class="obs-box-details ar-table-form full-width mt-4 <?php echo $config_type == 'normal_db' ? 'active' : '' ?>">
                    <div class="d-flex ar-table-form--row">
                        <label for="access-key"><?php echo __( "Access Key", 'arvancloud-object-storage' ) ?></label>
                        <input type="text" name="access-key"
                            value="<?php echo $config_type == 'normal_db' ? ($acs_settings_option['access-key']??'') : '' ?>"
                            autocomplete="off" <?php echo $config_type == 'normal_db' ? 'required="required"' : '' ?> maxlength="36" minlength="36" class="text-align-left">
                    </div>
                    <div class="d-flex ar-table-form--row">
                        <label for="secret-key"><?php echo __( "Secret Key", 'arvancloud-object-storage' ) ?></label>
                        <input type="password" id="secret-key" name="secret-key"
                            value="<?php echo $config_type == 'normal_db' && $acs_settings_option['secret-key'] != null ? __( "-- not shown --", 'arvancloud-object-storage' ) : '' ?>"
                            autocomplete="off" <?php echo $config_type == 'normal_db' ? 'required="required"' : '' ?> maxlength="64" minlength="64" class="text-align-left">
                        <?php if( $config_type == 'normal_db' && $acs_settings_option['secret-key'] == null ): ?>
                        <span toggle="#secret-key"
                            class="dashicons dashicons-visibility field-icon toggle-password"></span>
                        <?php endif; ?>
                    </div>
                    <div class="d-flex ar-table-form--row">
                        <label
                            for="endpoint-url"><?php echo __( "Endpoint URL", 'arvancloud-object-storage' ) ?>.</label>
                        <input name="endpoint-url"
                            value="<?php echo $config_type == 'normal_db' ? $acs_settings_option['endpoint-url'] : '' ?>"
                            autocomplete="on" <?php echo $config_type == 'normal_db' ? 'required="required"' : '' ?> class="text-align-left">

                    </div>
                    <div class="d-flex ar-table-form--row">
                        <label
                            for="endpoint-url"><?php echo __( "Region", 'arvancloud-object-storage' ) ?>.</label>
                        <input name="region"
                            value="<?php echo $config_type == 'normal_db' ? $acs_settings_option['region'] : '' ?>"
                            autocomplete="on" <?php echo $config_type == 'normal_db' ? 'required="required"' : '' ?> class="text-align-left">

                    </div>                    

                    <p><?php echo __( "", 'arvancloud-object-storage' ) ?></p>
                </div>
            </div>
            
            
            <div class="obs-box-outline d-flex items-center flex-wrap">
                <div class="obs-form-radio d-flex <?php echo $snippet_defined ? 'disabled' : '' ?>">
                    <input type="radio" name="config-type" value="machine_db" class="obs-input" id="config-type-db"
                        <?php echo $config_type == 'machine_db' ? 'checked' : '' ?>
                        <?php echo $snippet_defined ? 'disabled="disabled"' : '' ?> />
                    <div class="obs-custom-input"></div>
                </div>
                <div class="mis-4 d-flex flex-col">
                    <p><strong><?php _e('Machine user connection','arvancloud-object-storage') ?></strong></p>
                    <label for="config-type-db" class="mb-2">
                        <?php echo __( "I understand the risks but I'd like to store access keys in the database anyway (not recommended)", 'arvancloud-object-storage' ) ?>
                    </label>
                    <span>
                        <?php _e( "Storing your access keys in the database is less secure than the options above, but if you're ok with that, go ahead and enter your keys in the form below.", 'arvancloud-object-storage' ) ?>
                    </span>
                </div>


                <div class="obs-box-details ar-table-form full-width mt-4 <?php echo $config_type == 'machine_db' ? 'active' : '' ?>">
                    <div class="d-flex ar-table-form--row">
                        <label for="access-key"><?php echo __( "Access Key", 'arvancloud-object-storage' ) ?></label>
                        <input type="text" name="access-keyx"
                            value="<?php echo $config_type == 'machine_db' ? ($acs_settings_option['access-key']??'') : '' ?>"
                            autocomplete="off" <?php echo $config_type == 'machine_db' ? 'required="required"' : '' ?> maxlength="36" minlength="36" class="text-align-left">
                    </div>
                    <div class="d-flex ar-table-form--row">
                        <label for="secret-key"><?php echo __( "Secret Key", 'arvancloud-object-storage' ) ?></label>
                        <input type="password" id="secret-key" name="secret-keyx"
                            value="<?php echo $config_type == 'machine_db' && $acs_settings_option['secret-key'] != null ? __( "-- not shown --", 'arvancloud-object-storage' ) : '' ?>"
                            autocomplete="off" <?php echo $config_type == 'machine_db' ? 'required="required"' : '' ?> maxlength="64" minlength="64" class="text-align-left">
                        <?php if( $config_type == 'machine_db' && $acs_settings_option['secret-key'] == null ): ?>
                        <span toggle="#secret-key"
                            class="dashicons dashicons-visibility field-icon toggle-password"></span>
                        <?php endif; ?>
                    </div>
                    <div class="d-flex ar-table-form--row">
                        <label
                            for="endpoint-url"><?php echo __( "Endpoint URL", 'arvancloud-object-storage' ) ?>.</label>
                        <input name="endpoint-urlx"
                            value="<?php echo $config_type == 'machine_db' ? $acs_settings_option['endpoint-url'] : '' ?>"
                            autocomplete="on" <?php echo $config_type == 'machine_db' ? 'required="required"' : '' ?> class="text-align-left">

                    </div>
                    <div class="d-flex ar-table-form--row">
                        <label
                            for="endpoint-url"><?php echo __( "Region", 'arvancloud-object-storage' ) ?>.</label>
                        <input name="regionx"
                            value="<?php echo $config_type == 'machine_db' ? $acs_settings_option['region'] : '' ?>"
                            autocomplete="on" <?php echo $config_type == 'machine_db' ? 'required="required"' : '' ?> class="text-align-left">

                    </div>
                    <div class="d-flex ar-table-form--row">
                        <label
                            for="endpoint-url"><?php echo __( "Bucket name", 'arvancloud-object-storage' ) ?>.</label>
                        <input name="bucket_name"
                            value="<?php echo $config_type == 'machine_db' ? $acs_settings_option['bucket_name'] : '' ?>"
                            autocomplete="on" <?php echo $config_type == 'machine_db' ? 'required="required"' : '' ?> class="text-align-left">

                    </div>                    

                    <p><?php echo __( "", 'arvancloud-object-storage' ) ?></p>
                </div>
            </div>
            
            
            
            
            <div class="d-flex justify-left mt-4">
                <?php
                if ( ! empty( $acs_settings_option ) && ! isset( $_GET['error_message'] ) ) {
                    echo '<a class="obs-btn-primary-outline me-4" href="'. admin_url( 'admin.php?page=wp-arvancloud-storage' ) .'">'. __( "Cancel", 'arvancloud-object-storage' ) .'</a>';
                }
                ?>
                <button type="submit" class="obs-btn-primary" name="config-cloud-storage" value="1">
                    <?php echo __( "Next", 'arvancloud-object-storage' ) ?>
                </button>
            </div>
        </form>
    </div>
</div>
