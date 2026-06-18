<?php
namespace BalanceTesting\Form;

use BalanceTesting\SingletonTrait;
/**
 * This class will handle all form account related task.
 * 
 * @sicne 1.0
 */
defined('ABSPATH') || exit;

class AccountProfile {
    use SingletonTrait;

    public function init() {
        add_action('init', array($this, 'handle_account_form'));
    }

    public function handle_account_form() {
        if( isset($_POST['account_profile_form']) ) {
            // display warning required fields are empty.
            add_action('display_account_warning', array( $this, 'display_warning' ));
        }
    }

    public function display_warning() {
        if( isset($_POST['account_profile_form']) ) {
            $user_lang = sanitize_text_field( $_POST['language'] );
            update_user_meta(get_current_user_id(), 'locale', $user_lang); 
        }
    }
}