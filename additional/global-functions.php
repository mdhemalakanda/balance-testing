<?php
if(!function_exists('bt_query_arg_action_link')) {
    function bt_query_arg_action_link( $action, $permalink ) {
        return esc_url( add_query_arg( 'action', $action, $permalink ) );
    }
}
