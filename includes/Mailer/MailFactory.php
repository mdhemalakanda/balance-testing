<?php
namespace BalanceTesting\Mailer;

use BalanceTesting\Exception\Warning;
use BalanceTesting\SingletonTrait;
/**
 * This function will decide which mail should send.
 * 
 * @since 1.0
 */
defined('ABSPATH') || exit;
class MailFactory {
    use SingletonTrait;
    private $user_email;
    private $subject;
    private $message;

    /**
     * This function will send mail for round 1.
     * @return void
     */
    public function send_mail() {
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: Parempi tasapaino- verkkokurssit <noreply@parempitasapaino.fi>',
            'X-Priority: 1',
            'Importance: High',
        ];
        if ( wp_mail( $this->user_email, $this->subject, $this->message, $headers ) ) {
            Warning::instance()->generate_warning('email_warning_trigger', __("We've send an email. Please check.", 'balance-testing'), 'success');
        } else {
            error_log(
                sprintf(
                    'Email Sending Failed For: %s | Subject: %s',
                    (string) $this->user_email,
                    (string) $this->subject
                )
            );
        }
    }

    /**
     * Set user email.
     */
    public function set_user_mail( $email ) {
        $this->user_email = $email;
    }

    /**
     * Set message.
     */
    public function set_message( $message ) {
        $this->message = $message;
    }

    /**
     * Set subject.
     */
    public function set_subject( $subject ) {
        $this->subject = $subject;
    }
}
