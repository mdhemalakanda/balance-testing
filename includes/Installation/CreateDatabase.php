<?php
namespace BalanceTesting\Installation;

use BalanceTesting\SingletonTrait;

defined( 'ABSPATH' ) || exit;

class CreateDatabase {
    use SingletonTrait;

    public const DB_VERSION_OPTION = 'balance_testing_db_version';
    public const DB_VERSION        = '1.1.0';

    public function installations() {
        $this->create_databases();
        update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
    }

    public function maybe_upgrade() {
        $installed = get_option( self::DB_VERSION_OPTION, '1.0.0' );
        if ( version_compare( (string) $installed, self::DB_VERSION, '<' ) ) {
            $this->create_databases();
            update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
        }
    }

    private function create_databases() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $user_ratings_sql = "CREATE TABLE {$wpdb->prefix}user_ratings (
            attempt_id bigint(20) NOT NULL AUTO_INCREMENT,
            test_id bigint(20) DEFAULT NULL,
            rating bigint(20) DEFAULT NULL,
            user_id bigint(20) DEFAULT NULL,
            round bigint(20) DEFAULT 1,
            re_used bigint(20) DEFAULT 0,
            status varchar(20) DEFAULT 'publish',
            PRIMARY KEY  (attempt_id)
        ) $charset_collate;";

        $assignments_sql = "CREATE TABLE {$wpdb->prefix}user_exercise_assignments (
            assignment_id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL DEFAULT 0,
            round bigint(20) NOT NULL DEFAULT 1,
            exercise_id bigint(20) NOT NULL DEFAULT 0,
            source_test_id bigint(20) DEFAULT 0,
            sort_order int(11) NOT NULL DEFAULT 0,
            status varchar(20) NOT NULL DEFAULT 'suggested',
            is_manual tinyint(1) NOT NULL DEFAULT 0,
            assigned_at datetime DEFAULT NULL,
            approved_at datetime DEFAULT NULL,
            visible_at datetime DEFAULT NULL,
            deadline_at datetime DEFAULT NULL,
            PRIMARY KEY  (assignment_id),
            UNIQUE KEY user_round_exercise (user_id, round, exercise_id),
            KEY user_status (user_id, status),
            KEY user_sort (user_id, sort_order)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $user_ratings_sql );
        dbDelta( $assignments_sql );
    }
}
