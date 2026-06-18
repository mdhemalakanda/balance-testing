<?php
namespace BalanceTesting\Schedule;
use WP_User;
use BalanceTesting\RoundLimits;
use BalanceTesting\Utils;
use BalanceTesting\Mailer\MailFactory;
use BalanceTesting\SingletonTrait;
/**
 * This class will use for schedule mail for access test.
 * 
 * @since 1.0
 */
defined('ABSPATH') || exit;
class TestAccessMail {
    use SingletonTrait;
    private $schedule_settings;

    public function __construct() {
        $this->schedule_settings = Utils::instance()->get_schedule_settings();
    }

    public function init() {
        add_action('send_mail_for_access_second_test', array( $this, 'send_email_for_round_two' ), 10, 3);
        add_action('send_mail_for_access_third_test', array( $this, 'send_email_for_round_three' ), 10, 3);
        add_action('send_mail_for_access_forth_test', array( $this, 'send_email_for_round_four' ), 10, 3);

        // auto grant round access.
        add_action('bt_auto_grant_round_access', array($this, 'auto_grant_round_access'), 10, 2);
    }

    public function send_email_for_round_two( $user_id, $email, $subject ) {
        $user_hash = uniqid();
        $login_url = add_query_arg( 'test_question_access_key', $user_hash, USER_ACCOUNT_PROGRESS_PERMALINK );
        update_user_meta( $user_id, 'round_extended_question_access_hash', $user_hash );

        $message = $this->build_round_follow_up_email_html( $login_url );
        MailFactory::instance()->set_user_mail( $email );
        MailFactory::instance()->set_subject( $subject );
        MailFactory::instance()->set_message( $message );
        MailFactory::instance()->send_mail();
    }

    public function send_email_for_round_three( $user_id, $email, $subject ) {
        $user_hash = uniqid();
        $login_url = add_query_arg( 'test_question_access_key', $user_hash, USER_ACCOUNT_PROGRESS_PERMALINK );
        update_user_meta( $user_id, 'round_3_question_access_hash', $user_hash );

        $message = $this->build_round_follow_up_email_html( $login_url );
        MailFactory::instance()->set_user_mail( $email );
        MailFactory::instance()->set_subject( $subject );
        MailFactory::instance()->set_message( $message );
        MailFactory::instance()->send_mail();
    }

    /**
     * HTML body for round 2 and round 3 follow-up invite emails.
     */
    private function build_round_follow_up_email_html( $login_url ) {
        ob_start();
        ?>
        <!DOCTYPE html>
            <html>
            <head>
                <meta charset="utf-8">
                <meta name="viewport" content="width=device-width, initial-scale=1">
                <title><?php echo esc_html__( 'Testaus päättyi / Testing Completed', 'balance-testing' ); ?></title>
            </head>
            <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333333; margin: 0; padding: 0;">
                <p><?php echo esc_html__( 'Hyvä osallistuja,', 'balance-testing' ); ?></p>
                <p><?php echo esc_html__( 'Testien tekemisestä on kulunut kaksitoista päivää.', 'balance-testing' ); ?></p>
                <p><?php echo esc_html__( 'Jos pääsit aloittamaan harjoitukset heti ensimmäisinä päivinä harjoitusten saamisesta, kerrothan edistymisestäsi, tee seuraava testikierros ja lähetän sen jälkeen uudet harjoitteet.', 'balance-testing' ); ?></p>
                <p><?php echo esc_html__( 'Jos aloitit harjoitukset myöhemmin, tee testit noin kahdentoista päivän kuluttua harjoittelun aloittamisesta.', 'balance-testing' ); ?></p>
                <p>
                    <?php echo esc_html__( 'Kun haluat tehdä testit, voit kirjautua sisään seuraavan linkin kautta omalla käyttäjänimelläsi ja salasanallasi:', 'balance-testing' ); ?>
                    <a href="<?php echo esc_url( $login_url ); ?>"><?php echo esc_html__( 'Kirjaudu täältä', 'balance-testing' ); ?></a>
                </p>
                <p><?php echo esc_html__( 'Vastaan mielelläni kaikkiin kysymyksiin.', 'balance-testing' ); ?></p>
                <p><?php echo esc_html__( 'Ystävällisin terveisin, Jani Mikkonen / Parempi tasapaino- verkkokurssit', 'balance-testing' ); ?></p>
            </body>
        </html>
        <?php
        return ob_get_clean();
    }

    
    public function send_email_for_round_four( $user_id, $email, $subject ) {
        // set user round 2 access key for access round 2 later.
        $user_hash = uniqid();
        $user_access_round_test_four = add_query_arg( 'test_question_access_key', $user_hash , USER_ACCOUNT_PROGRESS_PERMALINK );
        update_user_meta($user_id, 'round_4_question_access_hash', $user_hash);

        ob_start();
            ?>
            <!DOCTYPE html>
                <html>
                <head>
                    <meta charset="utf-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1">
                    <title><?php echo esc_html__('Testaus päättyi / Testing Completed', 'balance-testing'); ?></title>
                </head>
                <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333333; margin: 0; padding: 0;">
                    <p><?php echo esc_html__("Aika seurata edistymistä viimeisen kerran. Lämmin kiitos osallistumisestasi! 
", 'balance-testing'); ?> <a href="<?php echo esc_url($user_access_round_test_four); ?>"><?php echo esc_html__("Kirjaudu täältä.", 'balance-testing'); ?></a></p>
                </body>
            </html>
        <?php
        $message = ob_get_clean();
        MailFactory::instance()->set_user_mail($email);
        MailFactory::instance()->set_subject($subject);
        MailFactory::instance()->set_message($message);
        MailFactory::instance()->send_mail();
    }

    /**
     * This funciton will create a schedule for access test 2.
     * 
     * @since 1.0
     * @return void
     */
    public function send_schedule_test_two_access( $user_id, $email, $subject ) {
       $this->maybe_schedule_auto_access($user_id, 2);
       $merged          = Utils::instance()->get_schedule_settings();
        // get delay settings for round 2.
        $delay_settings  = $merged['round_2_invite_delay'];
        $delay_seconds   = Utils::get_date_in_seconds($delay_settings['value'], $delay_settings['unit']);

        $args = array(
            'user_id' => $user_id,
            'email' => $email,
            'subject' => $subject
        );
        if( !wp_next_scheduled( 'send_mail_for_access_second_test', $args ) ) {
            wp_schedule_single_event( $delay_seconds, 'send_mail_for_access_second_test', $args );
            $this->send_mail_to_administrator($user_id, 1);
        }
    }
    public function auto_grant_round_access($user_id, $target_round) {
        $user_id = absint($user_id);
        if (!$user_id) return;
        update_user_meta($user_id, 'round_extended_question_access', true);
        update_user_meta($user_id, 'disable_progress_questions', false);
    }

    /**
     * This funciton will create a schedule for access test 2.
     * 
     * @since 1.0
     * @return void
     */
    public function send_schedule_test_three_access( $user_id, $email, $subject ) {
       $this->maybe_schedule_auto_access($user_id, 3);
       
       $merged         = Utils::instance()->get_schedule_settings();
        $delay_settings = $merged['round_3_invite_delay'];
        $delay_seconds  = Utils::get_date_in_seconds($delay_settings['value'], $delay_settings['unit']);

        $args = array(
            'user_id' => $user_id,
            'email' => $email,
            'subject' => $subject
        );
        if( !wp_next_scheduled( 'send_mail_for_access_third_test', $args ) ) {
            wp_schedule_single_event( $delay_seconds, 'send_mail_for_access_third_test', $args );
            $this->send_mail_to_administrator($user_id, 2);
        }
    }
    /**
     * This funciton will create a schedule for access test 4.
     * 
     * @since 1.0
     * @return void
     */
    public function send_schedule_test_four_access( $user_id, $email, $subject ) {
        $this->maybe_schedule_auto_access($user_id, 4);
        
        // get delay settings for round 4.
        $merged         = Utils::instance()->get_schedule_settings();
        $delay_settings = $merged['round_4_invite_delay'];
        $delay_seconds  = Utils::get_date_in_seconds($delay_settings['value'], $delay_settings['unit']);
        
        $args = array(
            'user_id' => $user_id,
            'email' => $email,
            'subject' => $subject
        );
        if( !wp_next_scheduled( 'send_mail_for_access_forth_test', $args ) ) {
            wp_schedule_single_event( $delay_seconds, 'send_mail_for_access_forth_test', $args );
            $this->send_mail_to_administrator($user_id, 3);
        }
    }

    private function send_mail_to_administrator($user_id, $round) {
        /**
         * Send mail to administrator.
        */
        $send_to = 'jani@selkakuntoutus.fi';
        $user = get_user_by('id', $user_id);
        $username = '';
        $email    = '';
        if ($user instanceof WP_User) {
            $username = $user->user_login;
            $email    = $user->user_email;
        }
        $subject = "Round {$round} Test Completed. User: ". $username;
        $message = "{$username} ({$email}) has completed round {$round} of tests.";

        MailFactory::instance()->set_user_mail($send_to);
        MailFactory::instance()->set_subject($subject);
        MailFactory::instance()->set_message($message);
        MailFactory::instance()->send_mail();
    }

    

    /**
     * This function will schedule auto access for the user.
     * 
     * @since 1.0
     * @param int $user_id
     * @param int $target_round
     * @return bool true if scheduled, false otherwise.
     */
    public function maybe_schedule_auto_access($user_id, $target_round) {
        $schedule_settings   = Utils::instance()->get_schedule_settings();
        $auto_access_delay   = $schedule_settings['auto_access_delay'] ?? ['value' => 10, 'unit' => 'days'];
        if (!empty($schedule_settings['enable_auto_access'])) {
            $run_at = Utils::get_date_in_seconds($auto_access_delay['value'], $auto_access_delay['unit']);
    
            $auto_args = [$user_id, $target_round];
            $existing  = wp_next_scheduled('bt_auto_grant_round_access', $auto_args);
            if ($existing) {
                wp_unschedule_event($existing, 'bt_auto_grant_round_access', $auto_args);
            }
            wp_schedule_single_event($run_at, 'bt_auto_grant_round_access', $auto_args);
            return true;
        }
        return false;
    }


    public function send_completed_mail_to_administrator($user_id, $send_to = 'jani@selkakuntoutus.fi' ) {
        /**
         * Send mail to administrator.
        */
        $user = get_user_by('id', $user_id);
        $username = '';
        $email    = '';

        if ($user instanceof WP_User) {
            $username = $user->user_login;
            $email    = $user->user_email;
        }

        $subject = "🎉 Test Completion Notification: {$username}";

        $message = "
            <div style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
                <h2 style='color:#2c3e50;'>Test Completion Report</h2>

                <p>Dear Administrator,</p>

                <p>We would like to inform you that the following user has successfully completed all required tests:</p>

                <table style='border-collapse: collapse; width: 100%; margin-top: 10px;'>
                    <tr>
                        <td style='padding: 8px; border: 1px solid #ddd;'><strong>Username</strong></td>
                        <td style='padding: 8px; border: 1px solid #ddd;'>{$username}</td>
                    </tr>
                    <tr>
                        <td style='padding: 8px; border: 1px solid #ddd;'><strong>Email</strong></td>
                        <td style='padding: 8px; border: 1px solid #ddd;'>{$email}</td>
                    </tr>
                </table>

                <p style='margin-top:15px;'>
                    All assigned tests have been marked as completed successfully.
                </p>

                <hr>

                <p style='font-size: 12px; color: #888;'>
                    This is an automated notification from the system.
                </p>
            </div>
        ";

        MailFactory::instance()->set_user_mail($send_to);
        MailFactory::instance()->set_subject($subject);
        MailFactory::instance()->set_message($message);
        MailFactory::instance()->send_mail();
    }

    public function send_low_rating_round_mail_to_administrator($user_id, $round, $valid_rating_count, $required_threshold = 6, $send_to = 'jani@selkakuntoutus.fi') {
        if ( is_string( $required_threshold ) && filter_var( $required_threshold, FILTER_VALIDATE_EMAIL ) ) {
            $send_to             = $required_threshold;
            $required_threshold  = RoundLimits::DEFAULT_RATING_THRESHOLD;
        }

        $required_threshold = max( 1, absint( $required_threshold ) );
        $user = get_user_by('id', $user_id);
        $username = '';
        $email    = '';

        if ($user instanceof WP_User) {
            $username = $user->user_login;
            $email    = $user->user_email;
        }

        $subject = "Round {$round} completed with fewer than {$required_threshold} ratings (3/4): {$username}";

        $message = "
            <div style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
                <h2 style='color:#2c3e50;'>Low 3/4 Ratings Alert</h2>
                <p>Dear Administrator,</p>
                <p>The user below completed all available tests for round {$round}, but has fewer than {$required_threshold} tests rated 3 or 4.</p>
                <table style='border-collapse: collapse; width: 100%; margin-top: 10px;'>
                    <tr>
                        <td style='padding: 8px; border: 1px solid #ddd;'><strong>Username</strong></td>
                        <td style='padding: 8px; border: 1px solid #ddd;'>{$username}</td>
                    </tr>
                    <tr>
                        <td style='padding: 8px; border: 1px solid #ddd;'><strong>Email</strong></td>
                        <td style='padding: 8px; border: 1px solid #ddd;'>{$email}</td>
                    </tr>
                    <tr>
                        <td style='padding: 8px; border: 1px solid #ddd;'><strong>Round</strong></td>
                        <td style='padding: 8px; border: 1px solid #ddd;'>{$round}</td>
                    </tr>
                    <tr>
                        <td style='padding: 8px; border: 1px solid #ddd;'><strong>Ratings (3 or 4)</strong></td>
                        <td style='padding: 8px; border: 1px solid #ddd;'>{$valid_rating_count}</td>
                    </tr>
                </table>
                <p style='margin-top:15px;'>This alert is sent once per user per round.</p>
            </div>
        ";

        MailFactory::instance()->set_user_mail($send_to);
        MailFactory::instance()->set_subject($subject);
        MailFactory::instance()->set_message($message);
        MailFactory::instance()->send_mail();
    }

}