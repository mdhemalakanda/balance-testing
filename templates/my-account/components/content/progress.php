<?php

use BalanceTesting\Utils;
    $page_id = get_the_ID();
    $user_id = get_current_user_id();
    $round_extended_question_access = get_user_meta($user_id, 'round_extended_question_access', true);
    $disable_progress_questions = get_user_meta($user_id, 'disable_progress_questions', true);
    $page_permalink = get_the_permalink( $page_id );
    $test_round = get_user_meta($user_id, 'test_round', true);

?>
<div id="progress-checkin" class="account-tab-panel">
    <?php if( $round_extended_question_access ) : ?>
        <?php if( !$disable_progress_questions ) : ?>
            <form method="post" class="bt-form customer_question_form_2" style="padding: 0;">
                <input type="hidden" name="round" value="<?php echo absint($test_round) + 1; ?>">
                <?php wp_nonce_field('user_question', 'user_question', true, true); ?>
                <input type="hidden" name="user_question_form_submit">
                <?php include __DIR__ . '/../partials/symptom-assessment-questions.php'; ?>
                <div class="bt-form-group">
                    <h3 class="bt-label"><?php echo esc_html__("Kuinka useana päivänä teit harjoituksia keskimäärin viikon aikana?", 'balance-testing'); ?></h3>
                    <div class="bt-radio-group">
                        <div class="bt-radio-group-single bt-radio-group-single-styled" style="margin-bottom: 12px;">
                            <input type="radio" name="exercise_days" value="5" id="_exercise_days-1">
                            <label for="_exercise_days-1"><?php echo esc_html__('5 - kaikkina päivinä', 'balance-testing'); ?></label>
                        </div>
                        <div class="bt-radio-group-single bt-radio-group-single-styled" style="margin-bottom: 12px;">
                            <input type="radio" name="exercise_days" value="4" id="_exercise_days-2">
                            <label for="_exercise_days-2"><?php echo esc_html__('4 - Useimpina päivinä', 'balance-testing'); ?></label>
                        </div>
                        <div class="bt-radio-group-single bt-radio-group-single-styled" style="margin-bottom: 12px;">
                            <input type="radio" name="exercise_days" value="3" id="_exercise_days-3">
                            <label for="_exercise_days-3"><?php echo esc_html__('3 - Noin joka toinen päivä', 'balance-testing'); ?></label>
                        </div>
                        <div class="bt-radio-group-single bt-radio-group-single-styled" style="margin-bottom: 12px;">
                            <input type="radio" name="exercise_days" value="2" id="_exercise_days-4">
                            <label for="_exercise_days-4"><?php echo esc_html__('2 - Harvemmin', 'balance-testing'); ?></label>
                        </div>
                        <div class="bt-radio-group-single bt-radio-group-single-styled" style="margin-bottom: 12px;">
                            <input type="radio" name="exercise_days" value="1" id="_exercise_days-5">
                            <label for="_exercise_days-5"><?php echo esc_html__('1 - En tehnyt harjoituksia lainkaan', 'balance-testing'); ?></label>
                        </div>
                    </div>
                </div>

                <div class="bt-form-group">
                    <h3 class="bt-label"><?php echo esc_html__("Kuinka monta kertaa päivässä teit harjoituksia keskimäärin?", 'balance-testing'); ?></h3>
                    <div class="bt-radio-group">
                        <div class="bt-radio-group-single bt-radio-group-single-styled" style="margin-bottom: 12px;">
                            <input type="radio" name="exercise_frequency" value="4" id="_exercise_frequency-1">
                            <label for="_exercise_frequency-1"><?php echo esc_html__('4 - Kolme kertaa päivässä', 'balance-testing'); ?></label>
                        </div>
                        <div class="bt-radio-group-single bt-radio-group-single-styled" style="margin-bottom: 12px;">
                            <input type="radio" name="exercise_frequency" value="3" id="_exercise_frequency-2">
                            <label for="_exercise_frequency-2"><?php echo esc_html__('3 - Kaksi kertaa päivässä', 'balance-testing'); ?></label>
                        </div>
                        <div class="bt-radio-group-single bt-radio-group-single-styled" style="margin-bottom: 12px;">
                            <input type="radio" name="exercise_frequency" value="2" id="_exercise_frequency-3">
                            <label for="_exercise_frequency-3"><?php echo esc_html__('2 - Kerran päivässä', 'balance-testing'); ?></label>
                        </div>
                        <div class="bt-radio-group-single bt-radio-group-single-styled" style="margin-bottom: 12px;">
                            <input type="radio" name="exercise_frequency" value="1" id="_exercise_frequency-4">
                            <label for="_exercise_frequency-4"><?php echo esc_html__('1 - Vaihteli paljon / vaikea sanoa', 'balance-testing'); ?></label>
                        </div>
                    </div>
                </div>
                
                <button type="submit"><?php echo esc_html__('Lähetä lomake', 'balance-testing'); ?></button>
            </form>
        <?php else: 
            $user_id = get_current_user_id();
            $initial_question_progress = Utils::instance()->get_user_question_answere( $user_id, 1 ); // 1 is for initial question set
            $first_round_questions = Utils::instance()->get_user_question_answere( $user_id, 2 ); // 2 is for first round access question set
            $second_round_questions = Utils::instance()->get_user_question_answere( $user_id, 3 ); // 3 is for second round access question set
            $third_round_questions = Utils::instance()->get_user_question_answere( $user_id, 4 ); // 4 is for third round access question set

            $symptom_progress_fields = [
                'oireiden_voimakkuus' => __( 'I. Oireiden voimakkuus', 'balance-testing' ),
                'vaikutus_toimintakykyyn' => __( 'II. Vaikutus toimintakykyyn', 'balance-testing' ),
            ];
            $symptom_progress_data = [];
            foreach ( $symptom_progress_fields as $field_key => $field_label ) {
                $progress_arr = [
                    $initial_question_progress[ $field_key ] ?? 0,
                    $first_round_questions[ $field_key ] ?? 0,
                    $second_round_questions[ $field_key ] ?? 0,
                    $third_round_questions[ $field_key ] ?? 0,
                ];
                $symptom_progress_data[ $field_key ] = [
                    'label'   => $field_label,
                    'values'  => $progress_arr,
                    'count'   => Utils::instance()->calculate_total_progress( $progress_arr ),
                ];
            }
            $exercise_days_round_1 = !empty($first_round_questions['exercise_days']) ? sanitize_text_field($first_round_questions['exercise_days']): '';
            $exercise_days_round_2 = !empty($second_round_questions['exercise_days']) ? sanitize_text_field($second_round_questions['exercise_days']): '';
            $exercise_days_round_3 = !empty($third_round_questions['exercise_days']) ? sanitize_text_field($third_round_questions['exercise_days']): '';
            
            $exercise_frequency_round_1 = !empty($first_round_questions['exercise_frequency']) ? sanitize_text_field($first_round_questions['exercise_frequency']): '';
            $exercise_frequency_round_2 = !empty($second_round_questions['exercise_frequency']) ? sanitize_text_field($second_round_questions['exercise_frequency']): '';
            $exercise_frequency_round_3 = !empty($third_round_questions['exercise_frequency']) ? sanitize_text_field($third_round_questions['exercise_frequency']): '';
            
            ?>
            <div class="bt-my-account-progress-table-wrapper">
                <table class="bt-my-account-progress-table display-record table-long-data">
                    <thead>
                        <tr>
                            <th colspan="2"><?php echo esc_html__( 'Huimaus- ja epätasapaino-oireet (asteikko 0–10): 0 = ei oireita / ei haittaa, 10 = voimakkain mahdollinen oire / suurin mahdollinen haitta.', 'balance-testing' ); ?></th>
                            <th><?php echo esc_html__( '1. Harjoituskierros', 'balance-testing' ); ?></th>
                            <th><?php echo esc_html__( '2. Harjoituskierros', 'balance-testing' ); ?></th>
                            <th><?php echo esc_html__( '3. Harjoituskierros', 'balance-testing' ); ?></th>
                            <th><?php echo esc_html__( 'Progress Count', 'balance-testing' ); ?></th>
                            <th><?php echo esc_html__( 'Progress Chart', 'balance-testing' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="2"><?php echo esc_html__( 'Kuinka useana päivänä teit harjoituksia keskimäärin viikon aikana?', 'balance-testing' ); ?></td>
                            <td><?php echo esc_html( Utils::instance()->get_exercise_days_label( $exercise_days_round_1 ) ); ?></td>
                            <td><?php echo esc_html( Utils::instance()->get_exercise_days_label( $exercise_days_round_2 ) ); ?></td>
                            <td><?php echo esc_html( Utils::instance()->get_exercise_days_label( $exercise_days_round_3 ) ); ?></td>
                            <td colspan="2"></td>
                        </tr>
                        <tr>
                            <td colspan="2"><?php echo esc_html__( 'Kuinka monta kertaa päivässä teit harjoituksia keskimäärin?', 'balance-testing' ); ?></td>
                            <td><?php echo esc_html( Utils::instance()->get_exercise_frequency_label( $exercise_frequency_round_1 ) ); ?></td>
                            <td><?php echo esc_html( Utils::instance()->get_exercise_frequency_label( $exercise_frequency_round_2 ) ); ?></td>
                            <td><?php echo esc_html( Utils::instance()->get_exercise_frequency_label( $exercise_frequency_round_3 ) ); ?></td>
                            <td colspan="2"></td>
                        </tr>
                        <?php foreach ( $symptom_progress_data as $field_key => $field_data ) : ?>
                        <tr>
                            <td><?php echo esc_html( $field_data['label'] ); ?></td>
                            <td><?php echo isset( $initial_question_progress[ $field_key ] ) ? esc_html( $initial_question_progress[ $field_key ] ) : ''; ?></td>
                            <td><?php echo isset( $first_round_questions[ $field_key ] ) ? esc_html( $first_round_questions[ $field_key ] ) : ''; ?></td>
                            <td><?php echo isset( $second_round_questions[ $field_key ] ) ? esc_html( $second_round_questions[ $field_key ] ) : ''; ?></td>
                            <td><?php echo isset( $third_round_questions[ $field_key ] ) ? esc_html( $third_round_questions[ $field_key ] ) : ''; ?></td>
                            <td><?php echo esc_html( (string) $field_data['count'] ); ?></td>
                            <td><div class="progress-chart" data-progress-values="<?php echo esc_attr( wp_json_encode( $field_data['values'] ) ); ?>"></div></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if((absint($test_round) < 3) && Utils::instance()->is_user_permitted_for_test($user_id)) : ?>
            <div class="bt-alert bt-alert-success">
                <h2><?php echo wp_kses_post("Olet jo täyttänyt palautekyselyn, joten voit <a href=".bt_query_arg_action_link( 'balance-tests', $page_permalink ) ."> tehdä täältä testikierroksen $test_round</a>"); ?></h2>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    <?php else: ?>
        <div class="bt-alert"><h2><?php echo esc_html__("Aloita tekemällä tasapainotestit", 'balance-testing') ?></h2></div>
    <?php endif; ?>
</div>
