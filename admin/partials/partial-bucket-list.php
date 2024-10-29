<?php
use WP_Arvan\OBS\Admin\Partials;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}


$selected_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'settings';
$settings_tab__class = $selected_tab == 'settings' ? 'active' : '';
$operations_tab__class = $selected_tab == 'operations' ? 'active' : '';
?>
<div class="arvan-wrapper">
    <div class="arvan-card">

        <div class="d-flex items-center justify-center mb-4">
            <div class="obs-tab">
                <?php echo '<a class="obs-tab-item '. $settings_tab__class .'" href="'. admin_url( 'admin.php?page=wp-arvancloud-storage' ) .'">'. __( 'Settings', 'arvancloud-object-storage' ) .'</a>'; ?>
                <?php echo '<a class="obs-tab-item '. $operations_tab__class .'" href="'. admin_url( 'admin.php?page=wp-arvancloud-storage&tab=operations' ) .'">'. __( 'Operations', 'arvancloud-object-storage' ) .'</a>'; ?>
            </div>
        </div>

        <?php
        if ( $selected_tab == 'settings' ) {
            Partials::settings_tab();
        } elseif ( $selected_tab == 'operations' ) {
            Partials::operations_tab();
        }
        ?>

    </div>
</div>