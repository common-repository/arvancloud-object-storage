<?php
namespace WP_Arvan\OBS\Admin;
use WP_Arvan\OBS\Admin\Controllers\EmptyCurrentBucketController;
use WP_Arvan\OBS\Admin\Controllers\RemoveLocalFilesController;
use WP_Arvan\OBS\ApiValidator;
use WP_Arvan\OBS\Helper;
use Aws\Exception\AwsException;
use Aws\S3\MultipartUploader;
use Aws\Exception\MultipartUploadException;
use PO;
use WP_Arvan\OBS\S3Singletone;
use WP_Encryption\Encryption;


/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Wp_Arvancloud_Storage
 * @subpackage Wp_Arvancloud_Storage/admin
 * @author     Khorshid <info@khorshidlab.com>
 */
class Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name 		= $plugin_name;
		$this->version 			= $version;
		$this->acs_settings 	= get_option( 'acs_settings', true );
		$this->bucket_name  	= Helper::get_bucket_name();
		$this->storage_settings	= Helper::get_storage_settings();

	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Wp_Arvancloud_Storage_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Wp_Arvancloud_Storage_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style( $this->plugin_name, ACS_PLUGIN_ROOT_URL . 'assets/css/main.css', array(), $this->version, 'all' );
        if(isset($_GET['page']) and $_GET['page'] == ACS_SLUG)
        wp_enqueue_style('tagify', ACS_PLUGIN_ROOT_URL . 'assets/css/tagify.css', array(), $this->version, 'all' );
        
	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		wp_enqueue_media();

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Wp_Arvancloud_Storage_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Wp_Arvancloud_Storage_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_script( $this->plugin_name, ACS_PLUGIN_ROOT_URL . 'assets/js/admin.js', array( 'jquery' ), $this->version, false );
        wp_enqueue_script( $this->plugin_name . '-bulkops', ACS_PLUGIN_ROOT_URL . 'assets/js/bulkops.js', array( 'jquery' ), $this->version, false );

		wp_localize_script( $this->plugin_name, 'acs_media', array(
			'strings' => $this->get_media_action_strings(),
			'nonces'  => array(
				'get_attachment_provider_details' => wp_create_nonce( 'get-attachment-s3-details' ),
				'generate_acl_url' => wp_create_nonce( 'generate_acl_url' ),
			),
			'ajax_url'  => admin_url( 'admin-ajax.php' ),
		) );

		if (isset( $_GET['system-info'] ) && $_GET['system-info'] == true) {
			wp_enqueue_script(  'clipboard' );
		}

        wp_localize_script( $this->plugin_name, 'obs_bulk_ops_nonce', array(

            'nonce'  => wp_create_nonce( 'obs-bulk-ops-nonce' ),

        ) );

        if (isset( $_GET['system-info'] ) && $_GET['system-info'] == true) {
            wp_enqueue_script(  'clipboard' );
        }
        if(isset($_GET['page']) and $_GET['page'] == ACS_SLUG)
        wp_enqueue_script('tagify', ACS_PLUGIN_ROOT_URL . 'assets/js/tagify.min.js', array( 'jquery' ), $this->version, false );
	}

	/**
     * Register submenu
	 * 
     * @return void
     */
    public function setup_admin_menu() {

        add_menu_page( 
			__( ACS_NAME, 'arvancloud-object-storage' ), 
			__( ACS_NAME, 'arvancloud-object-storage'), 
			'manage_options', 
			ACS_SLUG, 
			__CLASS__ . '::settings_page',
            ACS_PLUGIN_ROOT_URL . 'assets/img/arvancloud-logo.svg'
        );

		add_submenu_page(
			'wp-arvancloud-storage',
			$this->settings_page_title(),
			__( 'Settings', 'arvancloud-object-storage' ),
			'manage_options',
			ACS_SLUG,
			__CLASS__ . '::settings_page'
		);
        add_submenu_page(
            'wp-arvancloud-storage',
            $this->settings_page_title(),
            __( 'Scheduled Actions', 'arvancloud-object-storage' ),
            'manage_options',
            ACS_SLUG . '-filtertask',
            __CLASS__ . '::scheduler_filter'
        );
        add_submenu_page(
            'wp-arvancloud-storage',
            __( 'Direct Fetch', 'arvancloud-object-storage' ),
            __( 'Direct Fetch', 'arvancloud-object-storage' ),
            'manage_options',
            ACS_SLUG . '-download',
            __CLASS__ . '::download_from_arvan'
        );
        add_submenu_page(
            'wp-arvancloud-storage',
            __( 'About ArvanCloud', 'arvancloud-object-storage' ),
            __( 'About', 'arvancloud-object-storage' ),
            'manage_options',
            ACS_SLUG . '-about',
            __CLASS__ . '::about_us_page'
        );

    }
	
	/**
	 * settings_page
	 *
	 * @return void
	 */
	public static function settings_page() {
        $api_validator = new ApiValidator();

        $api_validator->periodicValidateApi();
		Partials::settings();

        (RemoveLocalFilesController::get_instance())->process();
    }

	public static function styles_page() {

		Partials::styles();
    }

	public function settings_page_title() {

		if (isset( $_GET['system-info'] ) && $_GET['system-info'] == true) {
			return __( 'System Info', 'arvancloud-object-storage' );
		}

		return __( 'Settings', 'arvancloud-object-storage' );
    }

	public static function download_from_arvan(){
		Partials::download_file_attachment();
	}
	
	/**
	 * about_us_page
	 *
	 * @return void
	 */
	public static function about_us_page() {

		Partials::about_us();

    }

    public static function scheduler_filter() {

        $arg = remove_query_arg( array( '_wp_http_referer', '_wpnonce' ), esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) );



        $admin_view = \ActionScheduler_AdminView::instance();

        if ( (!isset($_REQUEST['action']) || ($_REQUEST['action'] != 'delete')) && !empty( $_REQUEST['_wp_http_referer'] ) && ! empty( $_SERVER['REQUEST_URI'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            echo '<script>window.location = "'. $arg . '";</script>';

        }else if((isset($_REQUEST['action']) && ('delete' == $_REQUEST['action'])) || !isset($_REQUEST['s'])){
            echo '<script>window.location = "'. admin_url('admin.php?page=wp-arvancloud-storage-filtertask&s=obs') . '";</script>';

        }
        $admin_view->render_admin_ui();





    }
	/**
	 * Sets the access control system and saves it to an option after encryption
	 *
	 * @return void
	 */
	public function config_access_keys() {

		if( isset( $_POST[ 'config-cloud-storage' ] ) ) {
			$options = [ 'config-type'  => sanitize_text_field( $_POST[ 'config-type' ]??'' ) ];
            Helper::check_generic_nonce('obs_general_nonce', 'obs_general_nonce_data');
            Helper::check_user_authorization();
			if( $_POST[ 'config-type'] != 'snippet' ) {

                $access_key = empty($_POST[ 'access-keyx' ])?sanitize_key( $_POST[ 'access-key' ] ):sanitize_key( $_POST[ 'access-keyx' ] );
                $secret_key = empty($_POST[ 'secret-keyx' ])?sanitize_key( $_POST[ 'secret-key' ] ):sanitize_key( $_POST[ 'secret-keyx' ] );
                $region_key = empty($_POST[ 'regionx' ])?sanitize_key( $_POST[ 'region' ] ):sanitize_key( $_POST[ 'regionx' ] );
                $bucket_key = $_POST[ 'config-type' ] == 'normal_db' ? '' : sanitize_key( $_POST[ 'bucket_name' ] );
                $parsed_url = empty($_POST[ 'endpoint-urlx' ])?parse_url($_POST[ 'endpoint-url' ]):parse_url($_POST[ 'endpoint-urlx' ]);

                if(!isset($parsed_url['scheme']))
                    $parsed_url['scheme'] = 'https';

                $endpoint_url = $parsed_url['scheme'] . '://' . ($parsed_url['host']??'') . ($parsed_url['path']??'');

                $endpoint_url = wp_http_validate_url($endpoint_url);

                if( empty($access_key))
                {
                    Helper::show_admin_notice('Invalid access key');
                    return;
                }
                if( empty($secret_key)  )
                {
                    Helper::show_admin_notice('Invalid secret key');
                    return;
                }
                if( empty($region_key)  )
                {
                    Helper::show_admin_notice('Invalid region');
                    return;
                }
                
                if( empty($bucket_key) and $_POST[ 'config-type' ] == 'machine_db' )
                {
                    Helper::show_admin_notice('Invalid bucket name');
                    return;
                }               

                if( empty($endpoint_url) || !wp_http_validate_url($endpoint_url) )
                {

                    Helper::show_admin_notice('Invalid URL, Please make sure URL is correct');
                    return;
                }

                $options[ 'access-key' ]   = $access_key;
				$options[ 'secret-key' ]   = $secret_key;
				$options[ 'endpoint-url' ] = wp_http_validate_url($endpoint_url);
                $options[ 'region' ]       = $region_key;
                $options[ 'bucket_name' ]  = $bucket_key;

				if ( in_array($options[ 'secret-key' ],[__( "-- not shown --", 'arvancloud-object-storage' ),'----']) ) {
					$options[ 'secret-key' ] = $this->storage_settings[ 'secret-key' ];
				}


				// Validates that the access-key is UUID.
				if( !wp_is_uuid( $options[ 'access-key' ] ) ) {
					unset( $options[ 'access-key' ] );

					//update_option( 'arvan-cloud-storage-settings', (new Encryption)->encrypt( json_encode( $options ) ) );

					add_action( 'admin_notices', function () {
						echo '<div class="notice notice-error is-dismissible">
								<p>'. esc_html__( "Access Key is not valid!", 'arvancloud-object-storage' ) .'</p>
							</div>';
					} );

					return false;
				}

                if('FALSE' === Helper::check_secret_key($options)){
                    $url = admin_url('admin.php?page=' . ACS_SLUG . '&action=change-access-option');
                    Helper::show_admin_notice(__('There is a problem, please make sure your credentials is valid', 'wp-arvancloud-storage') . "<br/> <a href=$url>" . __('reset your credentials', 'wp-arvancloud-storage') . '</a> '  );
                    return;
                }
                
                if(!empty($options[ 'bucket_name' ])){
                    if('FALSE' === Helper::check_machine_bucket($options)){
                        $url = admin_url('admin.php?page=' . ACS_SLUG . '&action=change-access-option');
                        Helper::show_admin_notice(__('There is a problem, please make sure your bucket is valid', 'wp-arvancloud-storage') . "<br/> <a href=$url>" . __('reset your bucket', 'wp-arvancloud-storage') . '</a> '  );
                        return;
                    }                    
                }


			} else if ( ($_POST[ 'config-type' ]??'') == 'snippet' ) {
				$snippet_defined     = defined( 'ARVANCLOUD_STORAGE_SETTINGS' );

				if ( !$snippet_defined ) {
					add_action( 'admin_notices', function () {
						echo '<div class="notice notice-error is-dismissible">
								<p>'. esc_html__( "You have not defined your access keys in wp-config.php. Please try again.", 'arvancloud-object-storage' ) .'</p>
							</div>';
					} );

					return false;
				}
                $config = json_decode(ARVANCLOUD_STORAGE_SETTINGS, true);
                $endpoint_url = $config['endpoint-url']??'';
                $region_key   = $config['region']??'';

                if( empty($region_key)  )
                {
                    Helper::show_admin_notice('Invalid region');
                    return;
                }

                if( empty($endpoint_url) || !wp_http_validate_url($endpoint_url) )
                {

                    Helper::show_admin_notice('Invalid URL in wp-config.php settings, Please make sure URL begins with http:// or https://');
                    return;
                }
                if('FALSE' === Helper::check_secret_key(null, 'snippet')){
                    $url = admin_url('admin.php?page=' . ACS_SLUG . '&action=change-access-option');
                    Helper::show_admin_notice(__('There is a problem, please make sure your credentials is valid', 'wp-arvancloud-storage') . "<br/> <a href=$url>" . __('reset your credentials', 'wp-arvancloud-storage') . '</a> '  );
                    return;
                }
                
                if(!empty($config['bucket_name'])){
                    if('FALSE' === Helper::check_machine_bucket(null, 'snippet')){
                        $url = admin_url('admin.php?page=' . ACS_SLUG . '&action=change-access-option');
                        Helper::show_admin_notice(__('There is a problem, please make sure your bucket is valid', 'wp-arvancloud-storage') . "<br/> <a href=$url>" . __('reset your bucket', 'wp-arvancloud-storage') . '</a> '  );
                        return;
                    }                    
                }



			}else{
                Helper::show_admin_notice('Please select an item');
                return;
            }

			$save_settings = update_option( 'arvan-cloud-storage-settings', (new Encryption)->encrypt( json_encode( $options ) ) );
            update_option('OBS_INVALID_API_KEY', false);
			if( $save_settings ) {
				delete_option( 'arvan-cloud-storage-bucket-name' );
				
				if(!empty($options['bucket_name']) or !empty($config['bucket_name'])){
					$bucket = isset($options['bucket_name'])?$options['bucket_name']:$config['bucket_name'];
					update_option( 'arvan-cloud-storage-bucket-name',$bucket );
				}
					
				add_action( 'admin_notices', function () {
					echo '<div class="notice notice-success is-dismissible">
							<p>'. esc_html__( "Settings saved.", 'arvancloud-object-storage' ) .'</p>
						</div>';
				} );
			}
			return true;
		}

	}

	/**
	 * Saves selected bucket into database options table
	 *
	 * @return void
	 */
	public function store_selected_bucket_in_db() {

		if( isset( $_POST['acs-bucket-select-name'] ) ) {

            Helper::check_generic_nonce('obs_general_nonce', 'obs_general_nonce_data');
            Helper::check_user_authorization();
			if ( ! empty( get_option( 'arvan-cloud-storage-bucket-name' ) ) ) {
				delete_option( 'arvan-cloud-storage-bucket-name' );
			}

			$save_bucket = update_option( 'arvan-cloud-storage-bucket-name', sanitize_text_field( $_POST[ 'acs-bucket-select-name' ] ) );

			if( $save_bucket ) {
				wp_redirect(
					add_query_arg(
						array( 'notice' => 'selected-bucket-saved' ),
						wp_sanitize_redirect( admin_url( 'admin.php?page=wp-arvancloud-storage' ) )
					)
				);
				exit;
			} else {
				add_action( 'admin_notices', function () {
					echo '<div class="notice notice-error is-dismissible">
							<p>'. esc_html__( "Saving selected bucket failed. Please try again or contact with admin.", 'arvancloud-object-storage' ) .'</p>
						</div>';
				} );
			}
		}

	}

	/**
	 * Saves plugin settings into database options table
	 *
	 * @return void
	 */
	public function save_plugin_settings() {
		if( isset( $_POST['acs-settings'] ) ) {

      Helper::check_generic_nonce('obs_general_nonce', 'obs_general_nonce_data');
      Helper::check_user_authorization();
      $settings = [
				'keep-local-files' => isset( $_POST['keep-local-files'] ) ?: false,
				'sync-attachment-deletion' => isset( $_POST['sync-attachment-deletion'] ) ?: false,
                'file_ext'    => isset( $_POST['file_ext'] )    ?str_replace(['\\','.'],'',$_POST['file_ext']): '',
                'file_f_size' => isset( $_POST['file_f_size'] ) ?$_POST['file_f_size']: '',
                'file_t_size' => isset( $_POST['file_t_size'] ) ?$_POST['file_t_size']: '',
				'wooc_prev_upload_product' => isset( $_POST['wooc_prev_upload_product'] ) ?$_POST['wooc_prev_upload_product']: '',
			];

			$save_settings = update_option( 'acs_settings', $settings );

			if( $save_settings ) {
				add_action( 'admin_notices', function () {
					echo '<div class="notice notice-success is-dismissible">
							<p>'. esc_html__( "Settings saved.", 'arvancloud-object-storage' ) .'</p>
						</div>';
				} );
			} else {
				add_action( 'admin_notices', function () {
					echo '<div class="notice notice-error is-dismissible">
							<p>'. esc_html__( "Saving plugin settings failed. Please try again or contact with admin.", 'arvancloud-object-storage' ) .'</p>
						</div>';
				} );
			}
			
		}

	}
	
	/**
	 * Uploads media file to the storage bucket
	 *
	 * @param mixed $post_id 
	 * @param bool $force_upload Skips upload images by default
	 * @return void
	 */
	public function upload_media_to_storage( $post_id, $force_upload = false ) {


        $query_vars = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_QUERY);
        $result = [];
        parse_str($query_vars, $result);

        $action = $_REQUEST['action'] ?? '';

        if( 'copy_to_vod' == $action)
            return;

        if(isset($result['page']) && 'arvancloud-vod-videos-add' == $result['page'])
            return;
		if( !$this->bucket_name ) {
			return;
		}
        if(!self::check_valid_attachment($post_id))
        return false;

		if( $force_upload || ( is_numeric( $post_id ) && !wp_attachment_is_image( $post_id ) ) ) {
			if(  
				( isset( $_POST['action'] ) && ($_POST['action'] == 'upload-attachment' || $_POST['action'] == 'image-editor') ) || 
				$_SERVER['REQUEST_URI'] == '/wp-admin/async-upload.php' ||
				strpos( $_SERVER['REQUEST_URI'], 'media' ) !== false ||
				strpos( $_SERVER['REQUEST_URI'], 'action=copy' ) !== false ||
				( isset($_POST['html-upload']) && $_POST['html-upload'] == 'Upload' ) ||
				( isset($_POST['context']) && $_POST['context'] == 'site-icon') ||
				( isset($_POST['context']) && $_POST['context'] == 'custom_logo')||
				(is_admin() and isset($_GET['page']))
			) {

                $client = S3Singletone::get_instance()->get_s3client();
				$file 	   	  = is_numeric( $post_id ) ? get_attached_file( $post_id ) : $post_id;
				if(is_dir($file))
				return;
				$file_size 	  = number_format( filesize( $file ) / 1048576, 2 ); // File size in MB
				$up_dir = wp_upload_dir();
				$key = ltrim($up_dir['subdir'],'/').'/';
				if( $file_size > 400 ) {
					$uploader = new MultipartUploader( $client, $file, [
						'bucket' => $this->bucket_name,
						'key'    => $key.basename( $file ),
						'ACL' 	 => 'public-read', // or private
					]);
	
					try {
						$result = @$uploader->upload();
	
						add_action( 'admin_notices', function () use( $result ) {
							echo '<div class="notice notice-success is-dismissible">
									<p>'. esc_html__( "Upload complete:" . $result['ObjectURL'], 'arvancloud-object-storage' ) .'</p>
								</div>';
						} );
					} catch ( MultipartUploadException $e ) {
						add_action( 'admin_notices', function () use( $e ) {
							echo '<div class="notice notice-error is-dismissible">
									<p>'. esc_html( $e->getMessage() ) .'</p>
								</div>';
						} );
					}
				} else {
					try {
						$result = $client->putObject([
							'Bucket' 	 => $this->bucket_name,
							'Key' 		 => $key.basename( $file ),
							'SourceFile' => $file,
							'ACL' 		 => 'public-read', // or private
						]);
					} catch ( MultipartUploadException $e ) {
						add_action( 'admin_notices', function () use( $e ) {
							echo '<div class="notice notice-error is-dismissible">
									<p>'. esc_html( $e->getMessage() ) .'</p>
								</div>';
						} );
					}
				}
	
				if( is_numeric( $post_id ) ) {
					update_post_meta( $post_id, 'acs_storage_file_url', Helper::get_storage_url());
					update_post_meta( $post_id, 'acs_storage_file_dir', $key );
	
					if( !$this->acs_settings['keep-local-files'] && !wp_attachment_is_image( $post_id ) ) {
						unlink( $file );
					}
				}
				return true;
			}
		}
	}

	/**
	 * Uploads images and its sub sizes to the storage bucket
	 * 
	 * @param mixed $args 
	 * @return void
	 */
	public function upload_image_to_storage( $args ) {

        $query_vars = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_QUERY);

        $result = [];
        parse_str($query_vars, $result);

        $action = $_REQUEST['action'] ?? '';

        if( 'copy_to_vod' == $action)
            return;

        if(isset($result['page']) && 'arvancloud-vod-videos-add' == $result['page'])
            return;
		if(empty($args['file']))
			return false;
		
		$upload_dir = wp_upload_dir();
        if(!self::check_valid_attachment($upload_dir['basedir'] . '/' . $args['file']))
            return $args;
		$basename	= basename( $args['file'] );
		$path 		= str_replace( $basename, "", $args['file'] );
		$url	    = $upload_dir['baseurl'] . '/' . $args['file'];
		$post_id	= attachment_url_to_postid($url);
        if(!file_exists($upload_dir['basedir'] . '/' . $args['file']))
            return $args;


		$this->upload_media_to_storage( $upload_dir['basedir'] . '/' . $args['file'], true );
		
		$key = ltrim($upload_dir['subdir'],'/').'/';
        @(S3Singletone::get_instance())->get_s3client()->putObjectTagging([
            'Bucket' => $this->bucket_name,
            'Key' => $key.$basename,
            'Tagging' => [
                'TagSet' => [
                    [
                        'Key' => 'main_attachment',
                        'Value' => 'true',
                    ]
                ],
            ],
        ]);
		
		update_post_meta( $post_id, 'acs_storage_file_url', Helper::get_storage_url() );
		update_post_meta( $post_id, 'acs_storage_file_dir', $key );
		// Check if image has extra sizes
		if( array_key_exists( "sizes", $args ) ) {
			foreach ( $args['sizes'] as $sub_size ) {
				if ( $sub_size['file'] != "" ) {
					$file = $upload_dir['basedir'] . '/' . $path . $sub_size['file'];
                    if(!file_exists($file))
                        return;

                    
					$this->upload_media_to_storage( $file, true );

					if( isset($this->acs_settings['keep-local-files']) && !$this->acs_settings['keep-local-files'] ) {
						unlink( $file );
					}
				}
			}
		}

		if( isset($this->acs_settings['keep-local-files']) && !$this->acs_settings['keep-local-files'] ) {
			unlink( $upload_dir['basedir'] . '/' . $args['file'] );
		}

		return $args;

	}

	/**
	 * Deletes media from the storage bucket
	 *
	 * @param mixed $id 
	 * @return void
	 */
	public function delete_media_from_storage( $id ) {

        $acs_settings	     = get_option( 'acs_settings' );
        if(false == $acs_settings['sync-attachment-deletion'])
            return;

        if( !$this->bucket_name ) {
			return;
		}

		if( ( isset( $_POST['action'] ) && $_POST['action'] == 'delete-post' || $_POST['action'] == 'image-editor' ) && $this->is_attachment_served_by_storage( $id ) ) {

            $client = S3Singletone::get_instance()->get_s3client();

			if ( wp_attachment_is_image( $id ) ) {
				$args = wp_get_attachment_metadata( $id );

				$client->deleteObject ([
					'Bucket' => $this->bucket_name, 
					'Key' 	 => basename( $args['file'] )
				]);

				// Check if image has extra sizes
				if ( $args && array_key_exists( "sizes", $args ) ) {
					foreach ( $args['sizes'] as $list_file ) {
						if ( $list_file['file'] != "" ) {
							$client->deleteObject ([
								'Bucket' => $this->bucket_name, 
								'Key' 	 => basename( $list_file['file'] )
							]);
						}
					}
				}
			} else {
				$file = get_attached_file( $id );

				$client->deleteObject ([
					'Bucket' => $this->bucket_name, 
					'Key' 	 => basename( $file )
				]);
			}
		}
	}

	/**
	 * Calculates image srcset
	 *
	 * @param mixed $sources 
	 * @param mixed $size_array 
	 * @param mixed $image_src 
	 * @param mixed $image_meta 
	 * @param mixed $attachment_id 
	 * @return void
	 */
	public function calculate_image_srcset( $sources, $size_array, $image_src, $image_meta, $attachment_id ) {

		$base_upload      = wp_upload_dir();
		$uploads          = $base_upload['baseurl'];
		$filtered_sources = array();

		foreach ( $sources as $key => $source ) {
			if ( wp_attachment_is_image( $attachment_id ) ) {
				$cdn = get_post_meta( $attachment_id, 'acs_storage_file_url', true );
				
				if ( !empty( $cdn ) ) {
					$source['url'] = str_replace( trailingslashit( $uploads ), trailingslashit( Helper::get_storage_url() ), $source['url'] );
				}
			}

			$filtered_sources[ $key ] = $source;
		}

		return $filtered_sources;

	}

	/**
	 * Handles the upload of the attachment to provider when an attachment is updated using
	 * the 'wp_update_attachment_metadata' filter
	 *
	 * @param array $data meta data for attachment
	 * @param int   $post_id
	 *
	 * @return array
	 * @throws Exception
	 */
	function wp_update_attachment_metadata( $data, $post_id ) {

		if ( ! $this->bucket_name || ( isset( $_POST['action'] ) && $_POST['action'] == 'upload-attachment' ) ) {
			return $data;
		}

		// Protect against updates of partially formed metadata since WordPress 5.3.
		// Checks whether new upload currently has no subsizes recorded but is expected to have subsizes during upload,
		// and if so, are any of its currently missing sizes part of the set.
		if ( ! empty( $data ) && function_exists( 'wp_get_registered_image_subsizes' ) && function_exists( 'wp_get_missing_image_subsizes' ) ) {
			if ( empty( $data['sizes'] ) && wp_attachment_is_image( $post_id ) ) {
				// There is no unified way of checking whether subsizes are expected, so we have to duplicate WordPress code here.
				$new_sizes     = wp_get_registered_image_subsizes();
				$new_sizes     = apply_filters( 'intermediate_image_sizes_advanced', $new_sizes, $data, $post_id );
				$missing_sizes = wp_get_missing_image_subsizes( $post_id );

				if ( ! empty( $new_sizes ) && ! empty( $missing_sizes ) && array_intersect_key( $missing_sizes, $new_sizes ) ) {
					return $data;
				}
			}
		}

		// upload attachment to bucket
		if (!$this->is_attachment_served_by_storage( $post_id )) {
			$attachment_metadata = $this->upload_image_to_storage( $data );
	
			if ( is_wp_error( $attachment_metadata ) || empty( $attachment_metadata ) || ! is_array( $attachment_metadata ) ) {
				return $data;
			}
	
			return $attachment_metadata;
		}

		return $data;
	}

	/**
	 * Rewirtes media library url to the storage url
	 *
	 * @param mixed $url 
	 * @return void
	 */
	public function media_library_url_rewrite( $url, $attachment_id ) {

		$storage_file_url = get_post_meta( $attachment_id, 'acs_storage_file_url', true );

		if( !empty( $storage_file_url ) ) {
			$storage_file_url .= get_post_meta( $attachment_id, 'acs_storage_file_dir', true );
			$file_name = basename( $url );
			$url 	   = esc_url( $storage_file_url.$file_name );

			// Show private files in admin
			if ( is_admin() && get_post_meta( $attachment_id, 'acs_acl', true ) == 'private' ) {

				return $this->get_object_private_url( $attachment_id, $file_name, 8640);
			}
		}

		return $url;
	}

	public function attachment_image_src_filter( $image, $attachment_id, $size) {

		$storage_file_url = get_post_meta( $attachment_id, 'acs_storage_file_url', true );
		
		if( empty( $storage_file_url ) ) {
			return $image;
		}

		if ( isset($image[0]) ) {
			$parsed_url[0] = parse_url($image[0]);
			$path = '/' . $this->bucket_name . '/';
			if ( isset($parsed_url[0]['query']) && isset($parsed_url[0]['path']) && substr($parsed_url[0]['path'], 0, strlen($path)) === $path ) {
				return $image;
			}
			$image[0] = $this->media_library_url_rewrite( $image[0], $attachment_id );
		}

		return $image;
	}

	protected function generate_private_url( $file_key, $expiry ) {
		$client = $this->s3_client_creator();

		$bucket_selected = $this->bucket_name;

		// convert $expiry from minutes to string

		$date = (new \DateTime())->modify("+$expiry minutes");

		try {
			$result = $client->getCommand('GetObject', [
				'Bucket' => $bucket_selected,
				'Key' => $file_key,
			]);
			
			$request = $client->createPresignedRequest($result, $date);

			$presignedUrl = (string)$request->getUri();

		} catch (AwsException $e) {
			add_action( 'admin_notices', function () {
				echo '<div class="notice notice-error is-dismissible">
						<p>'. esc_html__( "There is a problem.", 'arvancloud-object-storage' ) .'</p>
					</div>';
			} );
			return false;
		}

		return $presignedUrl;
	}

	protected function get_object_private_url( $post_id, $file_key, $expiry ) {
		$presigned_url = get_post_meta( $post_id, 'acs_presigned_url', true );
		if ( isset( $presigned_url[$file_key] ) &&
			!empty($presigned_url[$file_key]['last_update']) &&
			strtotime($presigned_url[$file_key]['last_update']) > strtotime("-1 day")
			) {
			return $presigned_url[$file_key]['url'];
		}

		$private_url = $this->generate_private_url( $file_key, $expiry );

		if ( empty( $private_url ) ) {
			return false;
		}

		$presigned_url = empty( $presigned_url ) ? array() : (array)$presigned_url;
		
		$presigned_url[$file_key] = array(
			'url' => $private_url,
			'last_update' => date('Y-m-d H:i:s'),
		);
		update_post_meta( $post_id, 'acs_presigned_url', $presigned_url );

		return $private_url;

	}

	/**
	 * Adds copy to bucket link to Bulk actions
	 *
	 * @param mixed $bulk_actions 
	 * @return void
	 */
	public function bulk_actions_upload( $bulk_actions ) {

		if( $this->bucket_name ) {
			$bulk_actions['bulk_acs_copy'] = __( 'Copy to Bucket', 'arvancloud-object-storage' );
			$bulk_actions['bulk_acs_acl_public'] = __( 'Make Public in Bucket', 'arvancloud-object-storage' );
			$bulk_actions['bulk_acs_acl_private'] = __( 'Make Private in Bucket', 'arvancloud-object-storage' );
		}

		return $bulk_actions;

	}

	/**
	 * Handles bulk actions upload
	 *
	 * @param mixed $redirect 
	 * @param mixed $do_action 
	 * @param mixed $object_ids 
	 * @return void
	 */
	public function handle_bulk_actions_upload( $redirect, $do_action, $object_ids ) {

		$redirect = remove_query_arg( 'bulk_acs_copy_done', $redirect );

		if ( $do_action == 'bulk_acs_copy' ) {
			foreach ( $object_ids as $post_id ) {
				sleep( 2 ); // Delay execution
				if(!self::check_valid_attachment($post_id))
                    return;

				if( wp_attachment_is_image( $post_id ) ) {
					$file = wp_get_attachment_metadata($post_id);
					$this->upload_image_to_storage( $file );
				} else {
					$this->upload_media_to_storage( $post_id );
				}
			}
	
			// add query args to URL because we will show notices later
			$redirect = add_query_arg(
				'bulk_acs_copy_done', // just a parameter for URL ( we will use $_GET['acs_copy_done'] )
				count( $object_ids ), // parameter value - how much posts have been affected
			$redirect );
		}

		if ( $do_action == 'bulk_acs_acl_public' || $do_action == 'bulk_acs_acl_private' ) {
			global $post;
			$acl = 'public-read';
			if ( $do_action == 'bulk_acs_acl_private' ) {
				$acl = 'private';
			}
			$sleep_time = 1;
			$changed_count = 0;
			

			foreach ( $object_ids as $post_id ) {
				sleep( $sleep_time ); // Delay execution

				if ( !$this->is_attachment_served_by_storage( $post_id, true ) ) {
					continue;
				}
				
				$file = wp_get_attachment_metadata($post_id);
				$file_key = basename(get_the_guid($post_id));
	
				// Check if image has extra sizes
				if( wp_attachment_is_image( $post_id ) && array_key_exists( "sizes", $file ) ) {
					foreach ( $file['sizes'] as $sub_size ) {
						if ( $sub_size['file'] != "" ) {
							if ($this->change_object_acl( $sub_size['file'], $acl )) {
								$changed_count++;
							}
						}
					}
				}
				if ($this->change_object_acl( $file_key, $acl )) {
					$changed_count++;
					// Update post meta
					update_post_meta( $post_id, 'acs_acl', $acl );
				}
			}
	
			// add query args to URL because we will show notices later
			$done_key = $do_action == 'bulk_acs_acl_public' ? 'bulk_acs_acl_public_done' : 'bulk_acs_acl_private_done';
			$redirect = add_query_arg([
				$done_key => count( $object_ids ),
				'objects_changed' => $changed_count
			],
			$redirect );
		}

		return $redirect;

	}

	/**
	 * ajax_get_attachment_provider_details
	 *
	 * @return void
	 */
	public function ajax_get_attachment_provider_details() {
		
		if ( ! isset( $_POST['id'] ) ) {
			return;
		}

		check_ajax_referer( 'get-attachment-s3-details', '_nonce' );

		$id = intval( sanitize_text_field( $_POST['id'] ) );

		// get the actions available for the attachment
		$data = array(
			'links' => $this->add_media_row_actions( array(), $id ),
		);

		wp_send_json_success( $data );

	}

	/**
	 * Conditionally adds media action links for an attachment on the Media library list view.
	 *
	 * @param array       $actions
	 * @param WP_Post|int $post
	 *
	 * @return array
	 */
	function add_media_row_actions( array $actions, $post ) {

		$available_actions = $this->get_available_media_actions( 'singular' );

		if ( ! $available_actions ) {
			return $actions;
		}

		$post_id     = ( is_object( $post ) ) ? $post->ID : $post;
		$file        = get_attached_file( $post_id, true );
		$file_exists = file_exists( $file );

		if ( in_array( 'copy', $available_actions ) && $file_exists && ! $this->is_attachment_served_by_storage( $post_id, true ) ) {
			$this->add_media_row_action( $actions, $post_id, 'copy' );
		}

		return $actions;

	}

	/**
	 * Add an action link to the media actions array
	 *
	 * @param array  $actions
	 * @param int    $post_id
	 * @param string $action
	 * @param string $text
	 * @param bool   $show_warning
	 */
	function add_media_row_action( &$actions, $post_id, $action, $text = '', $show_warning = false ) {

		$url   = $this->get_media_action_url( $action, $post_id );
		$text  = $text ?: $this->get_media_action_strings( $action );
		$class = $action;

		if ( $show_warning ) {
			$class .= ' local-warning';
		}

		$actions[ 'acs_' . $action ] = '<a href="' . $url . '" class="ar-copy ' . $class . '" title="' . esc_attr( $text ) . '">' . esc_html( $text ) . '</a>';

	}

	/**
	 * Generate the URL for performing S3 media actions
	 *
	 * @param string      $action
	 * @param int         $post_id
	 * @param null|string $sendback_path
	 *
	 * @return string
	 */
	function get_media_action_url( $action, $post_id, $sendback_path = null ) {

		$args = array(
			'action' => $action,
			'ids'    => $post_id,
		);

		if ( ! is_null( $sendback_path ) ) {
			$args['sendback'] = urlencode( admin_url( $sendback_path ) );
		}

		$url = add_query_arg( $args, admin_url( 'upload.php' ) );
		$url = wp_nonce_url( $url, 'acs-' . $action );

		return esc_url( $url );

	}

	/**
	 * Get all strings or a specific string used for the media actions
	 *
	 * @param null|string $string
	 *
	 * @return array|string
	 */
	public function get_media_action_strings( $string = null ) {

		$strings = apply_filters( 'acs_media_action_strings', array(
			'copy' => __( 'Copy to Bucket', 'arvancloud-object-storage' ),
		) );

		if ( ! is_null( $string ) ) {
			return isset( $strings[ $string ] ) ? $strings[ $string ] : '';
		}

		return $strings;

	}

	/**
	 * Get a list of available media actions which can be performed according to plugin and user capability requirements.
	 *
	 * @param string|null $scope
	 *
	 * @return array
	 */
	public function get_available_media_actions( $scope = null ) {

		$actions = array();

		$actions['copy'] = array( 'singular', 'bulk' );

		if ( $scope ) {
			$in_scope = array_filter( $actions, function ( $scopes ) use ( $scope ) {
				return in_array( $scope, $scopes );
			} );

			return array_keys( $in_scope );
		}

		return $actions;

	}

	/**
	 * Is attachment served by object storage.
	 *
	 * @param int                   $attachment_id
	 *
	 * @return bool|Media_Library_Item
	 */
	public function is_attachment_served_by_storage( $attachment_id ) {

		$acs_item = get_post_meta( $attachment_id, 'acs_storage_file_url', true );

		if ( empty( $acs_item ) ) {
			// File not uploaded to a provider
			return false;
		}

		return true;

	}

	/**
	 * Handler for single and bulk media actions
	 *
	 * @return void
	 */
	function process_media_actions() {

		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			return;
		}

		global $pagenow;

		if ( 'upload.php' != $pagenow ) {
			return;
		}

		if ( ! isset( $_GET['action'] ) ) { // input var okay
			return;
		}

		if ( ! empty( $_REQUEST['action2'] ) && '-1' != $_REQUEST['action2'] ) {
			// Handle bulk actions from the footer bulk action select
			$action = sanitize_key( $_REQUEST['action2'] ); // input var okay
		} else {
			$action = sanitize_key( $_REQUEST['action'] ); // input var okay
		}

		if ( false === strpos( $action, 'bulk_acs_' ) ) {
			$available_actions = $this->get_available_media_actions( 'singular' );
			$referrer          = 'acs-' . $action;
			$doing_bulk_action = false;

			if ( ! isset( $_GET['ids'] ) ) {
				return;
			}

			$ids = explode( ',', sanitize_text_field( $_GET['ids'] ) ); // input var okay
		} else {
			$available_actions = $this->get_available_media_actions( 'bulk' );
			$action            = str_replace( 'bulk_acs_', '', $action );
			$referrer          = 'bulk-media';
			$doing_bulk_action = true;

			if ( ! isset( $_REQUEST['media'] ) ) {
				return;
			}

			$ids = Helper::acs_recursive_sanitize( $_REQUEST['media'] ); // input var okay
		}

		if ( ! in_array( $action, $available_actions ) ) {
			return;
		}

		$ids      = array_map( 'intval', $ids );
		$id_count = count( $ids );

		check_admin_referer( $referrer );

		$sendback = isset( $_GET['sendback'] ) ? sanitize_text_field( $_GET['sendback'] ) : admin_url( 'upload.php' );

		$args = array(
			'acs-action' => $action,
		);

		$result = $this->maybe_do_provider_action( $action, $ids, $doing_bulk_action );

		if ( ! $result ) {
			unset( $args['acs-action'] );
			$result = array();
		}

		// If we're uploading a single file, add the id to the `$args` array.
		if ( 'copy' === $action && 1 === $id_count && ! empty( $result ) && 1 === ( $result['count'] + $result['errors'] ) ) {
			$args['acs_id'] = array_shift( $ids );
		}

		$args = array_merge( $args, $result );
		$url  = add_query_arg( $args, $sendback );

		wp_redirect( esc_url_raw( $url ) );
		exit();

	}

	/**
	 * Wrapper for media actions
	 *
	 * @param string $action             type of media action, copy, remove, download, remove_local
	 * @param array  $ids                attachment IDs
	 * @param bool   $doing_bulk_action  flag for multiple attachments, if true then we need to
	 *                                   perform a check for each attachment
	 *
	 * @return bool|array on success array with success count and error count
	 * @throws Exception
	 */
	function maybe_do_provider_action( $action, $ids, $doing_bulk_action ) {

		switch ( $action ) {
			case 'copy':
				$result = $this->maybe_upload_attachments( $ids, $doing_bulk_action );
				break;
		}

		return $result;
	}

	/**
	 * Display notices after processing media actions
	 *
	 * @return void
	 */
	function maybe_display_media_action_message() {

		global $pagenow;

		if ( ! in_array( $pagenow, array( 'upload.php', 'post.php' ) ) ) {
			return;
		}

		if ( isset( $_GET['acs-action'] ) && isset( $_GET['errors'] ) && isset( $_GET['count'] ) ) {
			$action 	  = sanitize_key( $_GET['acs-action'] ); // input var okay
			$error_count  = absint( $_GET['errors'] ); // input var okay
			$count        = absint( $_GET['count'] ); // input var okay
			
			echo $this->get_media_action_result_message( $action, $count, $error_count );
		}

		if (( isset( $_GET['bulk_acs_acl_public_done'] ) || isset( $_GET['bulk_acs_acl_private_done'] ) ) && isset( $_GET['objects_changed'] ) ) {
			echo '<div class="notice notice-success is-dismissible">
					<p>' . esc_html__( " Object acl has been successfully changed.", 'arvancloud-object-storage' ) .'</p>
				</div>';
		}
	}

	/**
	 * Get the result message after an S3 action has been performed
	 *
	 * @param string $action      type of S3 action
	 * @param int    $count       count of successful processes
	 * @param int    $error_count count of errors
	 *
	 * @return bool|string
	 */
	function get_media_action_result_message( $action, $count = 0, $error_count = 0 ) {

		$class = 'updated';
		$type  = 'success';

		if ( 0 === $count && 0 === $error_count ) {
			// don't show any message if no attachments processed
			// i.e. they haven't met the checks for bulk actions
			return false;
		}

		if ( $error_count > 0 ) {
			$type = $class = 'error';

			// We have processed some successfully.
			if ( $count > 0 ) {
				$type = 'partial';
			}
		}

		$message = $this->get_message( $action, $type );

		// can't find a relevant message, abort
		if ( ! $message ) {
			return false;
		}

		$id = $this->filter_input( 'acs_id', INPUT_GET, FILTER_VALIDATE_INT );

		// If we're uploading a single item, add an edit link.
		if ( 1 === ( $count + $error_count ) && ! empty( $id ) ) {
			$url = esc_url( get_edit_post_link( $id ) );

			// Only add the link if we have a URL.
			if ( ! empty( $url ) ) {
				$text    = esc_html__( 'Edit attachment', 'arvancloud-object-storage' );
				$message .= sprintf( ' <a href="%1$s">%2$s</a>', $url, $text );
			}
		}

		$message = sprintf( '<div class="notice acs-notice %s is-dismissible"><p>%s</p></div>', $class, $message );

		return $message;

	}

	/**
	 * Retrieve all the media action related notice messages
	 *
	 * @return array
	 */
	function get_messages() {
		$messages = array(
			'copy'         => array(
				'success' => __( 'Media successfully copied to bucket.', 'arvancloud-object-storage' ),
				'partial' => __( 'Media copied to bucket with some errors.', 'arvancloud-object-storage' ),
				'error'   => __( 'There were errors when copying the media to bucket.', 'arvancloud-object-storage' ),
			)
		);

		return $messages;
	}

	/**
	 * Get a specific media action notice message
	 *
	 * @param string $action type of action, e.g. copy, remove, download
	 * @param string $type   if the action has resulted in success, error, partial (errors)
	 *
	 * @return string|bool
	 */
	function get_message( $action = 'copy', $type = 'success' ) {

		$messages = $this->get_messages();

		if ( isset( $messages[ $action ][ $type ] ) ) {
			return $messages[ $action ][ $type ];
		}

		return false;

	}

	/**
	 * Helper function for filtering super globals. Easily testable.
	 *
	 * @param string $variable
	 * @param int    $type
	 * @param int    $filter
	 * @param mixed  $options
	 *
	 * @return mixed
	 */
	public function filter_input( $variable, $type = INPUT_GET, $filter = FILTER_DEFAULT, $options = array() ) {
		return filter_input( $type, $variable, $filter, $options );
	}

	/**
	 * Wrapper for uploading multiple attachments to S3
	 *
	 * @param array $post_ids            attachment IDs
	 * @param bool  $doing_bulk_action   flag for multiple attachments, if true then we need to
	 *                                   perform a check for each attachment to make sure the
	 *                                   file exists locally before uploading to S3
	 *
	 * @return array|WP_Error
	 * @throws Exception
	 */
	function maybe_upload_attachments( $post_ids, $doing_bulk_action = false ) {

		$error_count    = 0;
		$uploaded_count = 0;

		foreach ( $post_ids as $post_id ) {
		    if(!self::check_valid_attachment($post_id))
            continue;

			$file = wp_get_attachment_metadata($post_id);

			if ( $doing_bulk_action ) {
				// if the file doesn't exist locally we can't copy
				if ( ! file_exists( get_attached_file($post_id) ) ) {
					continue;
				}
			}

			if( wp_attachment_is_image( $post_id ) ) {
				$result = $this->upload_image_to_storage( $file );
			} else {
				$result = $this->upload_media_to_storage( $post_id );
			}

			if ( is_wp_error( $result ) ) {
				$error_count++;
				continue;
			}

			$uploaded_count++;
		}

		$result = array(
			'errors' => $error_count,
			'count'  => $uploaded_count,
		);

		return $result;

	}

	/**
	 * add_edit_attachment_metabox
	 *
	 * @param mixed $post 
	 * @return void
	 */
	public function add_edit_attachment_metabox( $post ) {

			add_meta_box(
				'arvancloud-storage-metabox',
				__( 'ArvanCloud Storage', 'arvancloud-object-storage' ),
				array( $this, 'render_edit_attachment_metabox' ),
				'attachment',
				'side',
				'default'
			);

			$post_id = get_the_ID();

			if ( get_post_meta( $post_id, 'acs_acl', true ) === 'private' && !isset($_GET['change_acl']) ||
			(isset($_GET['change_acl']) && isset($_GET['acl']) && sanitize_text_field( $_GET['acl'] ) === 'private')) {
				add_meta_box(
					'arvancloud-storage-ACL-metabox',
					__( 'Generate Presigned URL', 'arvancloud-object-storage' ),
					array( $this, 'render_private_url_generator_metabox' ),
					'attachment',
					'side',
					'default'
				);
			}
    }

	public function render_private_url_generator_metabox() {

		
		$post_id = get_the_ID();
		
		$presigned_urls = get_post_meta($post_id, 'acs_presigned_urls', true);
		?>
		<div class="arvancloud-storage-generated_urls">
			<ul>
				<?php
				if (!empty($presigned_urls)){
					foreach($presigned_urls as $url) {
						if (!isset($url['url'])) {
							continue;
						}
						$date = (new \DateTime($url['expire']));
						$date1 = (new \DateTime("now"));
						$diff = date_diff($date1, $date);
						if ($diff->format('%r') == '-') {
							continue;
						}
						echo '<li>
								<input type="text" class="widefat urlfield" readonly="readonly" value="'. $url['url'] .'">
								<span>'. $diff->format(__('%d Days, %h Hours, %i Minutes', 'arvancloud-object-storage')) .'</span>
							</li>';
					}
				}
				?>
			</ul>
	
		</div>
			<div class="form-group">
				<label for="arvancloud-storage-acl-expiry">
					<?php _e( 'Expiry', 'arvancloud-object-storage' ); ?>
				</label>
				<select class="form-control" id="arvancloud-storage-acl-expiry" name="arvancloud-storage-acl-expiry">
					<option value="5"><?php _e('5 minutes', 'arvancloud-object-storage'); ?></option>
					<option value="30"><?php _e('30 minutes', 'arvancloud-object-storage'); ?></option>
					<option value="60"><?php _e('1 hour', 'arvancloud-object-storage'); ?></option>
					<option value="720"><?php _e('12 hours', 'arvancloud-object-storage'); ?></option>
					<option value="1440"><?php _e('1 day', 'arvancloud-object-storage'); ?></option>
					<option value="10080"><?php _e('1 week', 'arvancloud-object-storage'); ?></option>
					<option value="custom"><?php _e('Custom', 'arvancloud-object-storage'); ?></option>
				</select>
			</div>
			<div class="form-group" style="display: none;">
				<label for="arvancloud-storage-acl-expiry-custom">
					<?php _e( 'Custom expiration by the hour', 'arvancloud-object-storage' ); ?>
				</label>
				<input type="number" class="form-control" id="arvancloud-storage-acl-expiry-custom" name="arvancloud-storage-acl-expiry-custom" min="1" max="168" value="1">
				<input type="hidden" name="acl-post-id" value="<?php echo $post_id ?>">
			</div>
			<div class="acl-url-generator-holder">
				<button type="button" class="button" id="arvancloud-storage-acl-url-generator-button">
					<?php _e( 'Generate Presigned URL', 'arvancloud-object-storage' ); ?>
				</button>
			</div>

		<?php
	}

	public function handle_generate_acl_url() {

		check_ajax_referer( 'generate_acl_url', '_nonce' );

		$post_id = sanitize_text_field( $_GET['post_id'] );
		$expiry = sanitize_text_field( $_GET['expiry'] );

		$storage_file_url = get_the_guid($post_id);

		if( empty( $storage_file_url ) || empty( $expiry ) || empty( $post_id ) ) {
			wp_send_json_error( array(
				'message' => __( 'Something went wrong. Please try again.', 'arvancloud-object-storage' ),
			) );
			wp_die();
		}


		$file_name = basename( $storage_file_url );

		
		$url = $this->generate_private_url( $file_name, $expiry );
		
		$expiry++;
		$date = (new \DateTime())->modify("+$expiry minutes");
		$date1 = (new \DateTime("now"));
		$diff = date_diff($date1, $date);
		
		if (empty(get_post_meta($post_id, 'acs_presigned_urls'))) {
			update_post_meta($post_id, 'acs_presigned_urls', array());
		}
		$presigned_urls = get_post_meta($post_id, 'acs_presigned_urls', true);
		array_push($presigned_urls, array(
			'url'	=> $url,
			'expire'=> $date->format('Y/m/d H:i:s')
		));
		update_post_meta($post_id, 'acs_presigned_urls', $presigned_urls);
		
		wp_send_json_success( array(
			'url' => $url,
			'expiry' => __('Time left: ', 'arvancloud-object-storage') . $diff->format(__('%d Days, %h Hours, %i Minutes', 'arvancloud-object-storage')),
		), 200 );
		wp_die();

	}


	/**
	 * render_edit_attachment_metabox
	 *
	 * @return void
	 */
	public function render_edit_attachment_metabox() {

		global $post;

		$is_attachment_served_by_storage = $this->is_attachment_served_by_storage( $_GET['post'], true );

		if( !$is_attachment_served_by_storage ) {
	
			$actions = $this->add_media_row_actions( array(), $post );
			foreach( $actions as $action ) {
				echo wp_kses_post( $action );
			}

			return;
		}


		$client = $this->s3_client_creator();
		$bucket_selected = $this->bucket_name;
		$file_key	     = basename($post->guid);

		try {
			$result = $client->getObjectAcl([
				'Bucket' => $bucket_selected,
				'Key' => $file_key,
			]);

		} catch (AwsException $e) {
			return;
		}

		$acl = 'private';

		foreach($result['Grants'] as $Grants) {
			if( $Grants['Grantee']['Type'] == 'Group' && $Grants['Grantee']['URI'] == 'http://acs.amazonaws.com/groups/global/AllUsers' ) {
				$acl = 'public-read';
			}
		}

		$nonce = wp_create_nonce( 'object-storage-nonce-acl' );
		$url = add_query_arg( array(
			'action' => 'edit',
			'change_acl' => 'true',
			'nonce' => $nonce,
			'acl' => $acl == 'public-read' ? 'private' : 'public-read',
			'post' => $post->ID,
		), admin_url( 'post.php' ) );
		$string = $acl == 'private' ? __( 'Make Public in Bucket', 'arvancloud-object-storage' ) : __( 'Make Private in Bucket', 'arvancloud-object-storage' );

		echo '<a type="button" href="' . $url . '" class="button" title="' . '' . '">' . $string . '</a>';

		return;
    }

	/**
	 * Upload all images
	 *
	 * @param [type] $do_action
	 * @return true
	 */
	public function handle_bulk_upload() {
		ini_set('max_execution_time', '0');

		// Get images IDs
		$object_ids = get_posts( 
			array(
				'post_type'      => 'attachment', 
				'posts_per_page' => -1,
				'fields'         => 'ids',
			) 
		);

		// Reset option for ajax data (progress bar)
		update_option('arvan-cloud-storage-bulk-upload-percent', 0);
		update_option('arvan-cloud-storage-bulk-upload-new', 0);
		update_option('arvan-cloud-storage-bulk-upload-error', 0);

		$percentage_option = 0;
		$percentage = 0;
		$uploaded_count = 0;
		$error_count = 0;

		$one_percent = 0;

		foreach ( $object_ids as $post_id ) {
            if(!self::check_valid_attachment($post_id))
            continue;
			$one_percent++;
			$storage_file_url = get_post_meta( $post_id, 'acs_storage_file_url' );

			
			if (empty($storage_file_url)) {
				sleep( 0.3 ); // Delay execution

				$_POST['action'] = 'upload-attachment';

				// if the file doesn't exist locally we can't copy
				if ( ! file_exists( get_attached_file($post_id) ) ) {
					continue;
				}

				// Upload Attachment
				$type = get_post_mime_type($post_id);
				if( wp_attachment_is_image( $post_id ) && $type != 'image/svg+xml' ) {
					$file = wp_get_attachment_metadata($post_id);
					$result = $this->upload_image_to_storage( $file );
				} else {
					$result = $this->upload_media_to_storage( $post_id );
				}

				if ( is_wp_error( $result ) ) {
					$error_count++;
				}

				$uploaded_count++;
				update_option('arvan-cloud-storage-bulk-upload-new', $uploaded_count);
				update_option('arvan-cloud-storage-bulk-upload-error', $error_count);
			}

			$percentage = $one_percent / count($object_ids) * 100;
			update_option('arvan-cloud-storage-bulk-upload-percent', ceil($percentage));
		}

		wp_send_json_success( $uploaded_count, 200 );
		wp_die();

	}


	public function ajax_bulk_upload_res() {
		// checking nonce
		// if ( ! check_ajax_referer( 'ar-cdn-options-nonce', 'security', false ) ) {

		// 	wp_send_json_error( __('Invalid security token sent.', 'wp-arvancloud-cdn' ), 403 );
		// 	wp_die();

		// }
		// update_option('arvan-cloud-storage-bulk-upload-percent', 0);

		$percentage_option = get_option('arvan-cloud-storage-bulk-upload-percent', false);
		$new = get_option('arvan-cloud-storage-bulk-upload-new', false);
		$error = get_option('arvan-cloud-storage-bulk-upload-error', false);


		$data = [
			'percentage_option' => $percentage_option,
			'new' => $new,
			'error' => $error
		];


		wp_send_json_success( $data, 200 );
		wp_die();
	}

	public function maybe_change_acl() {
		global $post;
		$post_id = $post->ID;

		if ( isset ($_GET['change_acl']) && $_GET['change_acl'] == 'true' ) {
			// wp verify nonce
			if ( ! wp_verify_nonce( $_GET['nonce'], 'object-storage-nonce-acl' ) ) {
				return;
			}

			$acl = sanitize_text_field( $_GET['acl'] );
			if ( !in_array( $acl, array( 'private', 'public-read' ) ) ) {
				return;
			}

			$file = wp_get_attachment_metadata($post_id);
			$file_key = basename($post->guid);

			// Check if image has extra sizes
			if( wp_attachment_is_image( $post_id ) && array_key_exists( "sizes", $file ) ) {
				foreach ( $file['sizes'] as $sub_size ) {
					if ( $sub_size['file'] != "" ) {
						$this->change_object_acl( $sub_size['file'], $acl );
					}
				}
			}
			$this->change_object_acl( $file_key, $acl );

			// Update post meta
			update_post_meta( $post_id, 'acs_acl', $acl );


			add_action( 'admin_notices', function () {
				echo '<div class="notice notice-success is-dismissible">
						<p>'. esc_html__( "Object's acl has been successfully changed.", 'arvancloud-object-storage' ) .'</p>
					</div>';
			} );
		}

		return;

	}

	private function change_object_acl( $file_key, $acl ) {

		$client = $this->s3_client_creator();

		$bucket_selected = $this->bucket_name;

		
		try {
			$result = $client->putObjectAcl([
				'ACL' => $acl, // or private
				'Bucket' => $bucket_selected,
				'Key' => $file_key,
			]);
		} catch (AwsException $e) {
			add_action( 'admin_notices', function () {
				echo '<div class="notice notice-error is-dismissible">
						<p>'. esc_html__( "There is a problem.", 'arvancloud-object-storage' ) .'</p>
					</div>';
			} );
			return false;
		}

		return $result;
	}

	private function s3_client_creator() {



		$client = S3Singletone::get_instance()->get_s3client();

		return $client;
	}

	public function get_site_icon_url( $url, $size, $blog_id ) {
		$site_icon_id = get_option( 'site_icon' );
		
		if ( $site_icon_id ) {
			// Maybe file uploaded to S3 and should be rewrite
			$storage_file_url = get_post_meta( $site_icon_id, 'acs_storage_file_url', true );

			if( !empty( $storage_file_url ) ) {
				$storage_file_url .= get_post_meta( $site_icon_id, 'acs_storage_file_dir', true );
				$file_name = basename( $url );
				$url 	   = esc_url( $storage_file_url.$file_name );
			}
		}

		return $url;

	}

	public static function formatBytes($bytes, $precision = 2) { 
		$units = array(
			__( 'B', 'arvancloud-object-storage' ),
			__( 'KB', 'arvancloud-object-storage'),
			__( 'MB', 'arvancloud-object-storage'),
			__( 'GB', 'arvancloud-object-storage'),
			__( 'TB', 'arvancloud-object-storage')
		); 
	
		$bytes = max($bytes, 0); 
		$pow = floor(($bytes ? log($bytes) : 0) / log(1024)); 
		$pow = min($pow, count($units) - 1); 
	
		$bytes /= pow(1024, $pow);
	
		return round($bytes, $precision) . ' ' . $units[$pow]; 
	}
	
	public function handle_create_bucket() {

		check_ajax_referer( 'create-bucket', '_nonce' );

		if( !isset($_POST['bucket_name']) ) {
			wp_send_json_error( array(
				'message' => __( 'Something went wrong. Please try again.', 'arvancloud-object-storage' ),
			) );
			wp_die();
		}

		$bucket_name = strtolower(sanitize_text_field( $_POST['bucket_name'] ));
		$bucket_acl  = (isset($_POST['bucket_acl']) && $_POST['bucket_acl'] !== 'false') ? 'public-read' : 'private';

		if (strlen($bucket_name) < 3 || empty($bucket_name)) {
			wp_send_json_error( array(
				'message' => __( 'The bucket name should not be less than 3', 'arvancloud-object-storage' ),
			) );
			wp_die();
		}

		$client = $this->s3_client_creator();

		try {
			$result = $client->createBucket([
				'ACL' => $bucket_acl,
				'Bucket' => $bucket_name,
			]);
			
			wp_send_json_success( array(
				'bucket_name' => $bucket_name,
				'bucket_acl' => $bucket_acl,
				'message' => __( 'Bucket has been successfully created.', 'arvancloud-object-storage' ),
			), 200 );
			wp_die();

		} catch (AwsException $e) {
			$message = $e->getStatusCode() == 409 ? __('Bucket with provided information already exists.', 'arvancloud-object-storage') : __('Something wrong. Try again.', 'arvancloud-object-storage');

			wp_send_json_error( array(
				'message' => $message,
			) );
			wp_die();
		}
	}
    
    public function check_valid_attachment($attach_id){

        $file = is_numeric($attach_id)?get_attached_file($attach_id):$attach_id;
        if(empty($file)) return false;

        $opt = get_option( 'acs_settings' );
        if(!empty($opt['file_ext'])){
            $ext_arr = array_column(json_decode($opt['file_ext'],1),'value');
            $ext_arr = array_map('strtolower',$ext_arr);
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));

            if(!in_array($ext,$ext_arr))
            return false;
        }
        $size = @round(filesize($file)/1048576,2);
        if(!empty($opt['file_f_size']) and $opt['file_f_size']>$size)
        return false;
	
        if(!empty($opt['file_t_size']) and $opt['file_t_size']<$size)
        return false;

		if(isset($_GET['page']) or $_GET['page']=='wp-arvancloud-storage-download')
		return false;

		if($_POST['type'] == 'downloadable_product' and (empty($opt) or !empty($opt['wooc_prev_upload_product'])) )
		return false;

        return true;
    }

	
	public function restrict_manage_posts(){
		global $pagenow;
		if($pagenow != 'upload.php')
		return;
		$settings = Helper::get_storage_settings();

		$s3client = S3Singletone::get_instance();
		$out = "
		<select name='bucket' class='form-control'>
			<option value=''>".__('Select Bucket','arvancloud-object-storage')."</option>
			<option value='all'>".__('All Buckets','arvancloud-object-storage')."</option>";
		if(empty($settings['bucket_name']))
            $buckets = $s3client->get_s3client()->listBuckets()['Buckets'];
		else{
			$buckets = [['Name' => $settings['bucket_name']]];
		}
		if(empty($buckets))
			return;
		$select = empty($_GET['bucket'])?'':esc_sql($_GET['bucket']);
		foreach($buckets as $bucket){
			$out .= "<option value='{$bucket['Name']}' ".($select==$bucket['Name']?'selected="selected"':'').">{$bucket['Name']}</option>";
		}

		echo "$out\n</select>";
	}

	function pre_get_media_attachment($query){
		if(empty($_GET['bucket']))
		return;

		if (is_admin() && $query->get( 'post_type' ) == 'attachment' ){
			
			if($_GET['bucket']=='all'){
				$postmeta_query = array(array(
					'key' => 'acs_storage_file_url',
					'compare' => '=',
				),);
			}else{
				$postmeta_query = array(array(
					'key'     => 'acs_storage_file_url',
					'value'   => 'https://'.esc_sql($_GET['bucket']).'.',
					'compare' => 'LIKE',
				),);				
			}

			$query->set( 'meta_query', $postmeta_query );
		}
	}

	function manage_upload_columns($columns){
		$columns['bucket_name']  = __( 'Bucket Name', 'arvancloud-object-storage' );
		$columns['bucket_adder'] = __( 'Bucket Address', 'arvancloud-object-storage' );
		return $columns;
	}

	function manage_media_custom_column($column_name, $attachment_id){
		if('bucket_name' == $column_name){
			if($url = get_post_meta($attachment_id,'acs_storage_file_url',true)){
				preg_match('#https://([^\.]+)#',$url,$match);
				echo $match[1];
			}
		}
		if ( 'bucket_adder' == $column_name ) {
			if($url = get_post_meta($attachment_id,'acs_storage_file_url',true))
			echo '<a style="cursor:pointer;" title="Click to copy" onclick="copy_to_clipboard(\''.$url.'\');">'.__('Copy address', 'arvancloud-object-storage').'</a>';
		}
	}

	function admin_footer(){
		global $pagenow;
		if($pagenow != 'upload.php')
		return;
		?>
		<script>
			function copy_to_clipboard(str){
				var aux = document.createElement("input");
				aux.setAttribute("value", str);
				document.body.appendChild(aux);
				aux.select();
				document.execCommand("copy");
				document.body.removeChild(aux);
			}
		</script>
		<?php
	}

	function download_arvan_to_attachment(){
		if(!isset($_GET['page']) or $_GET['page']!='wp-arvancloud-storage-download')
		return;

		if(isset($_POST['save'])){
			$headers = get_headers($_POST['file_url']);
   			if(stripos($headers[0],"200 OK")){
				$fname  = strtok($_POST['file_url'], '?');
				$parts = parse_url(dirname($fname));
				$fname  = basename($fname);
				$tmp    = download_url($_POST['file_url']);
				if( is_wp_error( $tmp ) )
					return Helper::show_admin_notice(__('Problem download file','arvancloud-object-storage'));
				$file_array = array(
					'name' => basename( $_POST['file_url'] ),
					'tmp_name' => $tmp
				);
				
				$attach_id = media_handle_sideload( $file_array, 0 );
				if ( is_wp_error( $attach_id ) ) {
					@unlink( $file_array['tmp_name'] );
					return Helper::show_admin_notice(__('Problem create Attachment','arvancloud-object-storage'));
				}

				$url = admin_url("post.php?post=$attach_id&action=edit");
				return Helper::show_admin_notice(__('Your file has been successfully fetched.','arvancloud-object-storage')." <a href='$url'>".__('Edit','arvancloud-object-storage')."</a>",'notice-success');
			}else
			return Helper::show_admin_notice(__('File not found','arvancloud-object-storage'));
		}
	}
}
