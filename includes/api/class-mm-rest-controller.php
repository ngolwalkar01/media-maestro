<?php

/**
 * REST API Controller
 *
 * @package    Media_Maestro
 * @subpackage Media_Maestro/includes/api
 */

class Media_Maestro_REST_Controller extends WP_REST_Controller {

    /**
     * Namespace for the API.
     */
    protected $namespace = 'mm/v1';

    /**
     * Resource name.
     */
    protected $rest_base = 'jobs';

    /**
     * Register the routes.
     */
    public function register_routes() {
        register_rest_route( $this->namespace, '/' . $this->rest_base, array(
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'create_job' ),
                'permission_callback' => array( $this, 'create_job_permissions_check' ),
                'args'                => $this->get_create_job_args(),
            ),
        ) );

        register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_job' ),
                'permission_callback' => array( $this, 'get_job_permissions_check' ),
                'args'                => array(),
            ),
        ) );
    }

    /**
     * Permissions check for creating a job.
     */
    public function create_job_permissions_check( $request ) {
        return current_user_can( 'upload_files' );
    }

    /**
     * Permissions check for getting a job.
     */
    public function get_job_permissions_check( $request ) {
        // MVP: Allow if user can upload files (same as create)
        // Later: Check if user owns the job or is admin
        return current_user_can( 'upload_files' );
    }

    /**
     * Create a job.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error Response object.
     */
    public function create_job( $request ) {
        $attachment_id = $request->get_param( 'attachment_id' );
        $operation     = $request->get_param( 'operation' );
        $params        = $request->get_param( 'params' );

        $job_manager = new Media_Maestro_Job_Manager();
        $job_id      = $job_manager->create_job( $attachment_id, $operation, $params );

        if ( is_wp_error( $job_id ) ) {
            return $job_id;
        }

        return rest_ensure_response( array(
            'id' => $job_id,
            'status' => 'pending',
            'message' => 'Job started',
        ) );
    }

    /**
     * Get a job.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error Response object.
     */
    public function get_job( $request ) {
        $job_id = $request->get_param( 'id' );

        $job_manager = new Media_Maestro_Job_Manager();
        $job_data    = $job_manager->get_job( $job_id );

        if ( is_wp_error( $job_data ) ) {
            return $job_data;
        }

        return rest_ensure_response( $job_data );
    }

    /**
     * Get arguments for creating a job.
     */
    public function get_create_job_args() {
        return array(
            'attachment_id' => array(
                'description'       => 'Attachment ID to process',
                'type'              => 'integer',
                'required'          => true,
                'validate_callback' => function( $param, $request, $key ) {
                    return is_numeric( $param );
                }
            ),
            'operation' => array(
                'description'       => 'Operation to perform',
                'type'              => 'string',
                'required'          => true,
                'enum'              => array( 'remove_background', 'style_transfer', 'regenerate' ),
            ),
            'params' => array(
                'description'       => 'Additional parameters',
                'type'              => 'object',
                'required'          => false,
                'default'           => array(),
            ),
        );
    }
}
