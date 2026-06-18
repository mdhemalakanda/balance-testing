<?php
namespace BalanceTesting\Exception;

use BalanceTesting\SingletonTrait;

/**
 * This file will use for hook based warning.
 * 
 * @since 1.0
 */
defined('ABSPATH') || exit;

class Warning {
    use SingletonTrait;
    private $message, $type;

    public function generate_warning( $hook_name, $message, $type = '' ) {
        $this->set_message($message);
        $this->set_type($type);
        add_action( $hook_name, array( $this, 'display_warning' ) );
    }
    private function set_message($message) {
        $this->message = $message;
    }

    private function set_type($type) {
        $this->type = $type;
    }

    public function display_warning() {
        $type_class = '';
        switch( $this->type ) {
            case 'success':
                $type_class = 'bt-alert-success';
                break;
            case 'danger':
                $type_class = 'bt-alert-danger';
                break;
        }
        ob_start(); ?>
        <div class="bt-alert <?php echo esc_attr($type_class); ?>">
            <h2><?php echo esc_html($this->message); ?></h2>
        </div>
        <?php echo ob_get_clean();
    }
}