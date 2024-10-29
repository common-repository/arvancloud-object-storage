<?php
namespace WP_Arvan\OBS;

use WP_Arvan\OBS\Admin\Controllers\BulkDownloadController;
use WP_Arvan\OBS\Admin\Controllers\BulkUploaderController;
use WP_Arvan\OBS\Admin\Controllers\EmptyCurrentBucketController;
use WP_Arvan\OBS\Admin\Controllers\RemoveLocalFilesController;
use WP_Arvan\OBS\Admin\Admin;
use WP_Arvan\OBS\Admin\Controllers\BucketTransferController;

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Wp_Arvancloud_Storage
 * @subpackage Wp_Arvancloud_Storage/includes
 * @author     Khorshid <info@khorshidlab.com>
 */
class Storage {

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      Wp_Arvancloud_Storage_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		if ( defined( 'ACS_VERSION' ) ) {
			$this->version = ACS_VERSION;
		} else {
			$this->version = '0.9.15';
		}
		
		$this->plugin_name = ACS_NAME;

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();

	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - Wp_Arvancloud_Storage_Loader. Orchestrates the hooks of the plugin.
	 * - Wp_Arvancloud_Storage_i18n. Defines internationalization functionality.
	 * - Wp_Arvancloud_Storage_Admin. Defines all hooks for the admin area.
	 * - Wp_Arvancloud_Storage_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies() {

		$this->loader = new Loader();

	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the Wp_Arvancloud_Storage_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function set_locale() {

		$plugin_i18n = new i18n();

		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );

	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks() {

		$plugin_admin = new Admin( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' ,9999);
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );
		$this->loader->add_action( 'admin_menu', $plugin_admin, 'setup_admin_menu' );
		$this->loader->add_action( 'init', $plugin_admin, 'config_access_keys' );
		$this->loader->add_action( 'init', $plugin_admin, 'store_selected_bucket_in_db' );
		$this->loader->add_action( 'init', $plugin_admin, 'save_plugin_settings' );
		$this->loader->add_action( 'admin_init', $plugin_admin, 'download_arvan_to_attachment' );
		$this->loader->add_action( 'delete_attachment', $plugin_admin, 'delete_media_from_storage', 10, 1 );
		$this->loader->add_action( 'wp_ajax_acs_get_attachment_provider_details', $plugin_admin, 'ajax_get_attachment_provider_details' );
		$this->loader->add_action( 'admin_init', $plugin_admin, 'process_media_actions' );
		$this->loader->add_action( 'admin_notices', $plugin_admin, 'maybe_display_media_action_message' );
		$this->loader->add_action( 'add_meta_boxes', $plugin_admin, 'add_edit_attachment_metabox' );
		$this->loader->add_action( 'admin_head-post.php', $plugin_admin, 'maybe_change_acl' );

		$this->loader->add_filter( 'add_attachment', $plugin_admin, 'upload_media_to_storage', 10, 1 );
		$this->loader->add_filter( 'wp_generate_attachment_metadata', $plugin_admin, 'upload_image_to_storage', 10, 1 );
		$this->loader->add_filter( 'wp_get_attachment_url', $plugin_admin, 'media_library_url_rewrite', 10, 2 );
		$this->loader->add_filter( 'wp_get_attachment_image_src', $plugin_admin, 'attachment_image_src_filter', 10, 3 );
		$this->loader->add_filter( 'bulk_actions-upload', $plugin_admin, 'bulk_actions_upload' );
		$this->loader->add_filter( 'handle_bulk_actions-upload', $plugin_admin, 'handle_bulk_actions_upload', 10, 3 );
		$this->loader->add_filter( 'media_row_actions', $plugin_admin, 'add_media_row_actions', 10, 3 );
		$this->loader->add_filter( 'wp_calculate_image_srcset', $plugin_admin, 'calculate_image_srcset', 10, 5 );
		$this->loader->add_filter( 'wp_update_attachment_metadata', $plugin_admin, 'wp_update_attachment_metadata', 110, 2 );
		$this->loader->add_action( 'wp_ajax_ar_bulk_upload_res', $plugin_admin, 'ajax_bulk_upload_res' );
		$this->loader->add_action( 'wp_ajax_ar_handle_bulk_upload', $plugin_admin, 'handle_bulk_upload' );
		$this->loader->add_action( 'wp_ajax_ar_generate_acl_url', $plugin_admin, 'handle_generate_acl_url' );
		$this->loader->add_action( 'wp_ajax_ar_create_bucket', $plugin_admin, 'handle_create_bucket' );
		$this->loader->add_filter( 'get_site_icon_url', $plugin_admin, 'get_site_icon_url' , 99, 3 );
		$this->loader->add_action( 'restrict_manage_posts', $plugin_admin, 'restrict_manage_posts' );
		$this->loader->add_action( 'pre_get_posts', $plugin_admin, 'pre_get_media_attachment', 10, 1 );
		$this->loader->add_filter( 'manage_upload_columns', $plugin_admin, 'manage_upload_columns' , 10, 1);
		$this->loader->add_filter( 'manage_media_custom_column', $plugin_admin, 'manage_media_custom_column' , 10, 2);
		$this->loader->add_action( 'admin_footer', $plugin_admin, 'admin_footer');


        $bucket_transfer_controller = new BucketTransferController();

        $this->loader->add_action( 'wp_ajax_migrate_to_new_bucket_modal', $bucket_transfer_controller, 'render_view' );
        $this->loader->add_action( 'wp_ajax_get_migrate_to_new_bucket_task_status', $bucket_transfer_controller, 'get_migrate_to_new_bucket_task_status' );
        $this->loader->add_action( 'wp_ajax_do_migrate_to_new_bucket', $bucket_transfer_controller, 'control' );
        $this->loader->add_action( 'wp_ajax_do_reschedule_migration', $bucket_transfer_controller, 'wp_ajax_do_reschedule_migration' );
        $this->loader->add_action( 'wp_ajax_stop_migrate_to_new_bucket_task', $bucket_transfer_controller, 'stop_migrate_to_new_bucket_task' );





        $remove_local_files = RemoveLocalFilesController::get_instance();
        $this->loader->add_action( 'wp_ajax_bulk_remove_modal', $remove_local_files, 'render_view' );
        $this->loader->add_action( 'wp_ajax_do_bulk_remove', $remove_local_files, 'control' );
        $this->loader->add_action( 'wp_ajax_get_bulk_remove_task_status', $remove_local_files, 'get_bulk_remove_task_status' );
        $this->loader->add_action( 'wp_ajax_stop_current_bulk_remove_task', $remove_local_files, 'stop_current_bulk_remove_task' );


        $empty_current_bucket = EmptyCurrentBucketController::get_instance();

        $this->loader->add_action( 'wp_ajax_empty_bucket_modal', $empty_current_bucket, 'render_view' );
        $this->loader->add_action( 'wp_ajax_do_empty_bucket', $empty_current_bucket, 'control' );
        $this->loader->add_action( 'wp_ajax_get_empty_current_bucket_task_status', $empty_current_bucket, 'get_task_status' );
        $this->loader->add_action( 'wp_ajax_stop_current_bucket_emptying_task', $empty_current_bucket, 'stop_current_bucket_emptying_task' );


        $bulk_uploader = BulkUploaderController::get_instance();
        $this->loader->add_action( 'wp_ajax_bulk_upload_modal', $bulk_uploader, 'render_view' );
        $this->loader->add_action( 'wp_ajax_get_bulk_upload_task_status', $bulk_uploader, 'get_bulk_upload_task_status' );
        $this->loader->add_action( 'wp_ajax_do_bulk_upload', $bulk_uploader, 'control' );
        $this->loader->add_action( 'wp_ajax_stop_current_bulk_upload_task', $bulk_uploader, 'stop_current_bulk_upload_task' );

        $bulk_downloader = BulkDownloadController::get_instance();
        $this->loader->add_action( 'wp_ajax_bulk_download_modal', $bulk_downloader, 'render_view' );
        $this->loader->add_action( 'wp_ajax_get_bulk_download_task_status', $bulk_downloader, 'get_bulk_download_task_status' );
        $this->loader->add_action( 'wp_ajax_do_bulk_download', $bulk_downloader, 'control' );
        $this->loader->add_action( 'wp_ajax_stop_current_bulk_download_task', $bulk_downloader, 'stop_current_bulk_download_task' );


        add_action('init', function(){
            $api_validator = new ApiValidator();
            $api_validator->setup();
        });



	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}

}
