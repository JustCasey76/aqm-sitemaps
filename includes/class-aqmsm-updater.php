<?php
/**
 * AQM Sitemaps Updater
 * 
 * Simple GitHub updater for the AQM Sitemaps plugin.
 * Designed to avoid conflicts with other AQM plugins.
 */

// Don't allow direct access to this file
if (!defined('ABSPATH')) {
    exit;
}

/**
 * AQMSM_Updater Class
 * 
 * Handles checking for updates from GitHub and updating the plugin.
 */
class AQMSM_Updater {
    /**
     * Plugin file path
     *
     * @var string
     */
    private $file;

    /**
     * GitHub username
     *
     * @var string
     */
    private $username;

    /**
     * GitHub repository name
     *
     * @var string
     */
    private $repository;

    /**
     * GitHub access token (optional)
     *
     * @var string
     */
    private $access_token;

    /**
     * Plugin data
     *
     * @var array
     */
    private $plugin_data;

    /**
     * Plugin basename
     *
     * @var string
     */
    private $plugin_basename;

    /**
     * Initialize the updater
     *
     * @param string $file Plugin file path
     * @param string $username GitHub username
     * @param string $repository GitHub repository name
     * @param string $access_token GitHub access token (optional)
     */
    public function __construct($file, $username, $repository, $access_token = '') {
        // Set class properties
        $this->file = $file;
        $this->username = $username;
        $this->repository = $repository;
        $this->access_token = $access_token;

        // Get plugin data
        if (!function_exists('get_plugin_data')) {
            require_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }
        $this->plugin_data = get_plugin_data($this->file);
        $this->plugin_basename = plugin_basename($this->file);

        // Add filters and actions
        add_filter('pre_set_site_transient_update_plugins', array($this, 'check_for_updates'));
        add_filter('plugins_api', array($this, 'plugin_info'), 10, 3);
        add_filter('upgrader_pre_install', array($this, 'pre_install'), 10, 2);
        add_action('upgrader_process_complete', array($this, 'post_install'), 10, 2);
        add_filter('upgrader_source_selection', array($this, 'fix_directory_name'), 10, 4);
        add_action('admin_init', array($this, 'maybe_reactivate_plugin'));

        // Log initialization
        error_log('=========================================================');
        error_log('[AQMSM UPDATER] Initialized for ' . $this->repository);
        error_log('[AQMSM UPDATER] Plugin version: ' . $this->plugin_data['Version']);
        error_log('=========================================================');
    }

    /**
     * Check for updates
     *
     * @param object $transient Update transient
     * @return object Modified update transient
     */
    public function check_for_updates($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }

        // Get update data from GitHub
        $update_data = $this->get_github_update_data();

        // If update data is available and version is newer, add to transient
        if ($update_data && version_compare($this->plugin_data['Version'], $update_data->tag_name, '<')) {
            error_log('[AQMSM UPDATER] New version available: ' . $update_data->tag_name);

            // Create the plugin info object
            $plugin_info = new stdClass();
            $plugin_info->slug = $this->repository;
            $plugin_info->plugin = $this->plugin_basename;
            $plugin_info->new_version = ltrim($update_data->tag_name, 'v');
            $plugin_info->url = $update_data->html_url;
            $plugin_info->package = $update_data->zipball_url;

            // Add access token to package URL if provided
            if (!empty($this->access_token)) {
                $plugin_info->package = add_query_arg(array('access_token' => $this->access_token), $plugin_info->package);
            }

            // Add to transient
            $transient->response[$this->plugin_basename] = $plugin_info;
        }

        return $transient;
    }

    /**
     * Get GitHub update data
     *
     * @param bool $force_check Force check instead of using cached data
     * @return object|bool GitHub release data or false on failure
     */
    private function get_github_update_data($force_check = false) {
        // Check cache first
        $cache_key = 'aqmsm_github_data_' . md5($this->username . $this->repository);
        $cache = get_transient($cache_key);

        if ($cache !== false && !$force_check) {
            return $cache;
        }

        // Build API URL
        $api_url = "https://api.github.com/repos/{$this->username}/{$this->repository}/releases/latest";
        
        // Add access token if provided
        if (!empty($this->access_token)) {
            $api_url = add_query_arg(array('access_token' => $this->access_token), $api_url);
        }

        // Get data from GitHub API
        $response = wp_remote_get($api_url, array(
            'headers' => array(
                'Accept' => 'application/json',
                'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . get_bloginfo('url')
            ),
            'timeout' => 30
        ));

        // Check for errors
        if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)) {
            error_log('[AQMSM UPDATER] Error getting update data: ' . wp_remote_retrieve_response_message($response));
            if (is_wp_error($response)) {
                error_log('[AQMSM UPDATER] Error code: ' . $response->get_error_code());
                error_log('[AQMSM UPDATER] Error message: ' . $response->get_error_message());
            } else {
                error_log('[AQMSM UPDATER] Response code: ' . wp_remote_retrieve_response_code($response));
                error_log('[AQMSM UPDATER] Response body: ' . wp_remote_retrieve_body($response));
            }
            return false;
        }

        // Decode response
        $data = json_decode(wp_remote_retrieve_body($response));

        // Cache for 6 hours
        set_transient($cache_key, $data, 6 * HOUR_IN_SECONDS);

        return $data;
    }

    /**
     * Get plugin info for the WordPress updates screen
     *
     * @param object $result Plugin info result
     * @param string $action Action being performed
     * @param object $args Plugin arguments
     * @return object Modified plugin info result
     */
    public function plugin_info($result, $action, $args) {
        // Check if this is the right plugin
        if ($action !== 'plugin_information' || !isset($args->slug) || $args->slug !== $this->repository) {
            return $result;
        }

        // Get update data from GitHub
        $update_data = $this->get_github_update_data();

        if (!$update_data) {
            return $result;
        }

        // Create the plugin info object
        $plugin_info = new stdClass();
        $plugin_info->name = $this->plugin_data['Name'];
        $plugin_info->slug = $this->repository;
        $plugin_info->version = ltrim($update_data->tag_name, 'v');
        $plugin_info->author = $this->plugin_data['Author'];
        $plugin_info->homepage = $this->plugin_data['PluginURI'];
        $plugin_info->requires = '5.0';
        $plugin_info->tested = '6.8.1';
        $plugin_info->downloaded = 0;
        $plugin_info->last_updated = $update_data->published_at;
        $plugin_info->sections = array(
            'description' => $this->plugin_data['Description'],
            'changelog' => $update_data->body ? $update_data->body : 'No changelog provided.'
        );
        $plugin_info->download_link = $update_data->zipball_url;

        // Add access token to download link if provided
        if (!empty($this->access_token)) {
            $plugin_info->download_link = add_query_arg(array('access_token' => $this->access_token), $plugin_info->download_link);
        }

        return $plugin_info;
    }

    /**
     * Before installation, check if the plugin is active and set a transient
     *
     * @param bool $return Whether to proceed with installation
     * @param array $hook_extra Extra data about the plugin being updated
     * @return bool Whether to proceed with installation
     */
    public function pre_install($return, $hook_extra) {
        error_log('=========================================================');
        error_log('[AQMSM UPDATER] ENTERING pre_install hook');
        error_log('=========================================================');

        // Check if this is our plugin
        if (!isset($hook_extra['plugin']) || $hook_extra['plugin'] !== $this->plugin_basename) {
            return $return;
        }
        
        // Check if the plugin is active
        if (is_plugin_active($this->plugin_basename)) {
            // Set a transient to reactivate the plugin after update
            set_transient('aqmsm_was_active', true, 5 * MINUTE_IN_SECONDS);
            error_log('[AQMSM UPDATER] Plugin was active, setting transient');
        }
        
        return $return;
    }

    /**
     * After installation, check if we need to reactivate the plugin
     *
     * @param WP_Upgrader $upgrader_object WP_Upgrader instance
     * @param array $options Array of bulk item update data
     */
    public function post_install($upgrader_object, $options) {
        error_log('=========================================================');
        error_log('[AQMSM UPDATER] ENTERING post_install hook');
        error_log('=========================================================');

        // Check if this is a plugin update
        if ($options['action'] !== 'update' || $options['type'] !== 'plugin') {
            return;
        }
        
        // Check if our plugin was updated
        if (!isset($options['plugins']) || !in_array($this->plugin_basename, $options['plugins'])) {
            return;
        }
        
        // Set a transient to reactivate on next admin page load
        // This is a fallback in case the plugin can't be activated immediately
        set_transient('aqmsm_reactivate', true, 5 * MINUTE_IN_SECONDS);
        
        error_log('[AQMSM UPDATER] Update complete, setting reactivation transient');
        
        // Try to reactivate the plugin now
        if (get_transient('aqmsm_was_active')) {
            // Delete the transient
            delete_transient('aqmsm_was_active');
            
            // Make sure plugin functions are loaded
            if (!function_exists('activate_plugin')) {
                require_once(ABSPATH . 'wp-admin/includes/plugin.php');
            }
            
            // Reactivate the plugin
            $result = activate_plugin($this->plugin_basename);
            
            if (is_wp_error($result)) {
                error_log('[AQMSM UPDATER] Reactivation failed: ' . $result->get_error_message());
            } else {
                error_log('[AQMSM UPDATER] Plugin successfully reactivated');
                
                // Clear the reactivation transient since we successfully reactivated
                delete_transient('aqmsm_reactivate');
            }
            
            // Clear plugin cache
            wp_clean_plugins_cache(true);
        }
    }

    /**
     * Fix the directory name after extracting the ZIP file
     *
     * @param string $source Source directory
     * @param string $remote_source Remote source directory
     * @param WP_Upgrader $upgrader WP_Upgrader instance
     * @param array $hook_extra Extra data about the upgrade
     * @return string Modified source directory
     */
    public function fix_directory_name($source, $remote_source, $upgrader, $hook_extra) {
        error_log('=========================================================');
        error_log('[AQMSM UPDATER] DIRECTORY RENAMING HOOK FIRED');
        error_log('Source: ' . $source);
        error_log('Remote Source: ' . $remote_source);
        error_log('=========================================================');

        // Check if this is our plugin
        if (!isset($hook_extra['plugin']) || $hook_extra['plugin'] !== $this->plugin_basename) {
            return $source;
        }

        // Get the expected directory name
        $expected_directory = dirname($this->plugin_basename);
        
        // Get the current directory name
        $current_directory = basename($source);
        
        error_log('[AQMSM UPDATER] Expected directory: ' . $expected_directory);
        error_log('[AQMSM UPDATER] Current directory: ' . $current_directory);
        
        // If the directory names don't match, rename it
        if ($current_directory !== $expected_directory) {
            // Build the new path
            $new_source = trailingslashit(dirname($source)) . trailingslashit($expected_directory);
            
            error_log('[AQMSM UPDATER] Attempting to rename ' . $source . ' to ' . $new_source);
            
            // If the destination directory already exists, remove it
            if (is_dir($new_source)) {
                error_log('[AQMSM UPDATER] Destination directory already exists, removing it');
                $wp_filesystem = $this->get_filesystem();
                $wp_filesystem->delete($new_source, true);
            }
            
            // Rename the directory
            if (rename($source, $new_source)) {
                error_log('[AQMSM UPDATER] Directory renamed successfully');
                return $new_source;
            } else {
                error_log('[AQMSM UPDATER] Directory rename failed');
                
                // Try an alternative method using the filesystem API
                $wp_filesystem = $this->get_filesystem();
                if ($wp_filesystem->move($source, $new_source, true)) {
                    error_log('[AQMSM UPDATER] Directory renamed successfully using filesystem API');
                    return $new_source;
                } else {
                    error_log('[AQMSM UPDATER] Directory rename failed using filesystem API');
                }
            }
        }
        
        return $source;
    }

    /**
     * Check if we need to reactivate the plugin on admin page load
     */
    public function maybe_reactivate_plugin() {
        error_log('=========================================================');
        error_log('[AQMSM UPDATER] ENTERING maybe_reactivate_plugin function');
        error_log('=========================================================');

        // Check if the reactivation transient exists
        if (get_transient('aqmsm_reactivate')) {
            // Delete the transient
            delete_transient('aqmsm_reactivate');
            
            error_log('[AQMSM UPDATER] Attempting reactivation on admin page load');
            
            // Make sure plugin functions are loaded
            if (!function_exists('activate_plugin')) {
                require_once(ABSPATH . 'wp-admin/includes/plugin.php');
            }
            
            // Reactivate the plugin
            $result = activate_plugin($this->plugin_basename);
            
            if (is_wp_error($result)) {
                error_log('[AQMSM UPDATER] Reactivation failed: ' . $result->get_error_message());
            } else {
                error_log('[AQMSM UPDATER] Plugin successfully reactivated');
            }
            
            // Clear plugin cache
            wp_clean_plugins_cache(true);
        }
    }

    /**
     * Get the WordPress filesystem
     *
     * @return WP_Filesystem_Base WordPress filesystem
     */
    private function get_filesystem() {
        global $wp_filesystem;

        if (empty($wp_filesystem)) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            WP_Filesystem();
        }

        return $wp_filesystem;
    }
}
