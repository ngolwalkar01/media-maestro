<?php

/**
 * Stability AI Provider Implementation
 *
 * @package    Media_Maestro
 * @subpackage Media_Maestro/includes/providers
 */

class Media_Maestro_Provider_Stability implements Media_Maestro_Provider_Interface {

    private $api_key;
    private $api_base = 'https://api.stability.ai/v2beta';

    public function __construct() {
        $this->api_key = get_option( 'mm_stability_api_key' );
    }

    public function get_id() {
        return 'stability';
    }

    public function get_name() {
        return 'Stability AI';
    }

    // --- Interface Methods ---

    public function remove_background( $source_path, $options = array() ) {
        return $this->edit_remove_bg( $source_path );
    }

    public function style_transfer( $source_path, $prompt, $options = array() ) {
        // Map "Style Transfer" to Style Control or Image-to-Image
        return $this->control_style( $source_path, $prompt );
    }

    public function regenerate( $source_path, $prompt, $strength, $options = array() ) {
        // Map to Image-to-Image with strength
        return $this->generate_sd3( $source_path, $prompt, $strength );
    }

    // --- Specific Features ---

    // Generation
    public function generate_ultra( $source_path, $prompt ) {
        return $this->call_generate( 'stable-image/generate/ultra', $prompt, $source_path );
    }
    
    public function generate_core( $source_path, $prompt ) {
        return $this->call_generate( 'stable-image/generate/core', $prompt, $source_path );
    }

    public function generate_sd3( $source_path, $prompt, $strength = 0.7 ) {
        // SD3 supports image-to-image
        return $this->call_generate( 'stable-image/generate/sd3', $prompt, $source_path, $strength );
    }

    // Control
    public function sketch( $source_path, $prompt ) {
        return $this->call_control( 'stable-image/control/sketch', $source_path, $prompt );
    }

    public function structure( $source_path, $prompt ) {
        return $this->call_control( 'stable-image/control/structure', $source_path, $prompt );
    }

    public function control_style( $source_path, $prompt ) {
        return $this->call_control( 'stable-image/control/style', $source_path, $prompt ); // Note: Style might not use prompt in same way, verifying docs.
        // Actually Style Control takes an image and applies its style to a prompt? Or applies prompt style to image?
        // Stability "Control Style" extracts style from input image -> generates new from prompt.
        // User wants "Transfer style TO this image". 
        // For that, Image-to-Image (SD3) with prompt describing style is often best, OR "Structure" control preserving structure + new style prompt.
        // Let's stick to Control Style for now as requested.
    }

    // Upscale
    public function upscale_fast( $source_path ) {
        return $this->call_upscale( 'stable-image/upscale/fast', $source_path );
    }

    public function upscale_conservative( $source_path, $prompt ) {
         return $this->call_upscale( 'stable-image/upscale/conservative', $source_path, $prompt );
    }

    public function upscale_creative( $source_path, $prompt ) {
        return $this->call_upscale( 'stable-image/upscale/creative', $source_path, $prompt );
    }

    // Edit
    public function inpaint( $source_path, $prompt ) {
        // Inpaint requires a mask. We don't have one from UI yet.
        return new WP_Error( 'not_implemented', 'Inpaint requires a mask. Please implement masking UI.' );
    }
    
    public function outpaint( $source_path, $prompt, $strength = null, $params = array() ) {
        $direction = isset( $params['direction'] ) ? $params['direction'] : 'down';
        
        $data = array();
        $pixels = 512;
        
        // Map direction to API params (left, right, up, down)
        switch ( $direction ) {
            case 'up':
                $data['up'] = $pixels;
                break;
            case 'down':
                $data['down'] = $pixels;
                break;
            case 'left':
                $data['left'] = $pixels;
                break;
            case 'right':
                $data['right'] = $pixels;
                break;
            case 'all':
                $data['up'] = 256;
                $data['down'] = 256;
                $data['left'] = 256;
                $data['right'] = 256;
                break;
            default:
                $data['down'] = 512; // Default
        }
        
        if ( ! empty( $prompt ) ) {
            $data['prompt'] = $prompt; // Often outpaint only needs direction, prompt optional? 
            // Stability Outpaint uses prompt to fill the new area.
        }

        return $this->call_edit( 'stable-image/edit/outpaint', $source_path, $data );
    }

    public function erase( $source_path ) {
        // Erase requires mask.
         return new WP_Error( 'not_implemented', 'Erase requires a mask.' );
    }

    public function search_replace( $source_path, $prompt ) {
        // The prompt should be "search_prompt" and "replace_prompt".
        // Our UI only gives one "prompt".
        // We'll assume the prompt is "Search for X and replace with Y" -> Complex parsing?
        // Or we just use the prompt as the "search_prompt" and let it replace? No.
        // Let's try to parse: "Replace cat with dog"
        if ( preg_match( '/Replace (.*) with (.*)/i', $prompt, $matches ) ) {
            $search = trim( $matches[1] );
            $replace = trim( $matches[2] );
            return $this->call_edit( 'stable-image/edit/search-and-replace', $source_path, array( 'search_prompt' => $search, 'prompt' => $replace ) );
        }
        return new WP_Error( 'invalid_prompt', 'For Search & Replace, use format: "Replace [object] with [new object]"' );
    }

    public function edit_remove_bg( $source_path ) {
        return $this->call_edit( 'stable-image/edit/remove-background', $source_path );
    }

    public function replace_bg( $source_path, $prompt ) {
         // This endpoint requires 'subject_image' not 'image'.
         $data = array(
            'subject_image' => new CURLFile( $source_path ),
            'background_prompt' => $prompt,
            'output_format' => 'png'
        );
        return $this->request( 'stable-image/edit/replace-background-and-relight', $data );
    }


    // --- Helpers ---

    private function call_generate( $endpoint, $prompt, $image_path = null, $strength = null ) {
        $data = array(
            'prompt' => $prompt,
            'output_format' => 'png'
        );

        // Map Image-to-Image if image provided.
        // Note: 'ultra' doesn't support img2img, only 'sd3' and 'core' (maybe).
        // Actually 'core' does not support img2img directly in v2beta 'generate/core'? 
        // It does via 'mode'='image-to-image' parameter if supported.
        // Let's handle SD3 specifically.
        
        if ( strpos( $endpoint, 'sd3' ) !== false && $image_path ) {
             $data['image'] = new CURLFile( $image_path );
             $data['mode'] = 'image-to-image';
             $data['strength'] = $strength;
        } elseif ( $image_path && strpos( $endpoint, 'ultra' ) === false ) {
             // Try passing image for others if valid?
             // Core doesn't do img2img.
             // If Core selected but image provided, we just ignore image (Text-to-Image).
        }

        return $this->request( $endpoint, $data );
    }

    private function call_control( $endpoint, $image_path, $prompt ) {
         $data = array(
            'image' => new CURLFile( $image_path ),
            'prompt' => $prompt,
            'output_format' => 'png'
        );
        return $this->request( $endpoint, $data );
    }

    private function call_upscale( $endpoint, $image_path, $prompt = null ) {
        $data = array(
            'image' => new CURLFile( $image_path ),
            'output_format' => 'png'
        );
        if ( $prompt ) {
            $data['prompt'] = $prompt;
        }
        return $this->request( $endpoint, $data );
    }

    private function call_edit( $endpoint, $image_path, $extra_params = array() ) {
        $data = array(
            'image' => new CURLFile( $image_path ),
            'output_format' => 'png'
        );
        $data = array_merge( $data, $extra_params );
        return $this->request( $endpoint, $data );
    }

    private function request( $endpoint, $data ) {
        if ( empty( $this->api_key ) ) {
            return new WP_Error( 'missing_api_key', 'Stability API Key is missing.' );
        }

        $url = $this->api_base . '/' . $endpoint;
        
        $headers = array(
            'Authorization: Bearer ' . $this->api_key,
            'Accept: image/*'
        );

        $ch = curl_init();
        curl_setopt( $ch, CURLOPT_URL, $url );
        curl_setopt( $ch, CURLOPT_POST, 1 );
        curl_setopt( $ch, CURLOPT_POSTFIELDS, $data );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
        curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );

        // Increase timeout for AI generation
        curl_setopt( $ch, CURLOPT_TIMEOUT, 120 );

        error_log( "MM_STABILITY: Requesting $url..." );

        $result = curl_exec( $ch );
        $http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
        $error = curl_error( $ch );
        curl_close( $ch );

        if ( $error ) {
             error_log( "MM_STABILITY: cURL Error: $error" );
            return new WP_Error( 'api_error', 'Stability cURL Error: ' . $error );
        }

        if ( $http_code !== 200 ) {
            // Try to parse JSON error
            $json = json_decode( $result, true );
            $msg = isset( $json['errors'][0]['message'] ) ? $json['errors'][0]['message'] : substr( $result, 0, 200 );
            error_log( "MM_STABILITY: API Error ($http_code): $msg" );
            return new WP_Error( 'api_error', 'Stability API Error: ' . $msg );
        }
        
        // Check if it's JSON despite 200
        // If it starts with '{', it's likely an error (or metadata?) but Stability returns binary direct usually.
        //if ( substr( $result, 0, 1 ) === '{' ) {
             $json = json_decode( $result, true );
             $msg = json_encode( $json );
             return new WP_Error( 'api_error', 'Stability returned JSON (not image): ' . $msg );
       // }

        // Success! Result is binary image data.
        // Save to temp file.
        $upload_dir = wp_upload_dir();
        $filename = 'stability-' . time() . '.png';
        $file_path = $upload_dir['path'] . '/' . $filename;
        
        file_put_contents( $file_path, $result );
        
        error_log( "MM_STABILITY: Saved to $file_path" );
        return $file_path;
    }
}
