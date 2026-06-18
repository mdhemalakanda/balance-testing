<?php
namespace BalanceTesting\Form;

use BalanceTesting\SingletonTrait;
/**
 * User Questions
 * 
 * This will handle all user questions ( phase 1 ), and put data into 'user_questions' post type.
 * 
 * @since 1.0
 */
defined('ABSPATH') || exit;

class UserQuestions {
    use SingletonTrait;

    public function init() {
        add_action('init', array($this, 'handle_question_form'));
        add_action('init', array($this, 'grent_user_to_access_question_level'));
    }

    /**
     * This function will grant user to access level two questions.
     * @return void
     */
    public function grent_user_to_access_question_level() {
        $question_hash = !empty($_GET['test_question_access_key']) ? sanitize_text_field($_GET['test_question_access_key']): '';
        $user_id = get_current_user_id();
        $test_round = get_user_meta($user_id, 'test_round', true);

        /**
         * If user hash is match to url hash, then permitted to access level two questions.
        */
        if( !empty( $question_hash ) ) {
             if( 1 === absint($test_round) ) {
                $user_hash = get_user_meta($user_id, 'round_extended_question_access_hash', true);
                if( hash_equals($user_hash, $question_hash) ) {
                    update_user_meta($user_id, 'round_extended_question_access', true);
                    update_user_meta($user_id, 'disable_progress_questions', false);
                }
            } elseif( 2 === absint($test_round) ) {
                $user_hash = get_user_meta($user_id, 'round_3_question_access_hash', true);
                if( hash_equals($user_hash, $question_hash) ) {
                    update_user_meta($user_id, 'disable_progress_questions', false);
                }
            } elseif( absint($test_round) === 3) {
                $user_hash = get_user_meta($user_id, 'round_4_question_access_hash', true);
                if( hash_equals($user_hash, $question_hash) ) {
                    update_user_meta($user_id, 'round_extended_question_access', true);
                    update_user_meta($user_id, 'disable_progress_questions', false);
                }
            }
        }
        
    }

    public function handle_question_form() {
        if (
            !isset($_POST['user_question_form_submit'])
        ) {
            return;
        }
        
        if (
            !isset($_POST['user_question']) ||
            !wp_verify_nonce(
                sanitize_text_field(wp_unslash($_POST['user_question'])),
                'user_question'
            )
        ) {
            return;
        }

        if(!is_user_logged_in()) return;
        $user_info = array();
        $user_id = get_current_user_id();
        $user = get_user($user_id);
        if(empty($user)) echo 'user id is empty';
        $username = $user->user_login;
        $user_url = get_edit_user_link( $user_id );
        $user_info['user_id'] = $user_id;
        $user_info['user_url'] = $user_url;
        $current_round = absint(get_user_meta($user_id, 'test_round', true));
        $posted_round  = isset($_POST['round']) ? absint($_POST['round']) : $current_round;
        if ($posted_round < $current_round) {
            $posted_round = $current_round;
        }

        $user_info['round'] = $posted_round;

        $user_info['etunimi'] = !empty($_POST['etunimi']) ? sanitize_text_field($_POST['etunimi']): $username;
        $user_info['ika'] = !empty($_POST['ika']) ? sanitize_text_field($_POST['ika']): '';
        $user_info['tavallisimmin'] = !empty($_POST['tavallisimmin']) ? sanitize_text_field($_POST['tavallisimmin']): '';
        $user_info['oireiden_voimakkuus'] = isset( $_POST['oireiden_voimakkuus'] ) && '' !== $_POST['oireiden_voimakkuus'] ? absint( $_POST['oireiden_voimakkuus'] ) : '';
        $user_info['vaikutus_toimintakykyyn'] = isset( $_POST['vaikutus_toimintakykyyn'] ) && '' !== $_POST['vaikutus_toimintakykyyn'] ? absint( $_POST['vaikutus_toimintakykyyn'] ) : '';
        $user_info['user_symptom'] = !empty($_POST['user_symptom']) ? sanitize_text_field($_POST['user_symptom']): '';
        $user_info['dizziness_symptom'] = !empty($_POST['dizziness_symptom']) ? sanitize_text_field($_POST['dizziness_symptom']): '';
        $user_info['user_activity'] = !empty($_POST['user_activity']) ? sanitize_text_field($_POST['user_activity']): '';
        $user_info['user_second_activity'] = !empty($_POST['user_second_activity']) ? sanitize_text_field($_POST['user_second_activity']): '';
        $user_info['diagnosis_info'] = !empty($_POST['diagnosis_info']) ? sanitize_text_field($_POST['diagnosis_info']): '';
        // store some data for round 2.
        $user_info['exercise_days'] = !empty($_POST['exercise_days']) ? sanitize_text_field($_POST['exercise_days']): '';
        $user_info['exercise_frequency'] = !empty($_POST['exercise_frequency']) ? sanitize_text_field($_POST['exercise_frequency']): '';
        


        // create a post for user question.
        $args = array(
            'post_title'    => $user_info['etunimi'],
            'post_content'  => $user_info['diagnosis_info'],
            'post_status'   => 'publish',
            'post_type' => 'user_questions',
        );
        $post_id = wp_insert_post( $args );

        if ( is_wp_error( $post_id ) ) {
            error_log('Error creating post: ' . $post_id->get_error_message());
        } elseif ( $post_id ) {
            // set user round.
            update_user_meta($user_id, 'test_round', $user_info['round']);

            // add post meta.
            update_post_meta($post_id, 'user_id', $user_id);
            update_post_meta($post_id, 'user_info', $user_info);
            update_post_meta($post_id, 'test_round', $user_info['round']);

            // If request are comming from round 2, then 2/3 round question round will be disable.
            if( ( 2 === absint($user_info['round']) ) || ( 3 === absint($user_info['round']) ) ) {
                update_user_meta($user_id, 'disable_progress_questions', true);
            }
            if(  absint($user_info['round']) > 3 ) {
                update_user_meta($user_id, 'disable_progress_questions', true);
                update_user_meta($user_id, 'test_completed', true);
            }
        } else {
            error_log('Something went wrong on post');
        }
        $page_id        = get_queried_object_id();
        $page_permalink = get_the_permalink( $page_id );

        // redirect to test page.
        wp_redirect( $page_permalink . '?action=balance-tests' );
        exit;
    }
}
