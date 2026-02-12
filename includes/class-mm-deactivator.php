<?php
/**
 * Fired during plugin deactivation
 *
 * @link       https://example.com
 * @since      1.0.0
 *
 * @package    Media_Maestro
 * @subpackage Media_Maestro/includes
 */

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @since      1.0.0
 * @package    Media_Maestro
 * @subpackage Media_Maestro/includes
 * @author     Your Name <email@example.com>
 */
class Media_Maestro_Deactivator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function deactivate() {
        // Flush rewrite rules on deactivation to clean up
        flush_rewrite_rules();
	}

}
