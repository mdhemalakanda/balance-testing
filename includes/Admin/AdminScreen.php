<?php
namespace BalanceTesting\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Shared helpers for balance-testing wp-admin screens.
 */
class AdminScreen {

    /**
     * Hide notices from other plugins on a specific admin screen.
     */
    public static function suppress_third_party_notices( string $screen_id ): void {
        add_action(
            'admin_head',
            static function () use ( $screen_id ): void {
                $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
                if ( ! $screen || $screen->id !== $screen_id ) {
                    return;
                }
                remove_all_actions( 'admin_notices' );
                remove_all_actions( 'all_admin_notices' );
            },
            0
        );
    }

    /**
     * @param string $relative_path Path under plugin root, e.g. assets/css/admin-tools.css
     */
    public static function enqueue_style( string $handle, string $relative_path, string $screen_id ): void {
        add_action(
            'admin_enqueue_scripts',
            static function ( string $hook_suffix ) use ( $handle, $relative_path, $screen_id ): void {
                unset( $hook_suffix );
                $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
                if ( ! $screen || $screen->id !== $screen_id ) {
                    return;
                }
                $path = BT_DIR_PATH . ltrim( $relative_path, '/' );
                if ( ! file_exists( $path ) ) {
                    return;
                }
                wp_enqueue_style(
                    $handle,
                    plugin_dir_url( BT_FILE ) . ltrim( $relative_path, '/' ),
                    array(),
                    (string) filemtime( $path )
                );
            }
        );
    }
}
