<?php
/**
 * Admin Settings
 *
 * @package TPFMediaOrganizer\Admin
 */

namespace TPFMediaOrganizer\Admin;

use TPFMediaOrganizer\Core\DefaultSettings;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Settings page class
 */
class Settings {

    /**
     * Single instance
     *
     * @var Settings|null
     */
    private static $instance = null;

    /**
     * Settings page slug
     */
    const PAGE_SLUG = 'tpf-media-organizer';

    /**
     * Get instance
     *
     * @return Settings
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Media Organizer', 'tpf-media-organizer'),
            __('Media Organizer', 'tpf-media-organizer'),
            'upload_files',
            self::PAGE_SLUG,
            array($this, 'render_settings_page'),
            'dashicons-category',
            26
        );

        add_submenu_page(
            self::PAGE_SLUG,
            __('Settings', 'tpf-media-organizer'),
            __('Settings', 'tpf-media-organizer'),
            'manage_options',
            self::PAGE_SLUG,
            array($this, 'render_settings_page')
        );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        // Register setting group
        register_setting(
            'tpf_media_organizer_settings',
            'tpf_mo_enable_drag_drop',
            array(
                'type'              => 'boolean',
                'sanitize_callback' => 'rest_sanitize_boolean',
                'default'           => true,
            )
        );

        register_setting(
            'tpf_media_organizer_settings',
            'tpf_mo_show_folder_count',
            array(
                'type'              => 'boolean',
                'sanitize_callback' => 'rest_sanitize_boolean',
                'default'           => true,
            )
        );

        register_setting(
            'tpf_media_organizer_settings',
            'tpf_mo_show_uncategorized',
            array(
                'type'              => 'boolean',
                'sanitize_callback' => 'rest_sanitize_boolean',
                'default'           => true,
            )
        );

        register_setting(
            'tpf_media_organizer_settings',
            'tpf_mo_folder_tree_expanded',
            array(
                'type'              => 'boolean',
                'sanitize_callback' => 'rest_sanitize_boolean',
                'default'           => true,
            )
        );

        register_setting(
            'tpf_media_organizer_settings',
            'tpf_mo_enable_modal_filter',
            array(
                'type'              => 'boolean',
                'sanitize_callback' => 'rest_sanitize_boolean',
                'default'           => true,
            )
        );

        // Add settings section
        add_settings_section(
            'tpf_mo_general_section',
            __('General Settings', 'tpf-media-organizer'),
            array($this, 'render_general_section'),
            self::PAGE_SLUG
        );

        // Add settings fields
        add_settings_field(
            'tpf_mo_enable_drag_drop',
            __('Enable Drag & Drop', 'tpf-media-organizer'),
            array($this, 'render_checkbox_field'),
            self::PAGE_SLUG,
            'tpf_mo_general_section',
            array(
                'id'          => 'tpf_mo_enable_drag_drop',
                'description' => __('Allow dragging media items to folders in the media library.', 'tpf-media-organizer'),
            )
        );

        add_settings_field(
            'tpf_mo_show_folder_count',
            __('Show Folder Count', 'tpf-media-organizer'),
            array($this, 'render_checkbox_field'),
            self::PAGE_SLUG,
            'tpf_mo_general_section',
            array(
                'id'          => 'tpf_mo_show_folder_count',
                'description' => __('Display the number of items next to each folder name.', 'tpf-media-organizer'),
            )
        );

        add_settings_field(
            'tpf_mo_show_uncategorized',
            __('Show Uncategorized', 'tpf-media-organizer'),
            array($this, 'render_checkbox_field'),
            self::PAGE_SLUG,
            'tpf_mo_general_section',
            array(
                'id'          => 'tpf_mo_show_uncategorized',
                'description' => __('Show an "Uncategorized" option to filter media not in any folder.', 'tpf-media-organizer'),
            )
        );

        add_settings_field(
            'tpf_mo_folder_tree_expanded',
            __('Expand Folders by Default', 'tpf-media-organizer'),
            array($this, 'render_checkbox_field'),
            self::PAGE_SLUG,
            'tpf_mo_general_section',
            array(
                'id'          => 'tpf_mo_folder_tree_expanded',
                'description' => __('Expand the folder tree by default when loading the media library.', 'tpf-media-organizer'),
            )
        );

        add_settings_field(
            'tpf_mo_enable_modal_filter',
            __('Enable Modal Filter', 'tpf-media-organizer'),
            array($this, 'render_checkbox_field'),
            self::PAGE_SLUG,
            'tpf_mo_general_section',
            array(
                'id'          => 'tpf_mo_enable_modal_filter',
                'description' => __('Show folder filter in the media modal when inserting media into posts.', 'tpf-media-organizer'),
            )
        );
    }

    /**
     * Render general section description
     */
    public function render_general_section() {
        echo '<p>' . esc_html__('Configure how the media folder organizer behaves.', 'tpf-media-organizer') . '</p>';
    }

    /**
     * Render checkbox field
     *
     * @param array $args Field arguments
     */
    public function render_checkbox_field($args) {
        $id = $args['id'];
        $value = DefaultSettings::get_setting($id);
        $description = isset($args['description']) ? $args['description'] : '';

        printf(
            '<label><input type="checkbox" id="%1$s" name="%1$s" value="1" %2$s /> %3$s</label>',
            esc_attr($id),
            checked($value, true, false),
            esc_html($description)
        );
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Check if settings were saved
        if (isset($_GET['settings-updated'])) {
            add_settings_error(
                'tpf_mo_messages',
                'tpf_mo_message',
                __('Settings saved.', 'tpf-media-organizer'),
                'updated'
            );
        }

        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <?php settings_errors('tpf_mo_messages'); ?>

            <form action="options.php" method="post">
                <?php
                settings_fields('tpf_media_organizer_settings');
                do_settings_sections(self::PAGE_SLUG);
                submit_button(__('Save Settings', 'tpf-media-organizer'));
                ?>
            </form>
        </div>
        <?php
    }
}
