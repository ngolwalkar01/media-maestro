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

        // 1. If NO prompt, fallback to Variations (Image2Image without prompt)
        if ( empty( $prompt ) ) {
             return $this->generate_variation( $source_path );
        }

        // 2. "Smart Edit" Pipeline:
        //    A. Analyze Image (GPT-4o Vision) -> Get Description
        //    B. Generate New Image (DALL-E 3) -> Description + User Prompt

        // Step A: Describe Image
        error_log( "MM_OPENAI: Analyzing image for Smart Edit..." );
        $description = $this->analyze_image( $source_path );
        
        if ( is_wp_error( $description ) ) {
            error_log( "MM_OPENAI: Analysis failed: " . $description->get_error_message() );
            // Fallback: Just use the user prompt blindly
            $final_prompt = $prompt;
        } else {
            error_log( "MM_OPENAI: Image Description: " . substr( $description, 0, 100 ) . "..." );
            // Combine: Content Description + Style/Edit Instruction
            $final_prompt = "Create an image based on this description: " . $description . ". \n\n Modification/Style to apply: " . $prompt;
        }

        // Step B: Generate
        error_log( "MM_OPENAI: Generating with prompt: " . substr( $final_prompt, 0, 100 ) . "..." );
        return $this->generate_image( $final_prompt );
    }

    /**
     * Analyze image using GPT-4o.
     */
    private function analyze_image( $path ) {
        $type = mime_content_type( $path );
        $data = file_get_contents( $path );
        $base64 = base64_encode( $data );
        $data_url = 'data:' . $type . ';base64,' . $base64;

        $url = 'https://api.openai.com/v1/chat/completions';
        
        $body = array(
            'model' => 'gpt-4o',
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => array(
                        array(
                            'type' => 'text',
                            'text' => 'Describe the content, composition, and main subject of this image in detail. Be objective.'
                        ),
                        array(
                            'type' => 'image_url',
                            'image_url' => array(
                                'url' => $data_url
                            )
                        )
                    )
                )
            ),
            'max_tokens' => 300
        );

        $args = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type'  => 'application/json'
            ),
            'body'    => json_encode( $body ),
            'timeout' => 60
        );

        $response = wp_remote_post( $url, $args );

        if ( is_wp_error( $response ) ) {
            return $response;
        }
        
        $body = wp_remote_retrieve_body( $response );
        $json = json_decode( $body, true );

        if ( ! empty( $json['choices'][0]['message']['content'] ) ) {
            return $json['choices'][0]['message']['content'];
        }

        return new WP_Error( 'analysis_failed', 'Could not analyze image.' );
    }

    /**
     * Generate image using DALL-E 3.
     */
    private function generate_image( $prompt ) {
        $url = 'https://api.openai.com/v1/images/generations';
        
        $body = array(
            'model'  => 'dall-e-3',
            'prompt' => substr( $prompt, 0, 4000 ), // limit
            'n'      => 1,
            'size'   => '1024x1024'
        );

        $args = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type'  => 'application/json'
            ),
            'body'    => json_encode( $body ),
            'timeout' => 60
        );

        $response = wp_remote_post( $url, $args );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );

        if ( $code !== 200 ) {
             error_log( "MM_OPENAI: Gen Error: $body" );
             return new WP_Error( 'api_error', "OpenAI Error ($code): $body" );
        }

        $json = json_decode( $body, true );
        
        if ( empty( $json['data'][0]['url'] ) ) {
            return new WP_Error( 'api_error', 'Invalid response from DALL-E.' );
        }

        $image_url = $json['data'][0]['url'];
        
        if ( ! function_exists( 'download_url' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        return download_url( $image_url );
    }

    /**
     * Generate variation (Fallback).
     */
    private function generate_variation( $source_path ) {
        $url = 'https://api.openai.com/v1/images/variations';
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization: Bearer ' . $this->api_key,
            'Content-Type: multipart/form-data' 
        ));
        
        $cfile = new CURLFile($source_path);
        $data = array('image' => $cfile, 'n' => 1, 'size' => '1024x1024');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        
        $result = curl_exec($ch);
        curl_close($ch);
        
        $json = json_decode( $result, true );
        
        if ( empty( $json['data'][0]['url'] ) ) {
            return new WP_Error( 'api_error', 'Variation failed.' );
        }

        if ( ! function_exists( 'download_url' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        return download_url( $json['data'][0]['url'] );
    }

}
