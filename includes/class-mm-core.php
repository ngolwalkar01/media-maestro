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
        $this->define_tagging_hooks();
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
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/providers/class-mm-provider-openai.php';
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/providers/class-mm-provider-gemini.php';
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/providers/class-mm-provider-stability.php';
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
        add_action( 'wp_enqueue_media', array( $plugin_admin, 'enqueue_media_assets' ) );
        add_action( 'print_media_templates', array( $plugin_admin, 'print_media_templates' ) );
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
     * Register tagging hooks for auto-tagging and smart search.
     *
     * @since 1.0.0
     * @access private
     */
    private function define_tagging_hooks() {
        // Trigger auto-tagging when an attachment is added
        add_action( 'add_attachment', array( $this, 'trigger_auto_tagging' ) );

        // Modify search queries in the admin to look at our custom AI tags meta
        if ( is_admin() ) {
            add_filter( 'posts_search', array( $this, 'smart_media_search' ), 10, 2 );
        }
    }

    /**
     * Trigger auto-tagging job for newly uploaded images if enabled.
     *
     * @param int $attachment_id ID of the newly uploaded attachment.
     */
    public function trigger_auto_tagging( $attachment_id ) {
        if ( ! wp_attachment_is_image( $attachment_id ) ) {
            return;
        }

        $enabled = get_option( 'mm_enable_auto_tagging', '0' );
        if ( ! $enabled ) {
            return;
        }

        // Create a background job to handle the tagging
        $job_manager = new Media_Maestro_Job_Manager();
        $job_id      = $job_manager->create_job( $attachment_id, 'auto_tag_image', array() );
        
        if ( ! is_wp_error( $job_id ) ) {
            error_log( "MM_CORE: Scheduled auto-tagging job $job_id for attachment $attachment_id" );
        }
    }

    /**
     * Modify the search query to include our AI tags from post meta.
     *
     * @param string   $search The original search SQL string.
     * @param WP_Query $wp_query The WP_Query instance.
     * @return string Modified search SQL string.
     */
    public function smart_media_search( $search, $wp_query ) {
        global $wpdb;

        // Only modify if it's a search query 
        if ( empty( $search ) || ! $wp_query->is_search() ) {
            return $search;
        }

        // We only care if we are querying attachments (Media Library)
        if ( $wp_query->get( 'post_type' ) !== 'attachment' && $wp_query->get( 'post_type' ) !== 'any' ) {
            return $search;
        }

        $search_term = $wp_query->query_vars['s'];
        
        if ( empty( $search_term ) ) {
            return $search;
        }

        // Add a LIKE clause for our custom meta key _mm_ai_tags
        // The original $search usually looks like: AND (((wp_posts.post_title LIKE '%...%') OR (wp_posts.post_content LIKE '%...%')))
        
        $search_term_like = '%' . $wpdb->esc_like( $search_term ) . '%';
        
        $meta_search = $wpdb->prepare(
            " OR {$wpdb->posts}.ID IN ( SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_mm_ai_tags' AND meta_value LIKE %s ) ",
            $search_term_like
        );

        // Inject our OR clause right before the closing parenthesis of the main search block
        $search = preg_replace( '/\)\s*\)\s*$/', $meta_search . '))', $search );

        return $search;
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
     * Run the loader to execute all of the hooks with WordPress.
     *
     * @since    1.0.0
     */
    public function run() {
        // Hooks are registered in the constructor.
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
