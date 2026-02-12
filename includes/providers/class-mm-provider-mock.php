<?php

/**
 * Mock Provider
 *
 * @package    Media_Maestro
 * @subpackage Media_Maestro/includes/providers
 */

class Media_Maestro_Provider_Mock implements Media_Maestro_Provider_Interface {

    public function get_id() {
        return 'mock';
    }

    public function get_name() {
        return 'Mock Provider (Dev)';
    }

    public function remove_background( $image_path, $options = array() ) {
        // Simulate processing delay
        sleep( 2 ); 
        
        // Return original path for now, enabling "success" flow
        // In real app we'd copy it to a new path
        return $image_path; 
    }

    public function style_transfer( $image_path, $prompt, $options = array() ) {
        sleep( 2 );
        return $image_path;
    }

    public function regenerate( $image_path, $prompt, $strength, $options = array() ) {
        sleep( 2 );
        return $image_path;
    }
}
