<?php
namespace BalanceTesting;

use BalanceTesting\API\API;
use BalanceTesting\Database\PostType;
use BalanceTesting\Form\AccountProfile;
use BalanceTesting\Form\BalanceTest;
use BalanceTesting\Form\UserQuestions;
use BalanceTesting\Menu\UserMenu;
use BalanceTesting\Metabox\UserQuestionsMetaBox;
use BalanceTesting\Schedule\TestAccessMail;
use BalanceTesting\Shortcode\LoginForm;
use BalanceTesting\Shortcode\UserAccount;
use BalanceTesting\Database\Menu;
use BalanceTesting\Exercise\ExerciseAdminColumns;
use BalanceTesting\Installation\CreateDatabase;
use BalanceTesting\Migration\BulkCopyTestsToExercises\BulkCopyTestsToExercises;
use BalanceTesting\Migration\CopyToExcercise\CopyToExcercise;

/**
 * This is main file for this autoload.
 */
defined('ABSPATH') || exit;
class Main {
    use SingletonTrait;

    public function init() {
        add_action( 'plugins_loaded', array( CreateDatabase::instance(), 'maybe_upgrade' ) );
        $this->disable_mail_on_create_user();
        UserRegistrationGuard::instance()->init();
        QueryVars::instance()->init();
        LoginForm::instance()->init();
        Assets::instance()->init();
        PostType::instance()->init();
        UserQuestions::instance()->init();
        UserQuestionsMetaBox::instance()->init();
        UserAccount::instance()->init();
        AccountProfile::instance()->init();
        BalanceTest::instance()->init();
        TestAccessMail::instance()->init();
        UserMenu::instance()->init();
        Menu::instance()->init();
        CopyToExcercise::instance()->init();
        BulkCopyTestsToExercises::instance()->init();
        ExerciseAdminColumns::instance()->init();
        API::instance()->init();
    }

    public function disable_mail_on_create_user() {
        add_action( 'plugins_loaded', function () {
            add_filter( 'wp_send_new_user_notification_to_user', '__return_false' );
        });
    }
}