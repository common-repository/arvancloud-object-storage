<form method="post"
      action="<?php echo admin_url('admin.php?page=wp-arvancloud-storage&tab=operations') ?>">
    <div id="obs-form-acceptance" class="obs-modal-wrapper">
        <div class="obs-modal obs-modal-alert" id="form-acceptance">
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
            <div class="obs-modal-desc"><?php _e('Download all files from bucket to local?', 'arvancloud-object-storage'); ?></div>
            <div class="obs-modal-desc-small"><?php _e('You images will not be overwritten!', 'arvancloud-object-storage'); ?></div>
            <div class="obs-modal-confirm d-flex items-center justify-center">
                <div class="obs-form-check">
                    <input class="obs-input" type="checkbox" name="exampleRadios" id="form-acceptance-accept"
                           value="option1">
                    <div class="obs-custom-input"></div>
                </div>
                <label for="form-acceptance-accept"><?php _e('I\'m sure of bulk downloading', 'arvancloud-object-storage'); ?></label>
            </div>
            <p id="form-acceptance-response-success" style="color: green; text-align: center; font-weight: bold"></p>
            <p style="display: none; color: red; text-align: center; font-weight: bold"
               id="form-acceptance-response-fail"><?php _e('Failed', 'arvancloud-object-storage') ?></p>
            <?php
            $custom_query = (\WP_Arvan\OBS\CustomDB::get_instance())->get_option_by_fields(array(
                'operation'=>'DOWNLOAD',
                'status'=>'pending'

            ));
            if( is_array($custom_query) && (count($custom_query)>0) )
            { ?>
                <div class="obs-modal-confirm d-flex items-center justify-center">
                    <div class="obs-form-check">
                        <input class="obs-input" type="checkbox" name="exampleRadios" id="reschedule-uploading"
                               value="option1">
                        <div class="obs-custom-input"></div>
                    </div>
                    <label for="reschedule-uploading"><?php echo sprintf(__('%s pending operations, reschedule them only?', 'arvancloud-object-storage'), count($custom_query)); ?></label>
                </div>
            <?php }
            ?>
            <div class="obs-modal-actions">
                <a class="obs-btn-secondary-outline"
                   href="<?php echo admin_url('/admin.php?page=wp-arvancloud-storage&tab=operations') ?>"><?php _e('Cancel', 'arvancloud-object-storage') ?></a>
                <button name="submit-form-acceptance" class="obs-btn-danger disabled" id="submit-form-acceptance"
                        data-action="do_bulk_download"
                        data-statusaction="get_bulk_download_task_status"
                        onclick="return false;"> <?php _e('Bulk download', 'arvancloud-object-storage'); ?> </button>
            </div>
        </div>
        <!-- End of accept action modal form -->

        <!-- Progressbar form begins from here -->
        <div class="obs-content-wrapper" id="task-status-modal" style="display: none">
            <div class="obs-modal obs-modal-progress">
                <div class="obs-modal-title"><?php _e('Progressing', 'arvancloud-object-storage'); ?></div>
                <div class="obs-modal-desc"><?php _e('Bulk download is in progress', 'arvancloud-object-storage'); ?></div>
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
                            data-action="stop_current_bulk_download_task"
                            id="stop-current-task"><?php _e('Stop', 'arvancloud-object-storage'); ?></button>
                </div>
            </div>
        </div>
        <!-- end of progressbar -->
    </div>
</form>

<script type="text/javascript">

    jQuery(document).ready(function () {
        /* Runs when form loaded if current scheduled process is running */
        update_progress_form('get_bulk_upload_task_status');

    });

</script>
