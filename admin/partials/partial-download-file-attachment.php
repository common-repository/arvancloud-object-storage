<?php

use WP_Arvan\OBS\Admin\Partials;
//https://cheftak.s3.ir-thr-at1.arvanstorage.ir/00d10c3169ff9d57b21096f5f305e5fc-1024x444.png?versionId=
?>

<div class="wrap">
<form method="post">
    <div class="arvan-wrapper">
        <div class="arvan-card">
            <div class="obs-box-outline-title mb-4"><?php _e( 'Direct Fetch', 'arvancloud-object-storage' ) ?></div>
            <div class="obs-box-outline d-flex items-center flex-wrap">
                <div class="mis-4 d-flex flex-col">
                    <label class="mb-2">
                    <?php 
                    _e( 'Fetch files directly from your bucket with the object URL to your WordPress Media.', 'arvancloud-object-storage' );
                    ?>
                    </label>
                </div>
                <div class="obs-box-details ar-table-form full-width mt-4 active">
                    <div class="d-flex ar-table-form--row">
                        <label style="width:150px !important;"><?php _e('Object Storage URL','arvancloud-object-storage'); ?></label>
                        <input type="url" name="file_url" class="w-50 text-align-left" size="50" dir="ltr" placeholder="https://" required />
                    </div>
                </div>
            </div>
            <div class="d-flex justify-left mt-4">
                <a class="obs-btn-primary-outline me-4" href="<?php echo admin_url(); ?>admin.php?page=wp-arvancloud-storage">لغو</a>
                <button type="submit" name="save" class="obs-btn-primary" name="config-cloud-storage" value="1"><?php _e('Fetch','arvancloud-object-storage'); ?></button>
            </div>
        </div>
    </div>
</form>
<?php Partials::footer() ?>
</div>