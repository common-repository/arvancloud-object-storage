<form method="post"
      action="<?php echo admin_url('admin.php?page=wp-arvancloud-storage&tab=operations') ?>">
    <div id="obs-migrate-bucket" class="obs-modal-wrapper">
        <div class="obs-modal obs-modal-alert" id="form-migrate-bucket">
            <div class="obs-modal-title"><p
                        class="obs-modal-desc"><?php _e('Caution', 'arvancloud-object-storage'); ?></p></div>
            <figure class="obs-modal-figure">
                <svg class="icon" width="48" height="48" viewBox="0 0 48 48" fill="none"
                     xmlns="http://www.w3.org/2000/svg">
                    <path d="M20.5796 7.7209L3.63955 36.0009C3.29029 36.6057 3.10549 37.2915 3.10353 37.9899C3.10158 38.6884 3.28254 39.3752 3.62841 39.9819C3.97428 40.5887 4.473 41.0944 5.07497 41.4486C5.67693 41.8028 6.36115 41.9932 7.05955 42.0009H40.9396C41.638 41.9932 42.3222 41.8028 42.9241 41.4486C43.5261 41.0944 44.0248 40.5887 44.3707 39.9819C44.7166 39.3752 44.8975 38.6884 44.8956 37.9899C44.8936 37.2915 44.7088 36.6057 44.3596 36.0009L27.4196 7.7209C27.063 7.13311 26.561 6.64714 25.9619 6.30987C25.3629 5.97259 24.687 5.79541 23.9996 5.79541C23.3121 5.79541 22.6362 5.97259 22.0372 6.30987C21.4381 6.64714 20.9361 7.13311 20.5796 7.7209V7.7209Z"
                          stroke="#EE5353" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M24 18V26" stroke="#EE5353" stroke-width="4" stroke-linecap="round"
                          stroke-linejoin="round"/>
                    <path d="M24 34.0002H24.02" stroke="#EE5353" stroke-width="4" stroke-linecap="round"
                          stroke-linejoin="round"/>
                </svg>
            </figure>
            <div class="obs-modal-desc"><?php _e('Are you sure to want migrate to new bucket?', 'arvancloud-object-storage'); ?></div>
            <div class="obs-modal-desc-small"><?php _e('All files in your bucket will be copied', 'arvancloud-object-storage'); ?></div>
            <div class="obs-modal-confirm d-flex items-center justify-center">
                <div class="obs-form-check">
                    <input class="obs-input" type="checkbox" name="exampleRadios" id="accept-migrate-current-bucket"
                           value="option1">
                    <div class="obs-custom-input"></div>
                </div>
                <label for="accept-migrate-current-bucket"><?php _e('I\'m sure of migrating to new bucket', 'arvancloud-object-storage'); ?></label>
            </div>
            <p id="migrate-bucket-response-success" style="color: green; text-align: center; font-weight: bold"></p>
            <p style="display: none; color: red; text-align: center; font-weight: bold"
               id="migrate-bucket-response-fail"><?php _e('Failed', 'arvancloud-object-storage') ?></p>
            <?php
            $custom_query = (\WP_Arvan\OBS\CustomDB::get_instance())->get_option_by_fields(array(
                'operation'=>'MIGRATE',
                'status'=>'pending'

            ));
            if( is_array($custom_query) && (count($custom_query)>0) )
            { ?>
                <div class="obs-modal-confirm d-flex items-center justify-center">
                    <div class="obs-form-check">
                        <input class="obs-input" type="checkbox" name="exampleRadios" id="reschedule-migration"
                               value="option1">
                        <div class="obs-custom-input"></div>
                    </div>
                    <label for="reschedule-migration"><?php echo sprintf(__('%s pending operations, reschedule them only?', 'arvancloud-object-storage'), count($custom_query)); ?></label>
                </div>
            <?php }
            ?>
            <div class="obs-modal-actions">

                <a class="obs-btn-secondary-outline"
                   href="<?php echo admin_url('/admin.php?page=wp-arvancloud-storage&tab=operations') ?>"><?php _e('Cancel', 'arvancloud-object-storage') ?></a>
                <button name="submit-migrate-bucket" class="obs-btn-danger disabled" id="submit-migrate-bucket"
                        onclick="return false;"> <?php _e('Migrate now', 'arvancloud-object-storage'); ?> </button>
            </div>
        </div>
        <!-- End of accept action modal form -->

        <!-- Progressbar form begins from here -->
        <div class="obs-content-wrapper" id="task-status-modal" style="display: none">
            <div class="obs-modal obs-modal-progress">
                <div class="obs-modal-title"><?php _e('Progressing', 'arvancloud-object-storage'); ?></div>
                <div class="obs-modal-desc"><?php _e('Bucket migration task is in progress', 'arvancloud-object-storage'); ?></div>
                <div class="obs-upload-info">
                    <span class="obs-upload-size" id="task-status-modal-filesize"></span>
                    <span class="obs-upload-count" id="task-status-modal-filecounter"></span>
                </div>
                <div class="obs-progress">
                    <span class="obs-progress-percent" id="task-status-modal-percentage-text">0٪</span>
                    <div class="obs-progress-bar">
                        <div class="obs-progress-fill" style="width: 0%" id="task-status-modal-percentage"></div>
                    </div>
                </div>
                <div class="obs-modal-actions">
                    <a class="obs-btn-secondary-outline"
                       href="<?php echo admin_url('/admin.php?page=wp-arvancloud-storage&tab=operations') ?>"><?php _e('Close', 'arvancloud-object-storage'); ?></a>
                    <button class="obs-btn-primary" onclick="return false;"
                            id="stop-current-migrate-bucket-task"><?php _e('Stop', 'arvancloud-object-storage'); ?></button>
                </div>
            </div>
        </div>
        <!-- end of progressbar -->
    </div>
</form>

<script type="text/javascript">
    jQuery(document).ready(function () {

        /* Toggle button when accept clicked */
        jQuery('#accept-migrate-current-bucket').on('click', function () {
            if (jQuery(this).is(':checked'))
                jQuery('#submit-migrate-bucket').removeClass('disabled');
            else
                jQuery('#submit-migrate-bucket').addClass('disabled');
        });

        /* Submit the migrate request */
        jQuery('#submit-migrate-bucket').on('click', function () {
            let status = jQuery('#accept-migrate-current-bucket');
            let reschedule = jQuery('#reschedule-migration');
            if (status.prop('checked')) {

                const  bucket_from = jQuery('#bucket-files-transfer-from').val();
                const  bucket_to = jQuery('#bucket-files-transfer-to').val();
                const nonce = obs_bulk_ops_nonce.nonce;

                jQuery('#submit-migrate-bucket').addClass('loading');

                let data={};
                if( reschedule.prop('checked') )
                {
                    data = {
                        'action': 'do_migrate_to_new_bucket',
                        'bucket-files-transfer-from':bucket_from,
                        'bucket-files-transfer-to':bucket_to,
                        'reschedule':'true',
                        'obs_bulk_ops_nonce':nonce,

                    }
                }else{
                   data = {
                        'action': 'do_migrate_to_new_bucket',
                        'bucket-files-transfer-from':bucket_from,
                        'bucket-files-transfer-to':bucket_to,
                        'obs_bulk_ops_nonce':nonce,

                    }
                }

                jQuery.ajax({
                    method: 'post',
                    url: acs_media.ajax_url,
                    data: data,
                    success: function (response) {
                        migrate_bucket_success_handler(response);
                        jQuery('#submit-migrate-bucket').removeClass('loading');
                        jQuery('#submit-migrate-bucket').addClass('disabled');
                        jQuery('#accept-migrate-current-bucket').prop('checked', false);

                    },
                    failed: function (response) {
                        jQuery('#migrate-bucket-response-fail').text(response.data.message);
                        jQuery('#migrate-bucket-response-fail').show();
                        jQuery('#submit-migrate-bucket').removeClass('loading');
                    },
                    statusCode: {
                        403: function() {
                            alert( "Forbidden. Please refresh the page and try again." );
                        }
                    }
                });
            }
        });

        /* Stop current process */
        function stop_current_bucket_migration_task() {

            jQuery('#stop-current-migrate-bucket-task').on('click', function () {

                jQuery('#stop-current-migrate-bucket-task').addClass('loading');
                jQuery('#stop-current-migrate-bucket-task').addClass('disabled');

                jQuery.ajax({
                    method: 'post',
                    url: acs_media.ajax_url,
                    data: {
                        'action': 'stop_migrate_to_new_bucket_task',
                    },

                    success: function (raw_response, e) {


                        const response = raw_response.data;

                        console.log(response);
                        if (response.success && response.success === 'true') {
                            jQuery('#stop-current-migrate-bucket-task').removeClass('loading');
                            alert(response.response);
                        } else {
                            alert(response.response);
                            jQuery('#stop-current-migrate-bucket-task').removeClass('loading');

                        }
                        return null;
                    },
                    failed: function (response) {
                        console.error(response);
                    }
                })
            });
        }

        /* Get the status of current scheduled action */
        function get_migrate_bucket_schedule_status2(mycallback) {
            jQuery.ajax({
                method: 'post',
                url: acs_media.ajax_url,
                data: {
                    'action': 'get_migrate_to_new_bucket_task_status',
                },

                success: function (raw_response, e) {


                    const response = raw_response.data.response;

                    console.log(response);
                    if (response) {
                        mycallback(response, e);
                    }
                    return null;
                },
                failed: function (response) {
                    console.error(response);
                }
            })
        }

        /* Show the success or failed message */
        function migrate_bucket_success_handler(response) {
            if (response) {
                if (response.data && response.data.success && ('true' == response.data.success)) {
                    jQuery('#migrate-bucket-response-fail').hide();
                    jQuery('#migrate-bucket-response-success').show();
                    jQuery('#migrate-bucket-response-success').text(response.data.message);
                    check_migrate_bucket_status();
                    return;
                }
            }
            jQuery('#migrate-bucket-response-success').hide();
            jQuery('#migrate-bucket-response-fail').show();
            jQuery('#migrate-bucket-response-fail').text(response.data.message);
        }

        /* Recursive function to check progress status every 1.5 second */
        function check_migrate_bucket_status() {

            let timeOutResult = setTimeout(function () {
                get_migrate_bucket_schedule_status2(function (response) {

                    if (response.task_status === 'processing') {

                        const percentage = Math.ceil((response.processed_files_count * 100) / (response.files_count));
                        jQuery('#task-status-modal-filecounter').text(response.files_done);
                        jQuery('#task-status-modal-percentage-text').text(percentage + '%');
                        jQuery('#task-status-modal-percentage').css({'width': percentage + '%'});


                        jQuery('#wpbody-content .arvan-card #form-migrate-bucket').hide();
                        jQuery('#wpbody-content .arvan-card #task-status-modal').show();
                        ar_open_modal(null, '#task-status-modal');
                    } else if (response.task_status === 'done') {
                        jQuery('#task-status-modal-filecounter').text(response.files_done);
                        jQuery('#task-status-modal-percentage-text').text('100%');
                        jQuery('#task-status-modal-percentage').css({'width': '100%'});


                        return;
                    } else if( response.task_status === 'stop' ){

                        return;
                    }
                    /* Recurse it */
                    check_migrate_bucket_status();

                });

            }, 1500);

        }

        /* Runs when form loaded if current scheduled process is running */
        check_migrate_bucket_status();

        /* Stop current scheduled process handler */
        stop_current_bucket_migration_task();
    });


</script>
