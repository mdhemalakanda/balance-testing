<?php
namespace BalanceTesting\API;

use BalanceTesting\Menu\UserMenu;
use BalanceTesting\SingletonTrait;
use BalanceTesting\Utils;
use WP_Query;
use WP_REST_Response;
use WP_REST_Server;
/**
 * This file will create test users api.
 * 
 * @since 1.0
 */
defined('ABSPATH') || exit;

class TestUsers {
    use SingletonTrait;
    /**
     * TODO: Get all test users - Done
     * TODO: Get all test users by round - Done
     * TODO: Get users by search. - Done
     * TODO: Get single user info by id. - Done
     */

    public function init() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    // Get all test users.
    public function register_routes() {
        $version = '1';
        $namespace = 'balance-testing/v' . $version;
        register_rest_route($namespace,  '/users', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array( $this, 'get_test_users' ),
                'args' => array(
                    'round' => array(
                        'required' => false,
                        'type'     => 'integer',
                        'sanitize_callback' => 'absint',
                        'description' => 'Give Round number',
                    ),
                   's' => array(
                        'required' => false,
                        'type'     => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                        'description' => 'Search term for user questions',
                    ),
                    'user' => array(
                        'required' => false,
                        'type'     => 'integer',
                        'sanitize_callback' => 'absint',
                        'description' => 'Filter by specific user ID',
                    ),
                ),
                'permission_callback' => array( $this, 'admin_permissions_check' ),
            )
        ));
        register_rest_route($namespace,  '/user_progress', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array( $this, 'user_progress' ),
                'permission_callback' => array( $this, 'admin_permissions_check' ),
                'args' => array(
                'user' => array(
                    'required' => false,
                    'type'     => 'integer',
                    'sanitize_callback' => 'absint',
                    'description' => 'Filter by specific user ID',
                ),
                ),
            )
        ));
         register_rest_route($namespace,  '/user_progress', array(
            array(
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => array( $this, 'delete_test_user_data' ),
                'permission_callback' => array( $this, 'admin_permissions_check' ),
                'args' => array(
                    'user' => array(
                        'required' => false,
                        'type'     => 'integer',
                        'sanitize_callback' => 'absint',
                        'description' => 'Delete progress for specific user id',
                    ),
                    'delete_type' => array(
                        'required' => true,
                        'type'     => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                        'description' => 'Delete type',
                    ),
                ),
            )
        ));
    }

    // delete user progress data.
    public function delete_test_user_data($request) {
        $user_id = $request->get_param( 'user' );
        $delete_type = $request->get_param( 'delete_type' );
       
        UserMenu::instance()->delete_user_data($user_id, $delete_type);
        return rest_ensure_response([
            'success' => true,
            'message' => 'User data deleted successfully'
        ]);
    }

    // get all users.
    public function get_test_users( $request ) {
        $round   = $request->get_param( 'round' );
        $search  = $request->get_param( 's' );
        $requested_user_id = absint($request->get_param( 'user' ));
        $status = $request->get_param( 'status' );
        $test_user_args = array(
            'post_type'      => 'user_questions',
            'posts_per_page' => -1,
            'orderby'        => 'meta_value_num',
            'order'          => 'ASC',
        );
        if ( !empty($status) ) {
            $test_user_args['post_status'] = $status;
        }
        
        // dynamic meta query.
        $meta_query = array();

        if ( !empty($round) ) {
            $meta_query[] = array(
                'key'     => 'test_round',
                'value'   => $round,
                'compare' => '=',
            );
        }

        if ( !empty($requested_user_id) ) {
            $meta_query[] = array(
                'key'     => 'user_id',
                'value'   => $requested_user_id,
                'compare' => '=',
            );
        }

        if ( ! empty( $meta_query ) ) {
            $test_user_args['meta_query'] = $meta_query;
        }

        if ( !empty($search) ) {
            $test_user_args['s'] = $search;
        }
        
        $users = array();
        $test_user_query = new WP_Query($test_user_args);
        if( !empty($test_user_query) ) {
            while($test_user_query->have_posts()) {
                $test_user_query->the_post();
                $post_id = get_the_ID();
                $user_id = get_post_meta( $post_id, 'user_id', true );
                $user_id = absint($user_id);
                if ( empty($user_id) ) {
                    continue;
                }

                // Skip orphan records for deleted/non-existing WP users.
                $wp_user = get_user_by('id', $user_id);
                if ( ! $wp_user ) {
                    continue;
                }

                $round = get_post_meta( $post_id, 'test_round', true );
                $user_info = get_post_meta( $post_id, 'user_info', true );
                if( ! isset( $users[$user_id] ) ) {
                    $users[$user_id] = array(
                        'user_id' => $user_id,
                        'user_name' => Utils::instance()->get_username($user_id),
                        'questions' => array()
                    );
                }
                $users[$user_id]['questions'][] = array(
                    'round' => $round,
                    'user_info' => $user_info,
                );
            }
            wp_reset_query();
        }

        if ( !empty($requested_user_id) ) {
            $requested_user_id = (string) $requested_user_id;
            $single_user_payload = isset($users[$requested_user_id]) ? array($requested_user_id => $users[$requested_user_id]) : array();
            return new WP_REST_Response( $single_user_payload, 200 );
        }

        return new WP_REST_Response( $users, 200 );
    }

    public function user_progress( $request ) {
        $user_id = $request->get_param( 'user' );
        $round = Utils::instance()->get_round($user_id);
        $user_progress_info = Utils::instance()->get_user_progress_info($user_id, $round);
        return new WP_REST_Response( $user_progress_info, 200 );
    }



    public function admin_permissions_check( $request ) {
        return current_user_can( 'manage_options' );
    }

}