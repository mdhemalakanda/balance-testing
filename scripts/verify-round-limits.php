<?php
/**
 * One-off verification for pilot round limit defaults. Run from site root:
 * php wp-content/plugins/balance-testing/scripts/verify-round-limits.php
 */
$wp_load = dirname( __DIR__, 4 ) . '/wp-load.php';
if ( ! is_readable( $wp_load ) ) {
	fwrite( STDERR, "wp-load.php not found at {$wp_load}\n" );
	exit( 1 );
}
require_once __DIR__ . '/cli-bootstrap.php';
require $wp_load;

use BalanceTesting\RoundLimits;

$rl     = RoundLimits::instance();
$expect = array(
	1 => array( 'max' => 43, 'threshold' => 6 ),
	2 => array( 'max' => 43, 'threshold' => 6 ),
	3 => array( 'max' => 42, 'threshold' => 6 ),
);

$failed = 0;
echo "=== RoundLimits default constants ===\n";
foreach ( $expect as $round => $want ) {
	$max       = $rl->get_default_max_tests_for_round( $round );
	$threshold = $rl->get_default_rating_threshold_for_round( $round );
	$ok        = ( $max === $want['max'] && $threshold === $want['threshold'] );
	echo sprintf(
		"Round %d: max=%d (want %d) threshold=%d (want %d) %s\n",
		$round,
		$max,
		$want['max'],
		$threshold,
		$want['threshold'],
		$ok ? 'OK' : 'FAIL'
	);
	if ( ! $ok ) {
		++$failed;
	}
}

echo "\n=== API payload defaults (user without overrides) ===\n";
$users = get_users( array( 'number' => 1, 'orderby' => 'ID', 'order' => 'DESC', 'fields' => array( 'ID' ) ) );
$user_id = $users ? (int) $users[0]->ID : 0;
if ( ! $user_id ) {
	echo "No users found — skipping payload test.\n";
	exit( $failed ? 1 : 0 );
}

$stored_before = get_user_meta( $user_id, RoundLimits::USER_ROUND_RULES_META_KEY, true );
delete_user_meta( $user_id, RoundLimits::USER_ROUND_RULES_META_KEY );
$payload = $rl->get_user_round_rules_payload( $user_id );
foreach ( $expect as $round => $want ) {
	$r   = $payload['rounds'][ $round ] ?? array();
	$max = (int) ( $r['max_tests']['default'] ?? 0 );
	$th  = (int) ( $r['rating_threshold']['default'] ?? 0 );
	$em  = (int) ( $r['max_tests']['effective'] ?? 0 );
	$et  = (int) ( $r['rating_threshold']['effective'] ?? 0 );
	$ok  = $max === $want['max'] && $th === $want['threshold'] && $em === $want['max'] && $et === $want['threshold'];
	echo sprintf(
		"User %d round %d: default max=%d thresh=%d effective max=%d thresh=%d %s\n",
		$user_id,
		$round,
		$max,
		$th,
		$em,
		$et,
		$ok ? 'OK' : 'FAIL'
	);
	if ( ! $ok ) {
		++$failed;
	}
}

if ( is_array( $stored_before ) && ! empty( $stored_before ) ) {
	update_user_meta( $user_id, RoundLimits::USER_ROUND_RULES_META_KEY, $stored_before );
}

echo "\n=== Round-end invite scheduling (below 3/4 threshold) ===\n";
$schedule_backup = array(
	'test_round'            => get_user_meta( $user_id, 'test_round', true ),
	'round_2_mail_scheduled' => get_user_meta( $user_id, 'round_2_mail_scheduled', true ),
);
update_user_meta( $user_id, 'test_round', 1 );
delete_user_meta( $user_id, 'round_2_mail_scheduled' );

$rl->send_permitted_user_to_schedule_mail( $user_id );
$invite_scheduled = (bool) get_user_meta( $user_id, 'round_2_mail_scheduled', true );
echo sprintf(
	"User %d: round_2_mail_scheduled after round-end handler (below-threshold path) = %s %s\n",
	$user_id,
	$invite_scheduled ? 'true' : 'false',
	$invite_scheduled ? 'OK' : 'FAIL'
);
if ( ! $invite_scheduled ) {
	++$failed;
}

// Clean up scheduled cron + meta from this check.
$cron = _get_cron_array();
if ( is_array( $cron ) ) {
	foreach ( $cron as $timestamp => $hooks ) {
		if ( empty( $hooks['send_mail_for_access_second_test'] ) ) {
			continue;
		}
		foreach ( $hooks['send_mail_for_access_second_test'] as $key => $event ) {
			if ( isset( $event['args'][0] ) && (int) $event['args'][0] === $user_id ) {
				wp_unschedule_event( $timestamp, 'send_mail_for_access_second_test', $event['args'] );
			}
		}
	}
}
$auto_args = array( $user_id, 2 );
$auto_existing = wp_next_scheduled( 'bt_auto_grant_round_access', $auto_args );
if ( $auto_existing ) {
	wp_unschedule_event( $auto_existing, 'bt_auto_grant_round_access', $auto_args );
}

if ( '' !== $schedule_backup['test_round'] && false !== $schedule_backup['test_round'] ) {
	update_user_meta( $user_id, 'test_round', $schedule_backup['test_round'] );
} else {
	delete_user_meta( $user_id, 'test_round' );
}
if ( $schedule_backup['round_2_mail_scheduled'] ) {
	update_user_meta( $user_id, 'round_2_mail_scheduled', $schedule_backup['round_2_mail_scheduled'] );
} else {
	delete_user_meta( $user_id, 'round_2_mail_scheduled' );
}

echo "\n" . ( $failed ? "FAILED ({$failed} checks)\n" : "All checks passed.\n" );
exit( $failed ? 1 : 0 );
