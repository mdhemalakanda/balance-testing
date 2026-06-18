<?php
/**
 * Seed user data for priority-order UAT and print expected vs actual next test.
 *
 * Usage:
 *   php wp-content/plugins/balance-testing/scripts/setup-priority-order-uat.php [user_id]
 */

if ( ! defined( 'ABSPATH' ) ) {
	$plugin_scripts = __DIR__;
	require_once $plugin_scripts . '/cli-bootstrap.php';
	$wp_load = dirname( $plugin_scripts, 4 ) . '/wp-load.php';
	if ( ! file_exists( $wp_load ) ) {
		fwrite( STDERR, "wp-load.php not found\n" );
		exit( 1 );
	}
	require_once $wp_load;
}

use BalanceTesting\Menu\UserMenu;
use BalanceTesting\Utils;

$user_id = isset( $argv[1] ) ? absint( $argv[1] ) : 87;
global $wpdb;

UserMenu::instance()->delete_user_data( $user_id, 'delete' );

update_user_meta( $user_id, 'test_round', 1 );

$catalog_ids = get_posts(
	array(
		'post_type'      => 'test',
		'posts_per_page' => 11,
		'orderby'        => Utils::instance()->get_general_test_pool_orderby(),
		'order'          => 'DESC',
		'fields'         => 'ids',
	)
);

if ( count( $catalog_ids ) < 11 ) {
	echo "FAIL: need at least 11 published tests, got " . count( $catalog_ids ) . "\n";
	exit( 1 );
}

$table = $wpdb->prefix . 'user_ratings';
$pattern = array( 3, 2, 3, 2, 3, 2, 3, 2, 3, 2, 3 );

echo "Seeding round 1 for user {$user_id} (alternating 3/2 on first 11 catalog tests):\n";
foreach ( $catalog_ids as $i => $test_id ) {
	$rating = $pattern[ $i ];
	$wpdb->insert(
		$table,
		array(
			'test_id' => (int) $test_id,
			'rating'  => $rating,
			'user_id' => $user_id,
			'round'   => 1,
		),
		array( '%d', '%d', '%d', '%d' )
	);
	$title = get_the_title( $test_id );
	echo sprintf( "  %2d. %-40s rating=%d\n", $i + 1, $title, $rating );
}

update_user_meta( $user_id, 'test_round', 2 );
delete_user_meta( $user_id, 'disable_progress_questions' );

$utils        = Utils::instance();
$priority_ids = array_values( array_map( 'intval', (array) $utils->get_priority_test_ids( $user_id ) ) );
$exclude_ids  = array_values( array_map( 'intval', (array) $utils->get_exclude_test_ids( $user_id ) ) );

echo "\nRound 2 — priority pool (attempt order from round-1 ratings 3/4/5):\n";
foreach ( $priority_ids as $i => $id ) {
	echo sprintf( "  %2d. %s\n", $i + 1, get_the_title( $id ) );
}

$next_post_in = $utils->get_first_test_id_from_query_args(
	$utils->build_next_test_query_args( $priority_ids, $exclude_ids )
);

$broken_menu = get_posts(
	array(
		'post_type'      => 'test',
		'posts_per_page' => 1,
		'post__in'       => $priority_ids,
		'post__not_in'   => $exclude_ids,
		'orderby'        => $utils->get_general_test_pool_orderby(),
		'order'          => 'DESC',
		'fields'         => 'ids',
	)
);

$expected_title = ! empty( $priority_ids ) ? get_the_title( $priority_ids[0] ) : '(none)';
$fixed_title    = $next_post_in ? get_the_title( $next_post_in ) : '(none)';
$old_title      = ! empty( $broken_menu ) ? get_the_title( $broken_menu[0] ) : '(none)';

echo "\nExpected first round-2 test (attempt order): {$expected_title}\n";
echo "Next with orderby=post__in (fixed):          {$fixed_title}\n";
echo "Next with orderby=menu_order (old bug):      {$old_title}\n";
echo 'is_user_permitted_for_test: ' . ( $utils->is_user_permitted_for_test( $user_id ) ? 'yes' : 'no' ) . "\n";

if ( $fixed_title === $expected_title && $fixed_title !== $old_title ) {
	echo "\nPASS: fix changes ordering vs old bug.\n";
	exit( 0 );
}

if ( $fixed_title === $expected_title ) {
	echo "\nPASS: post__in matches attempt order (old query same — catalog may align on this seed).\n";
	exit( 0 );
}

echo "\nFAIL: fixed query did not match expected first priority test.\n";
exit( 1 );
