<?php
namespace BalanceTesting;

use BalanceTesting\Mailer\MailFactory;
use BalanceTesting\Schedule\TestAccessMail;
use WP_Query;

/**
 * This plugin will help for utils functionalities.
 * 
 * @since 1.0
 */
defined('ABSPATH') || exit;
class Utils {
    use SingletonTrait;

    /**
     * This will return all user tests.
     * 
     * @since 1.0
     */
    public function get_user_test_record( $user_id ) {
        global $wpdb;
        $user_id = intval($user_id);
        $table = $wpdb->prefix . 'user_ratings';
        $query = $wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d AND rating IN (1,2,3,4,5,6)",
            $user_id
        );
        $tests = $wpdb->get_results($query);

        return $tests;
    }

    /**
     * Get rating records by round.
     * 
     * This function will return test_id and rating filter by round and user id.
     * 
     * @return array this will return all filtered rating array.
     */
    public function get_user_rating_record($user_id) {
        global $wpdb;
        $user_id = intval($user_id);
        $table = $wpdb->prefix . 'user_ratings';
        $query = $wpdb->prepare(
            "SELECT rating, test_id, round FROM $table WHERE user_id = %d AND rating IN (1,2,3,4,5,6) ORDER BY round ASC",
            $user_id
        );
        $rating_records = $wpdb->get_results($query);
        return $rating_records;
    }

    /**
     * This will return user taken test ids.
     * 
     * @return array
     */
    public function user_taken_test_ids( $user_id ) {
        global $wpdb;
        $user_id = intval($user_id);

        $sql = $this->prepare_sql_for_rated_test($user_id);
        $test_ids = $wpdb->get_col(
            $sql
        );
        return $test_ids;
    }

    /**
     * Get total number of test in current round by user id.
     * 
     * @since 1.0
     */
    public function get_user_test_by_round() {
        global $wpdb;
        $user_id = get_current_user_id();
        $table = $wpdb->prefix. 'user_ratings';
        $test_round = get_user_meta($user_id, 'test_round', true);
        $user_count = $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE user_id = $user_id AND round = $test_round" );
        return $user_count;
    }

    /**
     * Get total number of test by user_id.
     * 
     * @since 1.0
     */
    public function get_user_tests() {
        global $wpdb;
        $user_id = get_current_user_id();
        $table = $wpdb->prefix. 'user_ratings';
        $user_count = $wpdb->get_var( "SELECT COUNT(DISTINCT test_id) FROM $table WHERE user_id = $user_id" );
        return $user_count;
    }

    /**
     * This will return user taken test ids.
     * 
     * @return array
     */
    public function get_exclude_test_ids( $user_id ) {
        global $wpdb;
        $user_id = intval($user_id);
        $sql = $this->prepare_sql_for_exclude_tests($user_id);
        $test_ids = $wpdb->get_col(
            $sql
        );
        return $test_ids;
    }

    /**
     * This will return total test count.
     */
    public function get_test_count() {
        return wp_count_posts( 'test' )->publish;
    }

    public function get_user_question_answere( $user_id, $round = 1 ) {
        $user_id = intval($user_id);
        $args = array(
            'post_type' => 'user_questions',
            'posts_per_page' => 1,
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key' => 'user_id',
                    'value' => $user_id,
                    'compare' => '=',
                    'type'    => 'NUMERIC',
                ),
                array(
                    'key' => 'test_round',
                    'value' => $round,
                    'compare' => '=',
                    'type'    => 'NUMERIC',
                ),
            ),
        );
        $query = new WP_Query( $args );
        $post_id = 0;
        if ( $query->have_posts() ) {
            while ( $query->have_posts() ) {
                $query->the_post();
                $post_id = get_the_ID();
            }
        }
        if( empty( $post_id ) ) {
            return array();
        }
        wp_reset_postdata();
        $user_answers = get_post_meta( $post_id, 'user_info', true );
        return $user_answers;
    }

    /**
     * Published test IDs in admin catalog order (menu_order DESC, title DESC).
     *
     * @return int[]
     */
    public function get_catalog_test_ids(): array {
        $ids = get_posts(
            array(
                'post_type'      => 'test',
                'posts_per_page' => -1,
                'post_status'    => 'publish',
                'orderby'        => $this->get_general_test_pool_orderby(),
                'order'          => 'DESC',
                'fields'         => 'ids',
            )
        );

        return array_values( array_map( 'intval', (array) $ids ) );
    }

    /**
     * WP_Query orderby for the general pool (round 1 and post-priority fallback).
     */
    public function get_general_test_pool_orderby(): array {
        return array(
            'menu_order' => 'DESC',
            'title'      => 'DESC',
        );
    }

    /**
     * WP_Query args for the next test candidate.
     *
     * Priority pool uses post__in to preserve attempt order from get_priority_test_ids().
     */
    public function build_next_test_query_args( array $priority_tests, array $exclude_tests ): array {
        if ( ! empty( $priority_tests ) ) {
            return array(
                'post_type'        => 'test',
                'posts_per_page'   => 1,
                'post__in'         => $priority_tests,
                'post__not_in'     => $exclude_tests,
                'orderby'          => 'post__in',
                'suppress_filters' => false,
            );
        }

        return array(
            'post_type'        => 'test',
            'posts_per_page'   => 1,
            'post__not_in'     => $exclude_tests,
            'orderby'          => $this->get_general_test_pool_orderby(),
            'order'            => 'DESC',
            'suppress_filters' => false,
        );
    }

    /**
     * First test ID from a query-args array (simulates next-test selection).
     */
    public function get_first_test_id_from_query_args( array $args ): ?int {
        $args['posts_per_page'] = 1;
        $args['fields']         = 'ids';
        $ids                    = get_posts( $args );

        return ! empty( $ids ) ? (int) $ids[0] : null;
    }

    /**
     * Resolve the next unrated test query for the user in the current round.
     *
     * @return WP_Query|null Query with one post, empty query, or null when loop exhausts skips.
     */
    public function resolve_next_test_query( $user_id ): ?WP_Query {
        $user_id   = absint( $user_id );
        $round_int = absint( get_user_meta( $user_id, 'test_round', true ) );

        $exclude_tests  = array_values( array_map( 'intval', (array) $this->get_exclude_test_ids( $user_id ) ) );
        $priority_tests = array_values( array_map( 'intval', (array) $this->get_priority_test_ids( $user_id ) ) );
        if ( ! empty( $exclude_tests ) ) {
            $priority_tests = array_values( array_diff( $priority_tests, $exclude_tests ) );
        }

        $max_skips  = 20;
        $test_query = null;

        while ( $max_skips-- > 0 ) {
            $args  = $this->build_next_test_query_args( $priority_tests, $exclude_tests );
            $probe = new WP_Query( $args );

            if ( ! $probe->have_posts() ) {
                return $probe;
            }

            $probe->the_post();
            $candidate_id = (int) get_the_ID();
            wp_reset_postdata();

            if ( ! $this->has_user_rated_on_test( $user_id, $candidate_id, $round_int ) ) {
                $probe->rewind_posts();
                return $probe;
            }

            $exclude_tests[] = $candidate_id;
            $priority_tests  = array_values( array_diff( $priority_tests, array( $candidate_id ) ) );
        }

        return $test_query;
    }

    public function get_priority_test_ids( $user_id ) {
        global $wpdb;
        $test_round = get_user_meta($user_id, 'test_round', true);
        $table = $wpdb->prefix. 'user_ratings';
        $test_ids = array();
        if( 2 === absint($test_round) ) {
            // $sql = $wpdb->prepare(
            //     "SELECT test_id 
            //     FROM {$table} 
            //     WHERE user_id = %d 
            //     AND round = 1 
            //     AND rating IN (3, 4) 
            //     AND (re_used = 0 OR re_used IS NULL)",
            //     $user_id
            // );

            // spacetech start
            $sql = $wpdb->prepare(
                "SELECT test_id
                 FROM {$table}
                 WHERE user_id = %d
                   AND round = 1
                   AND rating IN (3, 4, 5)
                   AND (re_used = 0 OR re_used IS NULL)
                 GROUP BY test_id
                 ORDER BY MIN(attempt_id) ASC",
                $user_id
            );
            // spacetech end
            $test_ids = $wpdb->get_col(
                $sql
            );
        } elseif( 3 === absint($test_round) ) {
           $sql = $wpdb->prepare(
                "
                SELECT t1.test_id
                FROM {$table} t1
                WHERE t1.user_id = %d
                AND t1.rating IN (3, 4, 5)
                AND (
                    -- Case 1: round 1 has 3/4/5
                    t1.round = 1

                    OR

                    -- Case 2: round 1 does NOT have 3/4/5, but round 2 does
                    (
                        t1.round = 2
                        AND NOT EXISTS (
                            SELECT 1 FROM {$table} t2
                            WHERE t2.user_id = %d
                            AND t2.test_id = t1.test_id
                            AND t2.round = 1
                            AND t2.rating IN (3, 4, 5)
                        )
                    )
                )
                GROUP BY t1.test_id
                ORDER BY MIN(t1.attempt_id) ASC
                ",
                $user_id,
                $user_id
            );

            $results = $wpdb->get_col($sql);

            if (!empty($results)) {
                return $results;
            }

            // If round 1 has none → check round 2
            $sql = $wpdb->prepare(
                "SELECT test_id
                FROM {$table}
                WHERE user_id = %d
                AND round = 2
                AND rating IN (3, 4, 5)
                AND re_used IN (0,1)
                GROUP BY test_id
                ORDER BY MIN(attempt_id) ASC",
                $user_id
            );
            $test_ids = $wpdb->get_col(
                $sql
            );
        }
        return $test_ids;
    }

    public function get_username($user_id) {
        $user = get_userdata($user_id);

        if ($user) {
            $display_name = $user->display_name;
            $username     = $user->user_login;

            return $display_name . ' (' . $username . ')';
        }

        return $user_id;
    }


   public function get_user_progress_info($user_id, $max_round = 3) {
        // 1️⃣ Define rounds dynamically
        $rounds_data = [];
        for ($round = 1; $round <= $max_round + 1; $round++) { // +1 to include initial round
            $round_data = Utils::instance()->get_user_question_answere($user_id, $round);

            if (!empty($round_data)) {
                $rounds_data[$round] = $round_data;
            } else {
                $rounds_data[$round] = [];
            }
        }

        // 2️⃣ Separate initial and subsequent rounds
        $initial = $rounds_data[1] ?? [];
        $first_round = $rounds_data[2] ?? [];
        $second_round = $rounds_data[3] ?? [];
        $third_round = $rounds_data[4] ?? [];

        // 3️⃣ Prepare progress arrays for each field
        $progress_fields = [
            'oireiden_voimakkuus',
            'vaikutus_toimintakykyyn',
        ];

        $progress_arr = [];
        foreach ($progress_fields as $field) {
            $progress_arr[$field] = [
                $initial[$field] ?? 0,
                $first_round[$field] ?? 0,
                $second_round[$field] ?? 0,
                $third_round[$field] ?? 0,
            ];
        }

        // 4️⃣ Prepare exercise days/frequency arrays
        $exercise_days = [
            'round_1' => $first_round['exercise_days'] ?? '',
            'round_2' => $second_round['exercise_days'] ?? '',
            'round_3' => $third_round['exercise_days'] ?? '',
        ];

        $exercise_frequency = [
            'round_1' => $first_round['exercise_frequency'] ?? '',
            'round_2' => $second_round['exercise_frequency'] ?? '',
            'round_3' => $third_round['exercise_frequency'] ?? '',
        ];

        // 5️⃣ Return structured array
        return [
            'initial' => $initial,
            'first_round' => $first_round,
            'second_round' => $second_round,
            'third_round' => $third_round,
            'progress_values' => $progress_arr,
            'exercise' => [
                'days' => $exercise_days,
                'frequency' => $exercise_frequency,
            ],
        ];
    }



    private function prepare_sql_for_exclude_tests( $user_id ) {
        global $wpdb;
        $table = $wpdb->prefix. 'user_ratings';
        $test_round = get_user_meta($user_id, 'test_round', true);
        $sql = '';
        if( 1 === absint($test_round) ) {
            $sql = $wpdb->prepare( "SELECT test_id FROM {$table} WHERE user_id = %d", $user_id );
        } elseif( 2 === absint($test_round) ) {
            $sql = $wpdb->prepare(
                "SELECT test_id
                FROM {$table}
                WHERE user_id = %d
                AND (
                    (round = 1 AND rating IN (1, 2))
                    OR rating = 6
                    OR re_used = 1
                    OR round = 2
                )",
                $user_id
            );
        } elseif( 3 === absint($test_round) ) {
            $sql = $wpdb->prepare("
                SELECT test_id
                FROM {$table}
                WHERE user_id = %d
                GROUP BY test_id
                HAVING
                    SUM(CASE WHEN re_used = 2 THEN 1 ELSE 0 END) > 0
                    OR SUM(CASE WHEN round IN (1, 2) AND rating IN (1, 2) THEN 1 ELSE 0 END) > 0
                    OR SUM(CASE WHEN rating = 6 THEN 1 ELSE 0 END) > 0
                    OR SUM(CASE WHEN round = 3 THEN 1 ELSE 0 END) > 0
            ", $user_id);

        }
        return $sql;
    }


    private function prepare_sql_for_rated_test( $user_id ) {
        global $wpdb;
        $table = $wpdb->prefix. 'user_ratings';
        $test_round = get_user_meta($user_id, 'test_round', true);
        $sql = '';
        if( 1 === absint($test_round) ) {
            $sql = $wpdb->prepare( "SELECT test_id FROM {$table} WHERE user_id = %d", $user_id );
        } elseif( 2 === absint($test_round) ) {
            $sql = $wpdb->prepare(
                "SELECT test_id FROM {$table} WHERE user_id = %d AND round = 2",
                $user_id
            );
        } elseif( 3 === absint($test_round) ) {
           $sql = $wpdb->prepare(
                "SELECT test_id FROM {$table} WHERE user_id = %d AND round = 3",
                $user_id
            );
        }
        return $sql;
    }

    /**
     * Check if user already rated on specific post.
     * 
     * @since 1.0
     * @return boolean true|false
     */
    public function has_user_rated_on_test( $user_id, $test_id, $round ) {
        $user_id = empty($user_id) ? get_current_user_id(): absint($user_id);
        $test_id = intval($test_id);
        $round = intval($round);
        $rated_test_ids = $this->user_taken_test_ids( $user_id );
        return in_array( $test_id, $rated_test_ids );
    }

    public function calculate_total_progress(array $values) {
		$values = array_values(array_filter($values, 'is_numeric'));

		$total = 0;

		for ($i = 1; $i < count($values); $i++) {
			$total += ($values[$i] - $values[$i - 1]);
		}

		return $total;
	}

    public function get_round($user_id) {
        if(empty($user_id)) return;
        return absint(get_user_meta($user_id, 'test_round', true));
    }

    /**
     * Check if user is permitted to take another test in the current round.
     *
     * @return boolean
     */
    public function is_user_permitted_for_test( $user_id = '' ) {
        return RoundLimits::instance()->is_user_permitted_for_test( $user_id );
    }

    public function send_permitted_user_to_schedule_mail( $user_id ) {
        RoundLimits::instance()->send_permitted_user_to_schedule_mail( $user_id );
    }

    public function get_schedule_settings(): array {
        $defaults = [
            'round_2_invite_delay' => ['value' => 12, 'unit' => 'days'],
            'round_3_invite_delay' => ['value' => 12, 'unit' => 'days'],
            'round_4_invite_delay' => ['value' => 12, 'unit' => 'days'],
            'enable_auto_access'   => 0,
            'auto_access_delay'    => ['value' => 10, 'unit' => 'days'],
            'exercise_recommended_days' => 12,
        ];
        $settings = get_option('balance_testing_round_invite_schedule_settings', []);
        $settings = is_array($settings) ? $settings : [];
        $out       = wp_parse_args($settings, $defaults);

        foreach (['round_2_invite_delay', 'round_3_invite_delay', 'round_4_invite_delay', 'auto_access_delay'] as $delay_key) {
            $out[$delay_key] = wp_parse_args(
                isset($out[$delay_key]) && is_array($out[$delay_key]) ? $out[$delay_key] : [],
                $defaults[$delay_key]
            );
        }
    
        return $out;
    }

    public static function get_date_in_seconds($value, $unit) {
        $unit_map = [
            'seconds' => 1,
            'minutes' => MINUTE_IN_SECONDS,
            'hours'   => HOUR_IN_SECONDS,
            'days'    => DAY_IN_SECONDS,
            'weeks'   => WEEK_IN_SECONDS,
        ];

        $unit_seconds  = $unit_map[$unit] ?? DAY_IN_SECONDS;
        $delay_seconds = absint($value) * $unit_seconds;
        return time() + $delay_seconds;
    }

    /**
     * This will return valid ratings.
     * 
     * @param mixed $raing_ids this ratings will be include. like if (3,4), it will return ratings, which are rated 3 or 4
     * @param mixed $user_id
     * @return array
     */
    public function get_valid_rating( $included_ratings, $user_id = '' ) {
        global $wpdb;
        $user_id = !empty($user_id) ? intval($user_id): get_current_user_id();
        $placeholders = implode(',', array_fill(0, count($included_ratings), '%d'));
        $values = array_merge(array($user_id), $included_ratings);
        $table = $wpdb->prefix. 'user_ratings';
        $test_round = absint(get_user_meta($user_id, 'test_round', true));
        
       $query = $wpdb->prepare(
            "SELECT * FROM {$table} WHERE user_id = %d AND rating IN ($placeholders)  AND ROUND = $test_round",
            $values
        );
        
        return $wpdb->get_results($query);
    }

    public function get_user_progress( $user_id ) {
        if(empty($user_id)) return;
        $test_records = $this->get_user_test_record($user_id);
        $all_rounds = [];
        $tests_by_round = [];
        foreach( $test_records as $record ) {
            $test_id = intval($record->test_id);
            $round = intval($record->round);
            $rating = esc_html($record->rating);

            $tests_by_round[$test_id][$round] = $rating;

            if (!in_array($round, $all_rounds)) {
                $all_rounds[] = $round;
            }
        }
        ob_start();
        ?>
         <table class="bt-my-account-progress-table display-record">
            <thead>
                <tr>
                    <th>Testin nimi</th>
                    <?php foreach($all_rounds as $round): ?>
                        <th>Testauskierros <?php echo $round; ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach($tests_by_round as $test_id => $rounds_data): ?>
                    <tr>
                        <td><?php echo esc_html(get_the_title($test_id)); ?></td>
                        <?php foreach($all_rounds as $round): ?>
                            <td>
                                <?php 
                                echo isset($rounds_data[$round]) ? $rounds_data[$round] : '(N/A)'; 
                                ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
        return ob_get_clean();
    }

    /**
     * Map stored exercise_days value (1–5) to the form answer label.
     */
    public function get_exercise_days_label( $value ) {
        if ( $value === '' || $value === null ) {
            return '';
        }

        $labels = [
            '5' => __( '5 - kaikkina päivinä', 'balance-testing' ),
            '4' => __( '4 - Useimpina päivinä', 'balance-testing' ),
            '3' => __( '3 - Noin joka toinen päivä', 'balance-testing' ),
            '2' => __( '2 - Harvemmin', 'balance-testing' ),
            '1' => __( '1 - En tehnyt harjoituksia lainkaan', 'balance-testing' ),
        ];

        $key = (string) $value;

        return $labels[ $key ] ?? (string) $value;
    }

    /**
     * Map stored exercise_frequency value (1–4) to the form answer label.
     */
    public function get_exercise_frequency_label( $value ) {
        if ( $value === '' || $value === null ) {
            return '';
        }

        $labels = [
            '4' => __( '4 - Kolme kertaa päivässä', 'balance-testing' ),
            '3' => __( '3 - Kaksi kertaa päivässä', 'balance-testing' ),
            '2' => __( '2 - Kerran päivässä', 'balance-testing' ),
            '1' => __( '1 - Vaihteli paljon / vaikea sanoa', 'balance-testing' ),
        ];

        $key = (string) $value;

        return $labels[ $key ] ?? (string) $value;
    }
}