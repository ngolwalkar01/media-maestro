<?php

/**
 * Interface for AI Service Providers.
 *
 * @package    Media_Maestro
 * @subpackage Media_Maestro/includes/providers
 */

interface Media_Maestro_Provider_Interface {

    /**
     * Get the provider ID.
     *
     * @return string Provider ID (e.g., 'openai').
     */
    public function get_id();

    /**
     * Get the provider name.
     *
     * @return string Provider Name (e.g., 'OpenAI').
     */
    public function get_name();

    /**
     * Remove background from an image.
     *
     * @param string $image_path Path to the local image file.
     * @param array  $options    Additional options.
     * @return string|WP_Error Path to the processed image or error.
     */
    public function remove_background( $image_path, $options = array() );

    /**
     * Apply style transfer.
     *
     * @param string $image_path Path to the local image file.
     * @param string $prompt     Style prompt.
     * @param array  $options    Additional options.
     * @return string|WP_Error Path to the processed image or error.
     */
    public function style_transfer( $image_path, $prompt, $options = array() );

    /**
     * Regenerate image (outpainting/inpainting or variation).
     *
     * @param string $image_path Path to the local image file.
     * @param string $prompt     Prompt.
     * @param float  $strength   Denoising strength.
     * @param array  $options    Additional options.
     * @return string|WP_Error Path to the processed image or error.
     */
    public function regenerate( $image_path, $prompt, $strength, $options = array() );
}
