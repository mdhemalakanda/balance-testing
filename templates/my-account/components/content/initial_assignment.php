<?php

use BalanceTesting\Schedule\TestAccessMail;
    $page_id        = get_the_ID();
    $page_permalink = get_the_permalink( $page_id );
    /**
     * Check if user has already initial assignment in round 1.
     */
    $user_id = get_current_user_id();
    $test_round = get_user_meta($user_id, 'test_round', true);
?>
<div id="initial-assessment" class="account-tab-panel">
    <?php if( !$test_round ) : ?>
    <form method="post" class="bt-form customer_question_form" style="padding: 0;">
        <input type="hidden" name="round" value="1">
        <?php wp_nonce_field('user_question', 'user_question', true, true); ?>
        <input type="hidden" name="user_question_form_submit">
        <div class="bt-form-group">
            <label class="bt-label" for="etunimi"><?php echo esc_html__('Etunimi', 'balance-testing'); ?></label>
            <input placeholder="Etunimi" type="text" name="etunimi" id="etunimi">
        </div>
        <div class="bt-form-group">
            <label class="bt-label"  for="ika"><?php echo esc_html__('Ikä', 'balance-testing'); ?></label>
            <input placeholder="Ikä" type="number" name="ika" id="ika">
        </div>
        <div class="bt-form-group">
            <h3 class="bt-label"  for="tavallisimmin"><?php echo esc_html__("Kärsinkö tavallisimmin? (Valitse sopivin vaihtoehto)", 'balance-testing'); ?></h3>
            <div class="bt-radio-group">
                <div class="bt-radio-group-single-styled" style="margin-bottom: 10px;">
                    <input type="radio" name="tavallisimmin" value="Keinuttavasta eli laivankansihuimauksesta." id="user-info-1">
                    <label for="user-info-1"><?php echo esc_html__('Keinuttavasta eli laivankansihuimauksesta.', 'balance-testing'); ?></label>
                </div>
                <div class="bt-radio-group-single-styled" style="margin-bottom: 10px;">
                    <input type="radio" name="tavallisimmin" id="user-info-2" value="Kiertohuimauksesta">
                    <label for="user-info-2"><?php echo esc_html__('Kiertohuimauksesta', 'balance-testing'); ?></label>
                </div>
                <div class="bt-radio-group-single-styled" style="margin-bottom: 10px;">
                    <input type="radio" name="tavallisimmin" id="user-info-3" value="Epätasapainon ja/tai huteruuden tunteesta">
                    <label for="user-info-3"><?php echo esc_html__('Epätasapainon ja/tai huteruuden tunteesta', 'balance-testing'); ?></label>
                </div>
            </div>
        </div>
        <?php include __DIR__ . '/../partials/symptom-assessment-questions.php'; ?>
        <div class="bt-form-group">
            <h3 class="bt-label" ><?php echo esc_html__("Kuinka pitkään olet kärsinyt huimauksesta ja/tai epätasapaino-oireista?(Valitse sopivin vaihtoehto)", 'balance-testing'); ?></h3>
            <div class="bt-radio-group">
                <div class="bt-radio-group-single bt-radio-group-single-styled" style="margin-bottom: 12px;">
                    <input type="radio" name="user_symptom" value="3 viikkoa – 3 kuukautta" id="user_symptom-1">
                    <label for="user_symptom-1"><?php echo esc_html__('3 viikkoa – 3 kuukautta', 'balance-testing'); ?></label>
                </div>
                <div class="bt-radio-group-single bt-radio-group-single-styled" style="margin-bottom: 12px;">
                    <input type="radio" name="user_symptom" value="3 kuukautta – 1 vuosi" id="user_symptom-2">
                    <label for="user_symptom-2"><?php echo esc_html__('3 kuukautta – 1 vuosi', 'balance-testing'); ?></label>
                </div>
                <div class="bt-radio-group-single bt-radio-group-single-styled" style="margin-bottom: 12px;">
                    <input type="radio" name="user_symptom" value="1 vuosi – 3 vuotta" id="user_symptom-3">
                    <label for="user_symptom-3"><?php echo esc_html__("1 vuosi – 3 vuotta", 'balance-testing'); ?></label>
                </div>
                <div class="bt-radio-group-single bt-radio-group-single-styled" style="margin-bottom: 12px;">
                    <input type="radio" name="user_symptom" value="3-10 vuotta" id="user_symptom-4">
                    <label for="user_symptom-4"><?php echo esc_html__("3-10 vuotta", 'balance-testing'); ?></label>
                </div>
                <div class="bt-radio-group-single bt-radio-group-single-styled" style="margin-bottom: 12px;">
                    <input type="radio" name="user_symptom" value="Yli 10 vuotta" id="user_symptom-5">
                    <label for="user_symptom-5"><?php echo esc_html__("Yli 10 vuotta", 'balance-testing'); ?></label>
                </div>
            </div>
        </div>
        <div class="bt-form-group">
            <h3 class="bt-label" ><?php echo esc_html__("Miten huimaus ja/tai epätasapaino-oireesi yleensä esiintyvät? (Valitse sopivin vaihtoehto) ", 'balance-testing'); ?></h3>
            <div class="bt-radio-group">
                <div class="bt-radio-group-single bt-radio-group-single-styled" style="margin-bottom: 12px;">
                    <input type="radio" name="dizziness_symptom" value="<?php echo "Enemmän päivittäisenä, jatkuvana tai säännöllisenä oireena" ?>" id="dizziness_symptom-1">
                    <label for="dizziness_symptom-1"><?php echo esc_html__('Enemmän päivittäisenä, jatkuvana tai säännöllisenä oireena', 'balance-testing'); ?></label>
                </div>
                <div class="bt-radio-group-single bt-radio-group-single-styled" style="margin-bottom: 12px;">
                    <input type="radio" name="dizziness_symptom" value="<?php echo "Episodimaisesti eli kohtauksittain, jolloin oireettomia jaksoja esiintyy kohtausten välillä"; ?>" id="dizziness_symptom-2">
                    <label for="dizziness_symptom-2"><?php echo esc_html__('Episodimaisesti eli kohtauksittain, jolloin oireettomia jaksoja esiintyy kohtausten välillä', 'balance-testing'); ?></label>
                </div>
            </div>
        </div>
        <div class="bt-form-group">
            <h3 class="bt-label" ><?php echo esc_html__("Oletko huomannut, että lihaskuntosi on heikentynyt tai että väsyisit aiempaa nopeammin fyysisessä rasituksessa?", 'balance-testing'); ?></h3>
            <div class="bt-radio-group">
                <div class="bt-radio-group-single bt-radio-group-single-styled" style="margin-bottom: 12px;">
                    <input type="radio" name="user_activity" value="yes" id="user_activity-1">
                    <label for="user_activity-1"><?php echo esc_html__('Kyllä', 'balance-testing'); ?></label>
                </div>
                <div class="bt-radio-group-single bt-radio-group-single-styled" style="margin-bottom: 12px;">
                    <input type="radio" name="user_activity" value="no" id="user_activity-2">
                    <label for="user_activity-2"><?php echo esc_html__('Ei', 'balance-testing'); ?></label>
                </div>
            </div>
        </div>
        <div class="bt-form-group">
            <h3 class="bt-label" ><?php echo esc_html__("Kärsitkö huolestuneisuudesta, kuormittuneisuudesta tai pelosta, joka liittyy huimaukseen tai epätasapainon tunteeseen?", 'balance-testing'); ?></h3>
            <div class="bt-radio-group">
                <div class="bt-radio-group-single bt-radio-group-single-styled" style="margin-bottom: 12px;">
                    <input type="radio" name="user_second_activity" value="yes" id="user_second_activity-1">
                    <label for="user_second_activity-1"><?php echo esc_html__('Kyllä', 'balance-testing'); ?></label>
                </div>
                <div class="bt-radio-group-single bt-radio-group-single-styled" style="margin-bottom: 12px;">
                    <input type="radio" name="user_second_activity" value="no" id="user_second_activity-2">
                    <label for="user_second_activity-2"><?php echo esc_html__('Ei', 'balance-testing'); ?></label>
                </div>
            </div>
        </div>
        <div class="bt-form-group">
            <label for="diagnosis_info" class="bt-label" ><?php echo esc_html__("Oletko saanut huimaukseen liittyviä diagnooseja? Kirjoita diagnoosit sekä arvioidut vuosiluvut alle. Esim. Hyvänlaatuinen asentohuimaus, 2015 ja 2021.", 'balance-testing'); ?></label>
            <textarea name="diagnosis_info" id="diagnosis_info" placeholder="Kirjoita vastauksesi tähän"></textarea>
        </div>
        <button type="submit"><?php echo esc_html__('Lähetä lomake', 'balance-testing'); ?></button>
    </form>
    <?php else: ?>
        <div class="bt-alert bt-alert-success">
            <h2>
                <?php
                    if( absint($test_round) < 4 ): 
                        $page_id        = get_queried_object_id();
                        $page_permalink = get_the_permalink( $page_id );

                        // redirect to test page.
                        wp_redirect( $page_permalink . '?action=progress-checkin' );
                        /**
                         * This will give access to browse progress.
                         */
                    echo wp_kses_post(
                        sprintf(
                            __('Olet täyttänyt kyselyn. Aloita %s.', 'text-domain'),
                            '<a href="' . esc_url( bt_query_arg_action_link( 'balance-tests', $page_permalink ) ) . '">testauskierros '.$test_round.'</a>'
                        )
                    );
                    else:
                      echo wp_kses_post(
                            sprintf(
                                __('Hienoa! Olet tehnyt kaikki tarvittavat testit. %s.', 'text-domain'),
                                '<a href="' . esc_url( bt_query_arg_action_link( 'progress-checkin', $page_permalink ) ) . '">Näytä tila</a>'
                            )
                        );
                    endif;
                ?>
            </h2>
        </div>
    <?php endif; ?>
</div>
