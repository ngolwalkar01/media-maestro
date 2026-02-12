<?php
/**
 * Fired during plugin activation
 *
 * @link       https://example.com
 * @since      1.0.0
 *
 * @package    Media_Maestro
 * @subpackage Media_Maestro/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    Media_Maestro
 * @subpackage Media_Maestro/includes
 * @author     Your Name <email@example.com>
 */
class Media_Maestro_Activator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function activate() {
        // We will add CPT registration here to ensure flush_rewrite_rules is called
        // But for now, we'll keep it simple as the CPT will be registered in the Core/Job Manager
        // It is often better to trigger a flush rewrite rules option here
        update_option( 'media_maestro_flush_rewrite_rules', true );
	}

}
