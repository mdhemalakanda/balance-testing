<?php
namespace BalanceTesting\API;

use BalanceTesting\Exercise\ExerciseAssigner;
use BalanceTesting\Exercise\ExerciseRepository;
use BalanceTesting\Exercise\ExerciseVisibility;
use BalanceTesting\SingletonTrait;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

class UserExercises {
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
            '/user_exercises',
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'get_user_exercises' ),
                'permission_callback' => array( $this, 'permissions_check' ),
                'args'                => $user_arg,
            )
        );

        register_rest_route(
            $namespace,
            '/user_exercises/search',
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'search_exercises' ),
                'permission_callback' => array( $this, 'permissions_check' ),
                'args'                => array(
                    'q' => array(
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                ),
            )
        );

        register_rest_route(
            $namespace,
            '/user_exercises/suggest',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'suggest_exercises' ),
                'permission_callback' => array( $this, 'permissions_check' ),
                'args'                => array_merge(
                    $user_arg,
                    array(
                        'round' => array(
                            'type'              => 'integer',
                            'sanitize_callback' => 'absint',
                        ),
                        'force' => array(
                            'type' => 'boolean',
                        ),
                    )
                ),
            )
        );

        register_rest_route(
            $namespace,
            '/user_exercises/approve',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'approve_exercises' ),
                'permission_callback' => array( $this, 'permissions_check' ),
                'args'                => $user_arg,
            )
        );

        register_rest_route(
            $namespace,
            '/user_exercises/add',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'add_exercise' ),
                'permission_callback' => array( $this, 'permissions_check' ),
                'args'                => $user_arg,
            )
        );

        register_rest_route(
            $namespace,
            '/user_exercises/reorder',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'reorder_exercises' ),
                'permission_callback' => array( $this, 'permissions_check' ),
                'args'                => $user_arg,
            )
        );

        register_rest_route(
            $namespace,
            '/user_exercises/visibility',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'set_visibility' ),
                'permission_callback' => array( $this, 'permissions_check' ),
                'args'                => $user_arg,
            )
        );

        register_rest_route(
            $namespace,
            '/user_exercises/(?P<assignment_id>\d+)',
            array(
                'methods'             => 'DELETE',
                'callback'            => array( $this, 'delete_exercise' ),
                'permission_callback' => array( $this, 'permissions_check' ),
                'args'                => array(
                    'assignment_id' => array(
                        'required'          => true,
                        'type'              => 'integer',
                        'sanitize_callback' => 'absint',
                    ),
                ),
            )
        );
    }

    public function permissions_check() {
        return current_user_can( 'manage_options' );
    }

    private function validate_user( int $user_id ) {
        if ( ! $user_id || ! get_user_by( 'id', $user_id ) ) {
            return new WP_REST_Response(
                array( 'message' => __( 'User not found.', 'balance-testing' ) ),
                404
            );
        }
        return null;
    }

    private function build_response( int $user_id ): array {
        $repo = ExerciseRepository::instance();
        return array(
            'assignments'      => $repo->format_assignments_payload( $user_id ),
            'visibility'       => ExerciseVisibility::instance()->get_visibility_payload( $user_id ),
            'approved_count'   => $repo->count_displayable_assignments( $user_id ),
            'max_per_round'    => ExerciseRepository::MAX_PER_ROUND,
        );
    }

    public function get_user_exercises( WP_REST_Request $request ) {
        $user_id = absint( $request->get_param( 'user' ) );
        $error   = $this->validate_user( $user_id );
        if ( $error ) {
            return $error;
        }

        return new WP_REST_Response( $this->build_response( $user_id ), 200 );
    }

    public function search_exercises( WP_REST_Request $request ) {
        $q       = (string) $request->get_param( 'q' );
        $results = ExerciseRepository::instance()->search_published_exercises( $q, 30 );
        return new WP_REST_Response( array( 'exercises' => $results ), 200 );
    }

    public function suggest_exercises( WP_REST_Request $request ) {
        $user_id = absint( $request->get_param( 'user' ) );
        $error   = $this->validate_user( $user_id );
        if ( $error ) {
            return $error;
        }

        $body  = $request->get_json_params();
        $round = isset( $body['round'] ) ? absint( $body['round'] ) : absint( get_user_meta( $user_id, 'test_round', true ) );
        $force = ! empty( $body['force'] );

        if ( ! $round ) {
            $round = 1;
        }

        $created = ExerciseAssigner::instance()->suggest_for_round( $user_id, $round, $force );

        return new WP_REST_Response(
            array_merge(
                $this->build_response( $user_id ),
                array( 'created_assignment_ids' => $created )
            ),
            200
        );
    }

    public function approve_exercises( WP_REST_Request $request ) {
        $user_id = absint( $request->get_param( 'user' ) );
        $error   = $this->validate_user( $user_id );
        if ( $error ) {
            return $error;
        }

        $body           = $request->get_json_params();
        $assignment_ids = array();
        if ( is_array( $body ) && ! empty( $body['assignment_ids'] ) && is_array( $body['assignment_ids'] ) ) {
            $assignment_ids = array_map( 'absint', $body['assignment_ids'] );
        }

        $approve_all = empty( $assignment_ids ) || ! empty( $body['approve_all'] );
        if ( $approve_all ) {
            ExerciseRepository::instance()->approve_assignments( $user_id );
        } else {
            $repo = ExerciseRepository::instance();
            $now  = current_time( 'mysql' );
            foreach ( $assignment_ids as $assignment_id ) {
                $row = $repo->get_assignment( $assignment_id );
                if ( $row && (int) $row->user_id === $user_id && ExerciseRepository::STATUS_SUGGESTED === $row->status ) {
                    $repo->update_assignment(
                        $assignment_id,
                        array(
                            'status'      => ExerciseRepository::STATUS_APPROVED,
                            'approved_at' => $now,
                        )
                    );
                }
            }
        }

        $this->promote_approved_if_user_display_active( $user_id );

        return new WP_REST_Response( $this->build_response( $user_id ), 200 );
    }

    public function add_exercise( WP_REST_Request $request ) {
        $user_id = absint( $request->get_param( 'user' ) );
        $error   = $this->validate_user( $user_id );
        if ( $error ) {
            return $error;
        }

        $body        = $request->get_json_params();
        $exercise_id = isset( $body['exercise_id'] ) ? absint( $body['exercise_id'] ) : 0;
        $round       = isset( $body['round'] ) ? absint( $body['round'] ) : absint( get_user_meta( $user_id, 'test_round', true ) );

        if ( ! $exercise_id || 'excercise' !== get_post_type( $exercise_id ) ) {
            return new WP_REST_Response(
                array( 'message' => __( 'Invalid exercise.', 'balance-testing' ) ),
                400
            );
        }

        if ( ! $round ) {
            $round = 1;
        }

        $now    = current_time( 'mysql' );
        $result = ExerciseRepository::instance()->insert_assignment(
            array(
                'user_id'     => $user_id,
                'round'       => $round,
                'exercise_id' => $exercise_id,
                'status'      => ExerciseRepository::STATUS_APPROVED,
                'is_manual'   => 1,
                'approved_at' => $now,
            )
        );

        if ( is_wp_error( $result ) ) {
            return new WP_REST_Response(
                array( 'message' => $result->get_error_message() ),
                400
            );
        }

        $this->promote_approved_if_user_display_active( $user_id );

        return new WP_REST_Response( $this->build_response( $user_id ), 200 );
    }

    public function reorder_exercises( WP_REST_Request $request ) {
        $user_id = absint( $request->get_param( 'user' ) );
        $error   = $this->validate_user( $user_id );
        if ( $error ) {
            return $error;
        }

        $body = $request->get_json_params();
        $items = is_array( $body ) && isset( $body['items'] ) && is_array( $body['items'] ) ? $body['items'] : array();

        $repo = ExerciseRepository::instance();
        foreach ( $items as $item ) {
            if ( empty( $item['assignment_id'] ) ) {
                continue;
            }
            $assignment_id = absint( $item['assignment_id'] );
            $row           = $repo->get_assignment( $assignment_id );
            if ( ! $row || (int) $row->user_id !== $user_id ) {
                continue;
            }
            $repo->update_assignment(
                $assignment_id,
                array( 'sort_order' => isset( $item['sort_order'] ) ? (int) $item['sort_order'] : 0 )
            );
        }

        return new WP_REST_Response( $this->build_response( $user_id ), 200 );
    }

    public function set_visibility( WP_REST_Request $request ) {
        $user_id = absint( $request->get_param( 'user' ) );
        $error   = $this->validate_user( $user_id );
        if ( $error ) {
            return $error;
        }

        $body    = $request->get_json_params();
        $visible = ! empty( $body['visible'] );

        $visibility = ExerciseVisibility::instance()->set_visible( $user_id, $visible );

        if ( is_wp_error( $visibility ) ) {
            return new WP_REST_Response(
                array( 'message' => $visibility->get_error_message() ),
                400
            );
        }

        return new WP_REST_Response(
            array_merge( $this->build_response( $user_id ), array( 'visibility' => $visibility ) ),
            200
        );
    }

    public function delete_exercise( WP_REST_Request $request ) {
        $assignment_id = absint( $request->get_param( 'assignment_id' ) );
        $row           = ExerciseRepository::instance()->get_assignment( $assignment_id );

        if ( ! $row ) {
            return new WP_REST_Response(
                array( 'message' => __( 'Assignment not found.', 'balance-testing' ) ),
                404
            );
        }

        ExerciseRepository::instance()->delete_assignment( $assignment_id );

        return new WP_REST_Response( $this->build_response( (int) $row->user_id ), 200 );
    }

    private function promote_approved_if_user_display_active( int $user_id ): void {
        $visibility = ExerciseVisibility::instance();
        if ( ! $visibility->is_visible_for_user( $user_id ) ) {
            return;
        }

        $visible_at = $visibility->get_visible_at( $user_id ) ?: current_time( 'mysql' );
        $visible_ts = (int) mysql2date( 'U', $visible_at );
        $days       = $visibility->get_recommended_days();
        $deadline_at = wp_date(
            'Y-m-d H:i:s',
            $visible_ts + ( $days * DAY_IN_SECONDS )
        );

        ExerciseRepository::instance()->mark_approved_as_visible( $user_id, $visible_at, $deadline_at );
    }
}
