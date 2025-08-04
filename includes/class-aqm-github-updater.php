<?php
namespace AQM_Sitemaps\Updater;

use stdClass;
use Exception;
use WP_Error;

/**
 * AQM GitHub Updater Class
 * 
 * This class provides update notifications for the AQM Sitemaps plugin
 * by checking the GitHub repository for new releases.
 * 
 * @version 1.1.0
 * @since 3.0.3
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
        add_action('admin_notices', array($this, 'show_update_success_notice'));
        
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
        // If we're doing a core update, don't interfere
        if (empty($transient) || !is_object($transient)) {
            return $transient;
        }
        
        // Log the check for updates
        error_log('AQM Sitemaps: Checking for updates - Version ' . $this->current_version);
        
        // Initialize checked property if not set
        if (!isset($transient->checked)) {
            $transient->checked = array();
        }
        
        // Force our plugin to be in the checked list
        $transient->checked[$this->plugin_basename] = $this->current_version;
        
        // Check if we're explicitly checking for updates
        $force_check = false;
        if (isset($_GET['aqm_check_for_updates']) && $_GET['aqm_check_for_updates'] === '1') {
            $force_check = true;
            error_log('AQM Sitemaps: Forcing update check');
        }
        
        // Get update data from GitHub, force refresh if explicitly checking
        $update_data = $this->get_github_update_data($force_check);
        
        if (!$update_data) {
            error_log('AQM Sitemaps: No update data available');
            return $transient;
        }
        
        // Clean version numbers for comparison
        $current_version = $this->current_version;
        $latest_version = $update_data['version'];
        
        error_log('AQM Sitemaps: Comparing versions - Current: ' . $current_version . ', Latest: ' . $latest_version);
        
        // Check if a new version is available
        if (version_compare($current_version, $latest_version, '<')) {
            error_log('AQM Sitemaps: New version available: ' . $latest_version);
            
            // Create the plugin info object
            $obj = new stdClass();
            $obj->slug = $this->github_repository;
            $obj->plugin = $this->plugin_basename;
            $obj->new_version = $latest_version;
            $obj->url = 'https://github.com/' . $this->github_username . '/' . $this->github_repository;
            $obj->package = $update_data['download_url'];
            $obj->tested = isset($update_data['tested']) ? $update_data['tested'] : '';
            $obj->requires = isset($update_data['requires']) ? $update_data['requires'] : '';
            $obj->requires_php = isset($update_data['requires_php']) ? $update_data['requires_php'] : '';
            
            // Add icons if available
            $obj->icons = array(
                '1x' => 'https://ps.w.org/aqm-sitemaps/assets/icon-128x128.png',
                '2x' => 'https://ps.w.org/aqm-sitemaps/assets/icon-256x256.png'
            );
            
            // Add the update info to the transient
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
            error_log('AQM Sitemaps: Update available - ' . $this->current_version . ' -> ' . $latest_version);
        } else {
            error_log('AQM Sitemaps: No new version available');
            
            // Make sure our plugin is not in the response array
            if (isset($transient->response[$this->plugin_basename])) {
                unset($transient->response[$this->plugin_basename]);
            }
            
            // Add to no_update list to show it's up to date
            if (!isset($transient->no_update)) {
                $transient->no_update = array();
            }
            
            // Create the plugin info object for no_update
            $obj = new stdClass();
            $obj->slug = $this->github_repository;
            $obj->plugin = $this->plugin_basename;
            $obj->new_version = $this->current_version;
            $obj->url = 'https://github.com/' . $this->github_username . '/' . $this->github_repository;
            $obj->package = '';
            $obj->icons = array(
                '1x' => 'https://ps.w.org/aqm-sitemaps/assets/icon-128x128.png',
                '2x' => 'https://ps.w.org/aqm-sitemaps/assets/icon-256x256.png'
            );
            
            // Add to no_update list
            $transient->no_update[$this->plugin_basename] = $obj;
        }
        
        return $transient;
    }

    /**
     * Get update data from GitHub
     * 
     * @param bool $force_check Whether to force a fresh check ignoring cache
     * @return array|false Update data or false on failure
     */
    public function get_github_update_data($force_check = false) {
        // Check cache first unless forcing a fresh check
        if (!$force_check) {
            $cached_data = get_transient($this->transient_name);
            if ($cached_data !== false) {
                error_log('AQM Sitemaps: Using cached update data');
                return $cached_data;
            }
        }
        
        error_log('AQM Sitemaps: Fetching fresh update data from GitHub');
        
        // Try to get the latest release first
        $update_data = $this->get_latest_github_release();
        
        // If that fails, try to get releases list
        if (!$update_data) {
            error_log('AQM Sitemaps: Failed to get latest release, trying releases list');
            $update_data = $this->get_github_releases_list();
        }
        
        // If we have update data, cache it
        if ($update_data) {
            set_transient($this->transient_name, $update_data, $this->cache_expiration);
            error_log('AQM Sitemaps: Successfully retrieved update data. Latest version: ' . $update_data['version']);
        }
        
        return $update_data;
    }
    
    /**
     * Get the latest GitHub release
     * 
     * @return array|false Update data or false on failure
     */
    private function get_latest_github_release() {
        // Construct the API URL for the latest release
        $api_url = sprintf(
            'https://api.github.com/repos/%s/%s/releases/latest',
            $this->github_username,
            $this->github_repository
        );
        
        // Make the API request
        $response = wp_remote_get($api_url, array(
            'timeout' => 10,
            'headers' => array(
                'Accept' => 'application/vnd.github.v3+json',
                'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . get_bloginfo('url')
            )
        ));
        
        // Check for errors
        if (is_wp_error($response)) {
            error_log('AQM Sitemaps: GitHub API request failed: ' . $response->get_error_message());
            return false;
        }
        
        // Check response code
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            error_log('AQM Sitemaps: GitHub API returned non-200 response: ' . $response_code);
            return false;
        }
        
        // Parse the response body
        $response_body = wp_remote_retrieve_body($response);
        $release_data = json_decode($response_body);
        
        if (empty($release_data) || !is_object($release_data)) {
            error_log('AQM Sitemaps: Failed to parse GitHub API response');
            return false;
        }
        
        return $this->format_github_release_data($release_data);
    }
    
    /**
     * Get list of GitHub releases and use the most recent one
     * 
     * @return array|false Update data or false on failure
     */
    private function get_github_releases_list() {
        // Construct the API URL for releases list
        $api_url = sprintf(
            'https://api.github.com/repos/%s/%s/releases',
            $this->github_username,
            $this->github_repository
        );
        
        // Make the API request
        $response = wp_remote_get($api_url, array(
            'timeout' => 10,
            'headers' => array(
                'Accept' => 'application/vnd.github.v3+json',
                'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . get_bloginfo('url')
            )
        ));
        
        // Check for errors
        if (is_wp_error($response)) {
            error_log('AQM Sitemaps: GitHub releases API request failed: ' . $response->get_error_message());
            return false;
        }
        
        // Check response code
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            error_log('AQM Sitemaps: GitHub releases API returned non-200 response: ' . $response_code);
            return false;
        }
        
        // Parse the response body
        $response_body = wp_remote_retrieve_body($response);
        $releases = json_decode($response_body);
        
        if (empty($releases) || !is_array($releases) || empty($releases[0])) {
            error_log('AQM Sitemaps: No releases found or failed to parse GitHub API response');
            return false;
        }
        
        // Get the most recent release
        return $this->format_github_release_data($releases[0]);
    }
    
    /**
     * Format GitHub release data into our update format
     * 
     * @param object $release_data GitHub release data
     * @return array|false Formatted update data or false on failure
     */
    private function format_github_release_data($release_data) {
        // Check if we have the required data
        if (!isset($release_data->tag_name) || !isset($release_data->zipball_url)) {
            error_log('AQM Sitemaps: GitHub API response missing required fields');
            return false;
        }
        
        // Clean the version number (remove 'v' prefix if present)
        $version = ltrim($release_data->tag_name, 'v');
        
        // Extract release notes
        $release_notes = '';
        if (isset($release_data->body)) {
            $release_notes = $release_data->body;
        }
        
        // Extract download URL
        $download_url = $release_data->zipball_url;
        
        // Check for release assets (preferred over zipball)
        if (isset($release_data->assets) && is_array($release_data->assets) && !empty($release_data->assets)) {
            foreach ($release_data->assets as $asset) {
                if (isset($asset->browser_download_url) && strpos($asset->name, '.zip') !== false) {
                    $download_url = $asset->browser_download_url;
                    break;
                }
            }
        }
        
        // If no asset was found, test if the zipball URL is accessible
        $test_response = wp_remote_head($download_url, array('timeout' => 5));
        if (is_wp_error($test_response) || wp_remote_retrieve_response_code($test_response) !== 200) {
            // Try with 'v' prefix
            $tag_with_v = (strpos($release_data->tag_name, 'v') === 0) ? $release_data->tag_name : 'v' . $release_data->tag_name;
            $download_url = 'https://github.com/' . $this->github_username . '/' . $this->github_repository . '/archive/refs/tags/' . $tag_with_v . '.zip';
            error_log('AQM Sitemaps: Zipball URL not accessible, trying with v-prefix: ' . $download_url);
            
            // Test again
            $test_response = wp_remote_head($download_url, array('timeout' => 5));
            if (is_wp_error($test_response) || wp_remote_retrieve_response_code($test_response) !== 200) {
                // Try without 'v' prefix as last resort
                $tag_without_v = ltrim($release_data->tag_name, 'v');
                $download_url = 'https://github.com/' . $this->github_username . '/' . $this->github_repository . '/archive/refs/tags/' . $tag_without_v . '.zip';
                error_log('AQM Sitemaps: v-prefix URL not accessible, trying without v-prefix: ' . $download_url);
            }
        }
        
        // Prepare the update data
        $update_data = array(
            'version' => $version,
            'release_notes' => $release_notes,
            'download_url' => $download_url,
            'requires' => isset($this->plugin_data['RequiresWP']) ? $this->plugin_data['RequiresWP'] : '',
            'requires_php' => isset($this->plugin_data['RequiresPHP']) ? $this->plugin_data['RequiresPHP'] : '',
            'tested' => isset($this->plugin_data['Tested up to']) ? $this->plugin_data['Tested up to'] : '',
            'last_updated' => isset($release_data->published_at) ? date('Y-m-d', strtotime($release_data->published_at)) : current_time('mysql'),
            'changelog' => $release_notes
        );
        
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
        
        error_log('AQM Sitemaps: Providing plugin info for WordPress updates screen');
        
        // Force a fresh check when viewing plugin details
        $update_data = $this->get_github_update_data(true);
        
        if (!$update_data) {
            error_log('AQM Sitemaps: Failed to get update data for plugin info');
            return $result;
        }
        
        $plugin_info = new stdClass();
        $plugin_info->name = $this->plugin_data['Name'];
        $plugin_info->slug = dirname($this->plugin_basename);
        $plugin_info->version = $update_data['version'];
        $plugin_info->author = $this->plugin_data['Author'];
        $plugin_info->author_profile = 'https://github.com/' . $this->github_username;
        $plugin_info->homepage = isset($this->plugin_data['PluginURI']) ? $this->plugin_data['PluginURI'] : 'https://github.com/' . $this->github_username . '/' . $this->github_repository;
        $plugin_info->requires = isset($update_data['requires']) ? $update_data['requires'] : '';
        $plugin_info->requires_php = isset($update_data['requires_php']) ? $update_data['requires_php'] : '';
        $plugin_info->tested = isset($update_data['tested']) ? $update_data['tested'] : '';
        $plugin_info->downloaded = 0;
        $plugin_info->last_updated = isset($update_data['last_updated']) ? $update_data['last_updated'] : '';
        $plugin_info->sections = array(
            'description' => isset($this->plugin_data['Description']) ? $this->plugin_data['Description'] : '',
            'changelog' => isset($update_data['changelog']) ? $this->format_changelog($update_data['changelog']) : ''
        );
        
        // Make sure the package URL is accessible
        $test_response = wp_remote_head($update_data['download_url'], array('timeout' => 5));
        if (is_wp_error($test_response) || wp_remote_retrieve_response_code($test_response) !== 200) {
            // Fallback to a direct GitHub download URL if the package URL is not accessible
            $plugin_info->download_link = 'https://github.com/' . $this->github_username . '/' . $this->github_repository . '/archive/refs/tags/v' . $update_data['version'] . '.zip';
            error_log('AQM Sitemaps: Using fallback download URL for plugin info: ' . $plugin_info->download_link);
        } else {
            $plugin_info->download_link = $update_data['download_url'];
            error_log('AQM Sitemaps: Using download URL for plugin info: ' . $plugin_info->download_link);
        }
        
        // Add required fields for WordPress 5.5+
        $plugin_info->id = $this->plugin_basename;
        $plugin_info->compatibility = new stdClass();
        $plugin_info->icons = array(
            '1x' => 'https://ps.w.org/aqm-sitemaps/assets/icon-128x128.png',
            '2x' => 'https://ps.w.org/aqm-sitemaps/assets/icon-256x256.png'
        );
        
        error_log('AQM Sitemaps: Successfully provided plugin info');
        return $plugin_info;
    }
    
    /**
     * Format changelog text to proper HTML
     *
     * @param string $changelog The changelog text from GitHub
     * @return string Formatted changelog HTML
     */
    private function format_changelog($changelog) {
        // Convert markdown to basic HTML
        $changelog = preg_replace('/\*\*(.+?)\*\*/is', '<strong>$1</strong>', $changelog);
        $changelog = preg_replace('/\*(.+?)\*/is', '<em>$1</em>', $changelog);
        $changelog = preg_replace('/\#\#\#\s(.+?)$/im', '<h4>$1</h4>', $changelog);
        $changelog = preg_replace('/\#\#\s(.+?)$/im', '<h3>$1</h3>', $changelog);
        $changelog = preg_replace('/\#\s(.+?)$/im', '<h2>$1</h2>', $changelog);
        $changelog = preg_replace('/\-\s(.+?)$/im', '<li>$1</li>', $changelog);
        $changelog = preg_replace('/\n\n/is', '<br><br>', $changelog);
        
        return '<div class="changelog">' . $changelog . '</div>';
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
        
        error_log('AQM Sitemaps: Starting directory name fix process');
        error_log('AQM Sitemaps: Source: ' . $source);
        error_log('AQM Sitemaps: Remote source: ' . $remote_source);
        
        // Check if we're dealing with a plugin update
        if (!isset($hook_extra['plugin']) && isset($hook_extra['theme'])) {
            error_log('AQM Sitemaps: This is a theme update, not our plugin');
            return $source; // This is a theme update, not our plugin
        }
        
        // For plugin updates, check if it's our plugin
        if (isset($hook_extra['plugin'])) {
            error_log('AQM Sitemaps: Checking plugin: ' . $hook_extra['plugin'] . ' vs ' . $this->plugin_basename);
            if ($hook_extra['plugin'] !== $this->plugin_basename) {
                error_log('AQM Sitemaps: Not our plugin');
                return $source; // Not our plugin
            }
        }
        
        // For core updates or bulk updates where plugin isn't specified
        if (!isset($hook_extra['plugin'])) {
            // Try to detect if this is our plugin based on the source directory
            $source_basename = basename($source);
            
            // Check if the source directory contains our repository name
            if (strpos(strtolower($source_basename), strtolower($this->github_repository)) === false) {
                error_log('AQM Sitemaps: Source directory does not contain our repository name');
                return $source; // Likely not our plugin
            }
            
            error_log('AQM Sitemaps: Detected potential plugin update during bulk update');
        }
        
        error_log('AQM Sitemaps: Fixing directory name during update');
        
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
            
            // Try an alternative approach - copy files instead of moving the directory
            error_log('AQM Sitemaps: Trying alternative approach - copying files');
            if (!$wp_filesystem->exists($correct_directory)) {
                $wp_filesystem->mkdir($correct_directory);
            }
            
            // Copy all files from source to target
            $files = $wp_filesystem->dirlist($source);
            if (is_array($files)) {
                foreach ($files as $file => $file_info) {
                    $wp_filesystem->copy($source . '/' . $file, $correct_directory . '/' . $file, true);
                }
                error_log('AQM Sitemaps: Files copied successfully');
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
            
            // Check if the plugin was active before update
            $was_active = is_plugin_active($this->plugin_basename);
            if ($was_active) {
                error_log('AQM Sitemaps: Plugin was active before update, marking for reactivation');
                update_option('aqm_sitemaps_was_active', true);
            }
            
            // Add our custom redirect parameter
            add_filter('wp_redirect', function($location) {
                return add_query_arg('aqm_updated', '1', $location);
            });
            
            // Log the update success with version info
            if (!function_exists('get_plugin_data')) {
                require_once(ABSPATH . 'wp-admin/includes/plugin.php');
            }
            
            // Try to get the new version info
            try {
                if (file_exists($this->plugin_file)) {
                    $plugin_data = get_plugin_data($this->plugin_file);
                    $new_version = isset($plugin_data['Version']) ? $plugin_data['Version'] : 'unknown';
                    error_log('AQM Sitemaps: Plugin updated to version ' . $new_version);
                } else {
                    error_log('AQM Sitemaps: Plugin file not found after update: ' . $this->plugin_file);
                }
            } catch (Exception $e) {
                error_log('AQM Sitemaps: Error getting plugin data after update: ' . $e->getMessage());
            }
            
            // Schedule plugin reactivation if it was active
            if ($was_active) {
                add_action('shutdown', function() {
                    if (!function_exists('activate_plugin')) {
                        require_once(ABSPATH . 'wp-admin/includes/plugin.php');
                    }
                    
                    error_log('AQM Sitemaps: Attempting to reactivate plugin');
                    activate_plugin($this->plugin_basename, '', is_network_admin());
                    error_log('AQM Sitemaps: Plugin reactivation attempted');
                });
            }
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
    
    /**
     * Show update success notice
     */
    public function show_update_success_notice() {
        if (!isset($_GET['aqm_updated']) || $_GET['aqm_updated'] !== '1') {
            return;
        }
        
        echo '<div class="notice notice-success is-dismissible">';
        echo '<p><strong>AQM Sitemaps Updated Successfully!</strong></p>';
        echo '<p>The plugin has been updated to version ' . esc_html($this->current_version) . '.</p>';
        echo '</div>';
    }

}
