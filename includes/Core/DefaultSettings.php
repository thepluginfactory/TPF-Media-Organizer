<?php
/**
 * Default Settings
 *
 * @package TPFMediaOrganizer\Core
 */

namespace TPFMediaOrganizer\Core;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Default settings class
 */
class DefaultSettings {

    /**
     * Get default plugin settings
     *
     * @return array
     */
    public static function get_defaults() {
        return array(
            'tpf_mo_enable_drag_drop'      => true,
            'tpf_mo_show_folder_count'     => true,
            'tpf_mo_default_folder'        => 0,
            'tpf_mo_show_uncategorized'    => true,
            'tpf_mo_folder_tree_expanded'  => true,
            'tpf_mo_enable_modal_filter'   => true,
        );
    }

    /**
     * Get a specific setting with default fallback
     *
     * @param string $key Setting key
     * @return mixed
     */
    public static function get_setting($key) {
        $defaults = self::get_defaults();
        $default = isset($defaults[$key]) ? $defaults[$key] : null;
        return get_option($key, $default);
    }
}
