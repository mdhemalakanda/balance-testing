<?php
    $redirect_to = home_url( '/omatestaus/' );
?>
<div class="bt-student-account-logout">
    <a href="<?php echo wp_logout_url($redirect_to); ?>"><?php echo esc_html__('Kirjaudu ulos', 'balance-testing'); ?></a>
</div>