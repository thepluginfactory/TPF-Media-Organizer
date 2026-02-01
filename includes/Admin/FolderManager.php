<?php
/**
 * Folder Manager
 *
 * @package TPFMediaOrganizer\Admin
 */

namespace TPFMediaOrganizer\Admin;

use TPFMediaOrganizer\Core\Taxonomy;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles folder CRUD operations via AJAX
 */
class FolderManager {

    /**
     * Single instance
     *
     * @var FolderManager|null
     */
    private static $instance = null;

    /**
     * Get instance
     *
     * @return FolderManager
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
        // AJAX handlers
        add_action('wp_ajax_tpf_mo_create_folder', array($this, 'ajax_create_folder'));
        add_action('wp_ajax_tpf_mo_rename_folder', array($this, 'ajax_rename_folder'));
        add_action('wp_ajax_tpf_mo_delete_folder', array($this, 'ajax_delete_folder'));
        add_action('wp_ajax_tpf_mo_move_folder', array($this, 'ajax_move_folder'));
        add_action('wp_ajax_tpf_mo_get_folders', array($this, 'ajax_get_folders'));
        add_action('wp_ajax_tpf_mo_assign_folder', array($this, 'ajax_assign_folder'));
    }

    /**
     * Verify AJAX request
     *
     * @return bool
     */
    private function verify_request() {
        if (!check_ajax_referer('tpf_mo_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed.', 'tpf-media-organizer')));
            return false;
        }

        if (!current_user_can('upload_files')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'tpf-media-organizer')));
            return false;
        }

        return true;
    }

    /**
     * AJAX: Create folder
     */
    public function ajax_create_folder() {
        if (!$this->verify_request()) {
            return;
        }

        $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
        $parent_id = isset($_POST['parent_id']) ? absint($_POST['parent_id']) : 0;

        if (empty($name)) {
            wp_send_json_error(array('message' => __('Folder name is required.', 'tpf-media-organizer')));
            return;
        }

        $result = Taxonomy::create_folder($name, $parent_id);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
            return;
        }

        $folder = Taxonomy::get_folder($result);

        wp_send_json_success(array(
            'message' => __('Folder created successfully.', 'tpf-media-organizer'),
            'folder'  => array(
                'id'     => $folder->term_id,
                'name'   => $folder->name,
                'slug'   => $folder->slug,
                'parent' => $folder->parent,
                'count'  => 0,
            ),
        ));
    }

    /**
     * AJAX: Rename folder
     */
    public function ajax_rename_folder() {
        if (!$this->verify_request()) {
            return;
        }

        $folder_id = isset($_POST['folder_id']) ? absint($_POST['folder_id']) : 0;
        $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';

        if ($folder_id === 0) {
            wp_send_json_error(array('message' => __('Invalid folder.', 'tpf-media-organizer')));
            return;
        }

        if (empty($name)) {
            wp_send_json_error(array('message' => __('Folder name is required.', 'tpf-media-organizer')));
            return;
        }

        $result = Taxonomy::update_folder($folder_id, array('name' => $name));

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
            return;
        }

        $folder = Taxonomy::get_folder($folder_id);

        wp_send_json_success(array(
            'message' => __('Folder renamed successfully.', 'tpf-media-organizer'),
            'folder'  => array(
                'id'   => $folder->term_id,
                'name' => $folder->name,
                'slug' => $folder->slug,
            ),
        ));
    }

    /**
     * AJAX: Delete folder
     */
    public function ajax_delete_folder() {
        if (!$this->verify_request()) {
            return;
        }

        $folder_id = isset($_POST['folder_id']) ? absint($_POST['folder_id']) : 0;
        $reassign = isset($_POST['reassign']) && $_POST['reassign'] === 'true';

        if ($folder_id === 0) {
            wp_send_json_error(array('message' => __('Invalid folder.', 'tpf-media-organizer')));
            return;
        }

        $result = Taxonomy::delete_folder($folder_id, $reassign);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
            return;
        }

        wp_send_json_success(array(
            'message' => __('Folder deleted successfully.', 'tpf-media-organizer'),
        ));
    }

    /**
     * AJAX: Move folder (change parent)
     */
    public function ajax_move_folder() {
        if (!$this->verify_request()) {
            return;
        }

        $folder_id = isset($_POST['folder_id']) ? absint($_POST['folder_id']) : 0;
        $parent_id = isset($_POST['parent_id']) ? absint($_POST['parent_id']) : 0;

        if ($folder_id === 0) {
            wp_send_json_error(array('message' => __('Invalid folder.', 'tpf-media-organizer')));
            return;
        }

        // Prevent moving folder to itself or its descendants
        if ($folder_id === $parent_id) {
            wp_send_json_error(array('message' => __('Cannot move folder to itself.', 'tpf-media-organizer')));
            return;
        }

        // Check if parent is a descendant of the folder
        if ($parent_id > 0) {
            $ancestors = get_ancestors($parent_id, Taxonomy::TAXONOMY, 'taxonomy');
            if (in_array($folder_id, $ancestors, true)) {
                wp_send_json_error(array('message' => __('Cannot move folder to its own descendant.', 'tpf-media-organizer')));
                return;
            }
        }

        $result = Taxonomy::update_folder($folder_id, array('parent' => $parent_id));

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
            return;
        }

        wp_send_json_success(array(
            'message' => __('Folder moved successfully.', 'tpf-media-organizer'),
        ));
    }

    /**
     * AJAX: Get all folders
     */
    public function ajax_get_folders() {
        if (!$this->verify_request()) {
            return;
        }

        $tree = Taxonomy::get_folder_tree();

        wp_send_json_success(array(
            'folders' => $tree,
        ));
    }

    /**
     * AJAX: Assign media to folder
     */
    public function ajax_assign_folder() {
        if (!$this->verify_request()) {
            return;
        }

        $attachment_ids = isset($_POST['attachment_ids']) ? array_map('absint', (array) $_POST['attachment_ids']) : array();
        $folder_id = isset($_POST['folder_id']) ? absint($_POST['folder_id']) : 0;

        if (empty($attachment_ids)) {
            wp_send_json_error(array('message' => __('No media items selected.', 'tpf-media-organizer')));
            return;
        }

        $success_count = 0;
        foreach ($attachment_ids as $attachment_id) {
            if (Taxonomy::assign_to_folder($attachment_id, $folder_id)) {
                $success_count++;
            }
        }

        wp_send_json_success(array(
            'message' => sprintf(
                /* translators: %d: number of items */
                _n(
                    '%d item moved to folder.',
                    '%d items moved to folder.',
                    $success_count,
                    'tpf-media-organizer'
                ),
                $success_count
            ),
        ));
    }
}
