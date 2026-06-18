<?php
    $action = get_query_var('action');
    $user_id = get_current_user_id();
?>
<div class="bt-student-account-wrap">

    <!-- LEFT SIDEBAR -->
    <div class="bt-student-account-sidebar">

        <?php
            bt_file_import(__DIR__. '/components/thumbnails.php');
            bt_file_import(__DIR__. '/components/meta.php');
            bt_file_import(__DIR__. '/components/navigation.php');
            bt_file_import(__DIR__. '/components/logout.php');
        ?>
    </div>

    <!-- RIGHT CONTENT -->
    <div class="bt-student-account-content">

        <?php
            switch ( $action ) {
                case 'initial-assessment':
                    bt_file_import( __DIR__ . '/components/content/initial_assignment.php' );
                    break;

                case 'balance-tests':
                    bt_file_import( __DIR__ . '/components/content/test.php' );
                    break;

                case 'exercises':
                    bt_file_import( __DIR__ . '/components/content/exercises.php' );
                    break;

                case 'progress-checkin':
                    bt_file_import( __DIR__ . '/components/content/progress.php' );
                    break;

                case 'dashboard':
                    bt_file_import( __DIR__ . '/components/content/dashboard.php' );
                    break;
                default:
                    bt_file_import( __DIR__ . '/components/content/initial_assignment.php' );
                    break;
            }
        ?>


    </div>

</div>