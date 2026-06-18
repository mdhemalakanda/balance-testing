<?php
namespace BalanceTesting;
/**
 * This file is used for manage query vars.
 */
defined('ABSPATH') || exit;

class QueryVars {
    use SingletonTrait;

    public function init() {
        add_filter( 'query_vars', array($this, 'register_query_vars') );
    }

    public function register_query_vars( $vars ) {
        $vars[] = 'action'; // this action will use for user navigation on dashboard.
        $vars[] = 'test_id'; // this will use for detect user test.
        $vars[] = 'test_question_access_key'; // this will use for detect user test.
        return $vars;
    }
}