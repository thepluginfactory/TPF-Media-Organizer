<?php
/**
 * Media Library Integration
 *
 * @package TPFMediaOrganizer\Admin
 */

namespace TPFMediaOrganizer\Admin;

use TPFMediaOrganizer\Core\Taxonomy;
use TPFMediaOrganizer\Core\DefaultSettings;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Integrates folder system with WordPress media library
 */
class MediaLibrary {

    /**
     * Single instance
     *
     * @var MediaLibrary|null
     */
    private static $instance = null;

    /**
     * Get instance
     *
     * @return MediaLibrary
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
        // Enqueue scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));

        // Add folder filter dropdown
        add_action('restrict_manage_posts', array($this, 'add_folder_filter'));

        // Filter media query
        add_action('pre_get_posts', array($this, 'filter_media_by_folder'));

        // Add folder column to list view
        add_filter('manage_media_columns', array($this, 'add_folder_column'));
        add_action('manage_media_custom_column', array($this, 'render_folder_column'), 10, 2);

        // AJAX handler for getting folder data
        add_action('wp_ajax_tpf_mo_get_folder_data', array($this, 'ajax_get_folder_data'));
    }

    /**
     * Enqueue admin assets
     *
     * @param string $hook Current admin page
     */
    public function enqueue_assets($hook) {
        // Only load on media pages
        if (!in_array($hook, array('upload.php', 'post.php', 'post-new.php'), true)) {
            return;
        }

        // Styles
        wp_enqueue_style(
            'tpf-media-organizer-admin',
            TPF_MEDIA_ORGANIZER_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            TPF_MEDIA_ORGANIZER_VERSION
        );

        // Scripts
        wp_enqueue_script(
            'tpf-media-organizer-library',
            TPF_MEDIA_ORGANIZER_PLUGIN_URL . 'assets/js/media-library.js',
            array('jquery', 'jquery-ui-draggable', 'jquery-ui-droppable'),
            TPF_MEDIA_ORGANIZER_VERSION,
            true
        );

        // Localize script
        wp_localize_script('tpf-media-organizer-library', 'tpfMediaOrganizer', array(
            'ajaxUrl'      => admin_url('admin-ajax.php'),
            'nonce'        => wp_create_nonce('tpf_mo_nonce'),
            'folders'      => Taxonomy::get_folder_tree(),
            'settings'     => array(
                'enableDragDrop'     => DefaultSettings::get_setting('tpf_mo_enable_drag_drop'),
                'showFolderCount'    => DefaultSettings::get_setting('tpf_mo_show_folder_count'),
                'showUncategorized'  => DefaultSettings::get_setting('tpf_mo_show_uncategorized'),
                'folderTreeExpanded' => DefaultSettings::get_setting('tpf_mo_folder_tree_expanded'),
            ),
            'strings'      => array(
                'allMedia'       => __('All Media', 'tpf-media-organizer'),
                'uncategorized'  => __('Uncategorized', 'tpf-media-organizer'),
                'newFolder'      => __('New Folder', 'tpf-media-organizer'),
                'folderName'     => __('Folder Name', 'tpf-media-organizer'),
                'create'         => __('Create', 'tpf-media-organizer'),
                'cancel'         => __('Cancel', 'tpf-media-organizer'),
                'rename'         => __('Rename', 'tpf-media-organizer'),
                'delete'         => __('Delete', 'tpf-media-organizer'),
                'confirmDelete'  => __('Are you sure you want to delete this folder? Media items will not be deleted.', 'tpf-media-organizer'),
                'moveToFolder'   => __('Move to Folder', 'tpf-media-organizer'),
                'dropHere'       => __('Drop items here', 'tpf-media-organizer'),
                'errorOccurred'  => __('An error occurred. Please try again.', 'tpf-media-organizer'),
            ),
            'uncategorizedCount' => Taxonomy::get_folder_count(0),
        ));
    }

    /**
     * Add folder filter dropdown in list view
     *
     * @param string $post_type Current post type
     */
    public function add_folder_filter($post_type) {
        if ($post_type !== 'attachment') {
            return;
        }

        $folders = Taxonomy::get_folders();
        $selected = isset($_GET['tpf_media_folder']) ? sanitize_text_field($_GET['tpf_media_folder']) : '';

        ?>
        <select name="tpf_media_folder" id="tpf-media-folder-filter">
            <option value=""><?php esc_html_e('All Folders', 'tpf-media-organizer'); ?></option>
            <?php if (DefaultSettings::get_setting('tpf_mo_show_uncategorized')) : ?>
                <option value="uncategorized" <?php selected($selected, 'uncategorized'); ?>>
                    <?php esc_html_e('Uncategorized', 'tpf-media-organizer'); ?>
                </option>
            <?php endif; ?>
            <?php
            foreach ($folders as $folder) {
                $indent = str_repeat('&nbsp;&nbsp;&nbsp;', $this->get_folder_depth($folder->term_id));
                printf(
                    '<option value="%d" %s>%s%s</option>',
                    esc_attr($folder->term_id),
                    selected($selected, $folder->term_id, false),
                    $indent,
                    esc_html($folder->name)
                );
            }
            ?>
        </select>
        <?php
    }

    /**
     * Get folder depth for indentation
     *
     * @param int $term_id Term ID
     * @return int
     */
    private function get_folder_depth($term_id) {
        $ancestors = get_ancestors($term_id, Taxonomy::TAXONOMY, 'taxonomy');
        return count($ancestors);
    }

    /**
     * Filter media query by folder
     *
     * @param \WP_Query $query
     */
    public function filter_media_by_folder($query) {
        global $pagenow;

        if (!is_admin() || $pagenow !== 'upload.php' || !$query->is_main_query()) {
            return;
        }

        if ($query->get('post_type') !== 'attachment') {
            return;
        }

        $folder = isset($_GET['tpf_media_folder']) ? sanitize_text_field($_GET['tpf_media_folder']) : '';

        if (empty($folder)) {
            return;
        }

        if ($folder === 'uncategorized') {
            $query->set('tax_query', array(
                array(
                    'taxonomy' => Taxonomy::TAXONOMY,
                    'operator' => 'NOT EXISTS',
                ),
            ));
        } else {
            $query->set('tax_query', array(
                array(
                    'taxonomy' => Taxonomy::TAXONOMY,
                    'field'    => 'term_id',
                    'terms'    => absint($folder),
                ),
            ));
        }
    }

    /**
     * Add folder column to media list
     *
     * @param array $columns Existing columns
     * @return array
     */
    public function add_folder_column($columns) {
        $new_columns = array();

        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            // Add folder column after title
            if ($key === 'title') {
                $new_columns['tpf_folder'] = __('Folder', 'tpf-media-organizer');
            }
        }

        return $new_columns;
    }

    /**
     * Render folder column content
     *
     * @param string $column_name Column name
     * @param int    $post_id     Attachment ID
     */
    public function render_folder_column($column_name, $post_id) {
        if ($column_name !== 'tpf_folder') {
            return;
        }

        $terms = wp_get_object_terms($post_id, Taxonomy::TAXONOMY);

        if (empty($terms) || is_wp_error($terms)) {
            echo '<span class="tpf-no-folder">' . esc_html__('Uncategorized', 'tpf-media-organizer') . '</span>';
            return;
        }

        $folder_links = array();
        foreach ($terms as $term) {
            $folder_links[] = sprintf(
                '<a href="%s">%s</a>',
                esc_url(add_query_arg(array(
                    'post_type'        => 'attachment',
                    'tpf_media_folder' => $term->term_id,
                ), admin_url('upload.php'))),
                esc_html($term->name)
            );
        }

        echo implode(', ', $folder_links);
    }

    /**
     * AJAX: Get folder data for JavaScript
     */
    public function ajax_get_folder_data() {
        check_ajax_referer('tpf_mo_nonce', 'nonce');

        if (!current_user_can('upload_files')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'tpf-media-organizer')));
            return;
        }

        wp_send_json_success(array(
            'folders'            => Taxonomy::get_folder_tree(),
            'uncategorizedCount' => Taxonomy::get_folder_count(0),
        ));
    }
}
