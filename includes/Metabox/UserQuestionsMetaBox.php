<?php
namespace BalanceTesting\Metabox;

use BalanceTesting\SingletonTrait;

/**
 * Metabox
 * 
 * This file will use to create metabox for User Questions Post Type (CPT)
 * 
 * @since 1.0
 */
defined('ABSPATH') || exit;

class UserQuestionsMetaBox {
    use SingletonTrait;

    public function init() {
        add_action('add_meta_boxes', array( $this, 'user_info_meta_boxes' ));
    }

    public function user_info_meta_boxes() {
        add_meta_box(
            'user_information',
            __('User Information', 'balance-testing'),
            array( $this, 'display_user_info' ),
            'user_questions',
            'normal',
            'default'
        );
    }

    public function display_user_info( $post ) {
        $post_id = $post->ID;
        $user_info = get_post_meta($post_id, 'user_info', true);
        
        $user_id                       = !empty($user_info['user_id']) ? absint($user_info['user_id']) : '';
        $user_url                      = !empty($user_info['user_url']) ? esc_url_raw($user_info['user_url']) : '';
        $etunimi                       = !empty($user_info['etunimi']) ? sanitize_text_field($user_info['etunimi']) : '';
        $ika                           = !empty($user_info['ika']) ? sanitize_text_field($user_info['ika']) : '';
        $tavallisimmin                 = !empty($user_info['tavallisimmin']) ? sanitize_text_field($user_info['tavallisimmin']) : '';

        $oireiden_voimakkuus           = isset( $user_info['oireiden_voimakkuus'] ) && '' !== $user_info['oireiden_voimakkuus'] ? sanitize_text_field( $user_info['oireiden_voimakkuus'] ) : '';
        $vaikutus_toimintakykyyn       = isset( $user_info['vaikutus_toimintakykyyn'] ) && '' !== $user_info['vaikutus_toimintakykyyn'] ? sanitize_text_field( $user_info['vaikutus_toimintakykyyn'] ) : '';

        $user_symptom                  = !empty($user_info['user_symptom']) ? sanitize_text_field($user_info['user_symptom']) : '';
        $dizziness_symptom             = !empty($user_info['dizziness_symptom']) ? sanitize_text_field($user_info['dizziness_symptom']) : '';
        $user_activity                 = !empty($user_info['user_activity']) ? sanitize_text_field($user_info['user_activity']) : '';
        $user_second_activity          = !empty($user_info['user_second_activity']) ? sanitize_text_field($user_info['user_second_activity']) : '';
        $diagnosis_info                = !empty($user_info['diagnosis_info']) ? sanitize_text_field($user_info['diagnosis_info']) : '';
        $exercise_days                = !empty($user_info['exercise_days']) ? sanitize_text_field($user_info['exercise_days']) : '';
        $exercise_frequency                = !empty($user_info['exercise_frequency']) ? sanitize_text_field($user_info['exercise_days']) : '';


        if(empty($user_info)) {
            echo __('Please fill the form first', 'balance-testing');
            return;
        }
        ?>
        <style>
            .bt-user-info-admin-box li {
                font-size: 16px;
            }

            .bt-user-info-admin-box li span {
                font-weight: 700;
            }
        </style>
        <div class="bt-user-info-admin-box">
           <ul>
            <li>
                    <span><?php echo esc_html__('Round: ', 'balance-testing'); ?></span>
                    <?php echo esc_html(get_post_meta($post_id, 'test_round', true)); ?>
                </li>
                <?php if ( !empty($user_id) ) : ?>
                <li>
                    <span><?php echo esc_html__('User ID: ', 'balance-testing'); ?></span>
                    <?php echo esc_html($user_id); ?>
                </li>
                <?php endif; ?>

                <?php if ( !empty($etunimi) ) : ?>
                <li>
                    <span><?php echo esc_html__('Etunimi', 'balance-testing'); ?>:</span>
                    <a href="<?php echo esc_url($user_url); ?>"><?php echo esc_html($etunimi); ?></a>
                </li>
                <?php endif; ?>

                <?php if ( !empty($ika) ) : ?>
                <li>
                    <span><?php echo esc_html__('Ikä', 'balance-testing'); ?>:</span>
                    <?php echo esc_html($ika); ?>
                </li>
                <?php endif; ?>

                <?php if ( !empty($tavallisimmin) ) : ?>
                <li>
                    <span><?php echo esc_html__('Kärsinkö tavallisimmin?', 'balance-testing'); ?>:</span>
                    <?php echo esc_html($tavallisimmin); ?>
                </li>
                <?php endif; ?>

                <?php if ( '' !== $oireiden_voimakkuus ) : ?>
                <li>
                    <span><?php echo esc_html__( 'I. Oireiden voimakkuus (0–10)', 'balance-testing' ); ?>:</span>
                    <?php echo esc_html( $oireiden_voimakkuus ); ?>
                </li>
                <?php endif; ?>

                <?php if ( '' !== $vaikutus_toimintakykyyn ) : ?>
                <li>
                    <span><?php echo esc_html__( 'II. Vaikutus toimintakykyyn (0–10)', 'balance-testing' ); ?>:</span>
                    <?php echo esc_html( $vaikutus_toimintakykyyn ); ?>
                </li>
                <?php endif; ?>

                <?php if ( !empty($user_symptom) ) : ?>
                <li>
                    <span><?php echo esc_html__('Kuinka pitkään olet kärsinyt huimauksesta ja/tai epätasapaino-oireista?', 'balance-testing'); ?>:</span>
                    <?php echo esc_html($user_symptom); ?>
                </li>
                <?php endif; ?>

                <?php if ( !empty($dizziness_symptom) ) : ?>
                <li>
                    <span><?php echo esc_html__('Miten huimaus ja/tai epätasapaino-oireesi yleensä esiintyvät?', 'balance-testing'); ?>:</span>
                    <?php echo esc_html($dizziness_symptom); ?>
                </li>
                <?php endif; ?>

                <?php if ( !empty($user_activity) ) : ?>
                <li>
                    <span><?php echo esc_html__('Oletko huomannut, että lihaskuntosi on heikentynyt tai että väsyisit aiempaa nopeammin fyysisessä rasituksessa?', 'balance-testing'); ?>:</span>
                    <?php echo esc_html($user_activity); ?>
                </li>
                <?php endif; ?>

                <?php if ( !empty($user_second_activity) ) : ?>
                <li>
                    <span><?php echo esc_html__('Kärsitkö huolestuneisuudesta, kuormittuneisuudesta tai pelosta, joka liittyy huimaukseen tai epätasapainon tunteeseen?', 'balance-testing'); ?>:</span>
                    <?php echo esc_html($user_second_activity); ?>
                </li>
                <?php endif; ?>

                <?php if ( !empty($diagnosis_info) ) : ?>
                <li>
                    <span><?php echo esc_html__('Kuinka monena päivänä teit suositeltuja harjoituksia?', 'balance-testing'); ?>:</span>
                    <?php echo esc_html($diagnosis_info); ?>
                </li>
                <?php endif; ?>

                <?php if ( !empty($exercise_days) ) : ?>
                <li>
                    <span><?php echo esc_html__('Kuinka monena päivänä teit suositeltuja harjoituksia? ', 'balance-testing'); ?>:</span>
                    <?php echo esc_html($exercise_days); ?>
                </li>
                <?php endif; ?>

                <?php if ( !empty($exercise_frequency) ) : ?>
                <li>
                    <span><?php echo esc_html__('Niinä päivinä, kun teit harjoituksia, kuinka monta kertaa päivässä keskimäärin teit ne? ', 'balance-testing'); ?>:</span>
                    <?php echo esc_html($exercise_frequency); ?>
                </li>
                <?php endif; ?>

            </ul>


        </div>
        <?php
    }
}