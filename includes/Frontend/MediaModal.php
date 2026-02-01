<?php
/**
 * Media Modal Integration
 *
 * @package TPFMediaOrganizer\Frontend
 */

namespace TPFMediaOrganizer\Frontend;

use TPFMediaOrganizer\Core\Taxonomy;
use TPFMediaOrganizer\Core\DefaultSettings;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Adds folder filtering to the WordPress media modal
 */
class MediaModal {

    /**
     * Single instance
     *
     * @var MediaModal|null
     */
    private static $instance = null;

    /**
     * Get instance
     *
     * @return MediaModal
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
        // Always filter AJAX queries (needed for both modal and main library grid)
        add_filter('ajax_query_attachments_args', array($this, 'filter_attachments_query'));

        // Add folder data to attachment JSON
        add_filter('wp_prepare_attachment_for_js', array($this, 'add_folder_to_attachment'), 10, 3);

        // Only add modal UI if setting is enabled
        if (DefaultSettings::get_setting('tpf_mo_enable_modal_filter')) {
            add_action('wp_enqueue_media', array($this, 'enqueue_modal_scripts'));
        }
    }

    /**
     * Enqueue scripts for media modal
     */
    public function enqueue_modal_scripts() {
        wp_enqueue_style(
            'tpf-media-organizer-modal',
            TPF_MEDIA_ORGANIZER_PLUGIN_URL . 'assets/css/media-modal.css',
            array(),
            TPF_MEDIA_ORGANIZER_VERSION
        );

        wp_enqueue_script(
            'tpf-media-organizer-modal',
            TPF_MEDIA_ORGANIZER_PLUGIN_URL . 'assets/js/media-modal.js',
            array('jquery', 'media-views'),
            TPF_MEDIA_ORGANIZER_VERSION,
            true
        );

        wp_localize_script('tpf-media-organizer-modal', 'tpfMediaOrganizerModal', array(
            'folders'  => $this->get_folders_for_dropdown(),
            'settings' => array(
                'showUncategorized' => DefaultSettings::get_setting('tpf_mo_show_uncategorized'),
            ),
            'strings'  => array(
                'allFolders'    => __('All Folders', 'tpf-media-organizer'),
                'uncategorized' => __('Uncategorized', 'tpf-media-organizer'),
                'filterLabel'   => __('Filter by Folder', 'tpf-media-organizer'),
            ),
        ));
    }

    /**
     * Get folders formatted for dropdown
     *
     * @return array
     */
    private function get_folders_for_dropdown() {
        $folders = Taxonomy::get_folders();
        $options = array();

        foreach ($folders as $folder) {
            $depth = count(get_ancestors($folder->term_id, Taxonomy::TAXONOMY, 'taxonomy'));
            $options[] = array(
                'id'    => $folder->term_id,
                'name'  => $folder->name,
                'depth' => $depth,
                'count' => $folder->count,
            );
        }

        return $options;
    }

    /**
     * Filter attachments query in media modal
     *
     * @param array $query Query arguments
     * @return array
     */
    public function filter_attachments_query($query) {
        // Check if folder filter is set
        if (empty($_REQUEST['tpf_media_folder'])) {
            return $query;
        }

        $folder = sanitize_text_field($_REQUEST['tpf_media_folder']);

        if ($folder === 'uncategorized') {
            $query['tax_query'] = array(
                array(
                    'taxonomy' => Taxonomy::TAXONOMY,
                    'operator' => 'NOT EXISTS',
                ),
            );
        } elseif (is_numeric($folder) && absint($folder) > 0) {
            $query['tax_query'] = array(
                array(
                    'taxonomy' => Taxonomy::TAXONOMY,
                    'field'    => 'term_id',
                    'terms'    => absint($folder),
                ),
            );
        }

        return $query;
    }

    /**
     * Add folder information to attachment JSON
     *
     * @param array       $response   Attachment data
     * @param \WP_Post    $attachment Attachment post object
     * @param array|false $meta       Attachment meta
     * @return array
     */
    public function add_folder_to_attachment($response, $attachment, $meta) {
        $terms = wp_get_object_terms($attachment->ID, Taxonomy::TAXONOMY);

        $response['tpfFolders'] = array();

        if (!is_wp_error($terms) && !empty($terms)) {
            foreach ($terms as $term) {
                $response['tpfFolders'][] = array(
                    'id'   => $term->term_id,
                    'name' => $term->name,
                    'slug' => $term->slug,
                );
            }
        }

        return $response;
    }
}
