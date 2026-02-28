<?php

/**
 * Worker Logic
 *
 * @package    Media_Maestro
 * @subpackage Media_Maestro/includes/jobs
 */

class Media_Maestro_Worker {

    /**
     * Init hooks.
     */
    public function init() {
        add_action( 'mm_process_job', array( $this, 'process_job' ) );
    }

    /**
     * Process a job.
     *
     * @param int $job_id Job ID.
     */
    public function process_job( $job_id ) {
        $job_manager = new Media_Maestro_Job_Manager();
        $job = $job_manager->get_job( $job_id );

        if ( is_wp_error( $job ) ) {
            return;
        }

        // Update status to processing
        update_post_meta( $job_id, '_mm_status', 'processing' );

        $source_id = $job['source_id'];
        $operation = $job['operation'];
        
        $source_path = get_attached_file( $source_id );
        if ( ! $source_path || ! file_exists( $source_path ) ) {
            $this->fail_job( $job_id, 'Source file not found.' );
            return;
        }

        // Get Provider
        $provider_manager = Media_Maestro_Provider_Manager::get_instance();
        $provider = $provider_manager->get_default_provider();

        if ( is_wp_error( $provider ) ) {
            $this->fail_job( $job_id, 'No provider available.' );
            return;
        }

        $result_path = false;

        // Execute Operation
        $params = isset( $job['params'] ) && is_array( $job['params'] ) ? $job['params'] : array();
        $prompt = isset( $params['prompt'] ) ? $params['prompt'] : '';

        // Execute Operation
        if ( in_array( $operation, array( 'style_transfer', 'product_placement' ) ) ) {
            if ( ! method_exists( $provider, $operation ) ) {
                $this->fail_job( $job_id, "Operation '$operation' not supported by this provider." );
                return;
            }
            
            $result_path = $provider->$operation( $source_path, $prompt, $params );
            
        } elseif ( $operation === 'auto_tag_image' ) {
            if ( ! method_exists( $provider, 'analyze_and_tag' ) ) {
                $this->fail_job( $job_id, "Auto-tagging is not supported by this provider." );
                return;
            }
            
            // Auto Tagging is a metadata operation, not an image generation operation.
            $tags = $provider->analyze_and_tag( $source_id, $source_path );
            
            if ( is_wp_error( $tags ) ) {
                update_post_meta( $source_id, '_mm_ai_tags_error', $tags->get_error_message() );
                $this->fail_job( $job_id, $tags->get_error_message() );
                return;
            }
            
            // Job complete, clears any old errors.
            delete_post_meta( $source_id, '_mm_ai_tags_error' );
            update_post_meta( $job_id, '_mm_status', 'completed' );
            return;
            
        } elseif ( $operation === 'auto_seo_image' ) {
            if ( ! method_exists( $provider, 'analyze_and_seo' ) ) {
                $this->fail_job( $job_id, "Auto-SEO is not supported by this provider." );
                return;
            }
            
            $product_context = '';
            // If WooCommerce is active, we can check if the parent post is a product
            $parent_id = wp_get_post_parent_id( $source_id );
            if ( $parent_id ) {
                $parent_post = get_post( $parent_id );
                if ( $parent_post && $parent_post->post_type === 'product' ) {
                    $product_context = $parent_post->post_title;
                }
            }
            
            $seo_result = $provider->analyze_and_seo( $source_id, $source_path, $product_context );
            
            if ( is_wp_error( $seo_result ) ) {
                update_post_meta( $source_id, '_mm_ai_seo_error', $seo_result->get_error_message() );
                $this->fail_job( $job_id, $seo_result->get_error_message() );
                return;
            }
            
            delete_post_meta( $source_id, '_mm_ai_seo_error' );
            update_post_meta( $job_id, '_mm_status', 'completed' );
            return;
            
        } else {
             $this->fail_job( $job_id, "Operation '$operation' is unknown or not supported." );
             return;
        }

        if ( is_wp_error( $result_path ) ) {
            $this->fail_job( $job_id, $result_path->get_error_message() );
            return;
        }

        // Handle success: Create new attachment
        $output_id = $this->create_attachment_from_path( $result_path, $source_id, $operation );

        if ( is_wp_error( $output_id ) ) {
            $this->fail_job( $job_id, 'Failed to create output attachment.' );
            return;
        }

        // Complete Job
        update_post_meta( $job_id, '_mm_status', 'completed' );
        update_post_meta( $job_id, '_mm_output_ids', array( $output_id ) );
    }

    /**
     * Fail a job.
     */
    private function fail_job( $job_id, $message ) {
        update_post_meta( $job_id, '_mm_status', 'failed' );
        update_post_meta( $job_id, '_mm_error_message', $message );
    }

    /**
     * Create attachment from file path.
     * 
     * @param string $path Path to file.
     * @param int $parent_id Original attachment ID.
     * @param string $operation Operation name.
     * @return int|WP_Error New attachment ID.
     */
    private function create_attachment_from_path( $path, $parent_id, $operation ) {
        // For MVP Mock, we might be receiving the SAME path as source if we didn't actually generate a new file.
        // If it's the same path, we should duplicate it first to avoid messing up the original if we ever delete it.
        // But for a real provider, $path would be a temp file or a new file.
        
        // Let's assume for MOCK that we need to simulate a new file
        $upload_dir = wp_upload_dir();
        $filename = basename( $path );
        $new_filename = pathinfo( $filename, PATHINFO_FILENAME ) . '-' . $operation . '-' . time() . '.' . pathinfo( $filename, PATHINFO_EXTENSION );
        $new_path = $upload_dir['path'] . '/' . $new_filename;
        
        copy( $path, $new_path );

        $filetype = wp_check_filetype( basename( $new_path ), null );

        $attachment = array(
            'guid'           => $upload_dir['url'] . '/' . basename( $new_path ), 
            'post_mime_type' => $filetype['type'],
            'post_title'     => 'AI ' . $operation . ' - ' . basename( $new_path ),
            'post_content'   => '',
            'post_status'    => 'inherit'
        );

        $attach_id = wp_insert_attachment( $attachment, $new_path, $parent_id );

        if ( is_wp_error( $attach_id ) ) {
            return $attach_id;
        }

        require_once( ABSPATH . 'wp-admin/includes/image.php' );
        $attach_data = wp_generate_attachment_metadata( $attach_id, $new_path );
        wp_update_attachment_metadata( $attach_id, $attach_data );

        // Add AIMS meta
        update_post_meta( $attach_id, '_mm_parent_id', $parent_id );
        update_post_meta( $attach_id, '_mm_operation', $operation );

        return $attach_id;
    }
}
