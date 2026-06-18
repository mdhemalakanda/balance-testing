<?php
/**
 * User account — assigned exercises (visible after admin approval).
 *
 * @package BalanceTesting
 */

defined( 'ABSPATH' ) || exit;

use BalanceTesting\Exercise\ExerciseRepository;
use BalanceTesting\Exercise\ExerciseVisibility;

$user_id    = get_current_user_id();
$visibility = ExerciseVisibility::instance();
$visible    = $visibility->is_visible_for_user( $user_id );
$countdown  = $visibility->get_countdown_message( $user_id );
$assignments = $visible
    ? ExerciseRepository::instance()->get_visible_assignments( $user_id )
    : array();
?>

<div id="exercises" class="account-tab-panel">
    <?php if ( ! $visible ) : ?>
        <div class="bt-alert">
            <?php echo esc_html__( 'Harjoitusohjelma tarkistetaan. Saat harjoitukset näkyviin, kun valmentaja on hyväksynyt ne.', 'balance-testing' ); ?>
        </div>
    <?php elseif ( empty( $assignments ) ) : ?>
        <div class="bt-alert">
            <?php echo esc_html__( 'Hyväksytyt harjoitukset eivät ole vielä näkyvissä. Ota yhteyttä valmentajaan, jos tämä viesti näkyy pitkään.', 'balance-testing' ); ?>
        </div>
    <?php else : ?>
        <?php if ( '' !== $countdown ) : ?>
            <div class="bt-exercise-countdown" role="status">
                <?php echo esc_html( $countdown ); ?>
            </div>
        <?php endif; ?>
        <div class="bt-exercises-list">
            <?php
            foreach ( $assignments as $assignment ) {
                $exercise_id = (int) $assignment->exercise_id;
                if ( 'publish' !== get_post_status( $exercise_id ) ) {
                    continue;
                }
                // Direct include keeps $exercise_id in scope (bt_file_import runs in a nested function scope).
                include __DIR__ . '/../partials/exercise-card.php';
            }
            ?>
        </div>
    <?php endif; ?>
</div>
