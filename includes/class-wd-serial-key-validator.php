<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class import-and-export-posts-to-csv {

    private static $instance = null;
    private $serial_key_validator;

    private function __construct() {
        // Initialize the serial key validator
        $serial_key_manager_url = 'https://yourwebsite.com/wp-json/wd_skm/v1/validate-key'; // Update with your actual URL
        $this->serial_key_validator = new WD_Serial_Key_Validator(
            $serial_key_manager_url,
            'wd_your_plugin_serial_key',
            'wd_your_plugin_activated',
            WD_YOUR_PLUGIN_DIR . 'logs/your-plugin-log.txt'
        );

        // Add admin menu for your plugin settings.
        add_action('admin_menu', [$this, 'add_admin_menu']);

        // Enqueue admin styles if needed.
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_styles']);

        // Register shortcode or other features.
        add_shortcode('wd_your_plugin_content', [$this, 'render_content_shortcode']);

        // Handle actions or form submissions.
        add_action('init', [$this, 'handle_actions']);

        // Add activation check on plugin activation.
        register_activation_hook(WD_YOUR_PLUGIN_DIR . 'import-and-export-posts-to-csv.php', [$this, 'check_activation']);

        // Add deactivation hook to clean up
        register_deactivation_hook(WD_YOUR_PLUGIN_DIR . 'import-and-export-posts-to-csv.php', [$this, 'on_deactivation']);
    }

    /**
     * Initialize the class instance.
     */
    public static function init() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Add admin menu for your plugin.
     */
    public function add_admin_menu() {
        add_menu_page(
            'Warf Designs Your Plugin',
            'Your Plugin',
            'manage_options',
            'wd_your_plugin',
            [$this, 'admin_page_content'],
            'dashicons-admin-generic',
            80
        );
    }

    /**
     * Enqueue admin styles.
     */
    public function enqueue_admin_styles($hook) {
        if ($hook !== 'toplevel_page_wd_your_plugin') {
            return;
        }
        wp_enqueue_style('wd-your-plugin-styles', WD_YOUR_PLUGIN_URL . 'assets/css/styles.css', [], WD_YOUR_PLUGIN_VERSION);
    }

    /**
     * Render the admin page content.
     */
    public function admin_page_content() {
        // Check if Serial Key Manager is active.
        if (!class_exists('WD_Serial_Key_UI')) {
            echo '<div class="notice notice-error"><p>Warf Designs Serial Key Manager is not active. Please activate it first.</p></div>';
            return;
        }

        // Handle plugin activation.
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Check nonce for security.
            if (!isset($_POST['wd_your_plugin_nonce']) || !wp_verify_nonce($_POST['wd_your_plugin_nonce'], 'wd_your_plugin_activate_plugin')) {
                echo '<div class="notice notice-error"><p>Security check failed.</p></div>';
                return;
            }

            if (isset($_POST['activate_plugin'])) {
                $serial_key = sanitize_text_field($_POST['wd_your_plugin_serial_key']);
                if ($this->serial_key_validator->validate_key($serial_key)) {
                    echo '<div class="notice notice-success"><p>Your Plugin has been activated successfully!</p></div>';
                } else {
                    echo '<div class="notice notice-error"><p>Invalid or inactive Serial Key. Please try again.</p></div>';
                }
            }
        }

        // Check activation status.
        $activated = $this->serial_key_validator->is_activated();

        ?>
        <div class="wrap">
            <h1>Warf Designs Your Plugin</h1>
            <?php if (!$activated) : ?>
                <p>Please enter your serial key to activate the plugin.</p>
                <?php
                // Render the serial key form.
                echo WD_Serial_Key_UI::init()->render_serial_key_form([
                    'form_title' => 'Activate Your Plugin',
                    'submit_button_text' => 'Activate',
                    'success_message' => 'Your Plugin has been activated successfully!',
                    'error_message' => 'Activation failed. Please check your serial key.',
                ]);
                ?>
            <?php else : ?>
                <p>Your Plugin is activated and ready to use!</p>
                <!-- Additional settings and features can be added here -->
                <!-- Example: Plugin functionalities, settings, etc. -->
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render content using shortcode.
     *
     * @param array $atts Shortcode attributes.
     * @return string HTML content.
     */
    public function render_content_shortcode($atts) {
        // Check activation status.
        if (!$this->serial_key_validator->is_activated()) {
            return '<p style="color: red;">This plugin is not activated. Please enter a valid serial key.</p>';
        }

        // Example content
        $content = '<div class="wd-your-plugin-content">';
        $content .= '<h2>Welcome to Your Plugin!</h2>';
        $content .= '<p>This plugin is activated and fully functional.</p>';
        // Add more content or functionalities as needed.
        $content .= '</div>';

        return $content;
    }

    /**
     * Handle plugin actions.
     */
    public function handle_actions() {
        // Add any actions or hooks that should only run when the plugin is activated.
        if ($this->serial_key_validator->is_activated()) {
            // Example: Initialize plugin features
        }
    }

    /**
     * Check activation status on plugin activation.
     */
    public function check_activation() {
        $serial_key = get_option('wd_your_plugin_serial_key');
        if (!$serial_key || !$this->serial_key_validator->validate_key($serial_key)) {
            deactivate_plugins(plugin_basename(WD_YOUR_PLUGIN_DIR . 'import-and-export-posts-to-csv.php'));
            wp_die('Warf Designs Your Plugin requires a valid serial key to be activated. Please enter a valid serial key.');
        }
    }

    /**
     * Clean up on plugin deactivation.
     */
    public function on_deactivation() {
        // Optionally, remove stored options or perform other clean-up tasks.
        // For example:
        // delete_option('import-and-export-posts-to-csv_serial_key');
        // delete_option('import-and-export-posts-to-csv_activated');
    }
}
