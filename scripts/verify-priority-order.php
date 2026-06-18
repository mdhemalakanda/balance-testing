<?php
/**
 * Verify priority test ordering for rounds 2 and 3.
 *
 * Usage (from site root, Local PHP):
 *   php wp-content/plugins/balance-testing/scripts/verify-priority-order.php [user_id]
 */

if ( ! defined( 'ABSPATH' ) ) {
	$plugin_scripts = __DIR__;
	require_once $plugin_scripts . '/cli-bootstrap.php';
	$wp_load = dirname( $plugin_scripts, 4 ) . '/wp-load.php';
	if ( ! file_exists( $wp_load ) ) {
		fwrite( STDERR, "wp-load.php not found at {$wp_load}\n" );
		exit( 1 );
	}
	require_once $wp_load;
}

use BalanceTesting\Utils;

$user_id = isset( $argv[1] ) ? absint( $argv[1] ) : 87;
$utils   = Utils::instance();

function bt_title_for_id( int $post_id ): string {
	$post = get_post( $post_id );
	return $post ? $post->post_title : "(missing #{$post_id})";
}

$round = absint( get_user_meta( $user_id, 'test_round', true ) );
echo "User {$user_id} — test_round={$round}\n";

if ( $round < 2 ) {
	echo "SKIP: user must be on round 2 or 3 to verify priority ordering.\n";
	exit( 0 );
}

$priority_ids = array_values( array_map( 'intval', (array) $utils->get_priority_test_ids( $user_id ) ) );
$exclude_ids  = array_values( array_map( 'intval', (array) $utils->get_exclude_test_ids( $user_id ) ) );

if ( empty( $priority_ids ) ) {
	echo "FAIL: no priority test IDs for this user/round.\n";
	exit( 1 );
}

echo "Priority pool (" . count( $priority_ids ) . " tests, attempt order):\n";
$shown = array_slice( $priority_ids, 0, 12 );
foreach ( $shown as $i => $id ) {
	echo sprintf( "  %2d. %s (ID %d)\n", $i + 1, bt_title_for_id( $id ), $id );
}
if ( count( $priority_ids ) > 12 ) {
	echo "  ... +" . ( count( $priority_ids ) - 12 ) . " more\n";
}

$with_post_in_id = $utils->get_first_test_id_from_query_args(
	$utils->build_next_test_query_args( $priority_ids, $exclude_ids )
);
$with_post_in = $with_post_in_id ? bt_title_for_id( $with_post_in_id ) : null;

$broken_args = array(
	'post_type'      => 'test',
	'posts_per_page' => 1,
	'post__in'       => $priority_ids,
	'post__not_in'   => $exclude_ids,
	'orderby'        => $utils->get_general_test_pool_orderby(),
	'order'          => 'DESC',
	'fields'         => 'ids',
);
$broken_ids      = get_posts( $broken_args );
$with_menu_order = ! empty( $broken_ids ) ? bt_title_for_id( (int) $broken_ids[0] ) : null;

echo "\nNext test with orderby=post__in (fixed):     {$with_post_in}\n";
echo "Next test with orderby=menu_order (old bug): {$with_menu_order}\n";

$expected = bt_title_for_id( $priority_ids[0] );
$pass     = ( $with_post_in === $expected );

if ( $with_post_in !== $with_menu_order ) {
	echo "\nNOTE: fixed and old ordering differ — bug would have changed user experience.\n";
}

if ( $pass ) {
	echo "\nPASS: post__in picks first priority test in attempt order ({$expected}).\n";
	exit( 0 );
}

echo "\nFAIL: expected first priority test {$expected}, got {$with_post_in}\n";
exit( 1 );
