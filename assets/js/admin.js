(function( $ ) {
	'use strict';

	$( document ).ready(function() {
		$('.acs-bucket-list li').click(function() {
			$(this).siblings().removeClass("selected");
			$( this ).addClass( 'selected' );
			$( '#acs-bucket-select-name' ).val( $( this ).html() );
	   	});

	   	$('.acs-bucket-action-refresh').click(function() {
			location.reload();
		});
		if(typeof AR_VOD === 'undefined') {
			if (typeof wp !== 'undefined' && typeof wp.media !== 'undefined') {
				// Local reference to the WordPress media namespace.

				var media = wp.media;

				// Local instance of the Attachment Details TwoColumn used in the edit attachment modal view
				var wpAttachmentDetailsTwoColumn = media.view.Attachment.Details.TwoColumn;
				if (wpAttachmentDetailsTwoColumn !== undefined) {

					media.view.Attachment.Details.TwoColumn = wpAttachmentDetailsTwoColumn.extend({
						render: function () {
							// Retrieve the S3 details for the attachment
							// before we render the view
							this.fetchS3Details(this.model.get('id'));
						},

						fetchS3Details: function (id) {

							wp.ajax.send('acs_get_attachment_provider_details', {
								data: {
									_nonce: acs_media.nonces.get_attachment_provider_details,
									id: id
								}
							}).done(_.bind(this.renderView, this));

						},

						renderView: function (response) {
							console.log(response);
							// Render parent media.view.Attachment.Details
							wpAttachmentDetailsTwoColumn.prototype.render.apply( this );

							this.renderActionLinks(response);
						},
						renderActionLinks: function (response) {
							var links = (response && response.links) || [];
							var $actionsHtml = this.$el.find('.actions');
							var $s3Actions = $('<div />', {
								'class': 'acs-actions'
							});

							var s3Links = [];
							_(links).each(function (link) {
								s3Links.push(link);
							});

							$s3Actions.append(s3Links.join(' | '));
							$actionsHtml.append($s3Actions);
						},

					});
				}
			}
		}

		$(".toggle-password").click(function() {

			$(this).toggleClass("dashicons-visibility dashicons-hidden");
			var input = $($(this).attr("toggle"));

			if (input.attr("type") == "password") {
			  input.attr("type", "text");
			} else {
			  input.attr("type", "password");
			}
		});

		
		function update_ar_bulk_upload() {
			$('#bulk_upload_progress .progress').show()
			$('#bulk_upload_text').show()
			$('#ar_obs_bulk_local_to_bucket').hide()
			$.ajax({
				url: acs_media.ajax_url,
				data: {
				  'action': 'ar_bulk_upload_res',
				//   'security': ar_cdn_ajax_object.security,
				},
				success:function(data) {

					$('#bulk_upload_progress .progress .percent').html(data.data.percentage_option + '%')
					$('#bulk_upload_progress .progress .bar').css('width', data.data.percentage_option * 2)
					$('#bulk_upload_text span:first-child').html( data.data.new )

					if (data.data < 100) {
						setTimeout(function(){update_ar_bulk_upload();}, 5000);
					}

				},
				error: function(errorThrown){
					console.log(errorThrown);
				}
			})
		}

		$('#ar_obs_bulk_local_to_bucket').on('click', function(e) {
			e.preventDefault();
			$.ajax({
				url: acs_media.ajax_url,
				data: {
				  'action': 'ar_handle_bulk_upload',
				//   'security': ar_cdn_ajax_object.security,
				}
			})
			update_ar_bulk_upload();
		})



		if ($('.site-health-copy-buttons').length) {
			$('.health-check-accordion-trigger').on('click', function() {
				var id = $(this).attr('aria-controls')
				$('#' + id).toggle()
			})

			var i = new ClipboardJS(".site-health-copy-buttons .copy-button");
			var a, l = wp.i18n.__;
			i.on("success", function (e) {
				var t = $(e.trigger),
					s = $(".success", t.closest("div"));
				e.clearSelection(), t.trigger("focus"), clearTimeout(a), s.removeClass("hidden"), a = setTimeout(function () {
					s.addClass("hidden"), i.clipboardAction.fakeElem && i.clipboardAction.removeFake && i.clipboardAction.removeFake()
				}, 3e3), wp.a11y.speak(l("Site information has been copied to your clipboard."))
			})
		}



		$('#arvancloud-storage-acl-url-generator-button').on('click', function(e) {
			e.preventDefault();
			var expiry = $('#arvancloud-storage-acl-expiry').val();
			var post_id = $('input[name=acl-post-id]').val();

			if (expiry == 'custom') {
				expiry = $('#arvancloud-storage-acl-expiry-custom').val() * 60;
			}

			$.ajax({
				url: acs_media.ajax_url,
				data: {
					'action': 'ar_generate_acl_url',
					'expiry': expiry,
					'post_id': post_id,
					'_nonce': acs_media.nonces.generate_acl_url
				},
				success:function(data) {
					$('.arvancloud-storage-generated_urls ul').append(`<li><input type="text" class="widefat urlfield" readonly="readonly" value="${data.data.url}"><span>${data.data.expiry}</span></li>`);
				}
			})
		})

		$('#arvancloud-storage-acl-expiry').on('change', function() {

			if ($(this).val() == 'custom') {
				$('#arvancloud-storage-acl-expiry-custom').parent().show();
			} else {
				$('#arvancloud-storage-acl-expiry-custom').parent().hide();
			}
		})

		$('.arvancloud-storage-config-form .obs-form-radio input[name="config-type"]').on('change', function() {
			let $details = $(this).parent().parent().find('.obs-box-details');
			if( $(this).val() == 'snippet' ){
				$(this).parent().parent().next('div').find('.obs-box-details input').removeAttr('required');

			}else{
				$('.obs-box-details input').removeAttr('required');
                $(this).closest('.obs-box-outline').find('.obs-box-details input').attr('required','required');
			}
			$('.obs-box-details.active').removeClass('active').hide().css({'opacity': 1 }).animate( { 'opacity': '0' }, 1000 );
			$details.addClass('active').show().css({'opacity': 0}).animate( { 'opacity': '1' }, 1000 );
		})

	});

	$(document).on('click', '.ar-close-modal', function(e) {
		e.preventDefault()
		jQuery('.obs-modal-wrapper').hide()
		$(this).closest('.obs-modal-wrapper').hide()
	})

	$(document).on('click', '#modal_create_bucket .obs-btn-primary', function(e) {
		e.preventDefault()
		$(this).addClass('loading')

		let bucket_name = $('#modal_create_bucket input[name=acs-new-bucket-name]').val()
		let bucket_acl  = $('#modal_create_bucket input[name=acs-new-bucket-public]').is(':checked')
		let bucket_nonce  = $('#modal_create_bucket input[name=bucket_nonce]').val()

		var response = {}
		var notice = {}

		$.ajax({
			url: acs_media.ajax_url,
			method: "POST",
			data: {
				'action': 'ar_create_bucket',
				'bucket_name': bucket_name,
				'bucket_acl': bucket_acl,
				'_nonce': bucket_nonce
			},
			success:function(data) {
				console.log(data)

				response = {
					'bucket_name': data.data.bucket_name,
					'bucket_acl': data.data.bucket_acl,
				}

				notice = {
					'type': 'success',
					'message': data.data.message
				}

				$('input[name=acs-bucket-select-name]').prop('checked', false);

				response.bucket_acl = response.bucket_acl == 'public-read' ? 'public' : 'private'

				$('.acs-bucket-list').prepend(
					`<li class="obs-box-outline d-flex align-items-center justify-content-between">
					<div class="d-flex items-center flex-wrap">
						<div class="obs-form-radio d-flex">
							<input type="radio" id="${response.bucket_name}" name="acs-bucket-select-name" value="${response.bucket_name}" class="obs-input no-compare" checked="checked">
							<div class="obs-custom-input"></div>
						</div>
						<label class="mis-4 p-0" for="${response.bucket_name}">${response.bucket_name}</label>
					</div>
					<div>
					  <span class="obs-badge obs-badge-gray">
					  	${response.bucket_acl}
					  </span>
					</div>
				  </li>`
				)
				let selector = `input[name=acs-bucket-select-name][value=${response.bucket_name}]`
				$(selector).prop('checked', true).trigger('change')

				ar_close_modal('modal_create_bucket')
				ar_show_notice(notice)
			},
			error: function(errorThrown){
				console.log(errorThrown);
				notice = {
					'type': 'error',
					'message': data.data.message
				}

				$(this).removeClass('loading')

				ar_close_modal('modal_create_bucket')
				ar_show_notice(notice)

			}
		})




	})


})( jQuery );

function copyToClipboard( selector ) {
	var text = jQuery(selector).text()
	copyTextToClipboard(text)
}

function ar_open_modal( e, modal_id ) {
	if(e)
		e.preventDefault()
    jQuery('.obs-modal-wrapper').css('display', 'flex')
    jQuery(modal_id).show()
}

function ar_show_notice( notice ) {
	let placeholder = jQuery('.arvan-wrapper').parent()
	jQuery(placeholder).prepend(`<div class="notice ar-notice notice-${notice.type}"><p>${notice.message}</p></div>`)
	jQuery('.wrap > .ar-notice:first-child').fadeIn(500)
	jQuery('html, body').animate({
        scrollTop: jQuery('.wrap > .ar-notice:first-child').offset().top - 35
    }, 100);
	setTimeout(function() {
		jQuery('.ar-notice').fadeOut(5000)
		jQuery('.ar-notice').hide()
	}, 
	10000)
}

function ar_close_modal( modal_id ) {
	jQuery(modal_id).hide()
	jQuery('.obs-modal-wrapper').hide()
}

function copyTextToClipboard(textToCopy) {
    // navigator clipboard api needs a secure context (https)
    if (navigator.clipboard && window.isSecureContext) {
        // navigator clipboard api method'
        return navigator.clipboard.writeText(textToCopy);
    } else {
        // text area method
        let textArea = document.createElement("textarea");
        textArea.value = textToCopy;
        // make the textarea out of viewport
        textArea.style.position = "fixed";
        textArea.style.left = "-999999px";
        textArea.style.top = "-999999px";
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();
        return new Promise((res, rej) => {
            // here the magic happens
            document.execCommand('copy') ? res() : rej();
            textArea.remove();
        });
    }
}
