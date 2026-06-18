<?php
    $user_image = get_avatar($user_id);
?>
<div class="bt-student-account-avatar">
    <?php echo wp_kses_post($user_image); ?>
</div>