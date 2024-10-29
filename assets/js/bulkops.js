jQuery(document).ready(function(){

    jQuery('.obs-btn-primary-outline').on('click', function(e){
        const id = jQuery(this).data('id');
        const modal_action = jQuery(this).data('modalaction');
        const status_action = jQuery(this).data('statusaction');

        jQuery.ajax({
            url: acs_media.ajax_url,
            data: {
                'action': modal_action,

                '_nonce': acs_media.nonces.generate_acl_url
            },
            success:function(data) {

                show_acceptance_form(data,status_action);
            }
        })
    });

    /* Toggle button when accept clicked */
    jQuery(document).on('click','#form-acceptance-accept', function () {

        if (jQuery(this).is(':checked'))
            jQuery('#submit-form-acceptance').removeClass('disabled');
        else
            jQuery('#submit-form-acceptance').addClass('disabled');
    });

    /* Submit the  request */
    jQuery(document).on('click','#submit-form-acceptance', function () {
        let status = jQuery('#form-acceptance-accept');
        const action = jQuery(this).data('action');
        let reschedule = jQuery('#reschedule-uploading');
        const status_action = jQuery(this).data('statusaction');
        const nonce = obs_bulk_ops_nonce.nonce;

        if (status.prop('checked')) {

            jQuery('#submit-form-acceptance').addClass('loading');

            let data={};
            if( reschedule.prop('checked') )
            {
                data = {
                    'action': action,
                    'obs_bulk_ops_nonce':nonce,
                    'reschedule':'true'

                }
            }else{
                data = {
                    'action': action,
                    'obs_bulk_ops_nonce':nonce,

                }
            }

            jQuery.ajax({
                method: 'post',
                url: acs_media.ajax_url,
                data: data,
                success: function (response) {
                    console.log(response);
                    success_handler(response, status_action);
                    jQuery('#submit-form-acceptance').removeClass('loading');
                    jQuery('#submit-form-acceptance').addClass('disabled');
                    jQuery('#form-acceptance-accept').prop('checked', false);

                },
                failed: function (response) {
                    console.log(response);
                    jQuery('#form-acceptance-response-fail').text(response.data.message);
                    jQuery('#form-acceptance-response-fail').show();
                    jQuery('#submit-form-acceptance').removeClass('loading');
                },
                statusCode: {
                    403: function() {
                        alert( "Forbidden. Please refresh the page and try again." );
                    }
                }
            });
        }
    });

    jQuery(document).on('click','#stop-current-task', function () {

        const  action = jQuery(this).data('action');
        jQuery('#stop-current-task').addClass('loading');
        jQuery('#stop-current-task').addClass('disabled');

        jQuery.ajax({
            method: 'post',
            url: acs_media.ajax_url,
            data: {
                'action': action,
            },

            success: function (raw_response, e) {

                const response = raw_response.data;

                if (response.success && response.success === 'true') {
                    jQuery('#stop-current-task').removeClass('loading');
                    alert(response.response);
                } else {
                    alert(response.response);
                    jQuery('#stop-current-task').removeClass('loading');

                }
                return null;
            },
            failed: function (response) {
                console.error(response);
            }
        })
    });
});

/*  Show acceptance form */
function show_acceptance_form(html_response, status_action){
    if(html_response) {

        get_schedule_status(function(response){
            if( response && (response.task_status === 'processing'))
            {
                jQuery('#wpbody-content .arvan-card #form-acceptance').remove();
                jQuery('#wpbody-content .arvan-card').append(html_response);
                jQuery('#wpbody-content .arvan-card #form-acceptance').hide();
                jQuery('#wpbody-content .arvan-card #task-status-modal').show();
                ar_open_modal(null, '#task-status-modal');

            }else{
                jQuery('#wpbody-content .arvan-card #form-acceptance').remove();

                jQuery('#wpbody-content .arvan-card').append(html_response);
                jQuery('#wpbody-content .arvan-card #form-acceptance').show();
                jQuery('#wpbody-content .arvan-card #task-status-modal').hide();
                ar_open_modal(null, '#form-acceptance');
            }
        }, status_action);
    }
}

/* Show the success or failed message */
function success_handler(response,action) {
    if (response.data) {
        if (response.data && response.data.success && ('true' == response.data.success)) {
            jQuery('#form-acceptance-response-fail').hide();
            jQuery('#form-acceptance-response-success').show();
            jQuery('#form-acceptance-response-success').text(response.data.message);
            update_progress_form(action);
            return;
        }

    }
    jQuery('#form-acceptance-response-success').hide();
    jQuery('#form-acceptance-response-fail').show();
    jQuery('#form-acceptance-response-fail').text(response.data.message);

}


/* Recursive function to check progress status every 1.5 second */
function update_progress_form(action) {

    let timeOutResult = setTimeout(function () {
        get_schedule_status(function (response) {

            if (response.task_status === 'processing') {

                const percentage = Math.ceil((response.processed_files_count  * 100) / (response.files_count));
                jQuery('#task-status-modal-filecounter').text(response.files_done);
                jQuery('#task-status-modal-percentage-text').text(percentage + '%');
                jQuery('#task-status-modal-percentage').css({'width': percentage + '%'});
                jQuery('#wpbody-content .arvan-card #form-acceptance').hide();
                jQuery('#wpbody-content .arvan-card #task-status-modal').show();
                ar_open_modal(null, '#task-status-modal');
            } else if (response.task_status === 'done') {
                jQuery('#task-status-modal-filecounter').text(response.files_done);
                jQuery('#task-status-modal-percentage-text').text('100%');
                jQuery('#task-status-modal-percentage').css({'width': '100%'});

                return;
            }
            /* Recurse it */
            update_progress_form(action);

        }, action);

    }, 1500);

}


/* Get the status of current scheduled action */
function get_schedule_status(mycallback, action) {
    jQuery.ajax({
        method: 'post',
        url: acs_media.ajax_url,
        data: {
            'action': action,
        },

        success: function (raw_response, e) {

            const response = raw_response.data.response;

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


