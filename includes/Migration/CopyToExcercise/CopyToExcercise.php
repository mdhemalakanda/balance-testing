<?php

namespace BalanceTesting\Migration\CopyToExcercise;

use BalanceTesting\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Bootstraps test → excercise copy migration from the admin edit screen.
 */
class CopyToExcercise {
	use SingletonTrait;

	public function init(): void {
		AdminButton::instance()->init();
		ListTableAction::instance()->init();
		add_action( 'admin_post_bt_copy_test_to_excercise', array( $this, 'handle_copy_request' ) );
	}

	/**
	 * @param int $post_id Test post ID.
	 */
	public static function get_copy_url( int $post_id ): string {
		return wp_nonce_url(
			admin_url( 'admin-post.php?action=bt_copy_test_to_excercise&post_id=' . absint( $post_id ) ),
			'bt_copy_test_to_excercise_' . absint( $post_id )
		);
	}

	public function handle_copy_request(): void {
		$post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( $post_id <= 0 ) {
			wp_die( esc_html__( 'Missing test post ID.', 'balance-testing' ) );
		}

		check_admin_referer( 'bt_copy_test_to_excercise_' . $post_id );

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( esc_html__( 'You do not have permission to copy this test.', 'balance-testing' ) );
		}

		$copier      = new PostCopier();
		$exercise_id = $copier->copy( $post_id );

		if ( is_wp_error( $exercise_id ) ) {
			wp_die( esc_html( $exercise_id->get_error_message() ) );
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'post'              => $exercise_id,
					'action'            => 'edit',
					'bt_copied_excercise' => $exercise_id,
					'bt_copied_from'    => $post_id,
				),
				admin_url( 'post.php' )
			)
		);
		exit;
	}
}
