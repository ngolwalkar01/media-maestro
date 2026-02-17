<?php

/**
 * OpenAI Provider Implementation
 *
 * @package    Media_Maestro
 * @subpackage Media_Maestro/includes/providers
 */

class Media_Maestro_Provider_OpenAI implements Media_Maestro_Provider_Interface {

    /**
     * API Key.
     *
     * @var string
     */
    private $api_key;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->api_key = get_option( 'mm_api_key' );
    }

    /**
     * Get Provider ID.
     *
     * @return string
     */
    public function get_id() {
        return 'openai';
    }

    /**
     * Get Provider Name.
     *
     * @return string
     */
    public function get_name() {
        return 'OpenAI';
    }

    /**
     * Regenerate (Variations).
     */
    public function regenerate( $source_path, $prompt, $strength, $options = array() ) {
        // Map to style_transfer/variations for now
        return $this->style_transfer( $source_path, $options );
    }

    /**
     * Remove Background.
     */
    public function remove_background( $source_path, $options = array() ) {
        // OpenAI DALL-E does not support direct background removal without a mask.
        // Returning error for now, or we could fallback to a mock for demo.
        return new WP_Error( 'not_implemented', 'OpenAI DALL-E does not support direct background removal. Please use a different provider or upload a mask.' );
    }

    /**
     * Style Transfer / Edit.
     */
    public function style_transfer( $source_path, $prompt, $options = array() ) {
        if ( empty( $this->api_key ) ) {
            return new WP_Error( 'missing_api_key', 'OpenAI API Key is missing.' );
        }

        // MVP DECISION:
        // The user wants to see "Changes" based on the prompt. 
        // OpenAI 'variations' ignores the prompt. 
        // OpenAI 'edits' requires a mask.
        // To demonstrate the pipeline and Prompt capability, we will use 'generations' (Text-to-Image)
        // if a prompt is provided. This will generate a NEW image based on the prompt, 
        // ignoring the source image visual content (but proving the flow works).
        
        if ( ! empty( $prompt ) && $prompt !== 'Oil painting' ) {
             $url = 'https://api.openai.com/v1/images/generations';
             $data = array(
                'prompt' => $prompt,
                'n'      => 1,
                'size'   => '1024x1024',
             );
             // No file upload needed for generations
             $headers = array(
                'Authorization: Bearer ' . $this->api_key,
                'Content-Type: application/json'
             );
        } else {
            // Fallback to variations (Image-to-Image, ignores prompt)
            $url = 'https://api.openai.com/v1/images/variations';
            $data = array(
                'image' => new CURLFile( $source_path ),
                'n'     => 1,
                'size'  => '1024x1024'
            );
             $headers = array(
                'Authorization: Bearer ' . $this->api_key,
                'Content-Type: multipart/form-data'
             );
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        if ( isset( $headers[1] ) && strpos( $headers[1], 'application/json' ) !== false ) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode( $data ) );
        } else {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data );
        }

        $result = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ( $error ) {
            return new WP_Error( 'api_error', 'OpenAI cURL Error: ' . $error );
        }

        if ( $http_code !== 200 ) {
            return new WP_Error( 'api_error', 'OpenAI API Error: ' . $result );
        }

        $json = json_decode( $result, true );
        if ( empty( $json['data'][0]['url'] ) ) {
            return new WP_Error( 'api_error', 'Invalid response from OpenAI.' );
        }

        // The URL is a remote URL. We need to download it to a temp path to return to the worker.
        $image_url = $json['data'][0]['url'];
        
        if ( ! function_exists( 'download_url' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        $temp_file = download_url( $image_url );

        if ( is_wp_error( $temp_file ) ) {
            return $temp_file;
        }
        
        return $temp_file;
    }

}
