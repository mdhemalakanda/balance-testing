<?php

namespace BalanceTesting\Migration\BulkCopyTestsToExercises;

use BalanceTesting\Admin\AdminScreen;
use BalanceTesting\Exercise\ExerciseIdentifier;
use BalanceTesting\Exercise\ExerciseRepository;
use BalanceTesting\Migration\CopyToExcercise\PostCopier;
use BalanceTesting\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Bulk copy all published tests to excercise posts (admin tool).
 */
class BulkCopyTestsToExercises {
    use SingletonTrait;

    public const SCREEN_ID = 'test_page_bt-bulk-copy-exercises';

    public function init(): void {
        AdminScreen::suppress_third_party_notices( self::SCREEN_ID );
        AdminScreen::enqueue_style( 'bt-admin-tools', 'assets/css/admin-tools.css', self::SCREEN_ID );

        add_action( 'admin_menu', array( $this, 'register_menu' ) );
        add_action( 'admin_post_bt_bulk_copy_tests_to_exercises', array( $this, 'handle_bulk_copy' ) );
    }

    public function register_menu(): void {
        add_submenu_page(
            'edit.php?post_type=test',
            __( 'Bulk copy to exercises', 'balance-testing' ),
            __( 'Bulk copy to exercises', 'balance-testing' ),
            'manage_options',
            'bt-bulk-copy-exercises',
            array( $this, 'render_page' )
        );
    }

    /**
     * @return array{total: int, linked: int, pending: int, test_ids: int[]}
     */
    private function get_stats(): array {
        $tests = get_posts(
            array(
                'post_type'      => 'test',
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'orderby'        => 'menu_order',
                'order'          => 'DESC',
            )
        );

        $linked = 0;
        $repo   = ExerciseRepository::instance();

        foreach ( $tests as $test_id ) {
            if ( $repo->resolve_exercise_for_test( (int) $test_id ) ) {
                ++$linked;
            }
        }

        $total = count( $tests );

        return array(
            'total'    => $total,
            'linked'   => $linked,
            'pending'  => max( 0, $total - $linked ),
            'test_ids' => $tests,
        );
    }

    public function render_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'balance-testing' ) );
        }

        $stats      = $this->get_stats();
        $action_url = wp_nonce_url(
            admin_url( 'admin-post.php?action=bt_bulk_copy_tests_to_exercises' ),
            'bt_bulk_copy_tests_to_exercises'
        );

        $copied  = isset( $_GET['bt_bulk_copied'] ) ? absint( $_GET['bt_bulk_copied'] ) : null; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $skipped = isset( $_GET['bt_bulk_skipped'] ) ? absint( $_GET['bt_bulk_skipped'] ) : null; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $errors  = isset( $_GET['bt_bulk_errors'] ) ? absint( $_GET['bt_bulk_errors'] ) : null; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $ran     = null !== $copied;
        ?>
        <div class="wrap bt-admin-tool">
            <h1><?php esc_html_e( 'Bulk copy tests to exercises', 'balance-testing' ); ?></h1>
            <p class="bt-admin-tool__intro">
                <?php esc_html_e( 'Create exercise posts from published balance tests. Each new exercise keeps the test content, video, and images, and is linked for the test-to-exercise assignment workflow.', 'balance-testing' ); ?>
            </p>

            <?php if ( $ran ) : ?>
                <div class="notice notice-success is-dismissible bt-admin-tool__notice">
                    <p>
                        <?php
                        printf(
                            /* translators: 1: copied count, 2: skipped count, 3: error count */
                            esc_html__( 'Bulk copy finished: %1$d created, %2$d already linked (skipped), %3$d errors.', 'balance-testing' ),
                            $copied,
                            $skipped,
                            $errors
                        );
                        ?>
                    </p>
                </div>
            <?php endif; ?>

            <div class="bt-admin-tool__grid" role="group" aria-label="<?php esc_attr_e( 'Copy statistics', 'balance-testing' ); ?>">
                <div class="bt-admin-tool__stat">
                    <span class="bt-admin-tool__stat-label"><?php esc_html_e( 'Published tests', 'balance-testing' ); ?></span>
                    <span class="bt-admin-tool__stat-value"><?php echo esc_html( (string) $stats['total'] ); ?></span>
                </div>
                <div class="bt-admin-tool__stat">
                    <span class="bt-admin-tool__stat-label"><?php esc_html_e( 'Already linked', 'balance-testing' ); ?></span>
                    <span class="bt-admin-tool__stat-value bt-admin-tool__stat-value--muted"><?php echo esc_html( (string) $stats['linked'] ); ?></span>
                </div>
                <div class="bt-admin-tool__stat">
                    <span class="bt-admin-tool__stat-label"><?php esc_html_e( 'Ready to copy', 'balance-testing' ); ?></span>
                    <span class="bt-admin-tool__stat-value bt-admin-tool__stat-value--accent"><?php echo esc_html( (string) $stats['pending'] ); ?></span>
                </div>
            </div>

            <div class="bt-admin-tool__card">
                <div class="bt-admin-tool__card-header">
                    <h2><?php esc_html_e( 'How it works', 'balance-testing' ); ?></h2>
                </div>
                <div class="bt-admin-tool__card-body">
                    <ul class="bt-admin-tool__list">
                        <li><?php esc_html_e( 'Only tests without an existing linked exercise are copied.', 'balance-testing' ); ?></li>
                        <li><?php esc_html_e( 'Identifier comes from the test meta field or the title prefix (e.g. "72." becomes TEST-72).', 'balance-testing' ); ?></li>
                        <li><?php esc_html_e( 'New exercises are published immediately and appear under Exercises in the admin.', 'balance-testing' ); ?></li>
                        <li><?php esc_html_e( 'Safe to run again — already linked tests are skipped.', 'balance-testing' ); ?></li>
                    </ul>
                </div>
            </div>

            <div class="bt-admin-tool__card">
                <div class="bt-admin-tool__card-header">
                    <h2><?php esc_html_e( 'Run migration', 'balance-testing' ); ?></h2>
                </div>
                <div class="bt-admin-tool__card-body">
                    <?php if ( $stats['pending'] > 0 ) : ?>
                        <p>
                            <?php
                            printf(
                                /* translators: %d: number of tests to copy */
                                esc_html( _n(
                                    '%d test will be copied to a new exercise.',
                                    '%d tests will be copied to new exercises.',
                                    $stats['pending'],
                                    'balance-testing'
                                ) ),
                                $stats['pending']
                            );
                            ?>
                        </p>
                    <?php else : ?>
                        <p><?php esc_html_e( 'All published tests already have a linked exercise. Nothing to copy right now.', 'balance-testing' ); ?></p>
                    <?php endif; ?>

                    <div class="bt-admin-tool__actions">
                        <?php if ( $stats['pending'] > 0 ) : ?>
                            <a
                                href="<?php echo esc_url( $action_url ); ?>"
                                class="button button-primary button-hero"
                                onclick="return confirm('<?php echo esc_js( __( 'Copy all unlinked tests to exercises now?', 'balance-testing' ) ); ?>');"
                            >
                                <span class="dashicons dashicons-migrate" aria-hidden="true"></span>
                                <?php esc_html_e( 'Run bulk copy', 'balance-testing' ); ?>
                            </a>
                        <?php else : ?>
                            <button type="button" class="button button-primary button-hero" disabled>
                                <span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
                                <?php esc_html_e( 'All tests linked', 'balance-testing' ); ?>
                            </button>
                        <?php endif; ?>

                        <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=excercise' ) ); ?>" class="button button-secondary">
                            <?php esc_html_e( 'View exercises', 'balance-testing' ); ?>
                        </a>
                    </div>

                    <?php if ( $stats['pending'] > 0 ) : ?>
                        <p class="bt-admin-tool__hint" style="margin-top: 14px;">
                            <?php esc_html_e( 'This may take a moment if you have many tests.', 'balance-testing' ); ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }

    public function handle_bulk_copy(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission.', 'balance-testing' ) );
        }

        check_admin_referer( 'bt_bulk_copy_tests_to_exercises' );

        $tests   = get_posts(
            array(
                'post_type'      => 'test',
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'fields'         => 'ids',
            )
        );

        $copier = new PostCopier();
        $repo   = ExerciseRepository::instance();
        $copied = 0;
        $skipped = 0;
        $errors  = 0;

        foreach ( $tests as $test_id ) {
            $test_id = (int) $test_id;
            if ( $repo->resolve_exercise_for_test( $test_id ) ) {
                ++$skipped;
                continue;
            }

            $exercise_id = $copier->copy( $test_id );
            if ( is_wp_error( $exercise_id ) ) {
                ++$errors;
                continue;
            }

            $identifier = ExerciseIdentifier::get( $test_id );
            if ( '' === $identifier ) {
                $identifier = $this->derive_identifier_from_title( get_the_title( $test_id ), $test_id );
                ExerciseIdentifier::set( $test_id, $identifier );
                ExerciseIdentifier::set( (int) $exercise_id, $identifier );
            }

            wp_update_post(
                array(
                    'ID'          => (int) $exercise_id,
                    'post_status' => 'publish',
                )
            );

            ++$copied;
        }

        wp_safe_redirect(
            add_query_arg(
                array(
                    'post_type'       => 'test',
                    'page'            => 'bt-bulk-copy-exercises',
                    'bt_bulk_copied'  => $copied,
                    'bt_bulk_skipped' => $skipped,
                    'bt_bulk_errors'  => $errors,
                ),
                admin_url( 'edit.php' )
            )
        );
        exit;
    }

    private function derive_identifier_from_title( string $title, int $test_id ): string {
        if ( preg_match( '/^(\d+)\./', $title, $matches ) ) {
            return 'TEST-' . $matches[1];
        }
        return 'TEST-' . $test_id;
    }
}
