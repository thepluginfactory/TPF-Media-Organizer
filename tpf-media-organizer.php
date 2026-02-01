<?php
/**
 * Plugin Name: TPF Media Organizer
 * Plugin URI: https://thepluginfactory.com/plugins/tpf-media-organizer
 * Description: Organize your WordPress media library into folders for easier management. Create virtual folders, drag-and-drop organization, and filter media by folder.
 * Version: 1.0.2
 * Author: The Plugin Factory
 * Author URI: https://thepluginfactory.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: tpf-media-organizer
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('TPF_MEDIA_ORGANIZER_VERSION', '1.0.2');
define('TPF_MEDIA_ORGANIZER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TPF_MEDIA_ORGANIZER_PLUGIN_URL', plugin_dir_url(__FILE__));
define('TPF_MEDIA_ORGANIZER_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main plugin class
 */
final class TPF_Media_Organizer {

    /**
     * Single instance of the class
     *
     * @var TPF_Media_Organizer|null
     */
    private static $instance = null;

    /**
     * Get single instance of the class
     *
     * @return TPF_Media_Organizer
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
        $this->load_dependencies();
        $this->init_hooks();
    }

    /**
     * Load required dependencies
     */
    private function load_dependencies() {
        // Try Composer autoloader first
        $autoloader = TPF_MEDIA_ORGANIZER_PLUGIN_DIR . 'vendor/autoload.php';
        if (file_exists($autoloader)) {
            require_once $autoloader;
        } else {
            // Manual loading fallback
            $this->manual_load();
        }
    }

    /**
     * Manual class loading fallback
     */
    private function manual_load() {
        // Core
        require_once TPF_MEDIA_ORGANIZER_PLUGIN_DIR . 'includes/Core/DefaultSettings.php';
        require_once TPF_MEDIA_ORGANIZER_PLUGIN_DIR . 'includes/Core/Logger.php';
        require_once TPF_MEDIA_ORGANIZER_PLUGIN_DIR . 'includes/Core/Taxonomy.php';

        // Admin
        require_once TPF_MEDIA_ORGANIZER_PLUGIN_DIR . 'includes/Admin/Settings.php';
        require_once TPF_MEDIA_ORGANIZER_PLUGIN_DIR . 'includes/Admin/FolderManager.php';
        require_once TPF_MEDIA_ORGANIZER_PLUGIN_DIR . 'includes/Admin/MediaLibrary.php';
        require_once TPF_MEDIA_ORGANIZER_PLUGIN_DIR . 'includes/Admin/BulkActions.php';
        require_once TPF_MEDIA_ORGANIZER_PLUGIN_DIR . 'includes/Admin/OurPlugins.php';

        // Frontend
        require_once TPF_MEDIA_ORGANIZER_PLUGIN_DIR . 'includes/Frontend/MediaModal.php';
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Load text domain
        add_action('plugins_loaded', array($this, 'load_textdomain'));

        // Initialize components
        add_action('init', array($this, 'init_components'));

        // Activation/deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }

    /**
     * Load plugin text domain
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'tpf-media-organizer',
            false,
            dirname(TPF_MEDIA_ORGANIZER_PLUGIN_BASENAME) . '/languages'
        );
    }

    /**
     * Initialize plugin components
     */
    public function init_components() {
        // Core
        TPFMediaOrganizer\Core\Taxonomy::get_instance();

        // Admin only
        if (is_admin()) {
            TPFMediaOrganizer\Admin\Settings::get_instance();
            TPFMediaOrganizer\Admin\FolderManager::get_instance();
            TPFMediaOrganizer\Admin\MediaLibrary::get_instance();
            TPFMediaOrganizer\Admin\BulkActions::get_instance();
            TPFMediaOrganizer\Admin\OurPlugins::get_instance();
        }

        // Frontend (media modal in admin also uses this)
        TPFMediaOrganizer\Frontend\MediaModal::get_instance();
    }

    /**
     * Plugin activation
     */
    public function activate() {
        // Register taxonomy on activation
        TPFMediaOrganizer\Core\Taxonomy::get_instance()->register_taxonomy();

        // Flush rewrite rules
        flush_rewrite_rules();

        // Set default options
        $defaults = TPFMediaOrganizer\Core\DefaultSettings::get_defaults();
        foreach ($defaults as $key => $value) {
            if (get_option($key) === false) {
                update_option($key, $value);
            }
        }

        // Store activation time for future reference
        update_option('tpf_media_organizer_activated', time());
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        flush_rewrite_rules();
    }
}

// Initialize the plugin
TPF_Media_Organizer::get_instance();
