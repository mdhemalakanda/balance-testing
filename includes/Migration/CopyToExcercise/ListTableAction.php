<?php

namespace BalanceTesting\Migration\CopyToExcercise;

use BalanceTesting\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Adds "Copy To excercise" to the Tests list table row actions.
 */
class ListTableAction {
	use SingletonTrait;

	public function init(): void {
		add_filter( 'post_row_actions', array( $this, 'add_row_action' ), 100, 2 );
	}

	/**
	 * @param array<string, string> $actions Row actions.
	 * @param \WP_Post              $post    Current post.
	 * @return array<string, string>
	 */
	public function add_row_action( array $actions, $post ): array {
		if ( ! $post instanceof \WP_Post || 'test' !== $post->post_type ) {
			return $actions;
		}

		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return $actions;
		}

		if ( in_array( $post->post_status, array( 'auto-draft', 'trash' ), true ) ) {
			return $actions;
		}

		$copy_link = sprintf(
			'<a href="%1$s" aria-label="%2$s" onclick="return confirm(%3$s);">%4$s</a>',
			esc_url( CopyToExcercise::get_copy_url( (int) $post->ID ) ),
			esc_attr(
				sprintf(
					/* translators: %s: post title */
					__( 'Copy "%s" to excercise', 'balance-testing' ),
					get_the_title( $post )
				)
			),
			wp_json_encode( __( 'Copy this test to a new Exercise draft?', 'balance-testing' ) ),
			esc_html__( 'Copy To excercise', 'balance-testing' )
		);

		$actions['bt_copy_to_excercise'] = $copy_link;

		return $actions;
	}
}
