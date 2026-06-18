<?php
namespace BalanceTesting\Exercise;

use BalanceTesting\SingletonTrait;

defined( 'ABSPATH' ) || exit;

class ExerciseRepository {
    use SingletonTrait;

    public const STATUS_SUGGESTED = 'suggested';
    public const STATUS_APPROVED  = 'approved';
    public const STATUS_VISIBLE   = 'visible';
    public const MAX_PER_ROUND    = 5;

    public function table_name(): string {
        global $wpdb;
        return $wpdb->prefix . 'user_exercise_assignments';
    }

    /**
     * Resolve excercise post for a test (copy link, then identifier match).
     */
    public function resolve_exercise_for_test( int $test_id ): int {
        if ( $test_id <= 0 ) {
            return 0;
        }

        $linked = get_posts(
            array(
                'post_type'      => 'excercise',
                'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
                'posts_per_page' => 1,
                'fields'         => 'ids',
                'meta_query'     => array(
                    array(
                        'key'   => '_bt_copied_from_test_id',
                        'value' => $test_id,
                    ),
                ),
            )
        );

        if ( ! empty( $linked[0] ) ) {
            return (int) $linked[0];
        }

        $identifier = ExerciseIdentifier::get( $test_id );
        if ( '' === $identifier ) {
            return 0;
        }

        $by_identifier = get_posts(
            array(
                'post_type'      => 'excercise',
                'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
                'posts_per_page' => 1,
                'fields'         => 'ids',
                'meta_query'     => array(
                    array(
                        'key'   => ExerciseIdentifier::META_KEY,
                        'value' => $identifier,
                    ),
                ),
            )
        );

        return ! empty( $by_identifier[0] ) ? (int) $by_identifier[0] : 0;
    }

    public function get_assignments_for_user( int $user_id, ?string $status = null, ?int $round = null ): array {
        global $wpdb;

        $user_id = absint( $user_id );
        if ( ! $user_id ) {
            return array();
        }

        $table  = $this->table_name();
        $where  = array( 'user_id = %d' );
        $params = array( $user_id );

        if ( null !== $status ) {
            $where[]  = 'status = %s';
            $params[] = $status;
        }

        if ( null !== $round ) {
            $where[]  = 'round = %d';
            $params[] = absint( $round );
        }

        $sql = "SELECT * FROM {$table} WHERE " . implode( ' AND ', $where ) . ' ORDER BY sort_order ASC, assignment_id ASC';

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        return $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
    }

    public function get_visible_assignments( int $user_id ): array {
        return $this->get_assignments_for_user( $user_id, self::STATUS_VISIBLE );
    }

    public function get_assignment( int $assignment_id ): ?object {
        global $wpdb;

        $assignment_id = absint( $assignment_id );
        if ( ! $assignment_id ) {
            return null;
        }

        $table = $this->table_name();
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE assignment_id = %d", $assignment_id ) );

        return $row ?: null;
    }

    public function count_assignments_for_round( int $user_id, int $round ): int {
        global $wpdb;

        $table = $this->table_name();
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND round = %d",
                absint( $user_id ),
                absint( $round )
            )
        );
    }

    public function count_approved_assignments( int $user_id ): int {
        global $wpdb;

        $table = $this->table_name();
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND status = %s",
                absint( $user_id ),
                self::STATUS_APPROVED
            )
        );
    }

    /**
     * Approved or already-promoted visible rows — used before toggling display on.
     */
    public function count_displayable_assignments( int $user_id ): int {
        global $wpdb;

        $table = $this->table_name();
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND status IN (%s, %s)",
                absint( $user_id ),
                self::STATUS_APPROVED,
                self::STATUS_VISIBLE
            )
        );
    }

    public function can_add_to_round( int $user_id, int $round ): bool {
        return $this->count_assignments_for_round( $user_id, $round ) < self::MAX_PER_ROUND;
    }

    public function assignment_exists( int $user_id, int $round, int $exercise_id ): bool {
        global $wpdb;

        $table = $this->table_name();
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $found = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT assignment_id FROM {$table} WHERE user_id = %d AND round = %d AND exercise_id = %d LIMIT 1",
                absint( $user_id ),
                absint( $round ),
                absint( $exercise_id )
            )
        );

        return ! empty( $found );
    }

    public function get_next_sort_order( int $user_id ): int {
        global $wpdb;

        $table = $this->table_name();
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $max = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT MAX(sort_order) FROM {$table} WHERE user_id = %d",
                absint( $user_id )
            )
        );

        return ( (int) $max ) + 10;
    }

    public function insert_assignment( array $data ) {
        global $wpdb;

        $defaults = array(
            'user_id'        => 0,
            'round'          => 1,
            'exercise_id'    => 0,
            'source_test_id' => 0,
            'sort_order'     => 0,
            'status'         => self::STATUS_SUGGESTED,
            'is_manual'      => 0,
            'assigned_at'    => current_time( 'mysql' ),
            'approved_at'    => null,
            'visible_at'     => null,
            'deadline_at'    => null,
        );

        $row = wp_parse_args( $data, $defaults );

        if ( $this->assignment_exists( (int) $row['user_id'], (int) $row['round'], (int) $row['exercise_id'] ) ) {
            return new \WP_Error( 'bt_duplicate_assignment', __( 'Exercise already assigned for this round.', 'balance-testing' ) );
        }

        if ( ! $this->can_add_to_round( (int) $row['user_id'], (int) $row['round'] ) ) {
            return new \WP_Error(
                'bt_max_exercises',
                sprintf(
                    /* translators: %d: maximum exercises per round */
                    __( 'Maximum %d exercises per round.', 'balance-testing' ),
                    self::MAX_PER_ROUND
                )
            );
        }

        if ( 0 === (int) $row['sort_order'] ) {
            $row['sort_order'] = $this->get_next_sort_order( (int) $row['user_id'] );
        }

        $inserted = $wpdb->insert(
            $this->table_name(),
            array(
                'user_id'        => absint( $row['user_id'] ),
                'round'          => absint( $row['round'] ),
                'exercise_id'    => absint( $row['exercise_id'] ),
                'source_test_id' => absint( $row['source_test_id'] ),
                'sort_order'     => (int) $row['sort_order'],
                'status'         => sanitize_key( $row['status'] ),
                'is_manual'      => ! empty( $row['is_manual'] ) ? 1 : 0,
                'assigned_at'    => $row['assigned_at'],
                'approved_at'    => $row['approved_at'],
                'visible_at'     => $row['visible_at'],
                'deadline_at'    => $row['deadline_at'],
            ),
            array( '%d', '%d', '%d', '%d', '%d', '%s', '%d', '%s', '%s', '%s', '%s' )
        );

        if ( false === $inserted ) {
            return new \WP_Error( 'bt_insert_failed', __( 'Could not create exercise assignment.', 'balance-testing' ) );
        }

        return (int) $wpdb->insert_id;
    }

    public function update_assignment( int $assignment_id, array $data ): bool {
        global $wpdb;

        $assignment_id = absint( $assignment_id );
        if ( ! $assignment_id ) {
            return false;
        }

        $allowed = array(
            'sort_order', 'status', 'approved_at', 'visible_at', 'deadline_at', 'source_test_id',
        );
        $update  = array();
        $formats = array();

        foreach ( $allowed as $key ) {
            if ( ! array_key_exists( $key, $data ) ) {
                continue;
            }
            $update[ $key ] = $data[ $key ];
            $formats[]      = in_array( $key, array( 'sort_order', 'source_test_id' ), true ) ? '%d' : '%s';
        }

        if ( empty( $update ) ) {
            return false;
        }

        return false !== $wpdb->update(
            $this->table_name(),
            $update,
            array( 'assignment_id' => $assignment_id ),
            $formats,
            array( '%d' )
        );
    }

    public function delete_assignment( int $assignment_id ): bool {
        global $wpdb;

        return false !== $wpdb->delete(
            $this->table_name(),
            array( 'assignment_id' => absint( $assignment_id ) ),
            array( '%d' )
        );
    }

    public function approve_assignments( int $user_id, array $assignment_ids = array() ): int {
        $user_id = absint( $user_id );
        $now     = current_time( 'mysql' );
        $count   = 0;

        $rows = $this->get_assignments_for_user( $user_id, self::STATUS_SUGGESTED );
        foreach ( $rows as $row ) {
            if ( ! empty( $assignment_ids ) && ! in_array( (int) $row->assignment_id, $assignment_ids, true ) ) {
                continue;
            }
            if ( $this->update_assignment(
                (int) $row->assignment_id,
                array(
                    'status'      => self::STATUS_APPROVED,
                    'approved_at' => $now,
                )
            ) ) {
                ++$count;
            }
        }

        return $count;
    }

    public function mark_approved_as_visible( int $user_id, string $visible_at, string $deadline_at ): int {
        $user_id = absint( $user_id );
        $count   = 0;
        $rows    = $this->get_assignments_for_user( $user_id, self::STATUS_APPROVED );

        foreach ( $rows as $row ) {
            if ( $this->update_assignment(
                (int) $row->assignment_id,
                array(
                    'status'      => self::STATUS_VISIBLE,
                    'visible_at'  => $visible_at,
                    'deadline_at' => $deadline_at,
                )
            ) ) {
                ++$count;
            }
        }

        return $count;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function format_assignments_payload( int $user_id ): array {
        $rows    = $this->get_assignments_for_user( $user_id );
        $payload = array();

        foreach ( $rows as $row ) {
            $exercise_id = (int) $row->exercise_id;
            $test_id     = (int) $row->source_test_id;
            $rating      = $this->get_rating_for_test_round( $user_id, $test_id, (int) $row->round );

            $payload[] = array(
                'assignment_id'  => (int) $row->assignment_id,
                'user_id'        => (int) $row->user_id,
                'round'          => (int) $row->round,
                'exercise_id'    => $exercise_id,
                'exercise_title' => get_the_title( $exercise_id ),
                'identifier'     => ExerciseIdentifier::get( $exercise_id ),
                'source_test_id' => $test_id,
                'source_test_title' => $test_id ? get_the_title( $test_id ) : '',
                'rating'         => $rating,
                'sort_order'     => (int) $row->sort_order,
                'status'         => (string) $row->status,
                'is_manual'      => (bool) $row->is_manual,
                'assigned_at'    => $row->assigned_at,
                'approved_at'    => $row->approved_at,
                'visible_at'     => $row->visible_at,
                'deadline_at'    => $row->deadline_at,
                'edit_url'       => get_edit_post_link( $exercise_id, 'raw' ) ?: '',
            );
        }

        return $payload;
    }

    public function get_rating_for_test_round( int $user_id, int $test_id, int $round ): ?int {
        if ( ! $test_id || ! $round ) {
            return null;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'user_ratings';
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rating = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT rating FROM {$table} WHERE user_id = %d AND test_id = %d AND round = %d ORDER BY attempt_id DESC LIMIT 1",
                absint( $user_id ),
                absint( $test_id ),
                absint( $round )
            )
        );

        return null !== $rating ? (int) $rating : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function search_published_exercises( string $search = '', int $limit = 20 ): array {
        $args = array(
            'post_type'      => 'excercise',
            'post_status'    => 'publish',
            'posts_per_page' => $limit,
            'orderby'        => 'title',
            'order'          => 'ASC',
        );

        if ( '' !== $search ) {
            $args['s'] = $search;
        }

        $query   = new \WP_Query( $args );
        $results = array();

        foreach ( $query->posts as $post ) {
            $results[] = array(
                'exercise_id' => (int) $post->ID,
                'title'       => $post->post_title,
                'identifier'  => ExerciseIdentifier::get( (int) $post->ID ),
            );
        }

        wp_reset_postdata();

        return $results;
    }
}
