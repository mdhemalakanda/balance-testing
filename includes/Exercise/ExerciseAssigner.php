<?php
namespace BalanceTesting\Exercise;

use BalanceTesting\RoundLimits;
use BalanceTesting\SingletonTrait;

defined( 'ABSPATH' ) || exit;

class ExerciseAssigner {
    use SingletonTrait;

    public const SUGGESTED_META_PREFIX = 'round_';
    public const SUGGESTED_META_SUFFIX = '_exercises_suggested';

    public function suggested_meta_key( int $round ): string {
        return self::SUGGESTED_META_PREFIX . absint( $round ) . self::SUGGESTED_META_SUFFIX;
    }

    public function has_suggested_for_round( int $user_id, int $round ): bool {
        return (bool) get_user_meta( absint( $user_id ), $this->suggested_meta_key( $round ), true );
    }

    /**
     * Auto-suggest up to 5 exercises from tests rated 3 or 4 in the given round.
     *
     * @return int[] New assignment IDs.
     */
    public function suggest_for_round( int $user_id, int $round, bool $force = false ): array {
        $user_id = absint( $user_id );
        $round   = absint( $round );

        if ( ! $user_id || ! $round ) {
            return array();
        }

        if ( ! $force && $this->has_suggested_for_round( $user_id, $round ) ) {
            return array();
        }

        $ratings = $this->get_rated_tests_for_round( $user_id, $round );
        if ( empty( $ratings ) ) {
            if ( ! $force ) {
                update_user_meta( $user_id, $this->suggested_meta_key( $round ), 1 );
            }
            return array();
        }

        $repo      = ExerciseRepository::instance();
        $created   = array();
        $sort_base = $repo->get_next_sort_order( $user_id );
        $index     = 0;

        foreach ( $ratings as $rating_row ) {
            if ( $index >= ExerciseRepository::MAX_PER_ROUND ) {
                break;
            }

            if ( ! ExerciseRepository::instance()->can_add_to_round( $user_id, $round ) ) {
                break;
            }

            $test_id     = (int) $rating_row->test_id;
            $exercise_id = $repo->resolve_exercise_for_test( $test_id );

            if ( ! $exercise_id ) {
                error_log(
                    sprintf(
                        '[balance-testing] No exercise linked for test_id=%d user_id=%d round=%d',
                        $test_id,
                        $user_id,
                        $round
                    )
                );
                continue;
            }

            if ( $repo->assignment_exists( $user_id, $round, $exercise_id ) ) {
                continue;
            }

            $assignment_id = $repo->insert_assignment(
                array(
                    'user_id'        => $user_id,
                    'round'          => $round,
                    'exercise_id'    => $exercise_id,
                    'source_test_id' => $test_id,
                    'sort_order'     => $sort_base + ( $index * 10 ),
                    'status'         => ExerciseRepository::STATUS_SUGGESTED,
                    'is_manual'      => 0,
                    'assigned_at'    => current_time( 'mysql' ),
                )
            );

            if ( is_wp_error( $assignment_id ) ) {
                continue;
            }

            $created[] = (int) $assignment_id;
            ++$index;
        }

        update_user_meta( $user_id, $this->suggested_meta_key( $round ), 1 );

        if ( ! empty( $created ) ) {
            ExerciseAdminNotifier::instance()->notify_suggestions_created( $user_id, $round, count( $created ) );
        }

        return $created;
    }

    /**
     * Called when a round ends — suggest exercises for the completed round.
     */
    public function maybe_suggest_on_round_end( int $user_id ): void {
        $user_id    = absint( $user_id );
        $test_round = absint( get_user_meta( $user_id, 'test_round', true ) );

        if ( ! in_array( $test_round, RoundLimits::ROUNDS_WITH_TEST_LIMITS, true ) ) {
            return;
        }

        if ( RoundLimits::instance()->is_user_permitted_for_test( $user_id ) ) {
            return;
        }

        $this->suggest_for_round( $user_id, $test_round );
    }

    /**
     * @return object[]
     */
    private function get_rated_tests_for_round( int $user_id, int $round ): array {
        global $wpdb;

        $table      = $wpdb->prefix . 'user_ratings';
        $status_sql = RoundLimits::instance()->get_published_ratings_status_sql();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $sql = $wpdb->prepare(
            "SELECT test_id, MIN(rating) AS rating, MIN(attempt_id) AS attempt_id
             FROM {$table}
             WHERE user_id = %d
               AND round = %d
               AND rating IN (3, 4)
               {$status_sql}
             GROUP BY test_id
             ORDER BY MIN(attempt_id) ASC",
            absint( $user_id ),
            absint( $round )
        );

        return $wpdb->get_results( $sql );
    }
}
