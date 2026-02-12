<?php

/**
 * Provider Manager
 *
 * @package    Media_Maestro
 * @subpackage Media_Maestro/includes/providers
 */

class Media_Maestro_Provider_Manager {

    /**
     * Registered providers.
     *
     * @var array
     */
    private $providers = array();

    /**
     * Instance.
     *
     * @var Media_Maestro_Provider_Manager
     */
    private static $instance = null;

    /**
     * Get instance.
     *
     * @return Media_Maestro_Provider_Manager
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {
        // Register default providers
        $this->register_provider( new Media_Maestro_Provider_Mock() );
        $this->register_provider( new Media_Maestro_Provider_OpenAI() );
    }

    /**
     * Register a provider.
     *
     * @param string $id Provider ID.
     * @param Media_Maestro_Provider_Interface $provider Provider instance.
     */
    public function register_provider( Media_Maestro_Provider_Interface $provider ) {
        $this->providers[ $provider->get_id() ] = $provider;
    }

    /**
     * Get a provider.
     *
     * @param string $id Provider ID.
     * @return Media_Maestro_Provider_Interface|WP_Error Provider or error.
     */
    public function get_provider( $id ) {
        if ( isset( $this->providers[ $id ] ) ) {
            return $this->providers[ $id ];
        }
        return new WP_Error( 'invalid_provider', 'Provider not found.' );
    }

    /**
     * Get all providers.
     *
     * @return array List of providers.
     */
    public function get_providers() {
        return $this->providers;
    }

    /**
     * Get default provider.
     *
     * @return Media_Maestro_Provider_Interface|WP_Error Default provider.
     */
    public function get_default_provider() {
        // For MVP, just return the Mock provider or the first one
        // Later, pull from settings
        $provider_id = 'mock'; // Default for dev
        return $this->get_provider( $provider_id );
    }
}
