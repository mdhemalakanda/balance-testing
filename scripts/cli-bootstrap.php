<?php
/**
 * CLI bootstrap for Local by Flywheel — use MySQL socket when localhost fails.
 */
if ( ! defined( 'DB_HOST' ) ) {
	$home = getenv( 'HOME' ) ?: '';
	$socks = $home ? glob( $home . '/Library/Application Support/Local/run/*/mysql/mysqld.sock' ) : array();
	if ( ! empty( $socks[0] ) && is_readable( $socks[0] ) ) {
		define( 'DB_HOST', 'localhost:' . $socks[0] );
	}
}
