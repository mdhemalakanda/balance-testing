<?php
namespace BalanceTesting\Menu;

use BalanceTesting\Exception\Warning;
use BalanceTesting\SingletonTrait;
use BalanceTesting\Table\UserTable;

/**
 * Creates an admin menu page to display users who have taken tests,
 * with searchable and sortable table.
 *
 * @since 1.0
 */
defined('ABSPATH') || exit;

class UserMenu {
    use SingletonTrait;
    private const SETTINGS_OPTION_KEY = 'balance_testing_round_invite_schedule_settings';
    private const SETTINGS_GROUP = 'balance_testing_round_invite_schedule_group';
    private const SETTINGS_PAGE = 'balance-testing-round-invite-schedule';

    public function init() {
        add_action('admin_menu', [ $this, 'create_user_table_menu' ]);
        add_action('admin_init', [ $this, 'handle_clear_test' ]);
        add_action('admin_init', array($this, 'blt_register_test_settings'));
        add_action('admin_init', array($this, 'blt_add_test_section'));
        add_action('admin_init', array($this, 'blt_add_test_fields'));
    }

    /**
     * Create user table menu.
     */

    public function create_user_table_menu() {
        add_submenu_page(
            'edit.php?post_type=test',
            __('Settings', 'balance-testing'),
            __('Settings', 'balance-testing'),
            'manage_options', 
            'settings',
            [ $this, 'display_user_table' ]
        );
    }

    /**
     * Handle clear test action on admin_init hook
     */
    public function handle_clear_test() {
        // Check if clear_test is set
        if (empty($_POST['clear_test'])) {
            return;
        }
        // Verify permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'balance-testing'));
        }

        // Get the user ID from the hidden input
        $user_id = isset($_POST['clear_test']) ? absint($_POST['clear_test']) : 0;
        if ($user_id === 0) {
            return;
        }
        
        // Settings page "Clear Test" should permanently remove data.
        $this->delete_user_data($user_id, 'delete');

        Warning::instance()->generate_warning('test_user_delete_warning', __("Käyttäjätesti onnistui selkeästi!", 'balance-testing'));
    }

    public function delete_user_data( $user_id, $delete_type = 'trash' ) {
        global $wpdb;
        $table = $wpdb->prefix . 'user_ratings';
        $posts = $this->get_user_question_post_ids($user_id);



        if ($delete_type === 'trash') {
            $wpdb->update($table, ['status' => 'trash'], ['user_id' => $user_id], ['%s']);

            foreach ($posts as $post_id) {
                wp_trash_post($post_id);
            }
            return;
        }  else if ($delete_type === 'publish') {
            $wpdb->update($table, ['status' => 'publish'], ['user_id' => $user_id], ['%s']);
            foreach ($posts as $post_id) {
                wp_untrash_post($post_id);
                wp_update_post(['ID' => $post_id, 'post_status' => 'publish']);
            }
            return;
        }
        elseif ($delete_type === 'delete') {
            $wpdb->delete($table, ['user_id' => $user_id], ['%d']);
            // remove user metas.
            delete_user_meta($user_id, 'test_completed');
            delete_user_meta($user_id, 'disable_progress_questions');
            delete_user_meta($user_id, 'round_2_mail_scheduled');
            delete_user_meta($user_id, 'round_3_mail_scheduled');
            delete_user_meta($user_id, 'round_4_mail_scheduled');
            delete_user_meta($user_id, 'round_extended_question_access');
            delete_user_meta($user_id, 'round_extended_question_access_hash');
            delete_user_meta($user_id, 'round_3_question_access_hash');
            delete_user_meta($user_id, 'round_4_question_access_hash');
            delete_user_meta($user_id, 'test_round');
            delete_user_meta($user_id, 'has_email_sent_completed_warning_to_administrator');
            delete_user_meta($user_id, \BalanceTesting\RoundLimits::USER_ROUND_RULES_META_KEY);
            delete_user_meta($user_id, 'round_1_low_rating_admin_mail_sent');
            delete_user_meta($user_id, 'round_2_low_rating_admin_mail_sent');
            delete_user_meta($user_id, 'round_3_low_rating_admin_mail_sent');
            delete_user_meta($user_id, 'bt_exercises_visible');
            delete_user_meta($user_id, 'bt_exercises_visible_at');
            delete_user_meta($user_id, 'bt_exercises_displayed_user_mail_sent');
            delete_user_meta($user_id, 'round_1_exercises_suggested');
            delete_user_meta($user_id, 'round_2_exercises_suggested');
            delete_user_meta($user_id, 'round_3_exercises_suggested');
            delete_user_meta($user_id, 'round_1_exercise_suggest_admin_mail_sent');
            delete_user_meta($user_id, 'round_2_exercise_suggest_admin_mail_sent');
            delete_user_meta($user_id, 'round_3_exercise_suggest_admin_mail_sent');

            $assignments_table = $wpdb->prefix . 'user_exercise_assignments';
            $wpdb->delete( $assignments_table, array( 'user_id' => $user_id ), array( '%d' ) );
    
            
            foreach ($posts as $post_id) {
                wp_delete_post($post_id, true);
            }
        }
    }

    public function display_user_table() {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'balance-testing'));
        }

        // Display success message if test was cleared
        if (isset($_GET['cleared']) && $_GET['cleared'] === 'true') {
            echo '<div class="notice notice-success is-dismissible"><p>';
            esc_html_e('Test cleared successfully.', 'balance-testing');
            echo '</p></div>';
        }

        // Get users who have taken tests
        $test_taken_users = $this->get_test_taken_users();
        $users_data = [];
        
        foreach ($test_taken_users as $user_id) {
            $user = get_user($user_id);
            if (empty($user)) {
                continue;
            }

            $users_data[] = [
                'id'    => $user_id,
                'name'  => $user->user_login,
                'email' => $user->user_email,
            ];
        }

        // Prepare data with action column
        $data = [];
        foreach ($users_data as $row) {
            $row['action'] = $this->get_action_button($row['id']);
            $data[] = $row;
        }

        // Handle search and sorting
        $data = $this->process_data($data);

        // Set up WP_List_Table
        $table = UserTable::instance();
        $table->set_data($data);
        $table->prepare_items();

        // Display the page
        ?>
         <div class="wrap">
            <h1><?php esc_html_e('Round Invite Schedule Settings', 'balance-testing'); ?></h1>
            <p><?php esc_html_e('Configure when each follow-up invite email should be sent after a round is completed.', 'balance-testing'); ?></p>
            <form method="post" action="options.php">
                <?php
                settings_fields(self::SETTINGS_GROUP);
                do_settings_sections(self::SETTINGS_PAGE);
                submit_button(__('Save schedule settings', 'balance-testing'));
                ?>
            </form>
        </div>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e('Test Users', 'balance-testing'); ?></h1>
            <?php do_action('test_user_delete_warning'); ?>
            <!-- GET Form for search and display -->
            <form method="post" class="search-box-form">
                <input type="hidden" name="page" value="settings">
                <input type="hidden" name="post_type" value="test">

                <?php $table->search_box(__('Search Users', 'balance-testing'), 'user'); ?>

                <?php $table->display(); ?>
            </form>
        </div>
        <?php
    }

    public function blt_register_test_settings() {
        register_setting(
            self::SETTINGS_GROUP,
            self::SETTINGS_OPTION_KEY,
            [
                'type'              => 'array',
                'sanitize_callback' => [ $this, 'sanitize_round_invite_schedule_settings' ],
                'default'           => $this->get_default_round_invite_schedule_settings(),
            ]
        );
    }

    public function blt_add_test_section() {
        add_settings_section(
            'balance_testing_round_invite_schedule_section',
            '',
            '__return_false',
            self::SETTINGS_PAGE
        );
    }

    public function blt_add_test_fields() {
        add_settings_field(
            'round_2_invite_delay',
            __('Round 2 invite delay', 'balance-testing'),
            [ $this, 'blt_render_delay_field' ],
            self::SETTINGS_PAGE,
            'balance_testing_round_invite_schedule_section',
            [
                'key' => 'round_2_invite_delay',
            ]
        );

        add_settings_field(
            'round_3_invite_delay',
            __('Round 3 invite delay', 'balance-testing'),
            [ $this, 'blt_render_delay_field' ],
            self::SETTINGS_PAGE,
            'balance_testing_round_invite_schedule_section',
            [
                'key' => 'round_3_invite_delay',
            ]
        );

        add_settings_field(
            'round_4_invite_delay',
            __('Final invite delay (round 4)', 'balance-testing'),
            [ $this, 'blt_render_delay_field' ],
            self::SETTINGS_PAGE,
            'balance_testing_round_invite_schedule_section',
            [
                'key' => 'round_4_invite_delay',
            ]
        );

        add_settings_field(
            'enable_auto_access',
            __('Enable auto access (without email click)', 'balance-testing'),
            [ $this, 'render_enable_auto_access_field' ],
            self::SETTINGS_PAGE,
            'balance_testing_round_invite_schedule_section'
        );

        add_settings_field(
            'auto_access_delay',
            __('Auto access delay', 'balance-testing'),
            [ $this, 'blt_render_delay_field' ],
            self::SETTINGS_PAGE,
            'balance_testing_round_invite_schedule_section',
            [
                'key' => 'auto_access_delay',
            ]
        );

        add_settings_field(
            'exercise_recommended_days',
            __('Exercise recommended period (days)', 'balance-testing'),
            [ $this, 'render_exercise_recommended_days_field' ],
            self::SETTINGS_PAGE,
            'balance_testing_round_invite_schedule_section'
        );
    }

    public function sanitize_round_invite_schedule_settings($input) {
        $defaults = $this->get_default_round_invite_schedule_settings();
        $input = is_array($input) ? $input : [];
        $valid_units = array_keys($this->get_delay_unit_choices());

        $sanitize_delay = function ($delay, $default_value, $default_unit) use ($valid_units) {
            $delay = is_array($delay) ? $delay : [];
            $value = isset($delay['value']) ? absint($delay['value']) : $default_value;
            $unit = isset($delay['unit']) ? sanitize_key($delay['unit']) : $default_unit;

            if (!in_array($unit, $valid_units, true)) {
                $unit = $default_unit;
            }

            return [
                'value' => $value,
                'unit'  => $unit,
            ];
        };

        return [
            'round_2_invite_delay' => $sanitize_delay(
                $input['round_2_invite_delay'] ?? [],
                $defaults['round_2_invite_delay']['value'],
                $defaults['round_2_invite_delay']['unit']
            ),
            'round_3_invite_delay' => $sanitize_delay(
                $input['round_3_invite_delay'] ?? [],
                $defaults['round_3_invite_delay']['value'],
                $defaults['round_3_invite_delay']['unit']
            ),
            'round_4_invite_delay' => $sanitize_delay(
                $input['round_4_invite_delay'] ?? [],
                $defaults['round_4_invite_delay']['value'],
                $defaults['round_4_invite_delay']['unit']
            ),
            'enable_auto_access' => !empty($input['enable_auto_access']) ? 1 : 0,
            'auto_access_delay' => $sanitize_delay(
                $input['auto_access_delay'] ?? [],
                $defaults['auto_access_delay']['value'],
                $defaults['auto_access_delay']['unit']
            ),
            'exercise_recommended_days' => max( 1, absint( $input['exercise_recommended_days'] ?? $defaults['exercise_recommended_days'] ) ),
        ];
    }

    private function get_default_round_invite_schedule_settings() {
        return [
            'round_2_invite_delay' => [
                'value' => 12,
                'unit'  => 'days',
            ],
            'round_3_invite_delay' => [
                'value' => 12,
                'unit'  => 'days',
            ],
            'round_4_invite_delay' => [
                'value' => 12,
                'unit'  => 'days',
            ],
            'enable_auto_access' => 1,
            'auto_access_delay' => [
                'value' => 10,
                'unit'  => 'days',
            ],
            'exercise_recommended_days' => 12,
        ];
    }

    private function get_round_invite_schedule_settings() {
        $settings = get_option(self::SETTINGS_OPTION_KEY, []);
        return wp_parse_args($settings, $this->get_default_round_invite_schedule_settings());
    }

    private function get_delay_unit_choices() {
        return [
            'seconds' => __('Second(s)', 'balance-testing'),
            'minutes' => __('Minute(s)', 'balance-testing'),
            'hours' => __('Hour(s)', 'balance-testing'),
            'days' => __('Day(s)', 'balance-testing'),
            'weeks' => __('Week(s)', 'balance-testing'),
        ];
    }

    public function blt_render_delay_field($args) {
        $key = isset($args['key']) ? sanitize_key($args['key']) : '';
        if (empty($key)) {
            return;
        }

        $settings = $this->get_round_invite_schedule_settings();
        $current = isset($settings[$key]) && is_array($settings[$key])
            ? $settings[$key]
            : ['value' => 0, 'unit' => 'days'];
        $value = isset($current['value']) ? absint($current['value']) : 0;
        $unit = isset($current['unit']) ? sanitize_key($current['unit']) : 'days';
        $units = $this->get_delay_unit_choices();
        ?>
        <input
            type="number"
            min="0"
            class="small-text"
            name="<?php echo esc_attr(self::SETTINGS_OPTION_KEY . '[' . $key . '][value]'); ?>"
            value="<?php echo esc_attr($value); ?>"
        />
        <select name="<?php echo esc_attr(self::SETTINGS_OPTION_KEY . '[' . $key . '][unit]'); ?>">
            <?php foreach ($units as $unit_key => $unit_label) : ?>
                <option value="<?php echo esc_attr($unit_key); ?>" <?php selected($unit, $unit_key); ?>>
                    <?php echo esc_html($unit_label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php
    }

    public function render_enable_auto_access_field() {
        $settings = $this->get_round_invite_schedule_settings();
        $is_enabled = !empty($settings['enable_auto_access']);
        ?>
        <label for="balance-testing-enable-auto-access">
            <input
                id="balance-testing-enable-auto-access"
                type="checkbox"
                name="<?php echo esc_attr(self::SETTINGS_OPTION_KEY . '[enable_auto_access]'); ?>"
                value="1"
                <?php checked($is_enabled); ?>
            />
            <?php esc_html_e('Grant questionnaire access automatically after configured delay, even if user did not open email link.', 'balance-testing'); ?>
        </label>
        <?php
    }

    public function render_exercise_recommended_days_field() {
        $settings = $this->get_round_invite_schedule_settings();
        $value    = isset( $settings['exercise_recommended_days'] ) ? absint( $settings['exercise_recommended_days'] ) : 12;
        ?>
        <input
            type="number"
            min="1"
            class="small-text"
            name="<?php echo esc_attr( self::SETTINGS_OPTION_KEY . '[exercise_recommended_days]' ); ?>"
            value="<?php echo esc_attr( $value ); ?>"
        />
        <p class="description">
            <?php esc_html_e( 'Days shown in the user countdown after exercises are displayed.', 'balance-testing' ); ?>
        </p>
        <?php
    }

    /**
     * Generate action button HTML for clearing test
     */
    private function get_action_button($user_id) {
        // Create a simple form for the action button
        $nonce = wp_create_nonce('clear_test_action');
        
        return sprintf(
            '<form method="post" style="display:inline;">
                <input type="hidden" name="clear_test" value="%d" />
                <input type="hidden" name="clear_test_nonce" value="%s" />
                <button type="submit" class="button button-secondary" onclick="return confirm(\'%s\');">%s</button>
            </form>',
            esc_attr($user_id),
            esc_attr($nonce),
            esc_attr(__('Are you sure you want to clear this user\'s test?', 'balance-testing')),
            esc_html__('Clear Test', 'balance-testing')
        );
    }

    private function get_test_taken_users() {
        global $wpdb;
        $table = $wpdb->prefix . 'user_ratings';

        $rating_user_ids = $wpdb->get_col("SELECT DISTINCT user_id FROM $table WHERE user_id IS NOT NULL AND user_id != 0");
        $question_user_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT pm.meta_value
                FROM {$wpdb->postmeta} pm
                INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                WHERE pm.meta_key = %s
                AND p.post_type = %s
                AND pm.meta_value IS NOT NULL
                AND pm.meta_value != ''",
                'user_id',
                'user_questions'
            )
        );

        $merged_ids = array_unique(array_merge((array) $rating_user_ids, (array) $question_user_ids));
        $merged_ids = array_map('absint', $merged_ids);
        $merged_ids = array_filter($merged_ids);

        return array_values($merged_ids);
    }

    private function get_user_question_post_ids($user_id) {
        $post_statuses = ['publish', 'future', 'draft', 'pending', 'private', 'trash'];

        $by_meta = get_posts([
            'post_type'      => 'user_questions',
            'posts_per_page' => -1,
            'post_status'    => $post_statuses,
            'fields'         => 'ids',
            'meta_query'     => [
                [
                    'key'     => 'user_id',
                    'value'   => $user_id,
                    'compare' => '=',
                ],
            ],
        ]);

        $by_author = get_posts([
            'post_type'      => 'user_questions',
            'posts_per_page' => -1,
            'post_status'    => $post_statuses,
            'fields'         => 'ids',
            'author'         => $user_id,
        ]);

        // Legacy fallback: some entries may only store user id inside user_info meta array.
        $all_question_posts = get_posts([
            'post_type'      => 'user_questions',
            'posts_per_page' => -1,
            'post_status'    => $post_statuses,
            'fields'         => 'ids',
        ]);

        $by_user_info_meta = [];
        foreach ((array) $all_question_posts as $post_id) {
            $user_info = get_post_meta($post_id, 'user_info', true);
            $meta_user_id = isset($user_info['user_id']) ? absint($user_info['user_id']) : 0;
            if ($meta_user_id === absint($user_id)) {
                $by_user_info_meta[] = $post_id;
            }
        }

        return array_values(
            array_unique(
                array_merge((array) $by_meta, (array) $by_author, (array) $by_user_info_meta)
            )
        );
    }

    /**
     * Process data: search and sorting
     */
    private function process_data(array $data): array {
        // Search
        if (!empty($_GET['s'])) {
            $search_term = strtolower(sanitize_text_field($_GET['s']));
            $data = array_filter($data, function ($item) use ($search_term) {
                return strpos(strtolower($item['name']), $search_term) !== false ||
                       strpos(strtolower($item['email']), $search_term) !== false;
            });
        }

        // Sorting
        $orderby = isset($_GET['orderby']) ? sanitize_key($_GET['orderby']) : '';
        $order   = isset($_GET['order']) ? sanitize_key($_GET['order']) : 'asc';
        $order   = strtolower($order) === 'desc' ? 'desc' : 'asc';

        if (in_array($orderby, ['name', 'email'], true)) {
            usort($data, function ($a, $b) use ($orderby, $order) {
                $comparison = $a[$orderby] <=> $b[$orderby];
                return $order === 'desc' ? -$comparison : $comparison;
            });
        }

        return array_values($data); // re-index array
    }

    /**
     * Search callback (kept for compatibility if needed)
     */
    public function datatable_search_by_name($item) {
        $search_name = strtolower(sanitize_text_field($_REQUEST['s'] ?? ''));
        return strpos(strtolower($item['name']), $search_name) !== false;
    }
}