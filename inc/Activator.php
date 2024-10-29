<?php
namespace WP_Arvan\OBS;
/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    Wp_Arvancloud_Storage
 * @subpackage Wp_Arvancloud_Storage/includes
 * @author     Khorshid <info@khorshidlab.com>
 */
class Activator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function activate() {

		// Set keep local files to true by default
        if($opt = get_option( 'acs_settings',[]))
            return;
		update_option( 'acs_settings', serialize( [ 'keep-local-files' => 1 ] ) );

	}

}
