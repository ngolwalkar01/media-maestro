<?php
/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://example.com
 * @since      1.0.0
 *
 * @package    Media_Maestro
 * @subpackage Media_Maestro/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * @since      1.0.0
 * @package    Media_Maestro
 * @subpackage Media_Maestro/includes
 * @author     Your Name <email@example.com>
 */
class Media_Maestro_Core {

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
        if ( defined( 'MEDIA_MAESTRO_VERSION' ) ) {
            $this->version = MEDIA_MAESTRO_VERSION;
        } else {
            $this->version = '1.0.0';
        }
        $this->plugin_name = 'media-maestro';

        $this->load_dependencies();
        $this->set_locale();
        $this->define_admin_hooks();
        $this->define_public_hooks();
        $this->define_job_hooks();
        $this->define_rest_hooks();
    }

    /**
     * Load the required dependencies for this plugin.
     *
     * Include the following files that make up the plugin:
     *
     * - Media_Maestro_i18n. Defines internationalization functionality.
     * - Media_Maestro_Admin. Defines all hooks for the admin area.
     * - Media_Maestro_Public. Defines all hooks for the public side of the site.
     *
     * @since    1.0.0
     * @access   private
     */
    private function load_dependencies() {

        /**
         * The class responsible for defining internationalization functionality
         * of the plugin.
         */
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-mm-i18n.php';

        /**
         * The class responsible for defining all actions that occur in the admin area.
         */
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-mm-admin.php';

        /**
         * The class responsible for defining all actions that occur in the public-facing
         * side of the site.
         */
        // require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-mm-public.php'; // Not needed yet

        // Load Job Manager
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/jobs/class-mm-job-manager.php';
        
        // Load Providers
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/providers/interface-mm-provider.php';
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/providers/class-mm-provider-mock.php';
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/providers/class-mm-provider-manager.php';

        // Load Worker
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/jobs/class-mm-worker.php';
    }

    /**
     * Define the locale for this plugin for internationalization.
     *
     * Uses the Media_Maestro_i18n class in order to set the domain and to register the hook
     * with WordPress.
     *
     * @since    1.0.0
     * @access   private
     */
    private function set_locale() {

        $plugin_i18n = new Media_Maestro_i18n();

        add_action( 'plugins_loaded', array( $plugin_i18n, 'load_plugin_textdomain' ) );

    }

    /**
     * Register all of the hooks related to the admin area functionality
     * of the plugin.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_admin_hooks() {

        $plugin_admin = new Media_Maestro_Admin( $this->get_plugin_name(), $this->get_version() );

        add_action( 'admin_enqueue_scripts', array( $plugin_admin, 'enqueue_styles' ) );
        add_action( 'admin_enqueue_scripts', array( $plugin_admin, 'enqueue_scripts' ) );

        add_action( 'admin_menu', array( $plugin_admin, 'add_plugin_admin_menu' ) );
        add_action( 'admin_init', array( $plugin_admin, 'register_settings' ) );
        add_action( 'add_meta_boxes', array( $plugin_admin, 'add_ai_metabox' ) );
    }

    /**
     * Register all of the hooks related to the public-facing functionality
     * of the plugin.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_public_hooks() {
        // Minimal public hooks for now
    }

    /**
     * Register job execution and CPT hooks.
     * 
     * @since 1.0.0
     * @access private
     */
    private function define_job_hooks() {
        $job_manager = new Media_Maestro_Job_Manager();
        add_action( 'init', array( $job_manager, 'register_cpt' ) );

        $worker = new Media_Maestro_Worker();
        $worker->init();
    }

    /**
     * Register REST API hooks.
     *
     * @since 1.0.0
     * @access private
     */
    private function define_rest_hooks() {
        add_action( 'rest_api_init', function() {
            require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/api/class-mm-rest-controller.php';
            $controller = new Media_Maestro_REST_Controller();
            $controller->register_routes();
        } );
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
     * @return    Media_Maestro_Loader    Orchestrates the hooks of the plugin.
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
