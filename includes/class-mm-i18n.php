<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Define the internationalization functionality
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @package    Media_Maestro
 * @subpackage Media_Maestro/includes
 */

/**
 * Define the internationalization functionality.
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @since      1.0.0
 * @package    Media_Maestro
 * @subpackage Media_Maestro/includes
 */
class Media_Maestro_i18n {

	/**
	 * Load the plugin text domain for translation.
	 *
	 * @since    1.0.0
	 */
	public function load_plugin_textdomain() {
		// Translations are automatically loaded by WordPress for repo plugins since version 4.6.
		// Intentionally left empty to satisfy the Plugin Check requirements.
	}

}
