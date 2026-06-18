<?php
namespace BalanceTesting;

use BalanceTesting\Exercise\ExerciseAssigner;
use BalanceTesting\Schedule\TestAccessMail;

defined( 'ABSPATH' ) || exit;

/**
 * Per-user, per-round test limits (max tests + 3/4 rating threshold).
 * Pilot defaults: rounds 1–2 max 43 tests, round 3 max 42; threshold 6 for all rounds.
 * Rating carry-over rules live in Utils.php.
 */
class RoundLimits {
    use SingletonTrait;

    public const USER_ROUND_RULES_META_KEY = 'balance_testing_user_round_rules';
    public const DEFAULT_RATING_THRESHOLD  = 6;
    public const DEFAULT_MAX_TESTS_BY_ROUND = array(
        1 => 43,
        2 => 43,
        3 => 42,
    );
    public const ROUNDS_WITH_TEST_LIMITS   = array( 1, 2, 3 );

    /** @var bool|null */
    private static $has_status_column = null;

    public function ratings_table_has_status_column(): bool {
        if ( null !== self::$has_status_column ) {
            return self::$has_status_column;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'user_ratings';
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $columns = $wpdb->get_results( "SHOW COLUMNS FROM `{$table}` LIKE 'status'" );
        self::$has_status_column = ! empty( $columns );

        return self::$has_status_column;
    }

    /**
     * SQL fragment for published ratings only (empty when status column is missing).
     */
    public function get_published_ratings_status_sql(): string {
        if ( ! $this->ratings_table_has_status_column() ) {
            return '';
        }

        return " AND (status = 'publish' OR status IS NULL OR status = '')";
    }

    public function get_user_round_rule_overrides( $user_id ) {
        $user_id = absint( $user_id );
        if ( ! $user_id ) {
            return array();
        }

        $stored = get_user_meta( $user_id, self::USER_ROUND_RULES_META_KEY, true );
        if ( ! is_array( $stored ) ) {
            return array();
        }

        $rules = array();
        foreach ( self::ROUNDS_WITH_TEST_LIMITS as $round ) {
            if ( empty( $stored[ $round ] ) || ! is_array( $stored[ $round ] ) ) {
                continue;
            }

            $entry = array();
            if ( isset( $stored[ $round ]['max_tests'] ) && '' !== $stored[ $round ]['max_tests'] ) {
                $entry['max_tests'] = max( 1, absint( $stored[ $round ]['max_tests'] ) );
            }
            if ( isset( $stored[ $round ]['rating_threshold'] ) && '' !== $stored[ $round ]['rating_threshold'] ) {
                $entry['rating_threshold'] = max( 1, absint( $stored[ $round ]['rating_threshold'] ) );
            }

            if ( ! empty( $entry ) ) {
                $rules[ $round ] = $entry;
            }
        }

        return $rules;
    }

    public function get_default_max_tests_for_round( $round = 0 ) {
        $round = absint( $round );
        if ( isset( self::DEFAULT_MAX_TESTS_BY_ROUND[ $round ] ) ) {
            return (int) self::DEFAULT_MAX_TESTS_BY_ROUND[ $round ];
        }

        return (int) self::DEFAULT_MAX_TESTS_BY_ROUND[1];
    }

    public function get_default_rating_threshold_for_round( $round = 0 ) {
        unset( $round );
        return self::DEFAULT_RATING_THRESHOLD;
    }

    public function get_user_max_tests_for_round( $user_id, $round ) {
        $round   = absint( $round );
        $user_id = absint( $user_id );
        $rules   = $this->get_user_round_rule_overrides( $user_id );

        if ( ! empty( $rules[ $round ]['max_tests'] ) ) {
            return (int) $rules[ $round ]['max_tests'];
        }

        return $this->get_default_max_tests_for_round( $round );
    }

    public function get_user_rating_threshold_for_round( $user_id, $round ) {
        $round   = absint( $round );
        $user_id = absint( $user_id );
        $rules   = $this->get_user_round_rule_overrides( $user_id );

        if ( ! empty( $rules[ $round ]['rating_threshold'] ) ) {
            return (int) $rules[ $round ]['rating_threshold'];
        }

        return $this->get_default_rating_threshold_for_round( $round );
    }

    public function count_completed_tests_for_round( $user_id, $round ) {
        global $wpdb;

        $user_id = absint( $user_id );
        $round   = absint( $round );
        if ( ! $user_id || ! $round ) {
            return 0;
        }

        $table          = $wpdb->prefix . 'user_ratings';
        $status_clause  = $this->get_published_ratings_status_sql();

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND round = %d{$status_clause}",
                $user_id,
                $round
            )
        );
    }

    public function count_valid_ratings_for_round( $user_id, $round ) {
        global $wpdb;

        $user_id = absint( $user_id );
        $round   = absint( $round );
        if ( ! $user_id || ! $round ) {
            return 0;
        }

        $table         = $wpdb->prefix . 'user_ratings';
        $status_clause = $this->get_published_ratings_status_sql();

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND round = %d AND rating IN (3, 4){$status_clause}",
                $user_id,
                $round
            )
        );
    }

    public function get_user_round_rules_payload( $user_id ) {
        $user_id = absint( $user_id );
        $user    = get_user_by( 'id', $user_id );

        $current_round = absint( get_user_meta( $user_id, 'test_round', true ) );
        $overrides     = $this->get_user_round_rule_overrides( $user_id );
        $rounds        = array();

        foreach ( self::ROUNDS_WITH_TEST_LIMITS as $round ) {
            $rounds[ $round ] = array(
                'max_tests' => array(
                    'override'  => $overrides[ $round ]['max_tests'] ?? null,
                    'effective' => $this->get_user_max_tests_for_round( $user_id, $round ),
                    'default'   => $this->get_default_max_tests_for_round( $round ),
                ),
                'rating_threshold' => array(
                    'override'  => $overrides[ $round ]['rating_threshold'] ?? null,
                    'effective' => $this->get_user_rating_threshold_for_round( $user_id, $round ),
                    'default'   => $this->get_default_rating_threshold_for_round( $round ),
                ),
                'tests_completed'   => $this->count_completed_tests_for_round( $user_id, $round ),
                'valid_ratings_3_4' => $this->count_valid_ratings_for_round( $user_id, $round ),
            );
        }

        $display_name = $user ? $user->user_login : '';

        return array(
            'user_id'       => $user_id,
            'user_name'     => $display_name,
            'user_login'    => $display_name,
            'current_round' => $current_round,
            'rounds'        => $rounds,
        );
    }

    /**
     * @param array $rounds_input Keys 1–3 with max_tests / rating_threshold (empty = use default).
     * @return array|\WP_Error
     */
    public function save_user_round_rule_overrides( $user_id, array $rounds_input ) {
        $user_id = absint( $user_id );
        if ( ! $user_id ) {
            return new \WP_Error( 'invalid_user', __( 'Invalid user.', 'balance-testing' ), array( 'status' => 400 ) );
        }

        $stored = array();
        foreach ( self::ROUNDS_WITH_TEST_LIMITS as $round ) {
            if ( empty( $rounds_input[ $round ] ) || ! is_array( $rounds_input[ $round ] ) ) {
                continue;
            }

            $entry = array();
            if ( array_key_exists( 'max_tests', $rounds_input[ $round ] ) && '' !== $rounds_input[ $round ]['max_tests'] && null !== $rounds_input[ $round ]['max_tests'] ) {
                $entry['max_tests'] = max( 1, absint( $rounds_input[ $round ]['max_tests'] ) );
            }
            if ( array_key_exists( 'rating_threshold', $rounds_input[ $round ] ) && '' !== $rounds_input[ $round ]['rating_threshold'] && null !== $rounds_input[ $round ]['rating_threshold'] ) {
                $entry['rating_threshold'] = max( 1, absint( $rounds_input[ $round ]['rating_threshold'] ) );
            }

            if ( ! empty( $entry ) ) {
                $stored[ $round ] = $entry;
            }
        }

        if ( empty( $stored ) ) {
            delete_user_meta( $user_id, self::USER_ROUND_RULES_META_KEY );
        } else {
            update_user_meta( $user_id, self::USER_ROUND_RULES_META_KEY, $stored );
        }

        return $this->get_user_round_rules_payload( $user_id );
    }

    public function has_user_reached_max_tests_for_round( $user_id, $round = 0 ) {
        $user_id = absint( $user_id );
        $round   = $round ? absint( $round ) : absint( get_user_meta( $user_id, 'test_round', true ) );

        if ( ! $user_id || ! $round ) {
            return false;
        }

        $max_tests = $this->get_user_max_tests_for_round( $user_id, $round );

        return $this->count_completed_tests_for_round( $user_id, $round ) >= $max_tests;
    }

    public function is_user_permitted_for_test( $user_id = '' ) {
        $user_id = empty( $user_id ) ? get_current_user_id() : absint( $user_id );
        $test_round = absint( get_user_meta( $user_id, 'test_round', true ) );

        if ( ! in_array( $test_round, self::ROUNDS_WITH_TEST_LIMITS, true ) ) {
            return true;
        }

        if ( $this->has_user_reached_max_tests_for_round( $user_id, $test_round ) ) {
            return false;
        }

        $valid_ratings      = Utils::instance()->get_valid_rating( array( 3, 4 ), $user_id );
        $valid_rating_count = count( $valid_ratings );
        $rating_threshold   = $this->get_user_rating_threshold_for_round( $user_id, $test_round );

        if ( $valid_rating_count >= $rating_threshold ) {
            if ( $test_round >= 2 ) {
                update_user_meta( $user_id, 'disable_progress_questions', true );
            }
            return false;
        }

        return true;
    }

    public function send_permitted_user_to_schedule_mail( $user_id ) {
        $user_id = empty( $user_id ) ? get_current_user_id() : absint( $user_id );
        $user    = get_user( $user_id );
        $email   = $user ? $user->user_email : '';

        $test_round = absint( get_user_meta( $user_id, 'test_round', true ) );
        if ( ! in_array( $test_round, self::ROUNDS_WITH_TEST_LIMITS, true ) ) {
            return;
        }

        $valid_rating_count = count( Utils::instance()->get_valid_rating( array( 3, 4 ), $user_id ) );
        $rating_threshold   = $this->get_user_rating_threshold_for_round( $user_id, $test_round );

        if ( $valid_rating_count < $rating_threshold ) {
            $low_rating_mail_meta_key = sprintf( 'round_%d_low_rating_admin_mail_sent', $test_round );
            $has_low_rating_mail_send = get_user_meta( $user_id, $low_rating_mail_meta_key, true );
            if ( ! $has_low_rating_mail_send ) {
                TestAccessMail::instance()->send_low_rating_round_mail_to_administrator(
                    $user_id,
                    $test_round,
                    $valid_rating_count,
                    $rating_threshold
                );
                update_user_meta( $user_id, $low_rating_mail_meta_key, true );
            }
        }

        // Round ended — always schedule the next-round invite and auto-access (if enabled),
        // even when the user is below the 3/4 threshold. Exercises may still be suggested
        // from available 3/4 ratings; the threshold only stops further tests in-round.
        if ( 1 === $test_round ) {
            $has_email_send = get_user_meta( $user_id, 'round_2_mail_scheduled', true );
            if ( ! $has_email_send ) {
                TestAccessMail::instance()->send_schedule_test_two_access( $user_id, $email, __( 'Aika seurata edistymistä ja tehdä toinen testikierros!', 'balance-testing' ) );
                update_user_meta( $user_id, 'round_2_mail_scheduled', true );
            }
        } elseif ( 2 === $test_round ) {
            $has_email_send = get_user_meta( $user_id, 'round_3_mail_scheduled', true );
            if ( ! $has_email_send ) {
                TestAccessMail::instance()->send_schedule_test_three_access( $user_id, $email, __( 'Aika seurata edistymistä ja tehdä kolmas testikierros! ', 'balance-testing' ) );
                update_user_meta( $user_id, 'round_3_mail_scheduled', true );
            }
        } elseif ( 3 === $test_round ) {
            $has_email_send = get_user_meta( $user_id, 'round_4_mail_scheduled', true );
            if ( ! $has_email_send ) {
                TestAccessMail::instance()->send_schedule_test_four_access( $user_id, $email, __( 'Aika seurata edistymistä vielä viimeisen. ', 'balance-testing' ) );
                update_user_meta( $user_id, 'round_4_mail_scheduled', true );
            }
        }

        ExerciseAssigner::instance()->maybe_suggest_on_round_end( $user_id );
    }
}
