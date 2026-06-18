<?php
namespace BalanceTesting\Shortcode;

use BalanceTesting\SingletonTrait;
/**
 * This file will use any type of login/registration features.
 * 
 * @since 1.0
 */
defined('ABSPATH') || exit;

class LoginForm {
    use SingletonTrait;

    public function init() {
        add_shortcode('bt_account', array($this, 'display_account'));
        add_action('template_redirect', array($this, 'redirect_user_account'));
        add_action('template_redirect', array($this, 'handle_frontend_login_post'), 1);
        add_action('wp_login_failed', array($this, 'handle_login_failed'));
        add_filter('authenticate', array($this, 'skip_wordfence_2fa_for_non_admins'), 26, 3);
        add_filter('authenticate', array($this, 'handle_empty_credentials'), 30, 3);
        add_action('init', array($this, 'handle_front_password_reset'));
        add_filter('login_form_bottom', array($this, 'append_login_hidden_fields'), 10, 2);
        add_action('wp_enqueue_scripts', array($this, 'enqueue_wordfence_login_assets'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_login_page_styles'));
    }

    public function redirect_user_account() {
        if (is_user_logged_in() && $this->page_has_login_shortcode()) {
            wp_safe_redirect(home_url('/bt-user-account/'), 302);
            exit;
        }
    }

    /**
     * Process login on the frontend page (same URL as the form) instead of wp-login.php.
     */
    public function handle_frontend_login_post() {
        if (is_admin() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST)) {
            return;
        }

        if ('POST' !== (isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : '')) {
            return;
        }

        if (empty($_POST['bt_login_page'])) {
            return;
        }

        $login_page = $this->get_login_page_url_from_request();

        // Step 2: 2FA code only (credentials stored in transient after step 1).
        $token = isset($_POST['bt_2fa_token']) ? sanitize_key(wp_unslash($_POST['bt_2fa_token'])) : '';
        if ('' !== $token && array_key_exists('wfls-token', $_POST)) {
            $this->complete_pending_2fa_login($login_page, $token);
            return;
        }

        if (!isset($_POST['wp-submit'])) {
            return;
        }

        if (empty($_POST['log'])) {
            return;
        }

        $username = $this->normalize_login_username(wp_unslash($_POST['log']));
        $password = isset($_POST['pwd']) ? wp_unslash($_POST['pwd']) : '';

        if ('' === $username || '' === $password) {
            $this->redirect_to_login_page($login_page, array('login' => 'empty'));
        }

        $remember = !empty($_POST['rememberme']);
        $user     = wp_signon(
            array(
                'user_login'    => $username,
                'user_password' => $password,
                'remember'      => $remember,
            ),
            $this->use_secure_auth_cookie()
        );

        if (is_wp_error($user)) {
            $code = $user->get_error_code();

            if ($this->maybe_complete_login_bypassing_2fa($login_page, $code, $username, $password, $remember)) {
                return;
            }

            if ('wfls_twofactor_required' === $code) {
                $token = $this->create_pending_2fa_login($username, $password, $remember);
                $query = array(
                    'login'    => '2fa_required',
                    'bt_token' => $token,
                );
                if (!empty($_POST['redirect_to'])) {
                    $query['redirect_to'] = wp_validate_redirect(
                        wp_unslash($_POST['redirect_to']),
                        home_url('/bt-user-account/')
                    );
                }
                $this->redirect_to_login_page($login_page, $query);
            }

            if ('wfls_twofactor_failed' === $code) {
                $this->redirect_to_login_page($login_page, array('login' => '2fa_failed'));
            }

            $this->redirect_to_login_page($login_page, array('login' => 'failed'));
        }

        $redirect_to = !empty($_POST['redirect_to'])
            ? wp_validate_redirect(wp_unslash($_POST['redirect_to']), home_url('/bt-user-account/'))
            : home_url('/bt-user-account/');

        $this->redirect_to_login_page($redirect_to);
    }

    public function handle_login_failed($username) {
        // Submissions with bt_login_page are handled on the frontend; skip wp-login.php redirect.
        if (!empty($_POST['bt_login_page'])) {
            return;
        }

        // Wordfence needs a second step (2FA). Do not redirect away from wp-login.php.
        if ($this->is_wordfence_2fa_pending($username)) {
            return;
        }

        $redirect_url = $this->get_login_page_url_from_request();
        $status       = $this->is_wordfence_2fa_invalid($username) ? '2fa_failed' : 'failed';
        $this->redirect_to_login_page($redirect_url, array('login' => $status));
    }

    public function enqueue_login_page_styles() {
        if (is_user_logged_in() || !$this->page_has_login_shortcode()) {
            return;
        }

        wp_enqueue_style('bt-style');
    }

    /**
     * Wordfence login.js uses AJAX and can fail silently on some hosts (blank page).
     * 2FA is handled via PHP on the custom form instead.
     */
    public function enqueue_wordfence_login_assets() {
        return;
    }

    public function handle_empty_credentials($user, $username, $password) {
        if (empty($_POST['bt_login_page'])) {
            return $user;
        }

        if (empty($username) || empty($password)) {
            $this->redirect_to_login_page(
                $this->get_login_page_url_from_request(),
                array('login' => 'empty')
            );
        }

        return $user;
    }

    private function is_omatestaus_page() {
        $queried_object = get_queried_object();
        return is_page('omatestaus') || ($queried_object && isset($queried_object->post_name) && 'omatestaus' === $queried_object->post_name);
    }

    private function is_omatestaus_referrer() {
        if ( empty($_SERVER['HTTP_REFERER']) ) {
            return false;
        }
        $referrer_path = wp_parse_url(wp_unslash($_SERVER['HTTP_REFERER']), PHP_URL_PATH);
        return '/omatestaus/' === trailingslashit((string) $referrer_path);
    }

    /**
     * Canonical URL for the login form (no query string — avoids POST/cache issues on live hosts).
     */
    private function get_login_page_url() {
        if (is_front_page()) {
            return trailingslashit(home_url('/'));
        }

        if (is_singular()) {
            $permalink = get_permalink();
            if ($permalink) {
                return $permalink;
            }
        }

        return home_url('/omatestaus/');
    }

    private function get_current_url() {
        return $this->get_login_page_url();
    }

    private function use_secure_auth_cookie() {
        return is_ssl() || 0 === strpos(home_url(), 'https://');
    }

    private function normalize_login_username($login) {
        $login = is_string($login) ? trim($login) : '';

        if ('' === $login) {
            return '';
        }

        if (false !== strpos($login, '@')) {
            return sanitize_email($login);
        }

        return sanitize_user($login, false);
    }

    private function redirect_to_login_page($url, $query_args = array()) {
        if (!empty($query_args)) {
            $url = add_query_arg($query_args, $url);
        }

        $url = wp_validate_redirect($url, home_url('/'));
        wp_safe_redirect($url, 303);
        exit;
    }

    private function get_pending_2fa_transient_key($token) {
        return 'bt_2fa_' . preg_replace('/[^a-zA-Z0-9]/', '', $token);
    }

    private function create_pending_2fa_login($username, $password, $remember) {
        $token = wp_generate_password(32, false, false);
        set_transient(
            $this->get_pending_2fa_transient_key($token),
            array(
                'user_login'    => $username,
                'user_password' => $password,
                'remember'      => (bool) $remember,
            ),
            10 * MINUTE_IN_SECONDS
        );

        return $token;
    }

    private function get_pending_2fa_login($token) {
        if ('' === $token) {
            return null;
        }

        $pending = get_transient($this->get_pending_2fa_transient_key($token));

        return is_array($pending) ? $pending : null;
    }

    private function delete_pending_2fa_login($token) {
        if ('' === $token) {
            return;
        }

        delete_transient($this->get_pending_2fa_transient_key($token));
    }

    private function complete_pending_2fa_login($login_page, $token) {
        $pending = $this->get_pending_2fa_login($token);

        if (!$pending) {
            $this->redirect_to_login_page($login_page, array('login' => '2fa_expired'));
        }

        $username = $pending['user_login'];
        $password = $pending['user_password'];
        $remember = !empty($pending['remember']);

        $user = wp_signon(
            array(
                'user_login'    => $username,
                'user_password' => $password,
                'remember'      => $remember,
            ),
            $this->use_secure_auth_cookie()
        );

        if (is_wp_error($user)) {
            $code = $user->get_error_code();

            if ('wfls_twofactor_failed' === $code || 'wfls_twofactor_required' === $code) {
                $this->redirect_to_login_page(
                    $login_page,
                    array(
                        'login'    => 'wfls_twofactor_failed' === $code ? '2fa_failed' : '2fa_required',
                        'bt_token' => $token,
                    )
                );
            }

            $this->delete_pending_2fa_login($token);
            $this->redirect_to_login_page($login_page, array('login' => 'failed'));
        }

        $this->delete_pending_2fa_login($token);

        $redirect_to = !empty($_POST['redirect_to'])
            ? wp_validate_redirect(wp_unslash($_POST['redirect_to']), home_url('/bt-user-account/'))
            : home_url('/bt-user-account/');

        $this->redirect_to_login_page($redirect_to);
    }

    private function is_2fa_step_display() {
        $login_status = isset($_GET['login']) ? sanitize_key(wp_unslash($_GET['login'])) : '';
        if (!in_array($login_status, array('2fa_required', '2fa_failed'), true)) {
            return false;
        }

        $token = isset($_GET['bt_token']) ? sanitize_key(wp_unslash($_GET['bt_token'])) : '';

        return '' !== $token && null !== $this->get_pending_2fa_login($token);
    }

    private function get_2fa_step_token() {
        return isset($_GET['bt_token']) ? sanitize_key(wp_unslash($_GET['bt_token'])) : '';
    }

    private function post_content_has_bt_account_shortcode($post) {
        if (!$post instanceof \WP_Post) {
            return false;
        }

        if (has_shortcode($post->post_content, 'bt_account')) {
            return true;
        }

        $elementor_data = get_post_meta($post->ID, '_elementor_data', true);
        return is_string($elementor_data) && false !== strpos($elementor_data, 'bt_account');
    }

    private function page_has_login_shortcode() {
        if ($this->is_omatestaus_page() || is_front_page()) {
            return true;
        }

        $page_id = get_queried_object_id();
        if (!$page_id && is_front_page()) {
            $page_id = (int) get_option('page_on_front');
        }

        if ($page_id) {
            return $this->post_content_has_bt_account_shortcode(get_post($page_id));
        }

        if (!is_singular()) {
            return false;
        }

        $post = get_queried_object();
        return $this->post_content_has_bt_account_shortcode($post);
    }

    private function resolve_login_user($username) {
        $username = sanitize_user($username, true);
        if ('' === $username) {
            return null;
        }

        $user = get_user_by('login', $username);
        if (!$user && false !== strpos($username, '@')) {
            $user = get_user_by('email', $username);
        }

        return $user instanceof \WP_User ? $user : null;
    }

    private function is_wordfence_login_security_available() {
        return class_exists('\WordfenceLS\Controller_Users');
    }

    /**
     * 2FA on the custom login form applies only to administrators (manage_options), not subscribers.
     */
    private function user_must_use_2fa( $user ) {
        if ( ! $user instanceof \WP_User ) {
            return false;
        }

        if ( is_multisite() && is_super_admin( $user->ID ) ) {
            return true;
        }

        return user_can( $user, 'manage_options' );
    }

    /**
     * After Wordfence authenticate: allow non-admin users to log in without 2FA.
     */
    public function skip_wordfence_2fa_for_non_admins( $user, $username, $password ) {
        if ( ! is_wp_error( $user ) ) {
            return $user;
        }

        $code = $user->get_error_code();
        if ( ! in_array( $code, array( 'wfls_twofactor_required', 'wfls_twofactor_blocked' ), true ) ) {
            return $user;
        }

        $wp_user = $this->resolve_login_user( $username );
        if ( ! $wp_user || $this->user_must_use_2fa( $wp_user ) ) {
            return $user;
        }

        $remember = ! empty( $_POST['rememberme'] );

        return $this->signon_without_wordfence_2fa( $username, $password, $remember );
    }

    private function signon_without_wordfence_2fa( $username, $password, $remember ) {
        $wf = null;

        if ( $this->is_wordfence_login_security_available() && class_exists( '\WordfenceLS\Controller_WordfenceLS' ) ) {
            $wf = \WordfenceLS\Controller_WordfenceLS::shared();
            remove_filter( 'authenticate', array( $wf, '_authenticate' ), 25 );
        }

        $user = wp_signon(
            array(
                'user_login'    => $username,
                'user_password' => $password,
                'remember'      => $remember,
            ),
            $this->use_secure_auth_cookie()
        );

        if ( $wf ) {
            add_filter( 'authenticate', array( $wf, '_authenticate' ), 25, 3 );
        }

        return $user;
    }

    /**
     * @return bool True when login completed and redirect was sent.
     */
    private function maybe_complete_login_bypassing_2fa( $login_page, $code, $username, $password, $remember ) {
        if ( ! in_array( $code, array( 'wfls_twofactor_required', 'wfls_twofactor_blocked' ), true ) ) {
            return false;
        }

        $wp_user = $this->resolve_login_user( $username );
        if ( ! $wp_user || $this->user_must_use_2fa( $wp_user ) ) {
            return false;
        }

        $user = $this->signon_without_wordfence_2fa( $username, $password, $remember );
        if ( is_wp_error( $user ) ) {
            return false;
        }

        $redirect_to = ! empty( $_POST['redirect_to'] )
            ? wp_validate_redirect( wp_unslash( $_POST['redirect_to'] ), home_url( '/bt-user-account/' ) )
            : home_url( '/bt-user-account/' );

        $this->redirect_to_login_page( $redirect_to );

        return true;
    }

    /**
     * Password was accepted but 2FA code not sent yet (Wordfence second step).
     */
    private function is_wordfence_2fa_pending($username) {
        if (!$this->is_wordfence_login_security_available()) {
            return false;
        }

        $user = $this->resolve_login_user($username);
        if (!$user || !$this->user_must_use_2fa($user) || !\WordfenceLS\Controller_Users::shared()->has_2fa_active($user)) {
            return false;
        }

        return empty($_POST['wfls-token']) || !is_string($_POST['wfls-token']);
    }

    /**
     * 2FA code was sent but rejected.
     */
    private function is_wordfence_2fa_invalid($username) {
        if (!$this->is_wordfence_login_security_available()) {
            return false;
        }

        $user = $this->resolve_login_user($username);
        if (!$user || !$this->user_must_use_2fa($user) || !\WordfenceLS\Controller_Users::shared()->has_2fa_active($user)) {
            return false;
        }

        return !empty($_POST['wfls-token']) && is_string($_POST['wfls-token']);
    }

    private function get_login_page_url_from_request() {
        $fallback_url = $this->get_login_page_url();

        if (!empty($_POST['bt_login_page'])) {
            $posted_url = esc_url_raw(wp_unslash($_POST['bt_login_page']));
            $validated  = wp_validate_redirect($posted_url, $fallback_url);
            if ($validated) {
                return remove_query_arg(array('login', 'reset', 'register', 'log'), $validated);
            }
        }

        if (!empty($_SERVER['HTTP_REFERER'])) {
            $referer_url = esc_url_raw(wp_unslash($_SERVER['HTTP_REFERER']));
            $validated   = wp_validate_redirect($referer_url, $fallback_url);
            if ($validated) {
                return remove_query_arg(array('login', 'reset', 'register', 'log'), $validated);
            }
        }

        return $fallback_url;
    }

    public function append_login_hidden_fields($content, $args) {
        if (empty($args['form_id']) || 'bt-login-form' !== $args['form_id']) {
            return $content;
        }

        $content .= '<input type="hidden" name="bt_login_page" value="' . esc_url($this->get_current_url()) . '">';
        return $content;
    }

    private function get_redirect_to_from_request() {
        if (!empty($_GET['redirect_to'])) {
            return wp_validate_redirect(wp_unslash($_GET['redirect_to']), home_url('/bt-user-account/'));
        }

        return home_url('/bt-user-account/');
    }

    private function render_2fa_step_form_markup() {
        $token   = $this->get_2fa_step_token();
        $pending = $this->get_pending_2fa_login($token);

        if (!$pending) {
            return '';
        }

        $username    = $pending['user_login'];
        $action_url  = esc_url($this->get_login_page_url());
        $redirect_to = esc_url($this->get_redirect_to_from_request());

        ob_start();
        ?>
        <form name="bt-login-2fa-form" id="bt-login-2fa-form" class="bt-login-2fa-form" action="<?php echo $action_url; ?>" method="post" autocomplete="on">
            <p class="bt-login-2fa-user">
                <?php
                echo esc_html(
                    sprintf(
                        /* translators: %s: username or email */
                        __('Kirjautuminen käyttäjälle %s', 'balance-testing'),
                        $username
                    )
                );
                ?>
            </p>
            <p class="login-2fa">
                <label for="wfls-token"><?php echo esc_html__('Wordfence 2FA -koodi', 'balance-testing'); ?></label>
                <input type="text" name="wfls-token" id="wfls-token" class="input" value="" size="6" autocomplete="one-time-code" inputmode="numeric" required />
            </p>
            <input type="hidden" name="bt_2fa_token" value="<?php echo esc_attr($token); ?>" />
            <input type="hidden" name="bt_login_page" value="<?php echo esc_url($this->get_current_url()); ?>" />
            <input type="hidden" name="redirect_to" value="<?php echo $redirect_to; ?>" />
            <p class="login-submit">
                <input type="submit" name="wp-submit" id="wp-submit" class="button button-primary" value="<?php echo esc_attr__('Kirjaudu sisään', 'balance-testing'); ?>" />
            </p>
        </form>
        <p class="bt-login-2fa-back">
            <a href="<?php echo esc_url($this->get_login_page_url()); ?>"><?php echo esc_html__('Takaisin kirjautumiseen', 'balance-testing'); ?></a>
        </p>
        <?php
        return ob_get_clean();
    }

    private function render_login_form_markup() {
        if ($this->is_2fa_step_display()) {
            return $this->render_2fa_step_form_markup();
        }

        $args = array(
            'echo'             => false,
            'redirect'         => home_url('/bt-user-account/'),
            'form_id'          => 'bt-login-form',
            'label_username'   => __('Käyttäjänimi tai sähköpostiosoite', 'balance-testing'),
            'label_password'   => __('Salasana', 'balance-testing'),
            'label_remember'   => __('Muista minut', 'balance-testing'),
            'label_log_in'     => __('Kirjaudu sisään', 'balance-testing'),
            'remember'         => true,
        );

        $form = wp_login_form($args);

        if (!$form) {
            return '';
        }

        $action_url = esc_url($this->get_login_page_url());

        $form = preg_replace(
            '/\saction=(["\']).*?\1/',
            ' action="' . $action_url . '"',
            $form,
            1
        );

        $form = preg_replace(
            '/<form([^>]*)>/',
            '<form$1 autocomplete="on">',
            $form,
            1
        );

        return $form;
    }

    private function is_password_reset_view() {
        $reset_view = isset($_GET['reset']) ? sanitize_key(wp_unslash($_GET['reset'])) : '';
        return 'form' === $reset_view;
    }

    private function get_password_reset_form_url() {
        return add_query_arg('reset', 'form', $this->get_login_page_url());
    }

    private function render_lostpassword_form_markup() {
        ob_start();
        ?>
        <form method="post" class="bt-lostpassword-form" autocomplete="off">
            <p>
                <label for="bt-user-login"><?php esc_html_e('Käyttäjänimi tai sähköpostiosoite', 'balance-testing'); ?></label>
                <input id="bt-user-login" type="text" name="bt_user_login" autocomplete="off" required>
            </p>
            <p>
                <input type="hidden" name="bt_login_page" value="<?php echo esc_url($this->get_current_url()); ?>">
                <?php wp_nonce_field('bt_front_reset_password', 'bt_reset_password_nonce'); ?>
                <button type="submit"><?php esc_html_e('Lähetä palautuslinkki', 'balance-testing'); ?></button>
            </p>
        </form>
        <p class="bt-login-back-link">
            <a href="<?php echo esc_url($this->get_login_page_url()); ?>"><?php esc_html_e('Takaisin kirjautumiseen', 'balance-testing'); ?></a>
        </p>
        <?php
        return ob_get_clean();
    }

    public function handle_front_password_reset() {
        if (is_user_logged_in() || empty($_POST['bt_reset_password_nonce'])) {
            return;
        }

        $nonce = sanitize_text_field(wp_unslash($_POST['bt_reset_password_nonce']));
        if (!wp_verify_nonce($nonce, 'bt_front_reset_password')) {
            return;
        }

        $login_page = $this->get_login_page_url_from_request();
        $user_login = isset($_POST['bt_user_login']) ? sanitize_text_field(wp_unslash($_POST['bt_user_login'])) : '';

        if (empty($user_login)) {
            wp_safe_redirect(add_query_arg('reset', 'empty', $login_page), 302);
            exit;
        }

        $result = retrieve_password($user_login);
        if (is_wp_error($result)) {
            wp_safe_redirect(add_query_arg('reset', 'failed', $login_page), 302);
            exit;
        }

        wp_safe_redirect(add_query_arg('reset', 'sent', $login_page), 302);
        exit;
    }

    private function render_login_notices() {
        $output = '';
        $login_status = isset($_GET['login']) ? sanitize_key(wp_unslash($_GET['login'])) : '';
        $reset_status = isset($_GET['reset']) ? sanitize_key(wp_unslash($_GET['reset'])) : '';

        if ('failed' === $login_status) {
            $output .= '<p class="bt-login-message bt-login-message--error">' . esc_html__('Virheellinen käyttäjätunnus tai salasana.', 'balance-testing') . '</p>';
        } elseif ('2fa_failed' === $login_status) {
            $output .= '<p class="bt-login-message bt-login-message--error">' . esc_html__('Kaksivaiheisen tunnistautumisen koodi on virheellinen tai vanhentunut. Yritä uudelleen.', 'balance-testing') . '</p>';
        } elseif ('2fa_required' === $login_status) {
            if ($this->is_2fa_step_display()) {
                $output .= '<p class="bt-login-message bt-login-message--info">' . esc_html__('Anna kaksivaiheisen tunnistautumisen koodi alla.', 'balance-testing') . '</p>';
            } else {
                $output .= '<p class="bt-login-message bt-login-message--error">' . esc_html__('Kirjautuminen vanhentui. Syötä käyttäjätunnus ja salasana uudelleen.', 'balance-testing') . '</p>';
            }
        } elseif ('2fa_expired' === $login_status) {
            $output .= '<p class="bt-login-message bt-login-message--error">' . esc_html__('Kirjautuminen vanhentui. Syötä käyttäjätunnus ja salasana uudelleen.', 'balance-testing') . '</p>';
        } elseif ('empty' === $login_status) {
            $output .= '<p class="bt-login-message bt-login-message--error">' . esc_html__('Kirjoita käyttäjätunnus ja salasana.', 'balance-testing') . '</p>';
        }

        if ('sent' === $reset_status) {
            $output .= '<p class="bt-login-message bt-login-message--success">' . esc_html__('Salasanan palautusviesti on lähetetty sähköpostiisi.', 'balance-testing') . '</p>';
        } elseif ('failed' === $reset_status) {
            $output .= '<p class="bt-login-message bt-login-message--error">' . esc_html__('Salasanan palautus epäonnistui. Tarkista käyttäjätunnus tai sähköpostiosoite.', 'balance-testing') . '</p>';
        } elseif ('empty' === $reset_status) {
            $output .= '<p class="bt-login-message bt-login-message--error">' . esc_html__('Syötä käyttäjätunnus tai sähköpostiosoite salasanan palautusta varten.', 'balance-testing') . '</p>';
        }

        $register_flag = isset( $_GET['register'] ) ? sanitize_key( wp_unslash( $_GET['register'] ) ) : '';
        if ( 'blocked' === $register_flag ) {
            $output .= '<p class="bt-login-message bt-login-message--error">' . esc_html__( 'Uuden tilin rekisteröinti on poistettu käytöstä. Pyydä tunnukset ylläpitäjältä.', 'balance-testing' ) . '</p>';
        }

        return $output;
    }

    public function display_account() {
        if (!is_user_logged_in()) {
            ob_start();
            $is_reset_view = $this->is_password_reset_view();
            $is_2fa_step   = $this->is_2fa_step_display();
            $form_classes  = 'bt-login-form login';

            if ($is_reset_view) {
                $form_classes .= ' bt-login-form--reset';
            }
            if ($is_2fa_step) {
                $form_classes .= ' bt-login-form--2fa-step';
            }

            echo '<div class="' . esc_attr($form_classes) . '">';
            echo $this->render_login_notices();

            if ($is_reset_view) {
                echo '<p class="bt-login-reset-heading">' . esc_html__('Salasanan palautus', 'balance-testing') . '</p>';
                echo $this->render_lostpassword_form_markup();
            } else {
                echo $this->render_login_form_markup();

                if (!$is_2fa_step) {
                    echo '<div class="bt-login-links">';
                    echo '<p><a href="' . esc_url($this->get_password_reset_form_url()) . '">' . esc_html__('Unohditko salasanasi?', 'balance-testing') . '</a></p>';
                    echo '</div>';
                }
            }

            echo '</div>';
            return ob_get_clean();
        }

        // User is logged in - should be redirected by redirect_user_account()
        return '';
    }
}