<?php
namespace BalanceTesting\API;

use BalanceTesting\RoundLimits;
use BalanceTesting\SingletonTrait;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

class RoundRules {
    use SingletonTrait;

    public function init() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    public function register_routes() {
        $namespace = 'balance-testing/v1';
        $user_arg  = array(
            'user' => array(
                'required'          => true,
                'type'              => 'integer',
                'sanitize_callback' => 'absint',
            ),
        );

        register_rest_route(
            $namespace,
            '/round_rules',
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'get_round_rules' ),
                'permission_callback' => array( $this, 'permissions_check' ),
                'args'                => $user_arg,
            )
        );

        register_rest_route(
            $namespace,
            '/round_rules',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'update_round_rules' ),
                'permission_callback' => array( $this, 'permissions_check' ),
                'args'                => $user_arg,
            )
        );
    }

    public function permissions_check() {
        return current_user_can( 'manage_options' );
    }

    public function get_round_rules( $request ) {
        $user_id = absint( $request->get_param( 'user' ) );
        if ( ! $user_id || ! get_user_by( 'id', $user_id ) ) {
            return new WP_REST_Response(
                array( 'message' => __( 'User not found.', 'balance-testing' ) ),
                404
            );
        }

        return new WP_REST_Response(
            RoundLimits::instance()->get_user_round_rules_payload( $user_id ),
            200
        );
    }

    public function update_round_rules( $request ) {
        $user_id = absint( $request->get_param( 'user' ) );
        if ( ! $user_id || ! get_user_by( 'id', $user_id ) ) {
            return new WP_REST_Response(
                array( 'message' => __( 'User not found.', 'balance-testing' ) ),
                404
            );
        }

        $body   = $request->get_json_params();
        $rounds = array();
        if ( is_array( $body ) && isset( $body['rounds'] ) && is_array( $body['rounds'] ) ) {
            $rounds = $body['rounds'];
        }

        $result = RoundLimits::instance()->save_user_round_rule_overrides( $user_id, $rounds );
        if ( is_wp_error( $result ) ) {
            return new WP_REST_Response(
                array( 'message' => $result->get_error_message() ),
                (int) ( $result->get_error_data()['status'] ?? 400 )
            );
        }

        return new WP_REST_Response( $result, 200 );
    }
}
