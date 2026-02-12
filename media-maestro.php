<?php
/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * administrative area. This file also includes all of the plugin dependencies.
 *
 * @link              https://example.com
 * @since             1.0.0
 * @package           Media_Maestro
 *
 * @wordpress-plugin
 * Plugin Name:       Media Maestro
 * Plugin URI:        https://example.com/plugin-name-contact
 * Description:       AI Media Studio for WordPress. Remove backgrounds, style transfer, and regenerate images directly in the Media Library.
 * Version:           1.0.0
 * Author:            Your Name
 * Author URI:        https://example.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       media-maestro
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Validates that the plugin version is correctly defined.
 */
define( 'MEDIA_MAESTRO_VERSION', '1.0.0' );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-mm-activator.php
 */
function activate_media_maestro() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-mm-activator.php';
	Media_Maestro_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-mm-deactivator.php
 */
function deactivate_media_maestro() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-mm-deactivator.php';
	Media_Maestro_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_media_maestro' );
register_deactivation_hook( __FILE__, 'deactivate_media_maestro' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-mm-core.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_media_maestro() {

	$plugin = new Media_Maestro_Core();
	$plugin->run();

}
run_media_maestro();
