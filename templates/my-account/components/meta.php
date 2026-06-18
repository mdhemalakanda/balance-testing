<?php
    $user_id = get_current_user_id();
    $user = get_user( $user_id );
    $display_name = '';
    $email = '';
    if ( $user ) {
        $display_name  = $user->user_login;
        $email = $user->user_email;
    }
?>
<?php if(!empty($display_name)) : ?>
    <h3 class="bt-student-account-user-name"><?php echo esc_html($display_name); ?></h3>
<?php endif; ?>
<?php if(!empty($email)) : ?>
<p class="bt-student-account-user-email"><?php echo esc_html($email); ?></p>
<?php endif; ?>