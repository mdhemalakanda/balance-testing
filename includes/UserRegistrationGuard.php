<?php
namespace BalanceTesting;

defined( 'ABSPATH' ) || exit;

/**
 * Keeps public registration off and logs new WP user accounts for audit.
 * Balance Testing does not create users; this guards against core/other-plugin signup.
 */
class UserRegistrationGuard {
    use SingletonTrait;

    public function init() {
        add_filter( 'pre_option_users_can_register', array( $this, 'force_public_registration_off' ) );
        add_action( 'init', array( $this, 'sync_registration_option_off' ), 1 );
        add_action( 'user_register', array( $this, 'log_new_user_registration' ), 10, 1 );
        add_filter( 'register_url', array( $this, 'disable_register_url' ) );
        add_action( 'login_init', array( $this, 'block_login_register_action' ) );
        add_filter( 'registration_errors', array( $this, 'block_registration_errors' ), 10, 3 );
        add_action( 'register_post', array( $this, 'block_register_post' ), 1, 3 );
    }

    /**
     * Always report registration as disabled to WordPress and other plugins.
     */
    public function force_public_registration_off( $value ) {
        if ( $value ) {
            $this->write_audit_log(
                'users_can_register was requested as enabled; balance-testing forces it off.',
                array( 'requested_value' => $value )
            );
        }
        return false;
    }

    /**
     * Persist option as 0 so the admin UI stays consistent.
     */
    public function sync_registration_option_off() {
        if ( get_option( 'users_can_register' ) ) {
            update_option( 'users_can_register', 0 );
            $this->write_audit_log(
                'users_can_register option was set to 1 in the database; corrected to 0.',
                array()
            );
        }
    }

    /**
     * Log every new WordPress user (any source) to debug.log for investigation.
     *
     * @param int $user_id New user ID.
     */
    public function log_new_user_registration( $user_id ) {
        $user = get_user_by( 'id', $user_id );
        if ( ! $user ) {
            return;
        }

        $roles      = is_array( $user->roles ) ? implode( ',', $user->roles ) : '';
        $backtrace  = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 12 );
        $caller     = array();
        foreach ( $backtrace as $frame ) {
            if ( empty( $frame['file'] ) ) {
                continue;
            }
            $caller[] = basename( $frame['file'] ) . ':' . ( $frame['line'] ?? '?' );
            if ( count( $caller ) >= 6 ) {
                break;
            }
        }

        $this->write_audit_log(
            sprintf(
                'New WP user #%d registered: login=%s email=%s roles=%s',
                $user_id,
                $user->user_login,
                $user->user_email,
                $roles
            ),
            array(
                'user_id'    => $user_id,
                'ip'         => $this->get_client_ip(),
                'request_uri' => isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '',
                'call_stack' => implode( ' <- ', $caller ),
            )
        );
    }

    /**
     * Remove default register URL (wp-login.php?action=register).
     */
    public function disable_register_url( $register_url ) {
        return home_url( '/omatestaus/?register=blocked' );
    }

    /**
     * Block wp-login.php registration screen.
     */
    public function block_login_register_action() {
        if ( empty( $_REQUEST['action'] ) || 'register' !== $_REQUEST['action'] ) {
            return;
        }

        wp_safe_redirect( home_url( '/omatestaus/?register=blocked' ), 302 );
        exit;
    }

    /**
     * @param \WP_Error $errors             Errors.
     * @param string    $sanitized_user_login Login.
     * @param string    $user_email         Email.
     * @return \WP_Error
     */
    public function block_registration_errors( $errors, $sanitized_user_login, $user_email ) {
        if ( ! $errors instanceof \WP_Error ) {
            $errors = new \WP_Error();
        }
        $errors->add(
            'bt_registration_disabled',
            __( 'Public registration is disabled. Contact the site administrator for an account.', 'balance-testing' )
        );
        $this->write_audit_log(
            'Blocked registration_errors.',
            array(
                'login' => $sanitized_user_login,
                'email' => $user_email,
                'ip'    => $this->get_client_ip(),
            )
        );
        return $errors;
    }

    /**
     * Block registration form POST if it somehow runs while guard is active.
     *
     * @param string    $sanitized_user_login Login.
     * @param string    $user_email         Email.
     * @param \WP_Error $errors             Errors.
     */
    public function block_register_post( $sanitized_user_login, $user_email, $errors ) {
        if ( $errors instanceof \WP_Error ) {
            $errors->add(
                'bt_registration_disabled',
                __( 'Public registration is disabled. Contact the site administrator for an account.', 'balance-testing' )
            );
        }
        $this->write_audit_log(
            'Blocked register_post attempt.',
            array(
                'login' => $sanitized_user_login,
                'email' => $user_email,
                'ip'    => $this->get_client_ip(),
            )
        );
    }

    private function get_client_ip() {
        if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            $ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
            $ip = explode( ',', $ip );
            return trim( $ip[0] );
        }
        if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
            return sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
        }
        return '';
    }

    private function write_audit_log( $message, array $context = array() ) {
        $line = '[balance-testing registration-guard] ' . $message;
        if ( ! empty( $context ) ) {
            $line .= ' | ' . wp_json_encode( $context );
        }
        error_log( $line );
    }
}
