<?php
namespace AQM_Sitemaps\Updater;

use stdClass;

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

class GitHub_Updater {
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
        $this->transient_name = 'aqm_github_update_' . md5($this->plugin_basename);
        
        // Get current plugin data
        if (!function_exists('get_plugin_data')) {
            require_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }
        $this->plugin_data = get_plugin_data($this->plugin_file);
        $this->current_version = $this->plugin_data['Version'];
        
        // Add filters for plugin update checks
        add_filter('pre_set_site_transient_update_plugins', array($this, 'check_for_updates'));
        add_filter('plugins_api', array($this, 'plugin_info'), 10, 3);
        add_filter('plugin_action_links_' . $this->plugin_basename, array($this, 'add_action_links'));
        
        // Add admin notice for updates
        add_action('admin_notices', array($this, 'show_update_notice'));
        add_action('admin_notices', array($this, 'show_check_success_notice'));
        
        // Handle manual update check
        add_action('admin_init', array($this, 'handle_manual_update_check'));
        
        // Add success redirect after update
        $this->add_success_redirect();
        
        // Fix directory structure during updates
        add_filter('upgrader_source_selection', array($this, 'fix_directory_name'), 10, 4);
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
        // Always check for updates, even if the transient is empty
        if (!is_object($transient)) {
            $transient = new stdClass();
        }
        
        // Log the transient object state
        error_log('AQM Sitemaps: Transient state: ' . (is_object($transient) ? 'is object' : 'not object'));
        
        if (!isset($transient->checked)) {
            $transient->checked = array();
        }
        
        // Force our plugin to be in the checked list
        $transient->checked[$this->plugin_basename] = $this->current_version;
        
        // Check if we're explicitly checking for updates
        $force_check = false;
        if (isset($_GET['aqm_checked']) && $_GET['aqm_checked'] === '1') {
            $force_check = true;
        }
        
        // Get update data from GitHub, force refresh if explicitly checking
        $update_data = $this->get_github_update_data($force_check);
        
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
            
            // Use the download URL from the update data, which should be the zipball_url
            $obj->package = $update_data['download_url'];
            
            // Log the package URL
            error_log('AQM Sitemaps: Using package URL: ' . $obj->package);
            
            // Verify the package URL is accessible
            $test_response = wp_remote_head($obj->package, array('timeout' => 5));
            if (is_wp_error($test_response) || wp_remote_retrieve_response_code($test_response) !== 200) {
                error_log('AQM Sitemaps: Package URL is not accessible, trying fallback URL');
                // Fallback to a direct GitHub download URL
                $obj->package = 'https://github.com/' . $this->github_username . '/' . $this->github_repository . '/archive/refs/tags/v' . $update_data['version'] . '.zip';
                error_log('AQM Sitemaps: Using fallback package URL: ' . $obj->package);
            }
            
            // Add to the response array
            if (!isset($transient->response)) {
                $transient->response = array();
            }
            
            $transient->response[$this->plugin_basename] = $obj;
            
            // Log that we found an update
            error_log('AQM Sitemaps: Update available - ' . $this->current_version . ' -> ' . $update_data['version']);
        } else {
            // Log that no update was found
            error_log('AQM Sitemaps: No update available or unable to check - Current version: ' . $this->current_version);
            if ($update_data) {
                error_log('AQM Sitemaps: Latest version from GitHub: ' . $update_data['version']);
            }
        }
        
        return $transient;
    }

    /**
     * Get update data from GitHub
     * 
     * @param bool $force_check Whether to force a fresh check ignoring cache
     * @return array|false Update data or false on failure
     */
    private function get_github_update_data($force_check = false) {
        // Force clear cache when manually checking for updates
        if ($force_check || (isset($_GET['aqm_checked']) && $_GET['aqm_checked'] === '1')) {
            delete_transient($this->transient_name);
            error_log('AQM Sitemaps: Forcing fresh update check from GitHub');
        } else {
            // Check cache first
            $cached_data = get_transient($this->transient_name);
            if ($cached_data !== false) {
                error_log('AQM Sitemaps: Using cached update data');
                return $cached_data;
            }
        }
        
        // Get latest release from GitHub API
        $api_url = 'https://api.github.com/repos/' . $this->github_username . '/' . $this->github_repository . '/releases/latest';
        error_log('AQM Sitemaps: Checking GitHub API: ' . $api_url);
        
        $response = wp_remote_get($api_url, array(
            'headers' => array(
                'Accept' => 'application/vnd.github.v3+json',
                'User-Agent' => 'WordPress/' . get_bloginfo('version')
            ),
            'timeout' => 30, // Increased timeout for slower connections
            'sslverify' => true // Enable SSL verification for security
        ));
        
        if (is_wp_error($response)) {
            error_log('AQM Sitemaps: GitHub API Error: ' . $response->get_error_message());
            error_log('AQM Sitemaps: Error code: ' . $response->get_error_code());
            return false;
        } else if (wp_remote_retrieve_response_code($response) !== 200) {
            $status_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);
            error_log('AQM Sitemaps: GitHub API returned status code: ' . $status_code);
            error_log('AQM Sitemaps: Response body: ' . $response_body);
            return false;
        }
        
        error_log('AQM Sitemaps: GitHub API request successful');
        
        $release_data = json_decode(wp_remote_retrieve_body($response), true);
        
        if (empty($release_data)) {
            error_log('AQM Sitemaps: Empty release data from GitHub');
            return false;
        }
        
        if (!isset($release_data['tag_name'])) {
            error_log('AQM Sitemaps: No tag_name found in GitHub release data');
            error_log('AQM Sitemaps: Release data keys: ' . implode(', ', array_keys($release_data)));
            return false;
        }
        
        error_log('AQM Sitemaps: Found GitHub release with tag: ' . $release_data['tag_name']);
        
        // Format version number (remove 'v' prefix if present)
        $version = ltrim($release_data['tag_name'], 'v');
        error_log('AQM Sitemaps: Formatted version: ' . $version);
        error_log('AQM Sitemaps: Original tag name: ' . $release_data['tag_name']);
        
        // Use GitHub's zipball_url which is more reliable than constructing our own URL
        $download_url = isset($release_data['zipball_url']) ? $release_data['zipball_url'] : '';
        
        // If zipball_url is not available, fall back to a direct download URL
        if (empty($download_url)) {
            // Try both formats - with and without 'v' prefix
            $tag_with_v = (strpos($release_data['tag_name'], 'v') === 0) ? $release_data['tag_name'] : 'v' . $release_data['tag_name'];
            $tag_without_v = ltrim($release_data['tag_name'], 'v');
            
            // First try with the exact tag name from the release
            $download_url = 'https://github.com/' . $this->github_username . '/' . $this->github_repository . '/archive/refs/tags/' . $release_data['tag_name'] . '.zip';
            error_log('AQM Sitemaps: Zipball URL not found, trying primary fallback URL: ' . $download_url);
            
            // Test if the URL is accessible
            $test_response = wp_remote_head($download_url, array('timeout' => 5));
            if (is_wp_error($test_response) || wp_remote_retrieve_response_code($test_response) !== 200) {
                // Try with 'v' prefix if not already there
                $download_url = 'https://github.com/' . $this->github_username . '/' . $this->github_repository . '/archive/refs/tags/' . $tag_with_v . '.zip';
                error_log('AQM Sitemaps: Primary fallback failed, trying with v-prefix: ' . $download_url);
                
                // Test again
                $test_response = wp_remote_head($download_url, array('timeout' => 5));
                if (is_wp_error($test_response) || wp_remote_retrieve_response_code($test_response) !== 200) {
                    // Try without 'v' prefix as last resort
                    $download_url = 'https://github.com/' . $this->github_username . '/' . $this->github_repository . '/archive/refs/tags/' . $tag_without_v . '.zip';
                    error_log('AQM Sitemaps: Secondary fallback failed, trying without v-prefix: ' . $download_url);
                }
            }
        } else {
            error_log('AQM Sitemaps: Using GitHub zipball_url: ' . $download_url);
        }
        
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
        
        // Check if we're dealing with a plugin update
        if (!isset($hook_extra['plugin']) && isset($hook_extra['theme'])) {
            return $source; // This is a theme update, not our plugin
        }
        
        // For plugin updates, check if it's our plugin
        if (isset($hook_extra['plugin']) && $hook_extra['plugin'] !== $this->plugin_basename) {
            return $source; // Not our plugin
        }
        
        // For core updates or bulk updates where plugin isn't specified
        if (!isset($hook_extra['plugin'])) {
            // Try to detect if this is our plugin based on the source directory
            $plugin_slug = dirname($this->plugin_basename);
            $source_basename = basename($source);
            
            // Check if the source directory contains our repository name
            if (strpos($source_basename, $this->github_repository) === false) {
                return $source; // Likely not our plugin
            }
            
            error_log('AQM Sitemaps: Detected potential plugin update during bulk update');
        }
        
        error_log('AQM Sitemaps: Fixing directory name during update');
        error_log('AQM Sitemaps: Source directory: ' . $source);
        
        // Get the expected plugin slug (folder name)
        $plugin_slug = dirname($this->plugin_basename);
        error_log('AQM Sitemaps: Plugin slug: ' . $plugin_slug);
        
        // Check if the source directory already has the correct name
        $source_basename = basename($source);
        if ($source_basename === $plugin_slug) {
            error_log('AQM Sitemaps: Source directory already has the correct name');
            return $source;
        }
        
        // GitHub zipball typically creates a directory like 'username-repository-hash' or 'repository-tag'
        // We need to rename it to match our plugin slug
        $correct_directory = trailingslashit($remote_source) . $plugin_slug;
        error_log('AQM Sitemaps: Target directory: ' . $correct_directory);
        
        // If the target directory already exists, remove it first
        if ($wp_filesystem->exists($correct_directory)) {
            error_log('AQM Sitemaps: Target directory exists, removing it');
            $wp_filesystem->delete($correct_directory, true);
        }
        
        // Check if source directory exists
        if (!$wp_filesystem->exists($source)) {
            error_log('AQM Sitemaps: Source directory does not exist: ' . $source);
            return $source;
        }
        
        // Rename the directory
        error_log('AQM Sitemaps: Attempting to rename ' . $source . ' to ' . $correct_directory);
        if ($wp_filesystem->move($source, $correct_directory)) {
            error_log('AQM Sitemaps: Directory renamed successfully');
            return $correct_directory;
        } else {
            error_log('AQM Sitemaps: Failed to rename directory');
            
            // Log filesystem details for debugging
            error_log('AQM Sitemaps: WP Filesystem method: ' . get_filesystem_method());
            
            // Try to determine why the move failed
            if (!$wp_filesystem->is_writable($remote_source)) {
                error_log('AQM Sitemaps: Remote source directory is not writable');
            }
            
            if ($wp_filesystem->exists($correct_directory)) {
                error_log('AQM Sitemaps: Target directory already exists after failed move');
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
        
        // Clear all update-related transients
        delete_transient($this->transient_name);
        delete_site_transient('update_plugins');
        delete_site_transient('update_themes');
        delete_site_transient('update_core');
        
        // Force WordPress to check for updates
        wp_clean_plugins_cache(true);
        
        // Force refresh of plugin update information
        $this->force_update_check();
        
        // Redirect back to the plugins page
        wp_redirect(admin_url('plugins.php?aqm_checked=1'));
        exit;
    }
    
    /**
     * Force an immediate update check
     */
    private function force_update_check() {
        $current = get_site_transient('update_plugins');
        if (!is_object($current)) {
            $current = new stdClass();
        }
        
        error_log('AQM Sitemaps: Forcing update check');
        
        if (!isset($current->checked)) {
            $current->checked = array();
        }
        
        // Set the last_checked to 0 to force a fresh check
        $current->last_checked = 0;
        
        // Make sure our plugin is in the checked list
        $current->checked[$this->plugin_basename] = $this->current_version;
        
        // Save the modified transient
        set_site_transient('update_plugins', $current);
    }
    
    /**
     * Add a query parameter to the success redirect URL
     * This will be called after a successful update
     */
    public function add_success_redirect() {
        add_filter('upgrader_post_install', array($this, 'after_update_success'), 10, 3);
    }
    
    /**
     * After update success callback
     * 
     * @param bool|WP_Error $response Installation response
     * @param array $hook_extra Extra arguments passed to hooked filters
     * @param array $result Installation result data
     * @return bool|WP_Error The passed result
     */
    public function after_update_success($response, $hook_extra, $result) {
        if (isset($hook_extra['plugin']) && $hook_extra['plugin'] === $this->plugin_basename) {
            error_log('AQM Sitemaps: Update completed successfully');
            
            // Clear all update-related transients
            delete_transient($this->transient_name);
            delete_site_transient('update_plugins');
            
            // Mark that the plugin was updated
            update_option('aqm_sitemaps_needs_update_check', true);
            
            // Make sure the plugin is marked as active
            update_option('aqm_sitemaps_was_active', true);
            
            // Add our custom redirect parameter
            add_filter('wp_redirect', function($location) {
                return add_query_arg('aqm_updated', '1', $location);
            });
            
            // Log the update success with version info
            if (!function_exists('get_plugin_data')) {
                require_once(ABSPATH . 'wp-admin/includes/plugin.php');
            }
            $plugin_data = get_plugin_data($this->plugin_file);
            $new_version = $plugin_data['Version'];
            error_log('AQM Sitemaps: Plugin updated to version ' . $new_version);
        }
        return $response;
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
