<?php
namespace BalanceTesting\Exercise;

use BalanceTesting\Mailer\MailFactory;
use BalanceTesting\SingletonTrait;
use WP_User;

defined( 'ABSPATH' ) || exit;

class ExerciseAdminNotifier {
    use SingletonTrait;

    private const ADMIN_EMAIL = 'jani@selkakuntoutus.fi';

    public function notify_suggestions_created( int $user_id, int $round, int $count ): void {
        $meta_key = sprintf( 'round_%d_exercise_suggest_admin_mail_sent', absint( $round ) );
        if ( get_user_meta( $user_id, $meta_key, true ) ) {
            return;
        }

        $user = get_user_by( 'id', $user_id );
        if ( ! $user instanceof WP_User ) {
            return;
        }

        $admin_url = add_query_arg(
            array(
                'post_type' => 'test',
                'page'      => 'test-users',
                'user_id'   => $user_id,
            ),
            admin_url( 'edit.php' )
        ) . '#/test/user/' . $user_id;

        $subject = sprintf(
            /* translators: 1: username, 2: round number */
            __( 'Review suggested exercises for %1$s (round %2$d)', 'balance-testing' ),
            $user->user_login,
            $round
        );

        $message = sprintf(
            '<div style="font-family: Arial, sans-serif; line-height: 1.6;">
                <h2>%s</h2>
                <p>%s</p>
                <ul>
                    <li><strong>%s</strong> %s</li>
                    <li><strong>%s</strong> %s</li>
                    <li><strong>%s</strong> %d</li>
                    <li><strong>%s</strong> %d</li>
                </ul>
                <p><a href="%s">%s</a></p>
            </div>',
            esc_html__( 'Exercise suggestions ready for review', 'balance-testing' ),
            esc_html__( 'A user completed a test round. Please review and approve suggested exercises before displaying them.', 'balance-testing' ),
            esc_html__( 'User:', 'balance-testing' ),
            esc_html( $user->user_login ),
            esc_html__( 'Email:', 'balance-testing' ),
            esc_html( $user->user_email ),
            esc_html__( 'Round:', 'balance-testing' ),
            $round,
            esc_html__( 'Suggestions:', 'balance-testing' ),
            $count,
            esc_url( $admin_url ),
            esc_html__( 'Open user in Users Progress', 'balance-testing' )
        );

        MailFactory::instance()->set_user_mail( self::ADMIN_EMAIL );
        MailFactory::instance()->set_subject( $subject );
        MailFactory::instance()->set_message( $message );
        MailFactory::instance()->send_mail();

        update_user_meta( $user_id, $meta_key, 1 );
    }

    public function notify_exercises_displayed_to_user( int $user_id ): void {
        if ( get_user_meta( $user_id, 'bt_exercises_displayed_user_mail_sent', true ) ) {
            return;
        }

        $user = get_user_by( 'id', $user_id );
        if ( ! $user instanceof WP_User ) {
            return;
        }

        $exercises_url = add_query_arg( 'action', 'exercises', home_url( '/bt-user-account/' ) );

        $subject = __( 'Harjoituksesi ovat valmiina', 'balance-testing' );

        $message = sprintf(
            '<div style="font-family: Arial, sans-serif; line-height: 1.6;">
                <p>%s</p>
                <p>%s</p>
                <p><a href="%s">%s</a></p>
                <p>%s</p>
            </div>',
            esc_html__( 'Hyvä osallistuja,', 'balance-testing' ),
            esc_html__( 'Harjoitusohjelmasi on nyt saatavilla tililläsi.', 'balance-testing' ),
            esc_url( $exercises_url ),
            esc_html__( 'Avaa harjoitukset', 'balance-testing' ),
            esc_html__( 'Ystävällisin terveisin, Jani Mikkonen / Parempi tasapaino- verkkokurssit', 'balance-testing' )
        );

        MailFactory::instance()->set_user_mail( $user->user_email );
        MailFactory::instance()->set_subject( $subject );
        MailFactory::instance()->set_message( $message );
        MailFactory::instance()->send_mail();

        update_user_meta( $user_id, 'bt_exercises_displayed_user_mail_sent', 1 );
    }
}
