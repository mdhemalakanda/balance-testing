<?php
namespace BalanceTesting\Database;

use BalanceTesting\Admin\AdminScreen;
use BalanceTesting\SingletonTrait;
/**
 * This file will create menu for this project.
 * 
 * @since 1.0
 */
defined('ABSPATH') || exit;

class Menu {
    use SingletonTrait;

    public function init() {
        AdminScreen::suppress_third_party_notices( 'test_page_test-users' );
        add_action('admin_menu', array($this, 'register_menu'));
    }

    public function register_menu() {
        add_submenu_page(
            'edit.php?post_type=test',
            __(' Users Progress', 'balance-testing'),
            __(' Users Progress', 'balance-testing'),
            'manage_options',
            'test-users',
            array($this, 'display_test_users'),
        );
    }

    public function display_test_users() {
        // React mount point
        echo '<div id="display-user">Loading...</div>';

        // JS redirect to include hash if not already present
        ?>
        <script>
            (function() {
                const currentHash = window.location.hash || '';
                if (!currentHash.startsWith('#/test/')) {
                    window.location.hash = '#/test/table';
                }
            })();
        </script>
        <?php
    }

}