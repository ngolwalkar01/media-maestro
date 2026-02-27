<?php

/**
 * The Job Manager handles the lifecycle of AI jobs.
 *
 * @link       https://example.com
 * @since      1.0.0
 *
 * @package    Media_Maestro
 * @subpackage Media_Maestro/includes/jobs
 */

/**
 * The Job Manager handles the lifecycle of AI jobs.
 *
 * Registers the Custom Post Type and provides methods to create and manage jobs.
 *
 * @package    Media_Maestro
 * @subpackage Media_Maestro/includes/jobs
 * @author     Your Name <email@example.com>
 */
class Media_Maestro_Job_Manager {

    /**
     * Post type slug.
     */
    const CPT = 'mm_job';

    /**
     * Initialize the class.
     *
     * @since    1.0.0
     */
    public function __construct() {
    }

    /**
     * Register the Custom Post Type.
     *
     * @since    1.0.0
     */
    public function register_cpt() {

        $labels = array(
            'name'                  => _x( 'AI Jobs', 'Post Type General Name', 'media-maestro' ),
            'singular_name'         => _x( 'AI Job', 'Post Type Singular Name', 'media-maestro' ),
            'menu_name'             => __( 'AI Jobs', 'media-maestro' ),
            'name_admin_bar'        => __( 'AI Job', 'media-maestro' ),
            'archives'              => __( 'Job Archives', 'media-maestro' ),
            'attributes'            => __( 'Job Attributes', 'media-maestro' ),
            'parent_item_colon'     => __( 'Parent Job:', 'media-maestro' ),
            'all_items'             => __( 'All Jobs', 'media-maestro' ),
            'add_new_item'          => __( 'Add New Job', 'media-maestro' ),
            'add_new'               => __( 'Add New', 'media-maestro' ),
            'new_item'              => __( 'New Job', 'media-maestro' ),
            'edit_item'             => __( 'Edit Job', 'media-maestro' ),
            'update_item'           => __( 'Update Job', 'media-maestro' ),
            'view_item'             => __( 'View Job', 'media-maestro' ),
            'view_items'            => __( 'View Jobs', 'media-maestro' ),
            'search_items'          => __( 'Search Job', 'media-maestro' ),
            'not_found'             => __( 'Not found', 'media-maestro' ),
            'not_found_in_trash'    => __( 'Not found in Trash', 'media-maestro' ),
            'featured_image'        => __( 'Featured Image', 'media-maestro' ),
            'set_featured_image'    => __( 'Set featured image', 'media-maestro' ),
            'remove_featured_image' => __( 'Remove featured image', 'media-maestro' ),
            'use_featured_image'    => __( 'Use as featured image', 'media-maestro' ),
            'insert_into_item'      => __( 'Insert into job', 'media-maestro' ),
            'uploaded_to_this_item' => __( 'Uploaded to this job', 'media-maestro' ),
            'items_list'            => __( 'Jobs list', 'media-maestro' ),
            'items_list_navigation' => __( 'Jobs list navigation', 'media-maestro' ),
            'filter_items_list'     => __( 'Filter jobs list', 'media-maestro' ),
        );
        $args = array(
            'label'                 => __( 'AI Job', 'media-maestro' ),
            'description'           => __( 'Background AI processing jobs.', 'media-maestro' ),
            'labels'                => $labels,
            'supports'              => array( 'title', 'custom-fields' ), // Title used for ID
            'hierarchical'          => false,
            'public'                => false,
            'show_ui'               => true, // Visible in admin for debugging
            'show_in_menu'          => true, // Show in sidebar
            'menu_position'         => 5,
            'show_in_admin_bar'     => false,
            'show_in_nav_menus'     => false,
            'can_export'            => true,
            'has_archive'           => false,
            'exclude_from_search'   => true,
            'publicly_queryable'    => false,
            'capability_type'       => 'post',
            'capabilities'          => array(
                'create_posts' => 'upload_files', // Allow editors/admins
            ),
            'map_meta_cap'          => true,
            'show_in_rest'          => false,
        );
        register_post_type( self::CPT, $args );

        // Add custom columns to the Job list
        if ( is_admin() ) {
            add_filter( 'manage_' . self::CPT . '_posts_columns', array( $this, 'set_custom_columns' ) );
            add_action( 'manage_' . self::CPT . '_posts_custom_column', array( $this, 'render_custom_column' ), 10, 2 );
        }
    }

    /**
     * Set custom columns for the Job list.
     */
    public function set_custom_columns( $columns ) {
        $columns['mm_operation'] = __( 'Operation', 'media-maestro' );
        $columns['mm_source']    = __( 'Source Image', 'media-maestro' );
        $columns['mm_status']    = __( 'Status', 'media-maestro' );
        $columns['mm_error']     = __( 'Error Message', 'media-maestro' );
        return $columns;
    }

    /**
     * Render the custom columns for the Job list.
     */
    public function render_custom_column( $column, $post_id ) {
        switch ( $column ) {
            case 'mm_operation':
                $op = get_post_meta( $post_id, '_mm_operation', true );
                echo esc_html( $op );
                break;
            case 'mm_source':
                $source_id = get_post_meta( $post_id, '_mm_source_id', true );
                if ( $source_id ) {
                    echo wp_get_attachment_image( $source_id, array( 50, 50 ) );
                }
                break;
            case 'mm_status':
                $status = get_post_meta( $post_id, '_mm_status', true );
                $color  = '#6b7280'; // gray
                if ( $status === 'completed' ) $color = '#10b981'; // green
                if ( $status === 'failed' )    $color = '#ef4444'; // red
                if ( $status === 'processing') $color = '#f59e0b'; // yellow
                echo '<span style="color:' . $color . '; font-weight:bold;">' . esc_html( strtoupper( $status ) ) . '</span>';
                break;
            case 'mm_error':
                $error = get_post_meta( $post_id, '_mm_error_message', true );
                if ( $error ) {
                    echo '<span style="color:#ef4444; font-size:12px;">' . esc_html( $error ) . '</span>';
                }
                break;
        }
    }

    /**
     * Create a new job.
     *
     * @param int    $attachment_id Source attachment ID.
     * @param string $operation     Operation type (e.g., 'remove_background').
     * @param array  $params        Job parameters.
     * @return int|WP_Error Job ID on success, WP_Error on failure.
     */
    public function create_job( $attachment_id, $operation, $params = array() ) {
        if ( ! current_user_can( 'upload_files' ) ) {
            return new WP_Error( 'permission_denied', 'You do not have permission to create jobs.' );
        }

        $user_id = get_current_user_id();
        $title   = sprintf( '%s - %s - %s', $operation, $attachment_id, time() );

        $job_id = wp_insert_post( array(
            'post_type'   => self::CPT,
            'post_status' => 'pending', // internal status map
            'post_title'  => $title,
            'post_author' => $user_id,
        ) );

        if ( is_wp_error( $job_id ) ) {
            return $job_id;
        }

        // Save metadata
        update_post_meta( $job_id, '_mm_source_id', $attachment_id );
        update_post_meta( $job_id, '_mm_user_id', $user_id );
        update_post_meta( $job_id, '_mm_operation', $operation );
        update_post_meta( $job_id, '_mm_params', $params );
        update_post_meta( $job_id, '_mm_status', 'pending' );

        // Trigger the background worker
        wp_schedule_single_event( time(), 'mm_process_job', array( $job_id ) );

        return $job_id;
    }

    /**
     * Get job status and details.
     *
     * @param int $job_id Job ID.
     * @return array|WP_Error Job data.
     */
    public function get_job( $job_id ) {
        $post = get_post( $job_id );
        if ( ! $post || $post->post_type !== self::CPT ) {
            return new WP_Error( 'invalid_job', 'Job not found.' );
        }

        return array(
            'id'        => $post->ID,
            'status'    => get_post_meta( $post->ID, '_mm_status', true ),
            'operation' => get_post_meta( $post->ID, '_mm_operation', true ),
            'source_id' => get_post_meta( $post->ID, '_mm_source_id', true ),
            'params'    => get_post_meta( $post->ID, '_mm_params', true ),
            'result'    => get_post_meta( $post->ID, '_mm_output_ids', true ), // Array of attachment IDs
            'error'     => get_post_meta( $post->ID, '_mm_error_message', true ),
        );
    }
}
