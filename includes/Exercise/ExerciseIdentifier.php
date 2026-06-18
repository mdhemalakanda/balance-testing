<?php
namespace BalanceTesting\Exercise;

defined( 'ABSPATH' ) || exit;

/**
 * Exercise identifier stored as post meta (ACF field exercise_identifier when present).
 */
class ExerciseIdentifier {

    public const META_KEY = '_bt_exercise_identifier';

    public static function get( int $post_id ): string {
        if ( $post_id <= 0 ) {
            return '';
        }

        if ( function_exists( 'get_field' ) ) {
            $acf = get_field( 'exercise_identifier', $post_id );
            if ( is_string( $acf ) && '' !== $acf ) {
                return sanitize_text_field( $acf );
            }
        }

        $meta = get_post_meta( $post_id, self::META_KEY, true );
        return is_string( $meta ) ? sanitize_text_field( $meta ) : '';
    }

    public static function set( int $post_id, string $identifier ): void {
        $identifier = sanitize_text_field( $identifier );
        update_post_meta( $post_id, self::META_KEY, $identifier );

        if ( function_exists( 'update_field' ) ) {
            update_field( 'exercise_identifier', $identifier, $post_id );
        }
    }
}
