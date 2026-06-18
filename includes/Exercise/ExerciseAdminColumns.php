<?php
namespace BalanceTesting\Exercise;

use BalanceTesting\SingletonTrait;

defined( 'ABSPATH' ) || exit;

class ExerciseAdminColumns {
    use SingletonTrait;

    public function init(): void {
        add_filter( 'manage_excercise_posts_columns', array( $this, 'add_columns' ) );
        add_action( 'manage_excercise_posts_custom_column', array( $this, 'render_column' ), 10, 2 );
        add_filter( 'manage_test_posts_columns', array( $this, 'add_columns' ) );
        add_action( 'manage_test_posts_custom_column', array( $this, 'render_column' ), 10, 2 );
    }

    public function add_columns( array $columns ): array {
        $new = array();
        foreach ( $columns as $key => $label ) {
            $new[ $key ] = $label;
            if ( 'title' === $key ) {
                $new['bt_exercise_identifier'] = __( 'Identifier', 'balance-testing' );
                $new['bt_linked_test']         = __( 'Linked test', 'balance-testing' );
            }
        }
        return $new;
    }

    public function render_column( string $column, int $post_id ): void {
        if ( 'bt_exercise_identifier' === $column ) {
            $id = ExerciseIdentifier::get( $post_id );
            echo $id ? esc_html( $id ) : '—';
            return;
        }

        if ( 'bt_linked_test' === $column ) {
            if ( 'excercise' === get_post_type( $post_id ) ) {
                $test_id = (int) get_post_meta( $post_id, '_bt_copied_from_test_id', true );
                if ( $test_id ) {
                    echo '<a href="' . esc_url( get_edit_post_link( $test_id ) ) . '">#' . esc_html( (string) $test_id ) . '</a>';
                } else {
                    echo '—';
                }
                return;
            }

            $exercise_id = ExerciseRepository::instance()->resolve_exercise_for_test( $post_id );
            if ( $exercise_id ) {
                echo '<a href="' . esc_url( get_edit_post_link( $exercise_id ) ) . '">#' . esc_html( (string) $exercise_id ) . '</a>';
            } else {
                echo '—';
            }
        }
    }
}
