<?php
namespace BalanceTesting\Installation;

use BalanceTesting\SingletonTrait;
/**
 * Installation
 * 
 * This file will use for handle all required installation.
 * 
 * @since 1.0
 */
defined('ABSPATH') || exit;

class CreatePages {
    use SingletonTrait;

    public function installations() {
        $this->create_user_account_page();
        $this->create_login_page();
    }

    

    public function create_user_account_page() {
        $page_title = __('Käyttäjätili', 'balance-testing');
        $page_slug    = 'bt-user-account';
        $page_content = '[bt_user_account]';
        $page = get_page_by_path($page_slug);

        if(!$page) {
            $page_data = array(
                'post_title'    => $page_title,
                'post_name'     => $page_slug,
                'post_content'  => $page_content,
                'post_status'   => 'publish',
                'post_type'     => 'page',
                'post_author'   => get_current_user_id() ? get_current_user_id() : 1,
            );

            $page_id = wp_insert_post($page_data);
            if($page_id && !is_wp_error($page_id)) {
                error_log('My account page created by balance testing');
            }
        }
    }

    public function create_login_page() {
        $page_title = __('Omatestaus', 'balance-testing');
        $page_slug    = 'omatestaus';
        $page_content = '[bt_account]';
        $page = get_page_by_path($page_slug);

        if(!$page) {
            $page_data = array(
                'post_title'    => $page_title,
                'post_name'     => $page_slug,
                'post_content'  => $page_content,
                'post_status'   => 'publish',
                'post_type'     => 'page',
                'post_author'   => get_current_user_id() ? get_current_user_id() : 1,
            );

            $page_id = wp_insert_post($page_data);

            if ( $page_id && !is_wp_error($page_id )) {
                error_log('My account page created by balance testing');
            }
        }
    }
}