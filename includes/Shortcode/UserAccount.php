<?php
namespace BalanceTesting\Shortcode;

use BalanceTesting\SingletonTrait;
/**
 * This file will use for create user account shortcode.
 * 
 * @since 1.0
 */
defined('ABSPATH') || exit;

class UserAccount {
    use SingletonTrait;

    public function init() {
        add_shortcode('bt_user_account', array( $this, 'display_user_account' ));
        add_action('template_redirect', array($this, 'redirect_user_account'));
        add_action('template_redirect', array($this, 'prevent_account_page_cache'), 0);
    }

    /**
     * Account / progress-checkin (incl. email links with test_question_access_key) must not be full-page cached.
     */
    public function prevent_account_page_cache() {
        $queried = get_queried_object();
        $is_account_page = is_page('bt-user-account')
            || ( $queried && isset($queried->post_name) && 'bt-user-account' === $queried->post_name );

        $has_email_access_key = ! empty($_GET['test_question_access_key']);

        if ( ! $is_account_page && ! $has_email_access_key ) {
            return;
        }

        // Email links often hit while logged out (then redirect to login) — still must not cache stale HTML/CSS.
        if ( ! is_user_logged_in() && ! $has_email_access_key ) {
            return;
        }

        if ( ! defined('DONOTCACHEPAGE') ) {
            define('DONOTCACHEPAGE', true);
        }

        nocache_headers();

        if ( ! headers_sent() ) {
            header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
            header( 'Pragma: no-cache' );
            header( 'X-LiteSpeed-Cache-Control: no-cache' );
        }

        if ( has_action('litespeed_control_set_nocache') ) {
            do_action('litespeed_control_set_nocache', 'balance-testing account' );
        }
    }

    public function redirect_user_account() {
        if ( !is_user_logged_in() && ( is_page('bt-user-account') || get_queried_object() && get_queried_object()->post_name === 'bt-user-account' )) {
            $question_hash = !empty($_GET['test_question_access_key']) ? sanitize_text_field($_GET['test_question_access_key']): '';
            $current_url = add_query_arg( 'test_question_access_key', $question_hash , USER_ACCOUNT_PROGRESS_PERMALINK );
            $current_url = urlencode($current_url);
            wp_safe_redirect( home_url("/omatestaus/?redirect_to=$current_url"), 302 );
            exit;
        }
    }
    public function display_user_account() {
        $file = BT_DIR_PATH . 'templates/my-account/user-account.php';
        ob_start();
        if (!file_exists($file)) {
            echo 'File not found: ' . $file;
        } else {
            ob_start();
            include $file;
            return ob_get_clean();
        }
    }
}