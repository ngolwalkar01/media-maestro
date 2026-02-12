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

        // Use 'images/variations' as a close proxy for "Style Transfer" without a mask
        // Or 'images/generations' if we just want new image.
        // Let's use 'images/variations' for now.
        $url = 'https://api.openai.com/v1/images/variations';

        // NOTE: Variations does NOT take a prompt. It just takes an image.
        // Edits take a prompt but require a mask.
        // Generations take a prompt but no image.
        // So stricly speaking, 'style_transfer' with OpenAI DALL-E 2 is tricky.
        // We will use VARIATIONS and ignore the prompt for now, or use GENERATIONS and ignore the source image if the user wants.
        // Let's stick to VARIATIONS for "image-to-image" style vibes.

        $args = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                // Content-Type is multipart/form-data, WP handles it if we pass 'body' as array with keys
            ),
            'body'    => array(
                'image' => new CURLFile( $source_path ),
                'n'     => 1,
                'size'  => '1024x1024',
            ),
            'timeout' => 60,
        );

        // WP's wp_remote_post with multipart is tricky.
        // We often need to use generic PHP cURL or a helper for files.
        // Let's try basic cURL for reliability here, or use a helper if we had one.
        // For MVP simplicity in WP, we might stick to wp_remote_post if we can format body right.
        // But wp_remote_post doesn't easily support multipart/form-data file uploads until recently.
        
        // Let's use a simple cURL wrapper for this specific call to avoid WP incompatibilities with native file handles.
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization: Bearer ' . $this->api_key,
            'Content-Type: multipart/form-data' 
        ));
        
        $cfile = new CURLFile($source_path);
        
        // DALL-E 2 Variations: image, n, size, response_format, user
        $data = array('image' => $cfile, 'n' => 1, 'size' => '1024x1024');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        
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
        $temp_file = download_url( $image_url );

        if ( is_wp_error( $temp_file ) ) {
            return $temp_file;
        }

        return $temp_file;
    }

}
