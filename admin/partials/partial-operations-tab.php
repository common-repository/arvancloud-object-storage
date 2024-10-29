<?php


if ( ! defined( 'WPINC' ) ) {
	die;
}
use WP_Arvan\OBS\Admin\Controllers\BucketTransferController;
use WP_Arvan\OBS\Helper;
?>

<div class="obs-box-outline-title mb-4">
    <?php _e( 'Operations', 'arvancloud-object-storage' ) ?>
</div>

<form method="post">
    <div class="obs-box-outline d-flex align-items-center justify-content-between">
        <div>
            <div class="obs-box-outline-title"><?php _e( "Migrate to new bucket", 'arvancloud-object-storage' ) ?>
            </div>
            <div class="obs-box-outline-desc">
                <?php  _e( 'Migrate to new bucket.', 'arvancloud-object-storage' ) ?>
            </div>
        </div>
        <div>
            <div class="rtl">
                <?php
                try {
                    $buckets = (new BucketTransferController())->get_bucket_list();
                }catch (Aws\Exception\AwsException $e) {
                    echo __('There is an error in ArvanCloud service connection:','arvancloud-object-storage') . '<br/>' . $e->getMessage();
                }
                if(is_array($buckets) && count($buckets)>1){
                    ?>
                    <span><?php _e('Copy from:', 'arvancloud-object-storage'); ?></span>
                    <select id="bucket-files-transfer-from">
                        <?php
                        echo '<option value="null">' . __('Source Bucket', 'arvancloud-object-storage') . '</option>';
                        foreach($buckets as $bucket){
                            echo "<option name='{$bucket['Name']}'>{$bucket['Name']}</option>";
                        }
                        ?>
                    </select>
                    <span><?php _e('To:', 'arvancloud-object-storage'); ?></span>
                    <select id="bucket-files-transfer-to">
                        <?php
                        echo '<option value="null">' . __('Destination Bucket', 'arvancloud-object-storage') . '</option>';
                        foreach($buckets as $bucket){
                            echo "<option name='{$bucket['Name']}'>{$bucket['Name']}</option>";
                        }
                        ?>
                    </select>
                    <?php
                }else{
                    echo '<h3>You have only one butcket.</h3>';
                }
                ?>

            </div>
        </div>
        <button  class="obs-btn-primary-outline" name="acs-move-bucket" onclick="return false;" data-id="btn-migrate-to-new-bucket"
        data-modalaction="migrate_to_new_bucket_modal" data-statusaction="get_migrate_to_new_bucket_task_status"
        >
            <?php  _e( 'Move', 'arvancloud-object-storage' ) ?>
        </button>
    </div>
</form>



<form method="post">

    <div class="obs-box-outline d-flex align-items-center justify-content-between">
        <div>
            <div class="obs-box-outline-title"><?php _e( 'Bulk upload', 'arvancloud-object-storage' ) ?></div>
            <div class="obs-box-outline-desc"><?php _e( 'Send all local media files to the desired bucket at once', 'arvancloud-object-storage' ) ?></div>
        </div>
        <div>
            <button onclick="return false;" class="obs-btn-primary-outline" id="btn-bulk-uploader"
                data-modalaction="bulk_upload_modal" data-statusaction="get_bulk_upload_task_status"
            >
                <?php _e( 'Bulk Upload', 'arvancloud-object-storage' ) ?>
            </button>
        </div>
    </div>

    <div class="obs-box-outline d-flex align-items-center justify-content-between">
        <div>
            <div class="obs-box-outline-title"><?php _e( 'Delete local files', 'arvancloud-object-storage' ) ?></div>
            <div class="obs-box-outline-desc"><?php _e( 'Delete all local files', 'arvancloud-object-storage' ) ?></div>
        </div>
        <div>
            <button class="obs-btn-primary-outline" id="btn-remove-local-files" onclick="return false "
                    data-modalaction="bulk_remove_modal" data-statusaction="get_bulk_remove_task_status">
                <?php echo __( "Delete", 'arvancloud-object-storage' ) ?></button>

        </div>
    </div>

    <div class="obs-box-outline d-flex align-items-center justify-content-between">
        <div>
            <div class="obs-box-outline-title"><?php _e( 'Empty the current bucket', 'arvancloud-object-storage' ) ?></div>
            <div class="obs-box-outline-desc"><?php _e( 'Delete all the files in the current bucket', 'arvancloud-object-storage' ) ?></div>
        </div>
        <div>
            <button class="obs-btn-primary-outline" onclick="return false" id="btn-clear-current-bucket"
                    data-modalaction="empty_bucket_modal" data-statusaction="get_empty_current_bucket_task_status"
            >
                <?php echo __( "Empty it", 'arvancloud-object-storage' ) ?></button>

        </div>
    </div>

    <div class="obs-box-outline d-flex align-items-center justify-content-between">
        <div>
            <div class="obs-box-outline-title"><?php _e( 'Download', 'arvancloud-object-storage' ) ?></div>
            <div class="obs-box-outline-desc"><?php _e( 'Get all the files from the desired bucket at once and save them locally', 'arvancloud-object-storage' ) ?></div>
        </div>
        <div>
            <button class="obs-btn-primary-outline" data-id="btn-bulk-download" onclick="return false;"
                    data-modalaction="bulk_download_modal" data-statusaction="get_bulk_download_task_status"
            >
                <?php _e( 'Bulk Download', 'arvancloud-object-storage' ) ?>
            </button>
        </div>
    </div>
</form>


<script>
    // jQuery(document).ready(function() {
        //     jQuery('#btn-remove-local-files').on('click', function(e){
        //         jQuery.ajax({
        //             url: acs_media.ajax_url,
        //             data: {
        //                 'action': 'remove_local_files_modal',
        //
        //                 '_nonce': acs_media.nonces.generate_acl_url
        //             },
        //             success:function(data) {
        //                 remove_local_files_response_handler(data,e);
        //             }
        //         })
        //     });
        // });
        //
        // function remove_local_files_response_handler(response,e){
        //     if(response) {
        //
        //
        //         jQuery('#wpbody-content .arvan-card #form-remove-local-files').remove();
        //         jQuery('#wpbody-content .arvan-card').append(response);
        //         ar_open_modal(e, '#form-remove-local-files');
        //     }
        // }


        /**
         * Migrate to new bucket js
         */


        jQuery(document).ready(function () {
            jQuery('#btn-migrate-to-new-bucket').on('click', function (e) {
                jQuery.ajax({
                    url: acs_media.ajax_url,
                    data: {
                        'action': 'migrate_to_new_bucket_modal',

                        '_nonce': acs_media.nonces.generate_acl_url
                    },
                    success: function (data) {

                        migrate_to_new_bucket_response_handler(data, e);
                    }
                })
            });
        });


        function get_migrate_to_new_bucket_schedule_status1(mycallback) {
            jQuery.ajax({
                method: 'post',
                url: acs_media.ajax_url,
                data: {
                    'action': 'get_migrate_to_new_bucket_task_status',
                },

                success: function (raw_response) {

                    console.log(raw_response);
                    const response = raw_response.data.response;

                    if (response) {
                        mycallback(response);
                    }
                    return null;
                },
                failed: function (response) {
                    console.error(response);
                }
            })
        }

        function migrate_to_new_bucket_response_handler(html_response, e) {
            if (html_response) {

                get_migrate_to_new_bucket_schedule_status1(function (response) {
                    if (response && (response.task_status === 'processing')) {
                        jQuery('#wpbody-content .arvan-card #form-migrate-bucket').remove();
                        jQuery('#wpbody-content .arvan-card').append(html_response);
                        jQuery('#wpbody-content .arvan-card #form-migrate-bucket').hide();
                        jQuery('#wpbody-content .arvan-card #task-status-modal').show();
                        ar_open_modal(e, '#task-status-modal');

                    } else {
                        jQuery('#wpbody-content .arvan-card #form-migrate-bucket').remove();

                        jQuery('#wpbody-content .arvan-card').append(html_response);
                        jQuery('#wpbody-content .arvan-card #form-migrate-bucket').show();
                        jQuery('#wpbody-content .arvan-card #task-status-modal').hide();
                        ar_open_modal(e, '#form-migrate-bucket');
                    }
                });


            }
        }


</script>
