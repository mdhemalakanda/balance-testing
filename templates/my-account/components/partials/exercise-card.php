<?php
/**
 * Single exercise card (assigned or library).
 *
 * @package BalanceTesting
 *
 * @var int $exercise_id Exercise post ID.
 */

defined( 'ABSPATH' ) || exit;

if ( empty( $exercise_id ) ) {
    return;
}

$video_embed = function_exists( 'get_field' ) ? get_field( 'test_video_embed', $exercise_id ) : '';
$video_url   = function_exists( 'get_field' ) ? get_field( 'test_upload_video', $exercise_id ) : '';

if ( empty( $video_embed ) ) {
    $video_embed = get_post_meta( $exercise_id, 'test_video_embed', true );
}
if ( empty( $video_url ) ) {
    $video_url = get_post_meta( $exercise_id, 'test_upload_video', true );
}

$has_video = ! empty( $video_embed ) || ! empty( $video_url );
$images    = function_exists( 'get_field' ) ? get_field( 'images', $exercise_id ) : '';
$post      = get_post( $exercise_id );

if ( ! $post ) {
    return;
}
?>
<article class="bt-test-box bt-exercise-box">
    <h2 class="bt-test-title"><?php echo esc_html( $post->post_title ); ?></h2>
    <?php if ( ! empty( $post->post_content ) ) : ?>
        <div class="bt-test-instructions">
            <?php echo apply_filters( 'the_content', $post->post_content ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </div>
    <?php endif; ?>
    <?php if ( $has_video ) : ?>
        <div class="bt-test-video">
            <?php if ( ! empty( $video_embed ) ) : ?>
                <?php echo $video_embed; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <?php endif; ?>
            <?php if ( ! empty( $video_url ) ) : ?>
                <video src="<?php echo esc_url( $video_url ); ?>" controls></video>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    <?php if ( ! empty( $images ) && is_array( $images ) ) : ?>
        <div class="bt-test-images">
            <?php foreach ( $images as $image ) : ?>
                <?php if ( ! empty( $image['url'] ) ) : ?>
                    <img src="<?php echo esc_url( $image['url'] ); ?>" alt="">
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</article>
