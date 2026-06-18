<?php

use BalanceTesting\Schedule\TestAccessMail;
use BalanceTesting\TestScale;
use BalanceTesting\Utils;
/**
 * This page is responsible all test.
 * 
 * @since 1.0
 */
$user_id = get_current_user_id();
$test_round = get_user_meta($user_id, 'test_round', true);
$round_int = absint($test_round);

// Permission first — skip test query when the round is already complete.
$is_user_permitted = Utils::instance()->is_user_permitted_for_test($user_id);
if ( ! $is_user_permitted ) {
    Utils::instance()->send_permitted_user_to_schedule_mail($user_id);
}

$test_query = null;

if ( $is_user_permitted ) {
    $test_query = Utils::instance()->resolve_next_test_query( $user_id );
}
?>

<div id="balance-tests" class="account-tab-panel">
    <?php if( absint($test_round) <= 3 ) : ?>
        <?php if( !empty($test_round) ) : ?>
            <?php
                do_action('test_result_warning');
            ?>
            <?php if( $is_user_permitted ) : ?>
                <?php if( $test_query && $test_query->have_posts() ) : ?>
                <form method="post">
                <?php
                    while($test_query->have_posts()) {
                        $test_query->the_post();
                        $test_id = get_the_ID();
                        $test_video_embed = function_exists('get_field') ? get_field('test_video_embed', $test_id): '';
                        $test_uploaded_url = function_exists('get_field') ? get_field('test_upload_video', $test_id): '';
                        // Fallback to native post meta when ACF helper is unavailable
                        // or when field values were stored directly as post meta.
                        if ( empty($test_video_embed) ) {
                            $test_video_embed = get_post_meta($test_id, 'test_video_embed', true);
                        }
                        if ( empty($test_uploaded_url) ) {
                            $test_uploaded_url = get_post_meta($test_id, 'test_upload_video', true);
                        }
                        $has_video = !empty($test_video_embed) || !empty($test_uploaded_url);
                        $images = function_exists('get_field') ? get_field('images', $test_id): '';
                        $test_scale_key = TestScale::instance()->get_scale_key_for_test( $test_id );
                        $test_scale = TestScale::instance()->get_scale( $test_scale_key );
                        ?>
                        <div class="bt-test-box">
                            <h2 class="bt-test-title"><?php the_title(); ?></h2>
                            <div class="bt-test-instructions">
                                <?php the_content(); ?>
                            </div>
                            <?php if( $has_video ) : ?>
                            <div class="bt-test-video">
                                <?php if(!empty($test_video_embed)) : ?>
                                    <?php echo $test_video_embed; ?>
                                <?php endif; ?>
                                <?php if(!empty($test_uploaded_url)) : ?>
                                    <video src="<?php echo esc_url($test_uploaded_url); ?>" controls></video>
                                <?php endif; ?>
                            </div>
                            <?php else : ?>
                                <?php
                                error_log(
                                    sprintf(
                                        'Missing test video content for test_id=%d, user_id=%d',
                                        absint($test_id),
                                        absint($user_id)
                                    )
                                );
                                ?>
                            <?php endif; ?>

                            <?php if(!empty($images)) : ?>
                                <div class="bt-test-images">
                                    <?php foreach( $images as $image ) : ?>
                                        <img src="<?php echo esc_url($image['url']); ?>" alt="">
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            

                            <div class="bt-form-group">

                            <div class="bt-user-rating-box">
                                <p><?php echo esc_html__('Arvioi testin vaikeustaso alla olevalla asteikolla ') ?></p>
                                <div id="test-rating"></div>
                                <input type="hidden" name="user_id" value="<?php echo esc_attr($user_id); ?>">
                                <input type="hidden" name="test_id" value="<?php echo esc_attr($test_id); ?>">
                                <input type="hidden" name="user_balance_test_rating">
                            </div>
                            <?php wp_nonce_field('balance_test', 'balance_test', true, true); ?>
                            <p id="rating-message"></p>
                            <div class="bt-my-account-info-single">

                                <table class="bt-my-account-progress-table bt-test-scale-table bt-test-scale-table--<?php echo esc_attr( $test_scale['slug'] ); ?>" style="margin-bottom: 10px;">
                                    <thead>
                                        <tr>
                                            <?php foreach ( $test_scale['headers'] as $header ) : ?>
                                                <th><?php echo esc_html( $header ); ?></th>
                                            <?php endforeach; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ( $test_scale['rows'] as $row ) : ?>
                                            <tr<?php echo 6 === (int) $row['level'] ? ' class="bt-test-scale-row--impossible"' : ''; ?>>
                                                <td><?php echo esc_html( (string) $row['level'] ); ?></td>
                                                <td><?php echo esc_html( $row['name'] ); ?></td>
                                                <td><?php echo esc_html( $row['description'] ); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <button class="bt-user-rating-btn" style="width: 100%;"><?php echo esc_html__('Seuraava testi ', 'balance-testing'); ?></button>
                        </div>
                        <?php } ?>
                </form>
                <?php else: ?>
                    <?php Utils::instance()->send_permitted_user_to_schedule_mail($user_id); ?>
                    <div class="bt-alert bt-alert-warning"><?php echo esc_html__("You've taken all tests already.", 'balance-testing'); ?></div>
                <?php endif; ?>
            <?php else: ?>
                <?php if( (1 === absint($test_round)) || (2 === absint($test_round)) ) : ?>
                <div class="bt-alert">
                    <h2><?php echo __( 'Tämä riittää tältä erää, kiitos!', 'balance-testing' ); ?></h2>
                    <h2><?php echo esc_html__('Lähetän sinulle testituloksiin perustuvan harjoitusohjelman sähköpostitse lähipäivinä.', 'balance-testing'); ?></h2>
                    <h2><?php echo esc_html__('Tästä kymmenen päivän kuluttua saat viestin, jossa pyydetään vastaamaan lyhyeen kyselyyn harjoittelun vaikutuksista ja tekemään uudelleen tasapainotestejä. Näiden perusteella laadin sinulle seuraavat harjoitukset.', 'balance-testing'); ?></h2>
                    <h2><?php echo esc_html__('Jos sinulla on kysyttävää tai haluat antaa palautetta, voit ottaa minuun helposti yhteyttä:', 'balance-testing'); ?> <a href="https://selkakuntoutus.fi/yhteydenotot" style="color: #1a5e95; text-decoration: none; font-weight: bold;"><?php echo esc_html__('selkakuntoutus.fi/yhteydenotot', 'balance-testing'); ?></a></h2>
                </div>
                <?php elseif( 3 === absint($test_round) ) :
                    $test_completed = get_user_meta( $user_id, 'test_completed', true );
                    if( ! $test_completed ) {
                        update_user_meta($user_id, 'disable_progress_questions', false);
                    }
                    $disable_progress_questions = get_user_meta( $user_id, 'disable_progress_questions', true );
                    ?>
                    <?php if( $disable_progress_questions ) : ?>
                    <div class="bt-alert">
                        <h2><?php echo __( 'Tämä riittää tältä erää, kiitos!', 'balance-testing' ); ?></h2>
                        <h2><?php echo esc_html__('Lähetän sinulle testituloksiin perustuvan harjoitusohjelman sähköpostitse lähipäivinä.', 'balance-testing'); ?></h2>
                        <h2><?php echo esc_html__('Tästä kymmenen päivän kuluttua saat viestin, jossa pyydetään vastaamaan viimeisen kerran kyselyyn harjoittelun vaikutuksista.', domain: 'balance-testing'); ?></h2>
                        <h2><?php echo esc_html__('Jos sinulla on kysyttävää tai haluat antaa palautetta, voit ottaa minuun helposti yhteyttä: selkakuntoutus.fi/yhteydenotot', 'balance-testing'); ?></h2>
                        <h2><?php echo esc_html__('Lämmin kiitos osallistumisesta. Kannattaa edelleen jatkaa harjoituksia!', 'balance-testing'); ?> </h2>
                    </div>
                    <?php else:
                        update_user_meta($user_id, 'round_extended_question_access', false);
                        ?>
                        <div class="bt-alert">
                            <h2><?php echo __( 'Tämä riittää tältä erää, kiitos!', 'balance-testing' ); ?></h2>
                            <h2><?php echo esc_html__('Lähetän sinulle testituloksiin perustuvan harjoitusohjelman sähköpostitse lähipäivinä.', 'balance-testing'); ?></h2>
                            <h2><?php echo esc_html__('Tästä kymmenen päivän kuluttua saat viestin, jossa pyydetään vastaamaan viimeisen kerran kyselyyn harjoittelun vaikutuksista.', 'balance-testing'); ?></h2>
                            <h2><?php echo esc_html__('Jos sinulla on kysyttävää tai haluat antaa palautetta, voit ottaa minuun helposti yhteyttä: ', 'balance-testing'); ?> <a href="https://selkakuntoutus.fi/yhteydenotot" target="_blank">selkakuntoutus.fi/yhteydenotot</a></h2>
                            <h2><?php echo esc_html__('Lämmin kiitos osallistumisesta. Kannattaa edelleen jatkaa harjoituksia!', 'balance-testing'); ?> </h2>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>
        <?php else: ?>
            <div class="bt-alert"><?php echo __( 'Please fill the question first!', 'balance-testing' ); ?></div>
        <?php endif; ?>
    <?php else: 
        $is_completed_mail_send = get_user_meta($user_id, 'has_email_sent_completed_warning_to_administrator', true);
        if(empty($is_completed_mail_send)) {
            TestAccessMail::instance()->send_completed_mail_to_administrator($user_id);
            update_user_meta($user_id, 'has_email_sent_completed_warning_to_administrator', true);
        }
        ?>
        <div class="bt-alert"><?php echo __( 'Hienoa! Olet tehnyt kaikki tarvittavat testit.', 'balance-testing' ); ?></div>
    <?php endif; ?>
</div>