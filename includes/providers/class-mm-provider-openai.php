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
     * 
     * Uses generic DALL-E 2/3 edit or similar if available, 
     * BUT OpenAI doesn't have a direct "remove background" endpoint yet.
     * Ideally we'd use a specific service like remove.bg or Stability.ai for this.
     * 
     * FOR MVP: We will use DALL-E 2 "Edit" endpoint to MASK the background if possible,
     * or actually, let's switch this to use a standard "Edit" with a prompt "remove background"
     * which might not work perfectly with DALL-E.
     * 
     * ALTERNATIVE: For "Remove Background", it's better to use something like Stability AI or remove.bg.
     * However, since we promised OpenAI, we might need to use "Variations" or "Edits".
     * 
     * ACTUALLY: Let's implement generic "Edit" for now, mapped to "Style Transfer" 
     * and keep "Remove Background" as a placeholder or mocked for OpenAI until we add a specific lib.
     * 
     * Wait, user expects background removal. 
     * OpenAI DALL-E 2 can do edits with a mask. We don't have a mask.
     * 
     * Let's assume for this step we are just wiring up the API connection.
     * I will implement `style_transfer` (Edit) first as it's supported.
     * For `remove_background`, I will throw an error saying "Not supported by OpenAI DALL-E 2 directly without mask".
     * OR better: We use `recraft.ai` or `remove.bg` for this specific feature later.
     * 
     * USE CASE: Many "AI" plugins use specific APIs for specific tasks.
     * 
     * Let's stick to the interface. I'll implement a basic `images/generations` call for "Style Transfer".
     */
    public function remove_background( $source_path ) {
        // OpenAI DALL-E does not support direct background removal without a mask.
        // Returning error for now, or we could fallback to a mock for demo.
        return new WP_Error( 'not_implemented', 'OpenAI DALL-E does not support direct background removal. Please use a different provider or upload a mask.' );
    }

    /**
     * Style Transfer / Edit.
     * 
     * Uses OpenAI `images/edits` or `images/generations`.
     * Note: `images/edits` requires a mask. 
     * `images/variations` takes an image and generates similar ones.
     */
    public function style_transfer( $source_path, $params = array() ) {
        if ( empty( $this->api_key ) ) {
            return new WP_Error( 'missing_api_key', 'OpenAI API Key is missing.' );
        }

        // Use 'images/variations' as a close proxy for "Style Transfer" without a mask
        // Or 'images/generations' if we just want new image.
        // Let's use 'images/variations' for now.
        $url = 'https://api.openai.com/v1/images/variations';

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
