<?php
/**
 * Our Plugins Promotional Page
 *
 * @package TPFMediaOrganizer\Admin
 */

namespace TPFMediaOrganizer\Admin;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Displays promotional page for other TPF plugins
 */
class OurPlugins {

    /**
     * Single instance
     *
     * @var OurPlugins|null
     */
    private static $instance = null;

    /**
     * Get instance
     *
     * @return OurPlugins
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
        add_action('admin_menu', array($this, 'add_submenu_page'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_styles'));
    }

    /**
     * Add submenu page
     */
    public function add_submenu_page() {
        add_submenu_page(
            Settings::PAGE_SLUG,
            __('More Plugins', 'tpf-media-organizer'),
            __('More Plugins', 'tpf-media-organizer'),
            'upload_files',
            'tpf-media-organizer-plugins',
            array($this, 'render_page')
        );
    }

    /**
     * Enqueue styles for the plugins page
     *
     * @param string $hook Current admin page
     */
    public function enqueue_styles($hook) {
        if ($hook !== 'media-organizer_page_tpf-media-organizer-plugins') {
            return;
        }

        wp_enqueue_style(
            'tpf-media-organizer-plugins-page',
            TPF_MEDIA_ORGANIZER_PLUGIN_URL . 'assets/css/our-plugins.css',
            array(),
            TPF_MEDIA_ORGANIZER_VERSION
        );
    }

    /**
     * Get list of TPF plugins to promote
     *
     * @return array
     */
    private function get_plugins() {
        return array(
            array(
                'name'        => 'TPF Starter Slider',
                'slug'        => 'tpf-starter-slider',
                'description' => __('A simple, powerful slider plugin for WordPress. Create beautiful sliders with text overlays, links, and smooth transitions.', 'tpf-media-organizer'),
                'icon'        => 'dashicons-images-alt2',
                'color'       => '#4CAF50',
                'wp_url'      => 'https://wordpress.org/plugins/tpf-starter-slider/',
                'pro_url'     => 'https://thepluginfactory.com/plugins/tpf-slider-pro/',
                'has_pro'     => true,
            ),
            array(
                'name'        => 'Easysign Pro',
                'slug'        => 'easysign-pro',
                'description' => __('Professional digital signature plugin for WordPress. Create legally binding agreements with email verification, compliant with ESIGN Act.', 'tpf-media-organizer'),
                'icon'        => 'dashicons-edit',
                'color'       => '#2196F3',
                'wp_url'      => 'https://thepluginfactory.com/plugins/easysign-pro/',
                'pro_url'     => null,
                'has_pro'     => false,
            ),
            array(
                'name'        => 'FB Call Now',
                'slug'        => 'fb-call-now',
                'description' => __('Add a floating "Call Now" button to your site. Increase customer calls with one-tap dialing on mobile devices.', 'tpf-media-organizer'),
                'icon'        => 'dashicons-phone',
                'color'       => '#FF5722',
                'wp_url'      => 'https://wordpress.org/plugins/fb-call-now/',
                'pro_url'     => 'https://thepluginfactory.com/plugins/fb-call-now-pro/',
                'has_pro'     => true,
            ),
            array(
                'name'        => 'FB Message Now',
                'slug'        => 'fb-message-now',
                'description' => __('A floating message button that opens a contact form popup for instant customer communication.', 'tpf-media-organizer'),
                'icon'        => 'dashicons-email-alt',
                'color'       => '#9C27B0',
                'wp_url'      => 'https://wordpress.org/plugins/fb-message-now/',
                'pro_url'     => null,
                'has_pro'     => false,
            ),
            array(
                'name'        => 'FB Download Now',
                'slug'        => 'fb-download-now',
                'description' => __('Capture leads with gated downloads. Collect user information before allowing file downloads.', 'tpf-media-organizer'),
                'icon'        => 'dashicons-download',
                'color'       => '#00BCD4',
                'wp_url'      => 'https://wordpress.org/plugins/fb-download-now/',
                'pro_url'     => null,
                'has_pro'     => false,
            ),
            array(
                'name'        => 'FB Website Quote',
                'slug'        => 'fb-website-quote',
                'description' => __('A floating quote button that collects project requirements and provides instant cost estimates based on configurable pricing.', 'tpf-media-organizer'),
                'icon'        => 'dashicons-calculator',
                'color'       => '#795548',
                'wp_url'      => 'https://wordpress.org/plugins/fb-website-quote/',
                'pro_url'     => null,
                'has_pro'     => false,
            ),
            array(
                'name'        => 'FB Resources',
                'slug'        => 'fb-resources',
                'description' => __('Display a floating button with a customizable list of resource links in a popup window.', 'tpf-media-organizer'),
                'icon'        => 'dashicons-media-document',
                'color'       => '#607D8B',
                'wp_url'      => 'https://wordpress.org/plugins/fb-resources/',
                'pro_url'     => null,
                'has_pro'     => false,
            ),
            array(
                'name'        => 'Multi-Location Google Reviews',
                'slug'        => 'multi-location-google-reviews',
                'description' => __('Aggregate and display Google Reviews from multiple business locations. Perfect for businesses with multiple branches.', 'tpf-media-organizer'),
                'icon'        => 'dashicons-star-filled',
                'color'       => '#FFC107',
                'wp_url'      => 'https://thepluginfactory.com/plugins/multi-location-google-reviews/',
                'pro_url'     => null,
                'has_pro'     => false,
            ),
        );
    }

    /**
     * Check if a plugin is installed
     *
     * @param string $slug Plugin slug
     * @return bool
     */
    private function is_plugin_installed($slug) {
        $installed_plugins = get_plugins();

        foreach ($installed_plugins as $plugin_path => $plugin_info) {
            if (strpos($plugin_path, $slug) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a plugin is active
     *
     * @param string $slug Plugin slug
     * @return bool
     */
    private function is_plugin_active($slug) {
        $active_plugins = get_option('active_plugins', array());

        foreach ($active_plugins as $plugin_path) {
            if (strpos($plugin_path, $slug) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Render the plugins page
     */
    public function render_page() {
        $plugins = $this->get_plugins();
        ?>
        <div class="wrap tpf-plugins-page">
            <h1><?php esc_html_e('More Plugins from The Plugin Factory', 'tpf-media-organizer'); ?></h1>
            <p class="tpf-plugins-intro">
                <?php esc_html_e('Discover more quality WordPress plugins to enhance your website.', 'tpf-media-organizer'); ?>
            </p>

            <div class="tpf-plugins-grid">
                <?php foreach ($plugins as $plugin) :
                    $is_installed = $this->is_plugin_installed($plugin['slug']);
                    $is_active = $this->is_plugin_active($plugin['slug']);
                ?>
                    <div class="tpf-plugin-card">
                        <div class="tpf-plugin-icon" style="background-color: <?php echo esc_attr($plugin['color']); ?>">
                            <span class="dashicons <?php echo esc_attr($plugin['icon']); ?>"></span>
                        </div>
                        <div class="tpf-plugin-info">
                            <h3 class="tpf-plugin-name">
                                <?php echo esc_html($plugin['name']); ?>
                                <?php if ($plugin['has_pro']) : ?>
                                    <span class="tpf-pro-badge"><?php esc_html_e('Pro Available', 'tpf-media-organizer'); ?></span>
                                <?php endif; ?>
                            </h3>
                            <p class="tpf-plugin-description"><?php echo esc_html($plugin['description']); ?></p>
                            <div class="tpf-plugin-status">
                                <?php if ($is_active) : ?>
                                    <span class="tpf-status tpf-status-active">
                                        <span class="dashicons dashicons-yes-alt"></span>
                                        <?php esc_html_e('Active', 'tpf-media-organizer'); ?>
                                    </span>
                                <?php elseif ($is_installed) : ?>
                                    <span class="tpf-status tpf-status-installed">
                                        <span class="dashicons dashicons-saved"></span>
                                        <?php esc_html_e('Installed', 'tpf-media-organizer'); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="tpf-plugin-actions">
                                <?php if (!empty($plugin['wp_url'])) : ?>
                                    <a href="<?php echo esc_url($plugin['wp_url']); ?>"
                                       class="button button-primary"
                                       target="_blank">
                                        <?php esc_html_e('Learn More', 'tpf-media-organizer'); ?>
                                    </a>
                                <?php endif; ?>
                                <?php if ($plugin['has_pro'] && !empty($plugin['pro_url'])) : ?>
                                    <a href="<?php echo esc_url($plugin['pro_url']); ?>"
                                       class="button"
                                       target="_blank">
                                        <?php esc_html_e('Get Pro', 'tpf-media-organizer'); ?>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="tpf-plugins-footer">
                <p>
                    <span class="dashicons dashicons-admin-site-alt3"></span>
                    <?php
                    printf(
                        /* translators: %s: TPF website URL */
                        esc_html__('Visit %s for more plugins and updates.', 'tpf-media-organizer'),
                        '<a href="https://thepluginfactory.com" target="_blank">thepluginfactory.com</a>'
                    );
                    ?>
                </p>
            </div>
        </div>
        <?php
    }
}
