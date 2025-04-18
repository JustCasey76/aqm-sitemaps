<?php
/**
 * AQM GitHub Updater Class
 * 
 * This class provides update notifications for the AQM Sitemaps plugin
 * by checking the GitHub repository for new releases.
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class AQM_GitHub_Updater {
    private $plugin_file;
    private $plugin_basename;
    private $github_username;
    private $github_repository;
    private $plugin_data;
    private $current_version;
    private $transient_name = 'aqm_sitemaps_github_update_data';
    private $cache_expiration = 43200; // 12 hours in seconds

    /**
     * Class constructor
     * 
     * @param string $plugin_file Path to the plugin file
     * @param string $github_username GitHub username
     * @param string $github_repository GitHub repository name
     */
    public function __construct($plugin_file, $github_username, $github_repository) {
        $this->plugin_file = $plugin_file;
        $this->plugin_basename = plugin_basename($plugin_file);
        $this->github_username = $github_username;
        $this->github_repository = $github_repository;
        
        // Get plugin data
        if (!function_exists('get_plugin_data')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $this->plugin_data = get_plugin_data($this->plugin_file);
        $this->current_version = $this->plugin_data['Version'];
        
        // Add filters for the update process
        add_filter('pre_set_site_transient_update_plugins', array($this, 'check_for_updates'));
        add_filter('plugins_api', array($this, 'plugin_info'), 10, 3);
        add_filter('upgrader_source_selection', array($this, 'fix_directory_name'), 10, 4);
        add_filter('plugin_action_links_' . $this->plugin_basename, array($this, 'add_action_links'));
        
        // Add admin notice for updates
        add_action('admin_notices', array($this, 'show_update_notice'));
        add_action('admin_notices', array($this, 'show_check_success_notice'));
        
        // Handle manual update check
        add_action('admin_init', array($this, 'handle_manual_update_check'));
    }

    /**
     * Add "Check for Updates" link to plugin actions
     * 
     * @param array $links Existing action links
     * @return array Modified action links
     */
    public function add_action_links($links) {
        $check_update_link = '<a href="' . wp_nonce_url(admin_url('plugins.php?aqm_check_for_updates=1&plugin=' . $this->plugin_basename), 'aqm-check-update') . '">Check for Updates</a>';
        array_unshift($links, $check_update_link);
        return $links;
    }

    /**
     * Check for updates when WordPress checks for plugin updates
     * 
     * @param object $transient Transient data for plugin updates
     * @return object Modified transient data
     */
    public function check_for_updates($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }
        
        // Get update data from GitHub
        $update_data = $this->get_github_update_data();
        
        if ($update_data && version_compare($update_data['version'], $this->current_version, '>')) {
            // Create a standard plugin_information object
            $obj = new stdClass();
            $obj->id = $this->plugin_basename;
            $obj->slug = dirname($this->plugin_basename);
            $obj->plugin = $this->plugin_basename;
            $obj->new_version = $update_data['version'];
            $obj->url = $update_data['url'];
            $obj->package = $update_data['download_url'];
            $obj->tested = isset($update_data['tested']) ? $update_data['tested'] : '';
            $obj->requires_php = isset($update_data['requires_php']) ? $update_data['requires_php'] : '';
            $obj->compatibility = new stdClass();
            $obj->icons = array(
                '1x' => 'https://ps.w.org/aqm-sitemaps/assets/icon-128x128.png',
                '2x' => 'https://ps.w.org/aqm-sitemaps/assets/icon-256x256.png'
            );
            
            // Make sure the package URL is accessible
            $test_response = wp_remote_head($update_data['download_url'], array('timeout' => 5));
            if (is_wp_error($test_response) || wp_remote_retrieve_response_code($test_response) !== 200) {
                // Fallback to a direct GitHub download URL if the package URL is not accessible
                $obj->package = 'https://github.com/' . $this->github_username . '/' . $this->github_repository . '/archive/refs/tags/v' . $update_data['version'] . '.zip';
            }
            
            $transient->response[$this->plugin_basename] = $obj;
        }
        
        return $transient;
    }

    /**
     * Get update data from GitHub
     * 
     * @return array|false Update data or false on failure
     */
    private function get_github_update_data() {
        // Force clear cache when manually checking for updates
        if (isset($_GET['aqm_checked']) && $_GET['aqm_checked'] === '1') {
            delete_transient($this->transient_name);
        } else {
            // Check cache first
            $cached_data = get_transient($this->transient_name);
            if ($cached_data !== false) {
                return $cached_data;
            }
        }
        
        // Get latest release from GitHub API
        $api_url = 'https://api.github.com/repos/' . $this->github_username . '/' . $this->github_repository . '/releases/latest';
        $response = wp_remote_get($api_url, array(
            'headers' => array(
                'Accept' => 'application/vnd.github.v3+json',
                'User-Agent' => 'WordPress/' . get_bloginfo('version')
            ),
            'timeout' => 10
        ));
        
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return false;
        }
        
        $release_data = json_decode(wp_remote_retrieve_body($response), true);
        
        if (empty($release_data) || !isset($release_data['tag_name'])) {
            return false;
        }
        
        // Format version number (remove 'v' prefix if present)
        $version = ltrim($release_data['tag_name'], 'v');
        
        // Direct download URL for the repository archive
        $download_url = 'https://github.com/' . $this->github_username . '/' . $this->github_repository . '/archive/refs/tags/' . $release_data['tag_name'] . '.zip';
        
        $update_data = array(
            'version' => $version,
            'url' => isset($release_data['html_url']) ? $release_data['html_url'] : '',
            'download_url' => $download_url,
            'requires_php' => $this->plugin_data['RequiresPHP'],
            'tested' => $this->plugin_data['RequiresWP'],
            'last_updated' => isset($release_data['published_at']) ? date('Y-m-d', strtotime($release_data['published_at'])) : '',
            'changelog' => isset($release_data['body']) ? $release_data['body'] : ''
        );
        
        // Cache the data
        set_transient($this->transient_name, $update_data, $this->cache_expiration);
        
        return $update_data;
    }

    /**
     * Provide plugin information for the WordPress updates screen
     * 
     * @param false|object|array $result The result object or array
     * @param string $action The API action being performed
     * @param object $args Plugin API arguments
     * @return false|object Plugin information
     */
    public function plugin_info($result, $action, $args) {
        // Check if this is the right plugin
        if ($action !== 'plugin_information' || !isset($args->slug) || $args->slug !== dirname($this->plugin_basename)) {
            return $result;
        }
        
        $update_data = $this->get_github_update_data();
        
        if (!$update_data) {
            return $result;
        }
        
        $plugin_info = new stdClass();
        $plugin_info->name = $this->plugin_data['Name'];
        $plugin_info->slug = dirname($this->plugin_basename);
        $plugin_info->version = $update_data['version'];
        $plugin_info->author = $this->plugin_data['Author'];
        $plugin_info->author_profile = 'https://github.com/' . $this->github_username;
        $plugin_info->homepage = $this->plugin_data['PluginURI'];
        $plugin_info->requires = $this->plugin_data['RequiresWP'];
        $plugin_info->requires_php = $this->plugin_data['RequiresPHP'];
        $plugin_info->downloaded = 0;
        $plugin_info->last_updated = $update_data['last_updated'];
        $plugin_info->sections = array(
            'description' => $this->plugin_data['Description'],
            'changelog' => $update_data['changelog']
        );
        
        // Make sure the package URL is accessible
        $test_response = wp_remote_head($update_data['download_url'], array('timeout' => 5));
        if (is_wp_error($test_response) || wp_remote_retrieve_response_code($test_response) !== 200) {
            // Fallback to a direct GitHub download URL if the package URL is not accessible
            $plugin_info->download_link = 'https://github.com/' . $this->github_username . '/' . $this->github_repository . '/archive/refs/tags/v' . $update_data['version'] . '.zip';
        } else {
            $plugin_info->download_link = $update_data['download_url'];
        }
        
        // Add required fields for WordPress 5.5+
        $plugin_info->id = $this->plugin_basename;
        $plugin_info->compatibility = new stdClass();
        $plugin_info->icons = array(
            '1x' => 'https://ps.w.org/aqm-sitemaps/assets/icon-128x128.png',
            '2x' => 'https://ps.w.org/aqm-sitemaps/assets/icon-256x256.png'
        );
        
        return $plugin_info;
    }

    /**
     * Fix directory name during plugin update
     * 
     * @param string $source Source directory
     * @param string $remote_source Remote source
     * @param object $upgrader Upgrader instance
     * @param array $hook_extra Extra arguments
     * @return string Modified source
     */
    public function fix_directory_name($source, $remote_source, $upgrader, $hook_extra) {
        global $wp_filesystem;
        
        // Only apply to this plugin
        if (!isset($hook_extra['plugin']) || $hook_extra['plugin'] !== $this->plugin_basename) {
            return $source;
        }
        
        // The source directory from GitHub will be named like 'aqm-sitemaps-1.0.1'
        // We need to rename it to just 'aqm-sitemaps'
        $desired_folder_name = dirname($this->plugin_basename);
        $correct_directory = dirname($source) . '/' . $desired_folder_name;
        
        // Check if source directory contains the tag name (e.g., 'aqm-sitemaps-1.0.1')
        if (strpos(basename($source), $desired_folder_name . '-') !== false) {
            // If the target directory already exists, remove it first
            if ($wp_filesystem->exists($correct_directory)) {
                $wp_filesystem->delete($correct_directory, true);
            }
            
            // Rename the directory
            if ($wp_filesystem->move($source, $correct_directory)) {
                return $correct_directory;
            }
        }
        
        return $source;
    }

    /**
     * Show update notice in admin
     */
    public function show_update_notice() {
        if (!current_user_can('update_plugins')) {
            return;
        }
        
        $update_data = $this->get_github_update_data();
        
        if ($update_data && version_compare($update_data['version'], $this->current_version, '>')) {
            // Get the update URL
            $update_url = wp_nonce_url(
                self_admin_url('update.php?action=upgrade-plugin&plugin=' . urlencode($this->plugin_basename)),
                'upgrade-plugin_' . $this->plugin_basename
            );
            
            echo '<div class="notice notice-warning">';
            echo '<p><strong>AQM Sitemaps Update Available!</strong></p>';
            echo '<p>Version ' . esc_html($update_data['version']) . ' is available. You are currently using version ' . esc_html($this->current_version) . '.</p>';
            echo '<p><a href="' . esc_url($update_url) . '" class="button button-primary">Update Now</a></p>';
            echo '</div>';
        }
    }
    
    /**
     * Handle manual update check
     */
    public function handle_manual_update_check() {
        if (!isset($_GET['aqm_check_for_updates']) || !isset($_GET['plugin']) || $_GET['plugin'] !== $this->plugin_basename) {
            return;
        }
        
        // Verify nonce
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'aqm-check-update')) {
            wp_die('Security check failed');
        }
        
        // Clear the cached update data
        delete_transient($this->transient_name);
        
        // Force WordPress to check for updates
        wp_clean_plugins_cache(true);
        
        // Redirect back to the plugins page
        wp_redirect(admin_url('plugins.php?aqm_checked=1'));
        exit;
    }
    
    /**
     * Show success notice after checking for updates
     */
    public function show_check_success_notice() {
        if (!isset($_GET['aqm_checked']) || $_GET['aqm_checked'] !== '1') {
            return;
        }
        
        // Get update data
        $update_data = $this->get_github_update_data();
        
        echo '<div class="notice notice-success is-dismissible">';
        echo '<p><strong>AQM Sitemaps Update Check Complete</strong></p>';
        
        if ($update_data && version_compare($update_data['version'], $this->current_version, '>')) {
            echo '<p>A new version (' . esc_html($update_data['version']) . ') is available! You are currently using version ' . esc_html($this->current_version) . '.</p>';
        } else {
            echo '<p>You are running the latest version (' . esc_html($this->current_version) . ').</p>';
        }
        
        echo '</div>';
    }
}
