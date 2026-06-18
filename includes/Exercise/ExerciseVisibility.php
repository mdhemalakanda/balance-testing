<?php
namespace BalanceTesting\Exercise;

use BalanceTesting\SingletonTrait;
use BalanceTesting\Utils;

defined( 'ABSPATH' ) || exit;

class ExerciseVisibility {
    use SingletonTrait;

    public const VISIBLE_META_KEY    = 'bt_exercises_visible';
    public const VISIBLE_AT_META_KEY = 'bt_exercises_visible_at';

    public function is_visible_for_user( int $user_id ): bool {
        return (bool) get_user_meta( absint( $user_id ), self::VISIBLE_META_KEY, true );
    }

    public function get_visible_at( int $user_id ): ?string {
        $value = get_user_meta( absint( $user_id ), self::VISIBLE_AT_META_KEY, true );
        return is_string( $value ) && '' !== $value ? $value : null;
    }

    public function get_recommended_days(): int {
        $settings = Utils::instance()->get_schedule_settings();
        $days     = isset( $settings['exercise_recommended_days'] ) ? absint( $settings['exercise_recommended_days'] ) : 12;
        return max( 1, $days );
    }

    public function get_deadline_timestamp( int $user_id ): ?int {
        if ( ! $this->is_visible_for_user( $user_id ) ) {
            return null;
        }

        $visible_at = $this->get_visible_at( $user_id );
        if ( ! $visible_at ) {
            return null;
        }

        $ts = (int) mysql2date( 'U', $visible_at );
        if ( $ts <= 0 ) {
            return null;
        }

        return $ts + ( $this->get_recommended_days() * DAY_IN_SECONDS );
    }

    public function get_days_remaining( int $user_id ): ?int {
        $deadline = $this->get_deadline_timestamp( $user_id );
        if ( null === $deadline ) {
            return null;
        }

        $remaining_seconds = $deadline - (int) current_time( 'timestamp' );
        if ( $remaining_seconds <= 0 ) {
            return 0;
        }

        return (int) ceil( $remaining_seconds / DAY_IN_SECONDS );
    }

    public function get_countdown_message( int $user_id ): string {
        $days = $this->get_days_remaining( $user_id );
        if ( null === $days ) {
            return '';
        }

        return sprintf(
            /* translators: %d: number of days remaining */
            __( '%d päivää jäljellä suositellusta ajasta tehdä harjoituksia', 'balance-testing' ),
            $days
        );
    }

    /**
     * @return array{visible: bool, visible_at: ?string, days_remaining: ?int, countdown_message: string}
     */
    public function get_visibility_payload( int $user_id ): array {
        return array(
            'visible'            => $this->is_visible_for_user( $user_id ),
            'visible_at'         => $this->get_visible_at( $user_id ),
            'days_remaining'     => $this->get_days_remaining( $user_id ),
            'countdown_message'  => $this->get_countdown_message( $user_id ),
            'recommended_days'   => $this->get_recommended_days(),
        );
    }

    /**
     * Show exercises to user: flip visibility meta and promote approved rows.
     *
     * @return array|\WP_Error
     */
    public function set_visible( int $user_id, bool $visible ) {
        $user_id = absint( $user_id );
        $now     = current_time( 'mysql' );
        $days    = $this->get_recommended_days();

        if ( $visible ) {
            $approved_count = ExerciseRepository::instance()->count_displayable_assignments( $user_id );
            if ( $approved_count < 1 ) {
                return new \WP_Error(
                    'bt_no_approved_exercises',
                    __( 'Approve at least one exercise before displaying to the user.', 'balance-testing' )
                );
            }

            $existing_at = $this->get_visible_at( $user_id );
            if ( ! $existing_at ) {
                update_user_meta( $user_id, self::VISIBLE_AT_META_KEY, $now );
            }
            update_user_meta( $user_id, self::VISIBLE_META_KEY, 1 );

            $visible_at  = $existing_at ?: $now;
            $visible_ts  = (int) mysql2date( 'U', $visible_at );
            $deadline_at = wp_date(
                'Y-m-d H:i:s',
                $visible_ts + ( $days * DAY_IN_SECONDS )
            );

            $promoted = ExerciseRepository::instance()->mark_approved_as_visible( $user_id, $visible_at, $deadline_at );

            if ( $promoted > 0 ) {
                ExerciseAdminNotifier::instance()->notify_exercises_displayed_to_user( $user_id );
            }

            return $this->get_visibility_payload( $user_id );
        }

        update_user_meta( $user_id, self::VISIBLE_META_KEY, 0 );

        return $this->get_visibility_payload( $user_id );
    }
}
