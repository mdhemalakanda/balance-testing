<?php
namespace BalanceTesting\API;
use BalanceTesting\SingletonTrait;
use BalanceTesting\Utils;
use WP_REST_Response;
use WP_REST_Server;
/**
 * This class will help to retrive rating data.
 * 
 * @since 1.0
 */
class Ratings {
    use SingletonTrait;

     public function init() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    public function register_routes() {
        $version = '1';
        $namespace = 'balance-testing/v' . $version;
          register_rest_route($namespace,  '/ratings', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array( $this, 'test_ratings' ),
                'round' => array(
                    'required' => true,
                    'type'     => 'integer',
                    'sanitize_callback' => 'absint',
                    'description' => 'Filter by specific round',
                ),
                'user' => array(
                    'required' => true,
                    'type'     => 'integer',
                    'sanitize_callback' => 'absint',
                    'description' => 'Filter by specific user',
                ),
            )
        ));
    }

    /**
     * Get ratings by round and user.
     */
    public function test_ratings( $request ) {
        $user_id   = $request->get_param( 'user' );
        if(empty($user_id)) {
            return new WP_REST_Response( __('Please enter round and user id', 'balance-testing'), 404 );
        }
        $ratings = Utils::instance()->get_user_rating_record($user_id);
        $rating_tests = array();

        if (!empty($ratings)) {
            foreach ($ratings as $record) {
                $rating_tests[$record->round][] = array(
                    'rating' => $record->rating,
                    'test_title' => get_the_title($record->test_id),
                );
            }
        }
        return new WP_REST_Response( $rating_tests, 200 );
    }
}