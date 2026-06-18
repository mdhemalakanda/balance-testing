<?php
namespace BalanceTesting;

/**
 * This file will manage all css and js assets.
 * 
 * @since 1.0
 */
defined('ABSPATH') || exit;

class Assets {
    use SingletonTrait;

    /**
     * Filemtime-based versions so deploys bust browser / CDN caches (e.g. progress form styles from email links).
     */
    private function asset_version( $relative_path ) {
        $path = BT_DIR_PATH . ltrim( $relative_path, '/' );
        return file_exists( $path ) ? (string) filemtime( $path ) : '1.0';
    }

    public function init() {
        add_action('wp_enqueue_scripts', array( $this, 'register_scripts' ));
        add_action('admin_enqueue_scripts', array( $this, 'register_scripts' ));
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_style' ) );
    }

    public function enqueue_scripts() {
        $range_js_ver  = $this->asset_version( 'assets/js/jquery.range.min.js' );
        $chartist_ver  = $this->asset_version( 'assets/js/chartist.min.js' );
        $main_js_ver   = $this->asset_version( 'assets/js/main.js' );
        $range_css_ver = $this->asset_version( 'assets/css/jquery.range.css' );

        wp_enqueue_script(
            'range-js',
            plugin_dir_url(BT_FILE) . 'assets/js/jquery.range.min.js',
            array('jquery'),
            $range_js_ver,
            true 
        );
         wp_enqueue_script(
            'chartist',
            plugin_dir_url(BT_FILE) . 'assets/js/chartist.min.js',
            array('jquery'),
            $chartist_ver,
            true 
        );
        wp_enqueue_script(
            'main-js',
            plugin_dir_url(BT_FILE) . 'assets/js/main.js',
            array('jquery', 'range-js', 'chartist'),
            $main_js_ver,
            true 
        );

        wp_enqueue_style(
            'range-css',
            plugin_dir_url(BT_FILE) . 'assets/css/jquery.range.css',
            array(),
            $range_css_ver
        );
        wp_enqueue_style('bt-style');
    }

    public function register_scripts() {
         wp_register_style(
            'chartist',
            plugin_dir_url(BT_FILE) . 'assets/css/chartist.min.css',
            array(),
            $this->asset_version( 'assets/css/chartist.min.css' )
        );
         wp_register_style(
            'bt-style',
            plugin_dir_url(BT_FILE) . 'assets/css/style.css',
            array(),
            $this->asset_version( 'assets/css/style.css' )
        );
    }

    public function enqueue_frontend_style() {
        wp_enqueue_style('chartist');

    }

    public function enqueue_admin_scripts($screen) {
        if($screen === 'test_page_test-users') {
            // wp_enqueue_script_module(
            //     'test-users',
            //     'http://localhost:5173/src/main.jsx',
            //     [],           // dependencies if any
            //     null          // no version = no ?ver= query string (Vite handles caching)
            // );
            wp_enqueue_script(
                'balance-testing-js',
                plugin_dir_url(BT_FILE) . 'frontend/test-user-management/dist/balance-testing.es.js',
                ['wp-element'],
                filemtime(plugin_dir_path(BT_FILE) . 'frontend/test-user-management/dist/balance-testing.umd.js'),
                true
            );
            wp_localize_script(
                'balance-testing-js',
                'btAdmin',
                array(
                    'restUrl' => esc_url_raw( rest_url( 'balance-testing/v1/' ) ),
                    'nonce'   => wp_create_nonce( 'wp_rest' ),
                )
            );
            wp_enqueue_style(
                'test-user-management-css',
                plugin_dir_url(BT_FILE) . 'frontend/test-user-management/dist/test-user-management.css',
                [],
                filemtime(plugin_dir_path(BT_FILE) . 'frontend/test-user-management/dist/test-user-management.css')
            );
            // wp_enqueue_script_module(
            //     'balance-testing-js',
            //     'http://localhost:5173/@vite/client',
            //     [],
            //     null
            // );
        }
    }
}