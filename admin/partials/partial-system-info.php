<?php
use  WP_Arvan\OBS\System_Info;
use WP_Arvan\OBS\Admin\Partials;
// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}
$system_info = new System_Info();

        
?>
<div class="ar-heading">
    <H1><?php _e( 'System Info', 'arvancloud-object-storage' ) ?></H1>
</div>
<hr>
<div class="health-check-body health-check-debug-tab hide-if-no-js">
<p><?php _e('If you want to export a handy list of all the information on this page, you can use the button below to copy it to the clipboard. You can then paste it in a text file and save it to your device, or paste it in an email exchange with a support engineer or theme/plugin developer for example.', 'arvancloud-object-storage') ?></p>
    <div class="site-health-copy-buttons">
        <div class="copy-button-wrapper">
            <button type="button" class="button copy-text copy-button" data-clipboard-text="`<?php echo $system_info->render_system_info_page(); ?>`"><span class="dashicons dashicons-clipboard"></span> <?php _e('Copy to Clipboard', 'arvancloud-object-storage'); ?></button>
            <span class="success hidden" aria-hidden="true"><?php _e('Copied!') ?></span>
        </div>
    </div>
    <div id="health-check-debug" class="health-check-accordion">
    <?php
    echo $system_info->render_system_info();
     ?>  
</div>
<?php
Partials::footer();
echo '</div>';
