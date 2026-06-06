<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * REST API Controller for Folders
 *
 * @package    Media_Maestro
 * @subpackage Media_Maestro/includes/api
 */

class Media_Maestro_Folder_Controller extends WP_REST_Controller {

    /**
     * Namespace for the API.
     */
    protected $namespace = 'mm/v1';

    /**
     * Resource name.
     */
    protected $rest_base = 'folders';

    /**
     * Register the routes.
     */
    public function register_routes() {
        register_rest_route( $this->namespace, '/' . $this->rest_base, array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_items' ),
                'permission_callback' => array( $this, 'get_items_permissions_check' ),
            ),
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'create_item' ),
                'permission_callback' => array( $this, 'create_item_permissions_check' ),
                'args'                => array(
                    'name' => array(
                        'type'              => 'string',
                        'required'          => true,
                        'sanitize_callback' => 'sanitize_text_field',
                        'validate_callback' => function( $param, $request, $key ) {
                            return ! empty( trim( $param ) );
                        }
                    ),
                ),
            ),
        ) );

        register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)', array(
            array(
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => array( $this, 'update_item' ),
                'permission_callback' => array( $this, 'update_item_permissions_check' ),
                'args'                => array(
                    'name' => array(
                        'type'              => 'string',
                        'required'          => true,
                        'sanitize_callback' => 'sanitize_text_field',
                        'validate_callback' => function( $param, $request, $key ) {
                            return ! empty( trim( $param ) );
                        }
                    ),
                ),
            ),
            array(
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => array( $this, 'delete_item' ),
                'permission_callback' => array( $this, 'delete_item_permissions_check' ),
            ),
        ) );

        register_rest_route( $this->namespace, '/' . $this->rest_base . '/assign', array(
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'assign_items' ),
                'permission_callback' => array( $this, 'assign_items_permissions_check' ),
                'args'                => array(
                    'attachment_ids' => array(
                        'type'     => 'array',
                        'required' => true,
                        'items'    => array(
                            'type' => 'integer',
                        ),
                    ),
                    'folder_id' => array(
                        'required'          => true,
                        'sanitize_callback' => 'sanitize_text_field', // Can be integer ID or 'unassigned'
                    ),
                ),
            ),
        ) );
    }

    /**
     * Check if user has permission to read folders.
     */
    public function get_items_permissions_check( $request ) {
        return current_user_can( 'upload_files' );
    }

    /**
     * Check if user has permission to create folder.
     */
    public function create_item_permissions_check( $request ) {
        return current_user_can( 'upload_files' );
    }

    /**
     * Check if user has permission to update folder.
     */
    public function update_item_permissions_check( $request ) {
        return current_user_can( 'upload_files' );
    }

    /**
     * Check if user has permission to delete folder.
     */
    public function delete_item_permissions_check( $request ) {
        return current_user_can( 'upload_files' );
    }

    /**
     * Check if user has permission to assign folders.
     */
    public function assign_items_permissions_check( $request ) {
        return current_user_can( 'upload_files' );
    }

    /**
     * Get all folders list.
     */
    public function get_items( $request ) {
        $terms = get_terms( array(
            'taxonomy'   => 'mm_folder',
            'hide_empty' => false,
        ) );

        if ( is_wp_error( $terms ) ) {
            return $terms;
        }

        $folders = array();
        foreach ( $terms as $term ) {
            $folders[] = array(
                'id'    => $term->term_id,
                'name'  => $term->name,
                'slug'  => $term->slug,
                'count' => (int) $term->count,
            );
        }

        // Calculate "Unassigned" count
        $unassigned_query = new WP_Query( array(
            'post_type'      => 'attachment',
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'tax_query'      => array(
                array(
                    'taxonomy' => 'mm_folder',
                    'operator' => 'NOT EXISTS',
                ),
            ),
        ) );
        $unassigned_count = count( $unassigned_query->posts );

        // Calculate "All Media" count
        $all_count = (int) wp_count_posts( 'attachment' )->inherit;

        return rest_ensure_response( array(
            'folders'    => $folders,
            'unassigned' => $unassigned_count,
            'all'        => $all_count,
        ) );
    }

    /**
     * Create a new folder term.
     */
    public function create_item( $request ) {
        $name = trim( $request->get_param( 'name' ) );

        // Check if duplicate exists (case insensitive)
        $exists = get_term_by( 'name', $name, 'mm_folder' );
        if ( $exists ) {
            return new WP_Error( 'folder_exists', __( 'A folder with this name already exists.', 'media-maestro' ), array( 'status' => 400 ) );
        }

        $result = wp_insert_term( $name, 'mm_folder' );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        $term = get_term( $result['term_id'], 'mm_folder' );

        return rest_ensure_response( array(
            'id'    => $term->term_id,
            'name'  => $term->name,
            'slug'  => $term->slug,
            'count' => 0,
        ) );
    }

    /**
     * Rename an existing folder term.
     */
    public function update_item( $request ) {
        $term_id = absint( $request->get_param( 'id' ) );
        $name = trim( $request->get_param( 'name' ) );

        $term = get_term( $term_id, 'mm_folder' );
        if ( ! $term || is_wp_error( $term ) ) {
            return new WP_Error( 'folder_not_found', __( 'Folder not found.', 'media-maestro' ), array( 'status' => 404 ) );
        }

        // Check if duplicate exists with different ID
        $exists = get_term_by( 'name', $name, 'mm_folder' );
        if ( $exists && (int) $exists->term_id !== $term_id ) {
            return new WP_Error( 'folder_exists', __( 'A folder with this name already exists.', 'media-maestro' ), array( 'status' => 400 ) );
        }

        $result = wp_update_term( $term_id, 'mm_folder', array(
            'name' => $name,
        ) );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        $updated_term = get_term( $term_id, 'mm_folder' );

        return rest_ensure_response( array(
            'id'    => $updated_term->term_id,
            'name'  => $updated_term->name,
            'slug'  => $updated_term->slug,
            'count' => (int) $updated_term->count,
        ) );
    }

    /**
     * Delete folder term.
     */
    public function delete_item( $request ) {
        $term_id = absint( $request->get_param( 'id' ) );

        $term = get_term( $term_id, 'mm_folder' );
        if ( ! $term || is_wp_error( $term ) ) {
            return new WP_Error( 'folder_not_found', __( 'Folder not found.', 'media-maestro' ), array( 'status' => 404 ) );
        }

        $result = wp_delete_term( $term_id, 'mm_folder' );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return rest_ensure_response( array(
            'success' => true,
            'message' => __( 'Folder deleted successfully.', 'media-maestro' ),
        ) );
    }

    /**
     * Assign folder to attachments.
     */
    public function assign_items( $request ) {
        $attachment_ids = $request->get_param( 'attachment_ids' );
        $folder_id      = $request->get_param( 'folder_id' );

        $folder_val = null;
        if ( 'unassigned' !== $folder_id && '0' !== $folder_id && ! empty( $folder_id ) ) {
            $folder_val = absint( $folder_id );
        }

        foreach ( $attachment_ids as $id ) {
            $post = get_post( $id );
            if ( ! $post || 'attachment' !== $post->post_type ) {
                continue;
            }

            if ( empty( $folder_val ) ) {
                // Clear terms
                wp_set_object_terms( $id, array(), 'mm_folder' );
            } else {
                // Set term (replace old ones since attachments can only be in one folder at a time for this structure)
                wp_set_object_terms( $id, array( $folder_val ), 'mm_folder', false );
            }
        }

        // Return the updated folders counts
        return $this->get_items( $request );
    }
}
