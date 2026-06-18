<?php
/**
 * Full test-ordering verification: catalog, priority pool, regression case.
 *
 * Usage:
 *   php wp-content/plugins/balance-testing/scripts/verify-test-ordering.php [--fix-catalog]
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

$fix_catalog = in_array( '--fix-catalog', $argv, true );
$utils       = Utils::instance();
$failed      = 0;

function bt_fail( string $message ): void {
	global $failed;
	echo "FAIL: {$message}\n";
	++$failed;
}

function bt_pass( string $message ): void {
	echo "PASS: {$message}\n";
}

function bt_extract_test_code( string $title ): ?string {
	if ( preg_match( '/\b(\d+[A-Z]\d+)\s*$/u', $title, $matches ) ) {
		return $matches[1];
	}

	return null;
}

function bt_code_sort_key( string $code ): array {
	if ( preg_match( '/^(\d+)([A-Z])(\d+)$/u', $code, $m ) ) {
		return array( (int) $m[1], $m[2], (int) $m[3] );
	}

	return array( 0, '', 0 );
}

function bt_codes_in_order( string $prev, string $next ): bool {
	$a = bt_code_sort_key( $prev );
	$b = bt_code_sort_key( $next );

	// Only compare within the same section (e.g. 1A*, 1B*).
	if ( $a[0] !== $b[0] || $a[1] !== $b[1] ) {
		return true;
	}

	return $a < $b;
}

function bt_label_for_test_id( int $post_id ): string {
	$code = bt_extract_test_code( get_the_title( $post_id ) );

	return $code ?: get_the_title( $post_id );
}

echo "=== Catalog order audit ===\n";
$catalog_ids = $utils->get_catalog_test_ids();
echo 'Published tests: ' . count( $catalog_ids ) . "\n";

$code_positions = array();
foreach ( $catalog_ids as $index => $post_id ) {
	$code = bt_extract_test_code( get_the_title( $post_id ) );
	if ( $code ) {
		$code_positions[ $code ] = $index + 1;
	}
}

if ( isset( $code_positions['1B7'], $code_positions['1B8'] ) && $code_positions['1B7'] > $code_positions['1B8'] ) {
	$id_1b7 = 0;
	$id_1b8 = 0;
	foreach ( get_posts( array( 'post_type' => 'test', 'posts_per_page' => -1, 'post_status' => 'publish', 'fields' => 'ids' ) ) as $tid ) {
		$title = get_the_title( $tid );
		if ( preg_match( '/\b1B7\s*$/u', $title ) ) {
			$id_1b7 = (int) $tid;
		}
		if ( preg_match( '/\b1B8\s*$/u', $title ) ) {
			$id_1b8 = (int) $tid;
		}
	}

	if ( $id_1b7 && $id_1b8 ) {
		$mo_1b7 = (int) get_post_field( 'menu_order', $id_1b7 );
		$mo_1b8 = (int) get_post_field( 'menu_order', $id_1b8 );
		echo "Catalog issue: 1B8 (pos {$code_positions['1B8']}) before 1B7 (pos {$code_positions['1B7']}) — menu_order {$mo_1b8}/{$mo_1b7}\n";

		if ( $fix_catalog ) {
			wp_update_post(
				array(
					'ID'         => $id_1b7,
					'menu_order' => $mo_1b8,
				)
			);
			wp_update_post(
				array(
					'ID'         => $id_1b8,
					'menu_order' => $mo_1b7,
				)
			);
			echo "Fixed: swapped menu_order for 1B7 (#{$id_1b7}) and 1B8 (#{$id_1b8}).\n";
			$catalog_ids    = $utils->get_catalog_test_ids();
			$code_positions = array();
			foreach ( $catalog_ids as $index => $post_id ) {
				$code = bt_extract_test_code( get_the_title( $post_id ) );
				if ( $code ) {
					$code_positions[ $code ] = $index + 1;
				}
			}
		} else {
			bt_fail( '1B7 after 1B8 in catalog — run with --fix-catalog to swap menu_order' );
		}
	}
}

$prev_code = null;
$catalog_issues      = 0;
$r1_catalog_issues   = 0;
$r1_limit            = 43;
foreach ( $catalog_ids as $index => $post_id ) {
	$code = bt_extract_test_code( get_the_title( $post_id ) );
	if ( ! $code ) {
		continue;
	}
	if ( $prev_code && ! bt_codes_in_order( $prev_code, $code ) ) {
		++$catalog_issues;
		if ( ( $index + 1 ) <= $r1_limit ) {
			++$r1_catalog_issues;
		}
		if ( $catalog_issues <= 5 ) {
			echo "  Order gap: {$prev_code} → {$code} (pos " . ( $index + 1 ) . ")\n";
		}
	}
	$prev_code = $code;
}

if ( 0 === $r1_catalog_issues ) {
	bt_pass( "Round 1 catalog (first {$r1_limit} tests) has no within-section order gaps" );
} else {
	bt_fail( "{$r1_catalog_issues} within-section gap(s) in round 1 catalog (positions 1–{$r1_limit})" );
}

if ( $catalog_issues > $r1_catalog_issues ) {
	echo "NOTE: {$catalog_issues} total within-section gap(s) in full catalog (beyond round 1 limit); run --fix-catalog for known swaps.\n";
}

echo "\n=== Round 1 first tests (catalog) ===\n";
$expected_r1 = array( 'Intro', '1A1', '1A2', '1A3', '1A4' );
$first_titles = array();
foreach ( array_slice( $catalog_ids, 0, 5 ) as $post_id ) {
	$first_titles[] = get_the_title( $post_id );
}
$r1_ok = true;
foreach ( $expected_r1 as $i => $needle ) {
	if ( false === stripos( $first_titles[ $i ] ?? '', $needle === 'Intro' ? 'Hyvä tietää' : $needle ) ) {
		$r1_ok = false;
		echo "  Expected pos " . ( $i + 1 ) . " ~{$needle}, got: " . ( $first_titles[ $i ] ?? '(none)' ) . "\n";
	}
}
if ( $r1_ok ) {
	bt_pass( 'Round 1 starts Intro → 1A1 → 1A2 → 1A3 → 1A4' );
} else {
	bt_fail( 'Round 1 catalog head sequence wrong' );
}

echo "\n=== Priority pool regression (attempt order 1A10 → 1A2 → 1A4) ===\n";
$reg_user = 99987;
if ( get_user_by( 'id', $reg_user ) ) {
	UserMenu::instance()->delete_user_data( $reg_user, 'delete' );
} else {
	$reg_user = 87;
	UserMenu::instance()->delete_user_data( $reg_user, 'delete' );
}

global $wpdb;
$table       = $wpdb->prefix . 'user_ratings';
$catalog_11  = array_slice( $catalog_ids, 0, 11 );
$reg_pattern = array( 3, 2, 3, 2, 3, 2, 3, 2, 3, 2, 3 );

foreach ( $catalog_11 as $i => $test_id ) {
	$wpdb->insert(
		$table,
		array(
			'test_id' => (int) $test_id,
			'rating'  => $reg_pattern[ $i ],
			'user_id' => $reg_user,
			'round'   => 1,
		),
		array( '%d', '%d', '%d', '%d' )
	);
}
update_user_meta( $reg_user, 'test_round', 2 );

$priority_ids = array_values( array_map( 'intval', (array) $utils->get_priority_test_ids( $reg_user ) ) );
$exclude_ids  = array_values( array_map( 'intval', (array) $utils->get_exclude_test_ids( $reg_user ) ) );

$fixed_id = $utils->get_first_test_id_from_query_args(
	$utils->build_next_test_query_args( $priority_ids, $exclude_ids )
);
$broken_args = array(
	'post_type'      => 'test',
	'posts_per_page' => 1,
	'post__in'       => $priority_ids,
	'post__not_in'   => $exclude_ids,
	'orderby'        => array(
		'menu_order' => 'DESC',
		'title'      => 'DESC',
	),
	'order'          => 'DESC',
	'fields'         => 'ids',
);
$broken_ids = get_posts( $broken_args );
$broken_id  = ! empty( $broken_ids ) ? (int) $broken_ids[0] : 0;

$expected_id = ! empty( $priority_ids ) ? (int) $priority_ids[0] : 0;
echo 'Priority pool: ' . implode(
	', ',
	array_map(
		static function ( $id ) {
			return bt_label_for_test_id( (int) $id );
		},
		$priority_ids
	)
) . "\n";
echo 'post__in next: ' . ( $fixed_id ? bt_label_for_test_id( $fixed_id ) : 'none' ) . "\n";
echo 'menu_order next (old bug): ' . ( $broken_id ? bt_label_for_test_id( $broken_id ) : 'none' ) . "\n";

if ( $fixed_id === $expected_id && $fixed_id > 0 ) {
	bt_pass( 'post__in returns first priority test in attempt order' );
} else {
	bt_fail( 'post__in did not match first priority ID' );
}

if ( $broken_id !== $fixed_id ) {
	bt_pass( 'Regression: fixed ordering differs from old menu_order bug' );
} else {
	echo "NOTE: On this seed, menu_order happens to match attempt order for the first priority test.\n";
}

echo "\n=== Full priority walk (simulate resolve_next_test_query) ===\n";
UserMenu::instance()->delete_user_data( $reg_user, 'delete' );
foreach ( $catalog_11 as $i => $test_id ) {
	$wpdb->insert(
		$table,
		array(
			'test_id' => (int) $test_id,
			'rating'  => $reg_pattern[ $i ],
			'user_id' => $reg_user,
			'round'   => 1,
		),
		array( '%d', '%d', '%d', '%d' )
	);
}
update_user_meta( $reg_user, 'test_round', 2 );

$priority_ids = array_values( array_map( 'intval', (array) $utils->get_priority_test_ids( $reg_user ) ) );

$walk_expected = array();
foreach ( $priority_ids as $pid ) {
	$walk_expected[] = bt_label_for_test_id( (int) $pid );
}
$walk_actual = array();
$max_steps   = count( $walk_expected );

for ( $step = 0; $step < $max_steps; $step++ ) {
	$query = $utils->resolve_next_test_query( $reg_user );
	if ( ! $query || ! $query->have_posts() ) {
		break;
	}
	$query->the_post();
	$tid = (int) get_the_ID();
	wp_reset_postdata();

	$walk_actual[] = bt_label_for_test_id( $tid );

	$wpdb->insert(
		$table,
		array(
			'test_id' => $tid,
			'rating'  => 3,
			'user_id' => $reg_user,
			'round'   => 2,
		),
		array( '%d', '%d', '%d', '%d' )
	);
	$utils->get_priority_test_ids( $reg_user );
}

if ( $walk_actual === $walk_expected ) {
	bt_pass( 'Full round-2 priority walk matches attempt order (' . implode( ' → ', $walk_actual ) . ')' );
} else {
	bt_fail(
		'Priority walk mismatch. Expected: ' . implode( ' → ', $walk_expected )
		. ' | Got: ' . implode( ' → ', $walk_actual )
	);
}

UserMenu::instance()->delete_user_data( $reg_user, 'delete' );

echo "\n" . ( $failed ? "FAILED ({$failed} check(s))\n" : "All ordering checks passed.\n" );
exit( $failed ? 1 : 0 );
