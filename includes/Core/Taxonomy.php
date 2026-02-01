<?php
/**
 * Folder Taxonomy
 *
 * @package TPFMediaOrganizer\Core
 */

namespace TPFMediaOrganizer\Core;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Registers and manages the media folder taxonomy
 */
class Taxonomy {

    /**
     * Taxonomy name
     */
    const TAXONOMY = 'tpf_media_folder';

    /**
     * Single instance
     *
     * @var Taxonomy|null
     */
    private static $instance = null;

    /**
     * Get instance
     *
     * @return Taxonomy
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
        // If init already fired, register immediately, otherwise hook it
        if (did_action('init')) {
            $this->register_taxonomy();
        } else {
            add_action('init', array($this, 'register_taxonomy'), 5);
        }
    }

    /**
     * Register the media folder taxonomy
     */
    public function register_taxonomy() {
        $labels = array(
            'name'              => _x('Media Folders', 'taxonomy general name', 'tpf-media-organizer'),
            'singular_name'     => _x('Media Folder', 'taxonomy singular name', 'tpf-media-organizer'),
            'search_items'      => __('Search Folders', 'tpf-media-organizer'),
            'all_items'         => __('All Folders', 'tpf-media-organizer'),
            'parent_item'       => __('Parent Folder', 'tpf-media-organizer'),
            'parent_item_colon' => __('Parent Folder:', 'tpf-media-organizer'),
            'edit_item'         => __('Edit Folder', 'tpf-media-organizer'),
            'update_item'       => __('Update Folder', 'tpf-media-organizer'),
            'add_new_item'      => __('Add New Folder', 'tpf-media-organizer'),
            'new_item_name'     => __('New Folder Name', 'tpf-media-organizer'),
            'menu_name'         => __('Folders', 'tpf-media-organizer'),
            'not_found'         => __('No folders found.', 'tpf-media-organizer'),
            'no_terms'          => __('No folders', 'tpf-media-organizer'),
            'back_to_items'     => __('&larr; Back to Folders', 'tpf-media-organizer'),
        );

        $args = array(
            'labels'            => $labels,
            'hierarchical'      => true,
            'public'            => false,
            'show_ui'           => true,
            'show_admin_column' => true,
            'show_in_nav_menus' => false,
            'show_tagcloud'     => false,
            'show_in_rest'      => true,
            'query_var'         => true,
            'rewrite'           => false,
            'capabilities'      => array(
                'manage_terms' => 'upload_files',
                'edit_terms'   => 'upload_files',
                'delete_terms' => 'upload_files',
                'assign_terms' => 'upload_files',
            ),
        );

        register_taxonomy(self::TAXONOMY, 'attachment', $args);
    }

    /**
     * Get all folders
     *
     * @param array $args Optional. Arguments for get_terms().
     * @return array
     */
    public static function get_folders($args = array()) {
        $defaults = array(
            'taxonomy'   => self::TAXONOMY,
            'hide_empty' => false,
            'orderby'    => 'name',
            'order'      => 'ASC',
        );

        $args = wp_parse_args($args, $defaults);
        $terms = get_terms($args);

        if (is_wp_error($terms)) {
            return array();
        }

        return $terms;
    }

    /**
     * Get folder by ID
     *
     * @param int $folder_id Folder term ID
     * @return \WP_Term|false
     */
    public static function get_folder($folder_id) {
        $term = get_term($folder_id, self::TAXONOMY);
        if (is_wp_error($term) || !$term) {
            return false;
        }
        return $term;
    }

    /**
     * Create a new folder
     *
     * @param string $name      Folder name
     * @param int    $parent_id Optional parent folder ID
     * @return int|\WP_Error    Term ID on success, WP_Error on failure
     */
    public static function create_folder($name, $parent_id = 0) {
        $args = array();
        if ($parent_id > 0) {
            $args['parent'] = $parent_id;
        }

        $result = wp_insert_term($name, self::TAXONOMY, $args);

        if (is_wp_error($result)) {
            return $result;
        }

        return $result['term_id'];
    }

    /**
     * Update a folder
     *
     * @param int   $folder_id Folder term ID
     * @param array $data      Data to update (name, parent, etc.)
     * @return bool|\WP_Error
     */
    public static function update_folder($folder_id, $data) {
        $result = wp_update_term($folder_id, self::TAXONOMY, $data);

        if (is_wp_error($result)) {
            return $result;
        }

        return true;
    }

    /**
     * Delete a folder
     *
     * @param int  $folder_id    Folder term ID
     * @param bool $reassign     Whether to reassign media to parent folder
     * @return bool|\WP_Error
     */
    public static function delete_folder($folder_id, $reassign = false) {
        $folder = self::get_folder($folder_id);
        if (!$folder) {
            return new \WP_Error('invalid_folder', __('Folder not found.', 'tpf-media-organizer'));
        }

        // If reassigning, move media to parent folder
        if ($reassign && $folder->parent > 0) {
            $attachments = self::get_folder_attachments($folder_id);
            foreach ($attachments as $attachment_id) {
                wp_set_object_terms($attachment_id, array($folder->parent), self::TAXONOMY);
            }
        }

        $result = wp_delete_term($folder_id, self::TAXONOMY);

        if (is_wp_error($result)) {
            return $result;
        }

        return true;
    }

    /**
     * Get attachments in a folder
     *
     * @param int $folder_id Folder term ID (0 for uncategorized)
     * @return array Array of attachment IDs
     */
    public static function get_folder_attachments($folder_id) {
        if ($folder_id === 0) {
            // Get uncategorized attachments
            return self::get_uncategorized_attachments();
        }

        $args = array(
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'tax_query'      => array(
                array(
                    'taxonomy' => self::TAXONOMY,
                    'field'    => 'term_id',
                    'terms'    => $folder_id,
                ),
            ),
        );

        return get_posts($args);
    }

    /**
     * Get uncategorized attachments (not in any folder)
     *
     * @return array Array of attachment IDs
     */
    public static function get_uncategorized_attachments() {
        global $wpdb;

        // Get all attachment IDs that ARE in a folder
        $attachments_in_folders = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT tr.object_id
                FROM {$wpdb->term_relationships} tr
                INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                WHERE tt.taxonomy = %s",
                self::TAXONOMY
            )
        );

        // Get all attachments
        $args = array(
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        );

        // Exclude attachments that are in folders
        if (!empty($attachments_in_folders)) {
            $args['post__not_in'] = $attachments_in_folders;
        }

        return get_posts($args);
    }

    /**
     * Assign attachment to folder
     *
     * @param int $attachment_id Attachment post ID
     * @param int $folder_id     Folder term ID (0 to remove from all folders)
     * @return bool
     */
    public static function assign_to_folder($attachment_id, $folder_id) {
        // Get current folder assignments before changing
        $old_terms = wp_get_object_terms($attachment_id, self::TAXONOMY, array('fields' => 'ids'));

        if ($folder_id === 0) {
            // Remove from all folders
            wp_set_object_terms($attachment_id, array(), self::TAXONOMY);
        } else {
            $result = wp_set_object_terms($attachment_id, array($folder_id), self::TAXONOMY);
            if (is_wp_error($result)) {
                return false;
            }
        }

        // Force recount for old folders (to decrease their counts)
        if (!empty($old_terms) && !is_wp_error($old_terms)) {
            wp_update_term_count_now($old_terms, self::TAXONOMY);
        }

        // Force recount for new folder (to increase its count)
        if ($folder_id > 0) {
            wp_update_term_count_now(array($folder_id), self::TAXONOMY);
        }

        // Clear any cached term counts
        clean_term_cache($folder_id, self::TAXONOMY);

        return true;
    }

    /**
     * Get folder count (number of attachments)
     *
     * @param int $folder_id Folder term ID
     * @return int
     */
    public static function get_folder_count($folder_id) {
        if ($folder_id === 0) {
            return count(self::get_uncategorized_attachments());
        }

        $term = get_term($folder_id, self::TAXONOMY);
        if (is_wp_error($term) || !$term) {
            return 0;
        }

        return (int) $term->count;
    }

    /**
     * Build folder tree structure
     *
     * @param int $parent_id Parent folder ID
     * @return array
     */
    public static function get_folder_tree($parent_id = 0) {
        $folders = self::get_folders(array('parent' => $parent_id));
        $tree = array();

        foreach ($folders as $folder) {
            $tree[] = array(
                'id'       => $folder->term_id,
                'name'     => $folder->name,
                'slug'     => $folder->slug,
                'parent'   => $folder->parent,
                'count'    => self::count_folder_attachments($folder->term_id),
                'children' => self::get_folder_tree($folder->term_id),
            );
        }

        return $tree;
    }

    /**
     * Count attachments in a folder (handles 'inherit' post status)
     *
     * @param int $folder_id Folder term ID
     * @return int
     */
    public static function count_folder_attachments($folder_id) {
        global $wpdb;

        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(DISTINCT p.ID)
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
                INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                WHERE tt.taxonomy = %s
                AND tt.term_id = %d
                AND p.post_type = 'attachment'
                AND p.post_status = 'inherit'",
                self::TAXONOMY,
                $folder_id
            )
        );

        return (int) $count;
    }
}
