<?php
namespace WP_Arvan\OBS\Admin;

class Partials {

    public $file_exist = false;

    protected static function load( $template_name, $_instance = null ) {
        if ( ! $_instance ) {
            $_instance = new self();
        }
        $template_path = \ACS_PLUGIN_ROOT . 'admin/partials/partial-' . $template_name . '.php';
        if ( file_exists( $template_path ) ) {
            require_once $template_path;
            $_instance->file_exist = true;
        }

        return $_instance;
    }

    public static function __callStatic( $name, $arguments ) {
        $template_name = str_replace( '_', '-', $name );
        return self::load( $template_name );
    }

    private function __construct() {}

    public function die() {
        if ( ! $this->file_exist ) {
            wp_die( 'Template file not found' );
        } else {
            die();
        }
    }

}