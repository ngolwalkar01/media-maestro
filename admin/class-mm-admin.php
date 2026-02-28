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
        wp_enqueue_script( $this->plugin_name . '-media-view', plugin_dir_url( __FILE__ ) . 'js/media-maestro-media-view.js', array( 'media-views' ), time() + 9, true );
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
                <div class="mm-config">
                    <label>Operation:
                        <select class="mm-operation-select" style="width:100%; margin-bottom:10px;">
                            <optgroup label="Generation">
                                <option value="generate_ultra">Stable Image Ultra</option>
                                <option value="generate_core">Stable Image Core</option>
                                <option value="generate_sd3">Stable Diffusion 3.5</option>
                            </optgroup>
                            <optgroup label="Edit / Transform">
                                <option value="product_placement">Product Placement (OpenAI Ref)</option>
                                <option value="style_transfer">Style Transfer (Control)</option>
                                <option value="sketch">Sketch to Image</option>
                                <option value="structure">Structure (Depth)</option>
                                <option value="inpaint">Inpaint (Masked)</option>
                                <option value="outpaint">Outpaint</option>
                                <option value="erase">Erase Object</option>
                                <option value="search_replace">Search & Replace</option>
                                <option value="remove_bg">Remove Background</option>
                                <option value="replace_bg">Replace Background</option>
                            </optgroup>
                            <optgroup label="Upscale">
                                <option value="upscale_conservative">Conservative Upscale</option>
                                <option value="upscale_creative">Creative Upscale</option>
                                <option value="upscale_fast">Fast Upscale (x4)</option>
                            </optgroup>
                        </select>
                    </label>

                    <label class="mm-prompt-label">Prompt:
                        <textarea class="mm-prompt-input" rows="3" placeholder="Describe the desired result..." style="width:100%; margin-bottom:10px;"></textarea>
                    </label>

                    <label class="mm-strength-label" style="display:none;">Strength (0.0 - 1.0):
                        <input type="range" class="mm-strength-input" min="0" max="1" step="0.1" value="0.7" style="width:100%;">
                    </label>

                    <label class="mm-direction-label" style="display:none;">Expand Direction:
                        <select class="mm-direction-select" style="width:100%; margin-bottom:10px;">
                            <option value="down">Down (512px)</option>
                            <option value="up">Up (512px)</option>
                            <option value="left">Left (512px)</option>
                            <option value="right">Right (512px)</option>
                            <option value="all">All Sides (256px)</option>
                        </select>
                    </label>
                </div>

                <div class="mm-actions">
                    <button type="button" class="button button-primary mm-btn-run">Run AI Job</button>
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
        register_setting( $this->plugin_name, 'mm_provider' );
        register_setting( $this->plugin_name, 'mm_api_key' );
        register_setting( $this->plugin_name, 'mm_gemini_api_key' );
        register_setting( $this->plugin_name, 'mm_stability_api_key' );
        register_setting( $this->plugin_name, 'mm_enable_auto_tagging' );
        register_setting( $this->plugin_name, 'mm_enable_auto_seo' );
        
        add_settings_section(
            'mm_general_section',
            'General Settings',
            null,
            $this->plugin_name
        );

        add_settings_field(
            'mm_enable_auto_tagging',
            'Enable AI Auto-Tagging',
            array( $this, 'auto_tagging_callback' ),
            $this->plugin_name,
            'mm_general_section'
        );

        add_settings_field(
            'mm_enable_auto_seo',
            'Enable AI Auto Image SEO',
            array( $this, 'auto_seo_callback' ),
            $this->plugin_name,
            'mm_general_section'
        );

        add_settings_field(
            'mm_provider',
            'Select AI Provider',
            array( $this, 'provider_callback' ),
            $this->plugin_name,
            'mm_general_section'
        );

        add_settings_field(
            'mm_api_key',
            'OpenAI API Key',
            array( $this, 'api_key_callback' ),
            $this->plugin_name,
            'mm_general_section'
        );

        add_settings_field(
            'mm_gemini_api_key',
            'Google Gemini API Key',
            array( $this, 'gemini_api_key_callback' ),
            $this->plugin_name,
            'mm_general_section'
        );
        add_settings_field(
            'mm_stability_api_key',
            'Stability AI API Key',
            array( $this, 'stability_api_key_callback' ),
            $this->plugin_name,
            'mm_general_section'
        );
    }

    public function auto_tagging_callback() {
        $enabled = get_option( 'mm_enable_auto_tagging', '0' );
        echo '<label><input type="checkbox" name="mm_enable_auto_tagging" value="1" ' . checked( 1, $enabled, false ) . '> Automatically tag images on upload (requires OpenAI API key)</label>';
        echo '<p class="description">When enabled, new image uploads will be analyzed by AI to detect objects, emotions, and categories, making them searchable in the Media Library.</p>';
    }

    public function auto_seo_callback() {
        $enabled = get_option( 'mm_enable_auto_seo', '0' );
        echo '<label><input type="checkbox" name="mm_enable_auto_seo" value="1" ' . checked( 1, $enabled, false ) . '> Automatically generate SEO metadata on upload (requires OpenAI API key)</label>';
        echo '<p class="description">When enabled, AI will generate a highly optimized Alt text, Title, Caption, and Description based on the image content.</p>';
    }

    public function api_key_callback() {
        $api_key = get_option( 'mm_api_key' );
        echo '<input type="password" name="mm_api_key" value="' . esc_attr( $api_key ) . '" class="regular-text">';
        echo '<p class="description">Enter your OpenAI API Key here if selected.</p>';
    }

    public function stability_api_key_callback() {
        $api_key = get_option( 'mm_stability_api_key' );
        echo '<input type="password" name="mm_stability_api_key" value="' . esc_attr( $api_key ) . '" class="regular-text">';
        echo '<p class="description">Enter your Stability AI API Key here (DreamStudio/Stability Platform).</p>';
    }

    public function provider_callback() {
        $provider = get_option( 'mm_provider', 'mock' );
        ?>
        <select name="mm_provider">
            <option value="mock" <?php selected( $provider, 'mock' ); ?>>Mock Provider (Dev)</option>
            <option value="openai" <?php selected( $provider, 'openai' ); ?>>OpenAI (DALL-E)</option>
            <option value="gemini" <?php selected( $provider, 'gemini' ); ?>>Google Gemini</option>
            <option value="stability" <?php selected( $provider, 'stability' ); ?>>Stability AI</option>
        </select>
        <p class="description">Select which AI service to use.</p>
        <?php
    }

    public function gemini_api_key_callback() {
        $api_key = get_option( 'mm_gemini_api_key' );
        echo '<input type="password" name="mm_gemini_api_key" value="' . esc_attr( $api_key ) . '" class="regular-text">';
        echo '<p class="description">Enter your Google Gemini API Key here if selected (via AI Studio).</p>';
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
