<?php

use BalanceTesting\Utils;
    $page_id        = get_the_ID();
    $page_permalink = get_the_permalink( $page_id );
    $action         = get_query_var( 'action' );
    $user_id = get_current_user_id();
    $test_round = Utils::instance()->get_round($user_id);
    if(empty($action)) {
        $action = 'initial-assessment';
    }
?>
<nav class="bt-student-account-nav">
   <ul>
        <?php if( !$test_round ) : ?>
        <li>
            <a href="<?php echo bt_query_arg_action_link( 'initial-assessment', $page_permalink ); ?>" class="<?php echo ( 'initial-assessment' === $action ) ? 'active' : ''; ?>">
                <?php echo esc_html__( 'Alkukysely', 'balance-testing' ); ?>
            </a>
        </li>
        <?php endif; ?>

        <?php if($test_round) : ?>
            <li style="display: none;">
                <a href="<?php echo bt_query_arg_action_link( 'dashboard', $page_permalink ); ?>" class="<?php echo ( 'dashboard' === $action || empty( $action ) ) ? 'active' : ''; ?>">
                    <?php echo esc_html__( 'Hallintapaneeli', 'balance-testing' ); ?>
                </a>
            </li>
            <li>
                <a href="<?php echo bt_query_arg_action_link( 'balance-tests', $page_permalink ); ?>" class="<?php echo ( 'balance-tests' === $action ) ? 'active' : ''; ?>">
                    <?php echo esc_html__( 'Tasapainotestit', 'balance-testing' ); ?>
                </a>
            </li>
            <li>
                <a href="<?php echo bt_query_arg_action_link( 'exercises', $page_permalink ); ?>" class="<?php echo ( 'exercises' === $action ) ? 'active' : ''; ?>">
                    <?php echo esc_html__( 'Harjoitukset', 'balance-testing' ); ?>
                </a>
            </li>
            <li>
                <a href="<?php echo bt_query_arg_action_link( 'progress-checkin', $page_permalink ); ?>" class="<?php echo ( 'progress-checkin' === $action ) ? 'active' : ''; ?>">
                    <?php echo esc_html__( 'Seuraa edistymistä', 'balance-testing' ); ?>
                </a>
            </li>
        <?php endif; ?>

    </ul>
</nav>
