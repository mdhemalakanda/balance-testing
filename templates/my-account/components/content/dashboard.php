<?php

    use BalanceTesting\Utils;
    $page_permalink = get_the_permalink(get_the_ID());
    $user_id = get_current_user_id();
    $user = get_user($user_id);
    $display_name = '';
    $email = '';
    if ( $user ) {
        $display_name  = $user->user_login;
        $email = $user->user_email;
    }
    $is_user_permitted = Utils::instance()->is_user_permitted_for_test($user_id);
    $username = $user->user_nicename;
    $test_round = get_user_meta($user_id, 'test_round', true);
    $disable_progress_questions = get_user_meta($user_id, 'disable_progress_questions', true);
    $user_state = 'primary_questions';
    if( 1 === absint($test_round) ) {
        $user_state = 'in_round_test';
    } elseif( 2 === absint($test_round) ) {
        $user_state = 'in_round_test';
    }
    if( !$disable_progress_questions ) {
        $user_state = 'in_progress_questions';
    }
    $test_count = Utils::instance()->get_test_count();
    $user_test_by_round_count = Utils::instance()->get_user_test_by_round();

    /**
     * If user in test round, user have not permission to more test and user can't access progress question
     * Then user in view_result mode.
     */
    if( !empty($test_round) && !$is_user_permitted && $disable_progress_questions ) {
        $user_state = 'view_result';
    }
    if( $user_state === 'view_result' && absint($test_round) === 3 ) {
        $user_state = 'complete';
    }
    if( empty($test_round) ) {
        $test_round = 0;
    }
    $test_records = Utils::instance()->get_user_test_record($user_id);
?>
<div id="user-dashboard" class="account-tab-panel">
    <h2 class="bt-student-account-title"><?php echo esc_html__(' Tervetuloa ', 'balance-testing'); ?> <?php echo esc_html(strtoupper($display_name. '!')); ?></h2>
    <h5 class="bt-my-account-quick-links-title" style="display: inline-block;"><?php echo esc_html__('Tehtävät', 'balance-testing'); ?></h5>
    <ul class="bt-my-account-quick-links">
        <?php if( ('primary_questions' === $user_state) && absint($test_round <= 3) ) : ?>
            <li><a href="<?php echo bt_query_arg_action_link( 'initial-assessment', $page_permalink ); ?>"><?php echo esc_html__('Täytä Kyselyt (Fill out Questionnaires)', 'balance-testing'); ?></a></li>
        <?php elseif( 'in_round_test' === $user_state ) : ?>
            <li><a href="<?php echo bt_query_arg_action_link( 'balance-tests', $page_permalink ); ?>"><?php echo esc_html__('Aloita testaus', 'balance-testing'); ?></a></li>
        <?php elseif( ('in_progress_questions' === $user_state) || absint($test_round >= 4) ) : ?>
            <li>
                <a href="<?php echo bt_query_arg_action_link( 'progress-checkin', $page_permalink ); ?>">
                    <?php echo esc_html__('Katso keskeneräiset tehtävät', 'balance-testing'); ?>
                </a>
            </li>
        <?php else: ?>
            <li><a href="<?php echo bt_query_arg_action_link( 'balance-tests', $page_permalink ); ?>"><?php echo esc_html__('Katso Tulokset', 'balance-testing'); ?></a></li>
        <?php endif; ?>
    </ul>
    <div class="bt-my-account-info">
        <div class="bt-my-account-info-single">
            <?php if( ('complete' != $user_state) && (absint($test_round) <= 3) ) : ?>
            <h2><?php echo esc_html__('Testauskierroksen numero', 'balance-testing'); ?>: <span><?php echo esc_html__("Testauskierros $test_round / 3", 'balance-testing'); ?></span></h2>
            <?php else: ?>
                <h2><?php echo esc_html__('Testauskierroksen numero', 'balance-testing'); ?>: <span><?php echo esc_html__("Testauskierroksen numero: Kaikki testit tehty.", 'balance-testing'); ?></span></h2>
            <?php endif; ?>
        </div>
        <div class="bt-my-account-info-single">
            <h2><?php echo esc_html__('Katso keskeneräiset tehtävät', 'balance-testing'); ?>: 
            <?php if( ('primary_questions' === $user_state) && absint($test_round <= 3) ) : ?>
            <a href="<?php echo bt_query_arg_action_link( 'initial-assessment', $page_permalink ); ?>"><?php echo esc_html__('Täytä Kyselyt (Fill out Questionnaires)', 'balance-testing'); ?></a>
            <?php elseif( 'in_round_test' === $user_state ) : ?>
                <a href="<?php echo bt_query_arg_action_link( 'balance-tests', $page_permalink ); ?>"><?php echo esc_html__('Aloita testaus', 'balance-testing'); ?></a>
            <?php elseif( ('in_progress_questions' === $user_state) || (absint($test_round >= 4))) : ?>
                    <a href="<?php echo bt_query_arg_action_link( 'progress-checkin', $page_permalink ); ?>">
                        <?php echo esc_html__('Katso keskeneräiset tehtävät', 'balance-testing'); ?>
                    </a>
            <?php else: ?>
                <a href="<?php echo bt_query_arg_action_link( 'balance-tests', $page_permalink ); ?>"><?php echo esc_html__('Katso Tulokset', 'balance-testing'); ?></a>
            <?php endif; ?>
        </h2>
        </div>
        <?php if(!empty($test_records)) : ?>
        <div class="bt-my-account-info-single">
            <h2><?php echo esc_html__('Testitulokset', 'balance-testing'); ?></h2>
            <?php
                $tests_by_round = [];
                $all_rounds = [];

                foreach( $test_records as $record ) {
                    $test_id = intval($record->test_id);
                    $round = intval($record->round);
                    $rating = esc_html($record->rating);

                    $tests_by_round[$test_id][$round] = $rating;

                    if (!in_array($round, $all_rounds)) {
                        $all_rounds[] = $round;
                    }
                }

                ?>

                <table class="bt-my-account-progress-table display-record">
                    <thead>
                        <tr>
                            <th>Testin nimi</th>
                            <?php foreach($all_rounds as $round): ?>
                                <th>Testauskierros <?php echo $round; ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($tests_by_round as $test_id => $rounds_data): ?>
                            <tr>
                                <td><?php echo esc_html(get_the_title($test_id)); ?></td>
                                <?php foreach($all_rounds as $round): ?>
                                    <td>
                                        <?php 
                                        echo isset($rounds_data[$round]) ? $rounds_data[$round] : '(N/A)'; 
                                        ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

        </div>
        <?php endif; ?>
    </div>
</div>
