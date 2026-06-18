<?php
namespace BalanceTesting\Database;

use BalanceTesting\SingletonTrait;
/**
 * This will handle all post type for this plugin.
 * 
 * @since 1.0
 */
defined('ABSPATH') || exit;

class PostType {
    use SingletonTrait;

    public function init() {
        add_action( 'init', array( $this, 'create_post_type' ) );
    }

    public function create_post_type() {
        register_post_type('user_questions',
            array(
                'labels'      => array(
                    'name'          => __('User Questions', 'balance-testing'),
                    'singular_name' => __('User Question', 'balance-testing'),
                ),
                'public'      => true,
                'show_ui'       => false,
                'has_archive' => true,
            )
        );
        register_post_type('test',
            array(
                'labels'      => array(
                    'name'          => __('Tests', 'balance-testing'),
                    'singular_name' => __('Test', 'balance-testing'),
                ),
                'public'      => true,
                'has_archive' => true,
            )
        );
        register_post_type(
            'excercise',
            array(
                'labels'      => array(
                    'name'          => __( 'Exercises', 'balance-testing' ),
                    'singular_name' => __( 'Exercise', 'balance-testing' ),
                ),
                'public'      => true,
                'has_archive' => true,
                'menu_icon'   => 'data:image/svg+xml;base64,' . base64_encode(
                    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="#a7aaad">
                        <path d="M2 8h2v4H2V8zm14 0h2v4h-2V8zM6 9h1v2H6V9zm7 0h1v2h-1V9zM4 7h2v6H4V7zm10 0h2v6h-2V7zM7 8h6v4H7V8z"/>
                    </svg>'
                ),
            )
        );
    }
}