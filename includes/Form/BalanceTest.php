<?php
namespace BalanceTesting\Form;

use BalanceTesting\Exception\Warning;
use BalanceTesting\SingletonTrait;
use BalanceTesting\Utils;
/**
 * This class will help for handle balance testing.
 * 
 * @since 1.0
 */
defined('ABSPATH') || exit;

class BalanceTest {
    use SingletonTrait;

    public function init() {
        add_action('init', array( $this, 'handle_balance_testing_form' ));
    }

    public function handle_balance_testing_form() {
        global $wpdb;
        $wpdb->hide_errors();

        $table = $wpdb->prefix. 'user_ratings';
        if( isset( $_POST['user_balance_test_rating'] ) ) {
            check_ajax_referer('balance_test', 'balance_test');
            $test_id = !empty($_POST['test_id']) ? sanitize_text_field($_POST['test_id']): '';
            $rating = !empty($_POST['user_balance_test_rating']) ? sanitize_text_field($_POST['user_balance_test_rating']): '';
            
            $user_id = get_current_user_id();
            $round = absint(get_user_meta($user_id, 'test_round', true));

            // Check if user already rated on this.
            $is_user_rated = Utils::instance()->has_user_rated_on_test( $user_id, $test_id , $round);
            if( $is_user_rated ) {
                Warning::instance()->generate_warning(
                    'test_result_warning',
                    __('Olet jo tehnyt tämän testin tässä kierroksessa.', 'balance-testing')
                );
                return;
            }

            if ( ! Utils::instance()->is_user_permitted_for_test( $user_id ) ) {
                return;
            }
            
            // if rating is empty, then show a warning and return.
            if( absint($round) > 1 ) {
                if( empty( $rating ) ) {
                    add_action( 'test_result_warning', function() {
                        ob_start(); ?>
                        <div class="bt-alert">
                            <h2><?php echo esc_html__('Anna ensin arvio', 'balance-testing'); ?></h2>
                        </div>
                        <?php echo ob_get_clean();
                    } );
                    return;
                }
            } else {
                if( empty( $rating ) ) {
                    add_action( 'test_result_warning', function() {
                        ob_start(); ?>
                        <div class="bt-alert">
                            <h2><?php echo esc_html__('Anna ensin arvio', 'balance-testing'); ?></h2>
                        </div>
                        <?php echo ob_get_clean();
                    } );
                    return;
                }
            }

            $wpdb->insert(
            $table,
            array(
                'test_id' => $test_id,
                'rating' => $rating,
                'user_id' => $user_id,
                'round' => $round,
            ),
            array(
                '%d', // test_id
                '%d', // rating
                '%d', // user_id
                '%d', // round
            )
        );
            if ( $wpdb->last_error ) {
                error_log(print_r($wpdb->last_error, true));
                Warning::instance()->generate_warning('test_result_warning', __("Something error on rating test. Please try differnent one.", 'balance-testing'));
                return;
            }
            if( ( $round === 2 ) || ( $round === 3 ) ) {
                // $this->find_and_update_re_used( $test_id, $round );
                // spacetech start
                $this->find_and_update_re_used($test_id, $round, $user_id);
                // spacetech end
            }

            if ( ! Utils::instance()->is_user_permitted_for_test( $user_id ) ) {
                Utils::instance()->send_permitted_user_to_schedule_mail( $user_id );
            }
        }
    }

    /**
     * This will find test_id and add re-used ( if it used again ).
     * @return void
     */
    // private function find_and_update_re_used( $test_id, $round ) {
    //     global $wpdb;
    //     $table = $wpdb->prefix. 'user_ratings';
    //     $rating_id = $wpdb->get_var( 
    //         $wpdb->prepare(
    //             "SELECT attempt_id FROM {$table} WHERE test_id = %s AND round = %d AND re_used = 0", 
    //             $test_id,
    //             $round - 1
    //         ) 
    //     );

    //     $round_decrease = $round - 1;

    //     if(!empty($rating_id)) {
    //         $wpdb->update(
    //             $table,
    //             array('re_used' => $round_decrease),
    //             array(
    //                 'attempt_id' => $rating_id
    //             )
    //         );
    //     }
    // }

    // spacetech start
    private function find_and_update_re_used($test_id, $round, $user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'user_ratings';
    
        $rating_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT attempt_id
                 FROM {$table}
                 WHERE user_id = %d
                   AND test_id = %d
                   AND round = %d
                   AND re_used = 0
                 ORDER BY attempt_id ASC
                 LIMIT 1",
                $user_id,
                $test_id,
                $round - 1
            )
        );
    
        if (!empty($rating_id)) {
            $wpdb->update(
                $table,
                array('re_used' => $round - 1),
                array('attempt_id' => $rating_id),
                array('%d'),
                array('%d')
            );
        }
    }
    // spacetech end
}