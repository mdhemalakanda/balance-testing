<?php
namespace BalanceTesting\Table;

use BalanceTesting\SingletonTrait;
use WP_List_Table;

defined('ABSPATH') || exit;

if ( ! class_exists('WP_List_Table') ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class UserTable extends WP_List_Table {
    use SingletonTrait;

    private $_items = [];

    public function __construct( $args = [] ) {
        parent::__construct([
            'singular' => 'user',
            'plural'   => 'users',
            'ajax'     => false,
        ]);
    }

    public function set_data( $data ) {
        $this->_items = $data;
        $this->items  = $data;
    }

    public function get_columns() {
        return [
            'cb'     => '<input type="checkbox" />',
            'name'   => __('Name', 'balance-testing'),
            'email'  => __('Email', 'balance-testing'),
            'action' => __('Action', 'balance-testing'),
        ];
    }

    protected function get_sortable_columns() {
        return [
            'name'  => ['name', true],
            'email' => ['email', true],
        ];
    }

    protected function column_cb( $item ) {
        return sprintf(
            '<input type="checkbox" name="bulk-select[]" value="%s" />',
            esc_attr($item['id'])
        );
    }

    protected function column_name( $item ) {
        return '<strong>' . esc_html($item['name']) . '</strong>';
    }

    protected function column_email( $item ) {
        return esc_html($item['email']);
    }

    protected function column_action( $item ) {
        return $item['action'];
    }

    protected function column_default( $item, $column_name ) {
        return $item[$column_name] ?? '';
    }

    public function prepare_items() {
        $columns  = $this->get_columns();
        $hidden   = [];
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = [$columns, $hidden, $sortable];

        $per_page = 10;
        $paged    = max(1, absint($_REQUEST['paged'] ?? 1));
        $total    = count($this->_items);

        $data = array_slice(
            $this->_items,
            ($paged - 1) * $per_page,
            $per_page
        );

        $this->items = $data;

        $this->set_pagination_args([
            'total_items' => $total,
            'per_page'    => $per_page,
            'total_pages' => ceil($total / $per_page),
        ]);
    }
}
