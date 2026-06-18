<?php
/**
 * Plugin Name: Balance Testing
 * Author: Space Tech Solutions
 * Plugin URI: https://spacetechsol.com
 * Author URI: https://jibranshabir.com
 * Description: This plugin will help for test balance and set reminder for next test update.
 * Version: 1.0.0
 */

use BalanceTesting\Installation\CreateDatabase;
use BalanceTesting\Installation\CreatePages;
use BalanceTesting\Main;

// define constants.
$user_account_permalink = home_url('/bt-user-account/');
$progress_check_in_permalink = add_query_arg( 'action', 'progress-checkin', $user_account_permalink );
DEFINE('USER_ACCOUNT_PROGRESS_PERMALINK', $progress_check_in_permalink);
DEFINE('BT_FILE', __FILE__);
DEFINE('BT_DIR_PATH', plugin_dir_path(BT_FILE));
DEFINE('BT_NAMESPACE', 'BalanceTesting');

// autoload.
spl_autoload_register('bt_class_loader');
function bt_class_loader( $class_name ) {
    if ( ! class_exists( $class_name ) ) {
        $class_name = preg_replace(
            array( '/([a-z])([A-Z])/', '/\\\/' ),
            array( '$1$2', DIRECTORY_SEPARATOR ),
            $class_name
        );

        $class_name = str_replace( BT_NAMESPACE . DIRECTORY_SEPARATOR, 'includes' . DIRECTORY_SEPARATOR, $class_name );
        $file_name  = BT_DIR_PATH. $class_name . '.php';
        if ( file_exists( $file_name ) ) {
            require_once $file_name;
        }
    }
}

$functions_file = BT_DIR_PATH . 'additional/global-functions.php';
if ( file_exists( $functions_file ) ) {
    require_once $functions_file;
} else {
    error_log( 'File not found: ' . $functions_file );
}

Main::instance()->init();


/**
 * Create essentials page on installations.
 */
register_activation_hook(__FILE__, 'bt_installation_process');
function bt_installation_process() {
    CreatePages::instance()->installations();
    CreateDatabase::instance()->installations();
    flush_rewrite_rules();
}

function bt_file_import($filepath) {
    global $user_id, $action;
    if(file_exists($filepath)) {
        include $filepath;
    } else {
        echo 'file not found on: '. $filepath;
    }
}