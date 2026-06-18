<?php

namespace BalanceTesting\Migration\CopyToExcercise;

use BalanceTesting\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the "Copy To excercise" button below Update in the Publish box.
 */
class AdminButton {
	use SingletonTrait;

	public function init(): void {
		add_action( 'admin_footer-post.php', array( $this, 'mount_button_after_update' ) );
		add_action( 'admin_notices', array( $this, 'render_notices' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_styles' ) );
	}

	/**
	 * WordPress fires submitpost_box before the sidebar meta boxes (top of column),
	 * so we mount the button after #major-publishing-actions inside #submitdiv instead.
	 */
	public function mount_button_after_update(): void {
		global $post;

		if ( ! $post instanceof \WP_Post || 'test' !== $post->post_type ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return;
		}

		if ( in_array( $post->post_status, array( 'auto-draft', 'trash' ), true ) ) {
			return;
		}

		?>
		<div id="bt-copy-to-excercise-mount" style="display:none;">
			<?php $this->render_button( $post ); ?>
		</div>
		<script>
			jQuery( function ( $ ) {
				var $mount  = $( '#bt-copy-to-excercise-mount' );
				var $target = $( '#submitdiv #major-publishing-actions' ).filter( function () {
					return $( this ).find( '#publishing-action, #publish' ).length;
				} ).first();

				if ( ! $mount.length || ! $target.length ) {
					return;
				}

				$( '#submitdiv .bt-copy-to-excercise-wrap' ).remove();

				$target.append( $mount.children() );
				$mount.remove();
			} );
		</script>
		<?php
	}

	/**
	 * @param \WP_Post $post Current post.
	 */
	public function render_button( $post ): void {
		if ( ! $post instanceof \WP_Post || 'test' !== $post->post_type ) {
			return;
		}

		$url = CopyToExcercise::get_copy_url( (int) $post->ID );
		?>
		<div class="bt-copy-to-excercise-wrap">
			<a
				href="<?php echo esc_url( $url ); ?>"
				class="button button-secondary bt-copy-to-excercise-btn"
				onclick="return confirm('<?php echo esc_js( __( 'Copy this test to a new Exercise draft?', 'balance-testing' ) ); ?>');"
			>
				<?php esc_html_e( 'Copy To excercise', 'balance-testing' ); ?>
			</a>
		</div>
		<?php
	}

	public function render_notices(): void {
		if ( empty( $_GET['bt_copied_excercise'] ) || empty( $_GET['bt_copied_from'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! $screen || 'excercise' !== $screen->post_type ) {
			return;
		}

		$exercise_id = absint( $_GET['bt_copied_excercise'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$test_id     = absint( $_GET['bt_copied_from'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( $exercise_id !== get_the_ID() ) {
			return;
		}

		$test_title = get_the_title( $test_id );
		?>
		<div class="notice notice-success is-dismissible">
			<p>
				<?php
				printf(
					/* translators: %s: source test title */
					esc_html__( 'Copied from test "%s". Review the exercise draft and publish when ready.', 'balance-testing' ),
					esc_html( $test_title )
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * @param string $hook_suffix Current admin page hook.
	 */
	public function enqueue_styles( string $hook_suffix ): void {
		if ( 'post.php' !== $hook_suffix ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! $screen || 'test' !== $screen->post_type ) {
			return;
		}

		$css = '
			#submitdiv #major-publishing-actions .bt-copy-to-excercise-wrap {
				clear: both;
				border-top: 1px solid #dcdcde;
				margin: 10px -12px -12px;
				padding: 12px;
				background: #f6f7f7;
			}
			#submitdiv #major-publishing-actions .bt-copy-to-excercise-btn {
				width: 100%;
				text-align: center;
				justify-content: center;
				display: block;
				box-sizing: border-box;
			}
		';

		wp_register_style( 'bt-copy-to-excercise-admin', false, array(), '1.0.3' );
		wp_enqueue_style( 'bt-copy-to-excercise-admin' );
		wp_add_inline_style( 'bt-copy-to-excercise-admin', $css );
	}
}
