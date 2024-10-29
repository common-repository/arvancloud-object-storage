<?php

use WP_Arvan\OBS\Admin\Partials;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
ini_set('max_execution_time', '0');

$admin_setting = esc_url( add_query_arg(array(
    'page' => ACS_SLUG,
), admin_url()) );
?>


<div class="media-item" id="bulk_upload_progress">
    <div class="original"><?php _e( 'Move files to the bucket', 'arvancloud-object-storage' ) ?></div>
    <div class="progress">
        <div class="percent">0%</div>
        <div class="bar" style="width: 0px;"></div>
    </div>
    <div id="bulk_upload_text">
        <span>0</span>
        <span><?php _e( 'New item added!', 'arvancloud-object-storage' ); ?></span>
    </div>
    <a type="button" id="ar_obs_bulk_local_to_bucket" class="button button-primary media-button select-mode-toggle-button" style="margin-top: 20px;"><?php _e( 'Start Moving', 'arvancloud-object-storage' ) ?></a>
    <a type="button" href="<?php echo $admin_setting ; ?>" class="button media-button select-mode-toggle-button" style="margin-top: 20px;"><?php _e( 'Back to settings', 'arvancloud-object-storage' ) ?></a>
</div>


<?php
Partials::footer()->die();
?>
