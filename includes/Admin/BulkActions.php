<?php
/**
 * Bulk Actions
 *
 * @package TPFMediaOrganizer\Admin
 */

namespace TPFMediaOrganizer\Admin;

use TPFMediaOrganizer\Core\Taxonomy;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles bulk folder assignment operations
 */
class BulkActions {

    /**
     * Single instance
     *
     * @var BulkActions|null
     */
    private static $instance = null;

    /**
     * Get instance
     *
     * @return BulkActions
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
        // Add bulk actions dropdown
        add_filter('bulk_actions-upload', array($this, 'add_bulk_actions'));

        // Handle bulk action
        add_filter('handle_bulk_actions-upload', array($this, 'handle_bulk_action'), 10, 3);

        // Show admin notice after bulk action
        add_action('admin_notices', array($this, 'bulk_action_notice'));

        // AJAX handler for bulk assign
        add_action('wp_ajax_tpf_mo_bulk_assign', array($this, 'ajax_bulk_assign'));
    }

    /**
     * Add bulk actions to dropdown
     *
     * @param array $actions Existing bulk actions
     * @return array
     */
    public function add_bulk_actions($actions) {
        $folders = Taxonomy::get_folders();

        if (empty($folders)) {
            return $actions;
        }

        // Add separator
        $actions['tpf_mo_separator'] = '─────────────';

        // Add "Move to folder" options
        foreach ($folders as $folder) {
            $depth = count(get_ancestors($folder->term_id, Taxonomy::TAXONOMY, 'taxonomy'));
            $indent = str_repeat('— ', $depth);
            $actions['tpf_mo_move_' . $folder->term_id] = sprintf(
                /* translators: %s: folder name */
                __('Move to: %s', 'tpf-media-organizer'),
                $indent . $folder->name
            );
        }

        // Add "Remove from folders" option
        $actions['tpf_mo_remove_folder'] = __('Remove from all folders', 'tpf-media-organizer');

        return $actions;
    }

    /**
     * Handle bulk action
     *
     * @param string $redirect_to Redirect URL
     * @param string $action      Action name
     * @param array  $post_ids    Selected post IDs
     * @return string
     */
    public function handle_bulk_action($redirect_to, $action, $post_ids) {
        // Check if it's our action
        if (strpos($action, 'tpf_mo_') !== 0) {
            return $redirect_to;
        }

        // Skip separator
        if ($action === 'tpf_mo_separator') {
            return $redirect_to;
        }

        $processed = 0;

        if ($action === 'tpf_mo_remove_folder') {
            // Remove from all folders
            foreach ($post_ids as $post_id) {
                if (Taxonomy::assign_to_folder($post_id, 0)) {
                    $processed++;
                }
            }
        } elseif (strpos($action, 'tpf_mo_move_') === 0) {
            // Move to specific folder
            $folder_id = absint(str_replace('tpf_mo_move_', '', $action));

            if ($folder_id > 0) {
                foreach ($post_ids as $post_id) {
                    if (Taxonomy::assign_to_folder($post_id, $folder_id)) {
                        $processed++;
                    }
                }
            }
        }

        return add_query_arg(array(
            'tpf_mo_bulk_action' => $action,
            'tpf_mo_processed'   => $processed,
        ), $redirect_to);
    }

    /**
     * Show admin notice after bulk action
     */
    public function bulk_action_notice() {
        if (empty($_GET['tpf_mo_bulk_action']) || !isset($_GET['tpf_mo_processed'])) {
            return;
        }

        $action = sanitize_text_field($_GET['tpf_mo_bulk_action']);
        $processed = absint($_GET['tpf_mo_processed']);

        if ($processed === 0) {
            return;
        }

        if ($action === 'tpf_mo_remove_folder') {
            $message = sprintf(
                /* translators: %d: number of items */
                _n(
                    '%d item removed from folders.',
                    '%d items removed from folders.',
                    $processed,
                    'tpf-media-organizer'
                ),
                $processed
            );
        } else {
            $folder_id = absint(str_replace('tpf_mo_move_', '', $action));
            $folder = Taxonomy::get_folder($folder_id);
            $folder_name = $folder ? $folder->name : __('folder', 'tpf-media-organizer');

            $message = sprintf(
                /* translators: 1: number of items, 2: folder name */
                _n(
                    '%1$d item moved to "%2$s".',
                    '%1$d items moved to "%2$s".',
                    $processed,
                    'tpf-media-organizer'
                ),
                $processed,
                $folder_name
            );
        }

        printf(
            '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
            esc_html($message)
        );
    }

    /**
     * AJAX: Bulk assign media to folder
     */
    public function ajax_bulk_assign() {
        check_ajax_referer('tpf_mo_nonce', 'nonce');

        if (!current_user_can('upload_files')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'tpf-media-organizer')));
            return;
        }

        $attachment_ids = isset($_POST['attachment_ids']) ? array_map('absint', (array) $_POST['attachment_ids']) : array();
        $folder_id = isset($_POST['folder_id']) ? absint($_POST['folder_id']) : 0;
        $action_type = isset($_POST['action_type']) ? sanitize_text_field($_POST['action_type']) : 'move';

        if (empty($attachment_ids)) {
            wp_send_json_error(array('message' => __('No media items selected.', 'tpf-media-organizer')));
            return;
        }

        $processed = 0;

        foreach ($attachment_ids as $attachment_id) {
            if ($action_type === 'add') {
                // Add to folder without removing from others
                $existing = wp_get_object_terms($attachment_id, Taxonomy::TAXONOMY, array('fields' => 'ids'));
                if (!in_array($folder_id, $existing, true)) {
                    $existing[] = $folder_id;
                    wp_set_object_terms($attachment_id, $existing, Taxonomy::TAXONOMY);
                    $processed++;
                }
            } else {
                // Move (replace existing folder assignments)
                if (Taxonomy::assign_to_folder($attachment_id, $folder_id)) {
                    $processed++;
                }
            }
        }

        $folder = $folder_id > 0 ? Taxonomy::get_folder($folder_id) : null;
        $folder_name = $folder ? $folder->name : __('Uncategorized', 'tpf-media-organizer');

        wp_send_json_success(array(
            'message'   => sprintf(
                /* translators: 1: number of items, 2: folder name */
                _n(
                    '%1$d item moved to "%2$s".',
                    '%1$d items moved to "%2$s".',
                    $processed,
                    'tpf-media-organizer'
                ),
                $processed,
                $folder_name
            ),
            'processed' => $processed,
        ));
    }
}
