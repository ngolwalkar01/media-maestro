<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://example.com
 * @since      1.0.0
 *
 * @package    Media_Maestro
 * @subpackage Media_Maestro/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Media_Maestro
 * @subpackage Media_Maestro/admin
 * @author     Your Name <email@example.com>
 */
class Media_Maestro_Admin {

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

		$this->plugin_name = $plugin_name;
		$this->version = $version;

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
		 * defined in Media_Maestro_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Media_Maestro_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/media-maestro-admin.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Media_Maestro_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Media_Maestro_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/media-maestro-admin.js', array( 'jquery' ), $this->version, true );
		
	}

    /**
     * Enqueue Media View assets.
     */
    public function enqueue_media_assets() {
        wp_enqueue_script( $this->plugin_name . '-media-view', plugin_dir_url( __FILE__ ) . 'js/media-maestro-media-view.js', array( 'media-views' ), time() + 1, true );
        wp_localize_script( $this->plugin_name . '-media-view', 'mm_data', array(
            'nonce'   => wp_create_nonce( 'wp_rest' ),
            'api_url' => get_rest_url( null, 'mm/v1/jobs' ),
        ) );
    }

    /**
     * Print JS Templates.
     */
    public function print_media_templates() {
        ?>
        <script type="text/html" id="tmpl-mm-sidebar-template">
            <div class="mm-media-sidebar">
                <h3>AI Media Studio</h3>
                <div class="mm-actions">
                    <button type="button" class="button mm-btn-remove-bg">Remove Background</button>
                    <button type="button" class="button mm-btn-style">Style Transfer (Dev)</button>
                </div>
                <div class="mm-status" style="margin-top: 10px; color: #666;"></div>
                <hr>
            </div>
        </script>
        <?php
    }

    /**
     * Register the administration menu for this plugin into the WordPress Dashboard menu.
     *
     * @since    1.0.0
     */
    public function add_plugin_admin_menu() {

        add_options_page(
            'Media Maestro Settings', 
            'Media Maestro', 
            'manage_options', 
            $this->plugin_name, 
            array( $this, 'display_plugin_setup_page' )
        );
    }

    /**
     * Render the settings page for this plugin.
     *
     * @since    1.0.0
     */
    public function display_plugin_setup_page() {
        include_once plugin_dir_path( __FILE__ ) . 'partials/mm-admin-display.php';
    }

    /**
     * Register settings.
     */
    public function register_settings() {
        register_setting( $this->plugin_name, 'mm_api_key' );
        register_setting( $this->plugin_name, 'mm_provider', array( 'default' => 'openai' ) );
        
        add_settings_section(
            'mm_general_section',
            'General Settings',
            null,
            $this->plugin_name
        );

        add_settings_field(
            'mm_api_key',
            'API Key',
            array( $this, 'render_api_key_field' ),
            $this->plugin_name,
            'mm_general_section'
        );

        add_settings_field(
            'mm_provider',
            'AI Provider',
            array( $this, 'render_provider_field' ),
            $this->plugin_name,
            'mm_general_section'
        );
    }

    public function render_api_key_field() {
        $api_key = get_option( 'mm_api_key' );
        echo '<input type="password" name="mm_api_key" value="' . esc_attr( $api_key ) . '" class="regular-text" />';
    }

    public function render_provider_field() {
        $provider = get_option( 'mm_provider', 'openai' );
        ?>
        <select name="mm_provider">
            <option value="openai" <?php selected( $provider, 'openai' ); ?>>OpenAI</option>
            <option value="mock" <?php selected( $provider, 'mock' ); ?>>Mock (Dev)</option>
        </select>
        <?php
    }

    /**
     * Add Meta Box to Attachment Edit Screen.
     */
    public function add_ai_metabox() {
        add_meta_box(
            'mm_ai_tools',
            'AI Media Studio',
            array( $this, 'render_ai_metabox' ),
            'attachment',
            'side',
            'high'
        );
    }

    /**
     * Render Meta Box.
     */
    public function render_ai_metabox( $post ) {
        // Only show for images
        if ( ! wp_attachment_is_image( $post->ID ) ) {
            echo '<p>AI tools available for images only.</p>';
            return;
        }

        // Pass data to JS
        wp_localize_script( $this->plugin_name, 'mm_data', array(
            'attachment_id' => $post->ID,
            'nonce'         => wp_create_nonce( 'wp_rest' ),
            'api_url'       => get_rest_url( null, 'mm/v1/jobs' ),
        ) );

        echo '<div id="mm-ai-tools-container" class="mm-ai-tools-container">';
        echo '<p><strong>Actions:</strong></p>';
        echo '<button type="button" class="button button-secondary" id="mm-btn-remove-bg">Remove Background</button>';
        echo '<button type="button" class="button button-secondary" id="mm-btn-style-transfer">Style Transfer</button>';
        echo '<hr>';
        echo '<div id="mm-job-status"></div>';
        echo '</div>';
    }

}
