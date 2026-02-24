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
     * Product Placement (Reference Image to new scene using dall-e-2)
     */
    public function product_placement( $source_path, $prompt, $options = array() ) {
        if ( empty( $this->api_key ) ) {
            return new WP_Error( 'missing_api_key', 'OpenAI API Key is missing.' );
        }

        if ( empty( $prompt ) ) {
            return new WP_Error( 'missing_prompt', 'A prompt is required for Product Placement.' );
        }

        // DALL-E edits require PNG
        $mime_type = mime_content_type( $source_path );
        if ( $mime_type !== 'image/png' ) {
            return new WP_Error( 'invalid_format', 'OpenAI image edits only support PNG images. Please use a PNG image instead of ' . $mime_type );
        }

        // Model (recommend gpt-image-1 for better fidelity)
        $model = ! empty( $options['model'] ) ? $options['model'] : 'gpt-image-1'; // or 'dall-e-2'

        // If user provides a mask, use it. Otherwise auto-generate mask + prep image.
        $mask_path = ! empty( $options['mask_path'] ) ? $options['mask_path'] : '';
        $prepared_image_path = $source_path;

        if ( empty( $mask_path ) || ! file_exists( $mask_path ) ) {
            // Create a prepared image with transparent background (best-effort)
            $prepared = $this->mm_make_background_transparent_png( $source_path, ! empty( $options['mask_tolerance'] ) ? (int) $options['mask_tolerance'] : 30 );
            if ( ! is_wp_error( $prepared ) ) {
                $prepared_image_path = $prepared;
            }

            // Create mask: transparent where edits allowed (background), opaque where shirt is kept
            $auto_mask = $this->mm_generate_mask_from_background( $prepared_image_path, ! empty( $options['mask_tolerance'] ) ? (int) $options['mask_tolerance'] : 30 );
            if ( is_wp_error( $auto_mask ) ) {
                return $auto_mask;
            }
            $mask_path = $auto_mask;
        }

        $url = 'https://api.openai.com/v1/images/edits';

        $ch = curl_init();
        curl_setopt( $ch, CURLOPT_URL, $url );
        curl_setopt( $ch, CURLOPT_POST, 1 );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );

        // IMPORTANT: do NOT set Content-Type manually; let cURL create the boundary
        curl_setopt( $ch, CURLOPT_HTTPHEADER, array(
            'Authorization: Bearer ' . $this->api_key,
        ) );

        $data = array(
            'image'  => new CURLFile( $prepared_image_path, 'image/png', wp_basename( $prepared_image_path ) ),
            'mask'   => new CURLFile( $mask_path, 'image/png', wp_basename( $mask_path ) ),
            'prompt' => substr( $prompt, 0, 4000 ),
            'model'  => $model,
            'n'      => 1,
            'size'   => '1024x1024',
        );

        curl_setopt( $ch, CURLOPT_POSTFIELDS, $data );

        error_log( "MM_OPENAI: Product Placement (model={$model}) using /v1/images/edits with mask..." );

        $result = curl_exec( $ch );
        $http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
        curl_close( $ch );

        if ( $http_code !== 200 ) {
            error_log( "MM_OPENAI: Product Placement Error ($http_code): $result" );
            $json_error = json_decode( $result, true );
            if ( isset( $json_error['error']['message'] ) ) {
                return new WP_Error( 'api_error', "OpenAI Error: " . $json_error['error']['message'] );
            }
            return new WP_Error( 'api_error', "OpenAI Error ($http_code): " . substr( $result, 0, 200 ) );
        }

        $json = json_decode( $result, true );

        if ( empty( $json['data'][0]['url'] ) ) {
            return new WP_Error( 'api_error', 'Image generation succeeded but no URL was returned: ' . $result );
        }

        if ( ! function_exists( 'download_url' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        return download_url( $json['data'][0]['url'] );
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

    private function mm_create_temp_file( $prefix = 'mm_' ) {
        $upload_dir = wp_upload_dir();
        $dir = ! empty( $upload_dir['basedir'] ) ? $upload_dir['basedir'] : sys_get_temp_dir();

        $tmp = tempnam( $dir, $prefix );
        if ( ! $tmp ) {
            $tmp = tempnam( sys_get_temp_dir(), $prefix );
        }

        return $tmp;
    }

    private function mm_make_background_transparent_png( $png_path, $tolerance = 30 ) {
        if ( ! file_exists( $png_path ) ) {
            return new WP_Error( 'missing_file', 'PNG file not found.' );
        }

        if ( ! function_exists( 'imagecreatefrompng' ) ) {
            return new WP_Error( 'gd_missing', 'GD library is required for automatic masking.' );
        }

        $im = imagecreatefrompng( $png_path );
        if ( ! $im ) {
            return new WP_Error( 'img_load_failed', 'Could not read PNG for transparency processing.' );
        }

        imagesavealpha( $im, true );

        $w = imagesx( $im );
        $h = imagesy( $im );

        // Sample background from 4 corners and average
        $corners = array(
            imagecolorsforindex( $im, imagecolorat( $im, 0, 0 ) ),
            imagecolorsforindex( $im, imagecolorat( $im, $w-1, 0 ) ),
            imagecolorsforindex( $im, imagecolorat( $im, 0, $h-1 ) ),
            imagecolorsforindex( $im, imagecolorat( $im, $w-1, $h-1 ) ),
        );

        $bg = array( 'red' => 0, 'green' => 0, 'blue' => 0 );
        foreach ( $corners as $c ) {
            $bg['red']   += (int) $c['red'];
            $bg['green'] += (int) $c['green'];
            $bg['blue']  += (int) $c['blue'];
        }
        $bg['red']   = (int) round( $bg['red'] / 4 );
        $bg['green'] = (int) round( $bg['green'] / 4 );
        $bg['blue']  = (int) round( $bg['blue'] / 4 );

        // Walk pixels and make background transparent
        for ( $y = 0; $y < $h; $y++ ) {
            for ( $x = 0; $x < $w; $x++ ) {
                $rgba = imagecolorsforindex( $im, imagecolorat( $im, $x, $y ) );

                // If pixel already transparent, keep it
                if ( isset( $rgba['alpha'] ) && $rgba['alpha'] >= 120 ) {
                    continue;
                }

                $is_bg =
                    abs( $rgba['red']   - $bg['red'] )   <= $tolerance &&
                    abs( $rgba['green'] - $bg['green'] ) <= $tolerance &&
                    abs( $rgba['blue']  - $bg['blue'] )  <= $tolerance;

                if ( $is_bg ) {
                    // alpha: 127 = fully transparent in GD
                    $col = imagecolorallocatealpha( $im, $rgba['red'], $rgba['green'], $rgba['blue'], 127 );
                    imagesetpixel( $im, $x, $y, $col );
                }
            }
        }

        $out = $this->mm_create_temp_file( 'mm_transparent_' );
        if ( ! $out ) {
            imagedestroy( $im );
            return new WP_Error( 'tmp_failed', 'Could not create temp file for transparent PNG.' );
        }

        // Ensure .png extension
        $out_png = $out . '.png';
        imagepng( $im, $out_png );
        imagedestroy( $im );

        return $out_png;
    }

    private function mm_generate_mask_from_background( $png_path, $tolerance = 30 ) {
        if ( ! file_exists( $png_path ) ) {
            return new WP_Error( 'missing_file', 'PNG file not found for mask generation.' );
        }

        if ( ! function_exists( 'imagecreatefrompng' ) ) {
            return new WP_Error( 'gd_missing', 'GD library is required for automatic masking.' );
        }

        $im = imagecreatefrompng( $png_path );
        if ( ! $im ) {
            return new WP_Error( 'img_load_failed', 'Could not read PNG for mask generation.' );
        }

        imagesavealpha( $im, true );

        $w = imagesx( $im );
        $h = imagesy( $im );

        // Determine background color from corners
        $corners = array(
            imagecolorsforindex( $im, imagecolorat( $im, 0, 0 ) ),
            imagecolorsforindex( $im, imagecolorat( $im, $w-1, 0 ) ),
            imagecolorsforindex( $im, imagecolorat( $im, 0, $h-1 ) ),
            imagecolorsforindex( $im, imagecolorat( $im, $w-1, $h-1 ) ),
        );

        $bg = array( 'red' => 0, 'green' => 0, 'blue' => 0 );
        foreach ( $corners as $c ) {
            $bg['red']   += (int) $c['red'];
            $bg['green'] += (int) $c['green'];
            $bg['blue']  += (int) $c['blue'];
        }
        $bg['red']   = (int) round( $bg['red'] / 4 );
        $bg['green'] = (int) round( $bg['green'] / 4 );
        $bg['blue']  = (int) round( $bg['blue'] / 4 );

        // Create mask image: transparent = editable area, opaque = keep shirt
        $mask = imagecreatetruecolor( $w, $h );
        imagesavealpha( $mask, true );
        $transparent = imagecolorallocatealpha( $mask, 0, 0, 0, 127 );
        imagefill( $mask, 0, 0, $transparent );

        $opaque = imagecolorallocatealpha( $mask, 0, 0, 0, 0 );

        for ( $y = 0; $y < $h; $y++ ) {
            for ( $x = 0; $x < $w; $x++ ) {
                $rgba = imagecolorsforindex( $im, imagecolorat( $im, $x, $y ) );

                // If image pixel is transparent, it's definitely editable
                if ( isset( $rgba['alpha'] ) && $rgba['alpha'] >= 120 ) {
                    continue;
                }

                $is_bg =
                    abs( $rgba['red']   - $bg['red'] )   <= $tolerance &&
                    abs( $rgba['green'] - $bg['green'] ) <= $tolerance &&
                    abs( $rgba['blue']  - $bg['blue'] )  <= $tolerance;

                if ( ! $is_bg ) {
                    // keep region (shirt) => opaque in mask
                    imagesetpixel( $mask, $x, $y, $opaque );
                }
            }
        }

        $out = $this->mm_create_temp_file( 'mm_mask_' );
        if ( ! $out ) {
            imagedestroy( $im );
            imagedestroy( $mask );
            return new WP_Error( 'tmp_failed', 'Could not create temp file for mask PNG.' );
        }

        $out_png = $out . '.png';
        imagepng( $mask, $out_png );

        imagedestroy( $im );
        imagedestroy( $mask );

        return $out_png;
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
