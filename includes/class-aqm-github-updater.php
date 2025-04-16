<?php
/**
 * AQM Manual Update Notifier Class
 * 
 * This class provides manual update notifications for the AQM Sitemap plugin.
 * It does NOT handle automatic updates, only notifications.
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class AQM_Sitemap_GitHub_Updater {
    private $plugin_file;
    private $plugin_data;
    private $github_username;
    private $github_repository;
    private $current_version;
    private $latest_version;
    private $download_url;

    /**
     * Class constructor
     * 
     * @param string $plugin_file Path to the plugin file
     * @param string $github_username GitHub username
     * @param string $github_repository GitHub repository name
     */
    public function __construct($plugin_file, $github_username, $github_repository) {
        $this->plugin_file = $plugin_file;
        $this->github_username = $github_username;
        $this->github_repository = $github_repository;
        
        // Get plugin data
        if (!function_exists('get_plugin_data')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $this->plugin_data = get_plugin_data($this->plugin_file);
        $this->current_version = $this->plugin_data['Version'];
        
        // Add admin notice for updates
        add_action('admin_init', array($this, 'check_for_updates'));
        add_action('admin_notices', array($this, 'show_update_notification'));

        // Set plugin slug for update API
        $this->slug = plugin_basename($this->plugin_file);

        // Enable one-click updates from the Plugins page
        add_filter('pre_set_site_transient_update_plugins', array($this, 'set_transient'));
        add_filter('plugins_api', array($this, 'set_plugin_info'), 10, 3);

        // Add "Check for Updates" link to plugin actions
        add_filter('plugin_action_links_' . plugin_basename($this->plugin_file), array($this, 'add_plugin_action_links'));

        // Add AJAX handler for manual check
        add_action('wp_ajax_aqm_check_for_updates', array($this, 'ajax_check_for_updates'));
        
        // Add hook to check for manual update checks from the plugins page
        add_action('admin_init', array($this, 'maybe_check_for_updates'));
        
        // Add hooks for renaming GitHub folders during update
        add_filter('upgrader_source_selection', array($this, 'rename_github_folder'), 10, 4);
        add_filter('upgrader_package_options', array($this, 'modify_package_options'));
        add_filter('upgrader_pre_download', array($this, 'modify_download_package'), 10, 4);
        add_filter('upgrader_post_install', array($this, 'post_install'), 10, 3);
    }


    /**
     * Check for updates from GitHub
     */
    public function check_for_updates() {
        // Check if we've already checked recently (transient)
        $update_data = get_transient('aqm_sitemap_update_data');
        
        if (false === $update_data || isset($_GET['force-check']) || (defined('DOING_AJAX') && DOING_AJAX)) {
            // Get latest release from GitHub API
            $url = 'https://api.github.com/repos/' . $this->github_username . '/' . $this->github_repository . '/releases/latest';
            
            // Get API response
            $response = wp_remote_get($url, array(
                'sslverify' => true,
                'user-agent' => 'WordPress/' . get_bloginfo('version')
            ));
            
            // Check for errors
            if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)) {
                error_log('AQM Sitemap: Error checking for updates - ' . wp_remote_retrieve_response_message($response));
                return;
            }
            
            // Parse response
            $response_body = wp_remote_retrieve_body($response);
            $release_data = json_decode($response_body);
            
            // Check if we have valid data
            if (empty($release_data) || !is_object($release_data)) {
                error_log('AQM Sitemap: Invalid GitHub API response');
                return;
            }
            
            // Get version number (remove 'v' prefix if present)
            $this->latest_version = ltrim($release_data->tag_name, 'v');
            
            // Set download URL
            $this->download_url = 'https://github.com/' . $this->github_username . '/' . $this->github_repository . '/archive/refs/tags/' . $release_data->tag_name . '.zip';
            
            // Store update data in transient (cache for 12 hours)
            $update_data = array(
                'version' => $this->latest_version,
                'download_url' => $this->download_url,
                'last_checked' => time(),
                'changelog' => isset($release_data->body) ? $release_data->body : ''
            );
            
            set_transient('aqm_sitemap_update_data', $update_data, 12 * HOUR_IN_SECONDS);
        } else {
            // Use cached data
            $this->latest_version = $update_data['version'];
            $this->download_url = $update_data['download_url'];
        }
    }

    /**
     * Show update notification in admin
     */
    public function show_update_notification() {
        // Only show to users who can update plugins
        if (!current_user_can('update_plugins')) {
            return;
        }
        
        // Make sure we have version data
        if (empty($this->latest_version)) {
            return;
        }
        
        // Check if there's a new version available
        if (version_compare($this->latest_version, $this->current_version, '>')) {
            echo '<div class="notice notice-warning">';
            echo '<p><strong>AQM Enhanced Sitemap Update Available!</strong></p>';
            echo '<p>Version ' . esc_html($this->latest_version) . ' is available. You are currently using version ' . esc_html($this->current_version) . '.</p>';
            echo '</div>';
        }
    }
    
    /**
     * AJAX handler for checking updates
     */
    public function ajax_check_for_updates() {
        // Security check
        if (!current_user_can('update_plugins')) {
            wp_die('Unauthorized access');
        }
        
        // Force update check
        delete_transient('aqm_sitemap_update_data');
        $this->check_for_updates();
        
        $has_update = version_compare($this->latest_version, $this->current_version, '>');
        
        wp_send_json(array(
            'success' => true,
            'has_update' => $has_update,
            'current_version' => $this->current_version,
            'latest_version' => $this->latest_version,
            'download_url' => $this->download_url
        ));
    }
    
    /**
     * Add plugin action links
     * 
     * @param array $links Existing action links
     * @return array Modified action links
     */
    public function add_plugin_action_links($links) {
        // Add a "Check for Updates" link
        $check_update_link = '<a href="' . wp_nonce_url(admin_url('plugins.php?aqm_check_for_updates=1&plugin=' . $this->slug), 'aqm-check-update') . '">Check for Updates</a>';
        array_unshift($links, $check_update_link);
        return $links;
    }

    /**
     * Get repository API info from GitHub
     * 
     * @return array|false GitHub API data or false on failure
     */
    private function get_repository_info() {
        if (!empty($this->github_response)) {
            return $this->github_response;
        }

        // Set a reasonable timeout
        $timeout = 10;
        
        // GitHub API URL to fetch release info
        $url = "https://api.github.com/repos/{$this->github_username}/{$this->github_repository}/releases";
        
        // Include access token if available
        if (!empty($this->access_token)) {
            $url = add_query_arg(array('access_token' => $this->access_token), $url);
        }
        

        
        // Send remote request
        $response = wp_remote_get($url, array(
            'timeout' => $timeout,
            'headers' => array(
                'Accept' => 'application/vnd.github.v3+json',
                'User-Agent' => 'WordPress/' . get_bloginfo('version')
            )
        ));
        
        // Check for errors
        if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)) {
            error_log('AQM Sitemap: GitHub API request failed. ' . wp_remote_retrieve_response_message($response));
            return false;
        }
        
        // Parse response
        $response_body = wp_remote_retrieve_body($response);
        $releases = json_decode($response_body);
        
        // Check if response is valid and has at least one release
        if (is_array($releases) && !empty($releases)) {
            // Get the latest release (first element)
            $latest_release = $releases[0];
            
            // Store the result
            $this->github_response = array(
                'tag_name' => isset($latest_release->tag_name) ? $latest_release->tag_name : null,
                'published_at' => isset($latest_release->published_at) ? $latest_release->published_at : null,
                'zipball_url' => isset($latest_release->zipball_url) ? $latest_release->zipball_url : null,
                'body' => isset($latest_release->body) ? $latest_release->body : '',
            );
            
            return $this->github_response;
        }
        
        return false;
    }

    /**
     * Update the plugin transient with update info if available
     * 
     * @param object $transient Plugins update transient
     * @return object Modified transient with GitHub update info
     */
    public function set_transient($transient) {
        // If we're checking for updates, get the latest release info
        if (empty($transient->checked)) {
            return $transient;
        }
        
        // Get GitHub info
        $github_data = $this->get_repository_info();
        
        // If we have valid data
        if ($github_data && isset($github_data['tag_name'])) {
            // Get current version and remove leading 'v' if present in tag name
            $current_version = $this->plugin_data['Version'];
            $latest_version = ltrim($github_data['tag_name'], 'v');
            
            // Check if we need to update
            if (version_compare($latest_version, $current_version, '>')) {
                error_log('AQM Sitemap: Update available - current: ' . $current_version . ', latest: ' . $latest_version);
                
                // Instead of using GitHub's zipball URL directly, we'll use a custom URL that
                // will download the plugin with the correct directory structure
                $download_url = 'https://github.com/' . $this->username . '/' . $this->repository . '/archive/refs/tags/' . $github_data['tag_name'] . '.zip';
                
                // Log the download URL we're using
                error_log('AQM Sitemap: Using download URL: ' . $download_url);
                
                // Prepare the update object
                $obj = new stdClass();
                $obj->slug = dirname($this->slug); // Use directory name as slug
                $obj->plugin = $this->slug; // Full path including main file
                $obj->new_version = $latest_version;
                $obj->url = $this->plugin_data['PluginURI'];
                $obj->package = $download_url;
                $obj->tested = '6.5'; // Tested up to this WordPress version
                $obj->icons = array(
                    '1x' => 'https://ps.w.org/plugin-directory-assets/icon-256x256.png', // Default icon
                    '2x' => 'https://ps.w.org/plugin-directory-assets/icon-256x256.png'  // Default icon
                );
                
                // Add it to the response
                $transient->response[$this->slug] = $obj;
                
                // Store that we're going to update this plugin
                update_option('aqm_sitemap_updating_to_version', $latest_version);
                update_option('aqm_sitemap_was_active', is_plugin_active($this->slug));
            } else {
                // No update needed, just add to no_update
                $obj = new stdClass();
                $obj->slug = dirname($this->slug); // Use directory name as slug
                $obj->plugin = $this->slug; // Full path including main file
                $obj->new_version = $current_version;
                $obj->url = $this->plugin_data['PluginURI'];
                $obj->package = '';
                $obj->tested = '6.5';
                $obj->icons = array(
                    '1x' => 'https://ps.w.org/plugin-directory-assets/icon-256x256.png', // Default icon
                    '2x' => 'https://ps.w.org/plugin-directory-assets/icon-256x256.png'  // Default icon
                );
                
                $transient->no_update[$this->slug] = $obj;
            }
            
            // Return the modified transient
            return $transient;
        }
        
        // If we don't have GitHub data, return the original transient
        return $transient;
    }

    /**
     * Set plugin info for View Details screen
     * 
     * @param false|object|array $result The result object or array
     * @param string $action The type of information being requested
     * @param object $args Plugin API arguments
     * @return object|false Plugin info or false
     */
    public function set_plugin_info($result, $action, $args) {
        // Check if this API call is for this plugin
        if (empty($args->slug) || $args->slug !== dirname($this->slug)) {
            return $result;
        }
        
        // Check for "plugin_information" action
        if ('plugin_information' !== $action) {
            return $result;
        }
        
        // Get GitHub data
        $github_data = $this->get_repository_info();
        
        if (!$github_data) {
            return $result;
        }
        
        // Remove 'v' prefix from tag name for version
        $version = ltrim($github_data['tag_name'], 'v');
        
        // Create plugin info object
        $plugin_info = new stdClass();
        $plugin_info->name = $this->plugin_data['Name'];
        $plugin_info->slug = dirname($this->slug);
        $plugin_info->version = $version;
        $plugin_info->author = $this->plugin_data['Author'];
        $plugin_info->homepage = $this->plugin_data['PluginURI'];
        $plugin_info->requires = '5.0'; // Minimum WordPress version
        $plugin_info->tested = '6.5'; // Tested up to this WordPress version
        $plugin_info->downloaded = 0; // We don't track downloads
        $plugin_info->last_updated = $github_data['published_at'];
        $plugin_info->sections = array(
            'description' => $this->plugin_data['Description'],
            'changelog' => $this->format_github_changelog($github_data['body']),
        );
        $plugin_info->download_link = $github_data['zipball_url'];
        
        return $plugin_info;
    }

    /**
     * Check if we should manually check for updates (from the plugins page)
     */
    public function maybe_check_for_updates() {
        if (isset($_GET['aqm_check_for_updates']) && $_GET['aqm_check_for_updates'] == '1') {
            // Verify nonce
            if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'aqm-check-update')) {
                wp_die('Security check failed');
            }
            
            // Check if this is our plugin
            if (isset($_GET['plugin']) && $_GET['plugin'] == $this->slug) {
                // Clear the update transient to force a fresh check
                delete_transient('aqm_sitemap_update_data');
                delete_site_transient('update_plugins');
                
                // Force WordPress to check for updates
                wp_clean_plugins_cache(true);
                
                // Redirect back to the plugins page
                wp_redirect(admin_url('plugins.php?aqm_checked=1'));
                exit;
            }
        }
        
        // Show admin notice after checking for updates
        if (isset($_GET['aqm_checked']) && $_GET['aqm_checked'] == '1') {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success is-dismissible"><p>AQM Enhanced Sitemap: Checked for updates. If an update is available, you will see an update notification.</p></div>';
            });
        }
    }

    /**
     * Format GitHub release notes as changelog
     * 
     * @param string $release_notes GitHub release notes
     * @return string Formatted HTML changelog
     */
    public function format_github_changelog($release_notes) {
        // Convert markdown to HTML if needed
        if (function_exists('Markdown')) {
            $changelog = Markdown($release_notes);
        } else {
            // Basic formatting - handle lists and headers
            $changelog = '<pre>' . esc_html($release_notes) . '</pre>';
        }
        
        return $changelog;
    }

    /**
     * Rename the GitHub downloaded folder to match our plugin's directory name
     * This prevents WordPress from creating a new directory with the GitHub format
     * 
     * @param string $source The source directory path
     * @param string $remote_source The remote source directory path
     * @param object $upgrader The WordPress upgrader object
     * @param array $args Additional arguments
     * @return string Modified source path
     */
    public function rename_github_folder($source, $remote_source, $upgrader, $args = array()) {
        global $wp_filesystem;
        
        // Log all parameters for debugging
        error_log('AQM Sitemap Debug - Source: ' . $source);
        error_log('AQM Sitemap Debug - Remote Source: ' . $remote_source);
        
        $basename = basename($source);
        error_log('AQM Sitemap Debug - Basename: ' . $basename);

        // If already correct, do nothing
        if ($basename === 'aqm-sitemap-enhanced') {
            error_log('AQM Sitemap: Folder already correctly named. No action needed.');
            return $source;
        }

        // Strictly match known GitHub ZIP folder patterns
        // 1. aqm-sitemap-enhanced-* (tag/release)
        // 2. JustCasey76-aqm-sitemap-enhanced-* (API/branch/commit)
        if (preg_match('/^(aqm-sitemap-enhanced(-[\w.-]+)?|JustCasey76-aqm-sitemap-enhanced-[\w.-]+)$/', $basename)) {
            // Ensure WordPress filesystem is initialized
            if (!$wp_filesystem) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
                WP_Filesystem();
            }
            $correct_directory = 'aqm-sitemap-enhanced';
            $target_dir = trailingslashit($remote_source) . $correct_directory;
            error_log('AQM Sitemap: Attempting to rename folder from ' . $source . ' to ' . $target_dir);

            // If the target directory already exists, remove it
            if ($wp_filesystem->exists($target_dir)) {
                error_log('AQM Sitemap: Target directory already exists, removing it');
                $wp_filesystem->delete($target_dir, true);
            }
            // Try to move the directory
            if ($wp_filesystem->move($source, $target_dir)) {
                error_log('AQM Sitemap: Successfully renamed GitHub folder to ' . $correct_directory);
                return $target_dir;
            } else {
                error_log('AQM Sitemap: Failed to rename folder - WP Filesystem error');
                // Fallback: recursively copy all files and subdirectories
                error_log('AQM Sitemap: Attempting to recursively copy directory contents as fallback');
                if (!$wp_filesystem->exists($target_dir)) {
                    $wp_filesystem->mkdir($target_dir);
                }
                $this->recursive_copy_dir($source, $target_dir, $wp_filesystem);
                // Clean up the original source folder
                $wp_filesystem->delete($source, true);
                return $target_dir;
            }
        }
        // If not a recognized GitHub pattern, return as is
        error_log('AQM Sitemap: Folder name did not match expected GitHub patterns. No action taken.');
        return $source;
    }

    /**
     * Recursively copy all files and subdirectories from source to destination
     *
     * @param string $source Source directory
     * @param string $destination Destination directory
     * @param WP_Filesystem_Base $wp_filesystem WordPress filesystem object
     */
    private function recursive_copy_dir($source, $destination, $wp_filesystem) {
        $files = $wp_filesystem->dirlist($source, true);
        foreach ($files as $file => $file_info) {
            $src_path = trailingslashit($source) . $file;
            $dst_path = trailingslashit($destination) . $file;
            if ('f' === $file_info['type']) {
                $wp_filesystem->copy($src_path, $dst_path, true, FS_CHMOD_FILE);
            } elseif ('d' === $file_info['type']) {
                if (!$wp_filesystem->exists($dst_path)) {
                    $wp_filesystem->mkdir($dst_path);
                }
                $this->recursive_copy_dir($src_path, $dst_path, $wp_filesystem);
            }
        }
    }
    
    /**
     * Modify the download package URL to ensure it extracts with the correct directory name
     * 
     * @param bool $reply Whether to abort the download
     * @param string $package The package URL
     * @param object $upgrader The WordPress upgrader object
     * @param array $hook_extra Extra data
     * @return bool|WP_Error Whether to abort the download or WP_Error
     */
    public function modify_download_package($reply, $package, $upgrader, $hook_extra = array()) {
        // Only process our plugin's package
        if (strpos($package, 'github.com/JustCasey76/aqm-sitemap-enhanced') !== false) {
            error_log('AQM Sitemap: Modifying download package for GitHub update');
            
            // Store the original package URL for reference
            $upgrader->skin->feedback('AQM Sitemap: Using custom directory name for GitHub download');
            
            // Set a flag to indicate this is our plugin being updated
            update_option('aqm_sitemap_is_updating', true);
        }
        
        return $reply; // Return the original reply
    }
    
    /**
     * Modify package options to ensure the correct directory name is used
     * 
     * @param array $options Package options
     * @return array Modified package options
     */
    public function modify_package_options($options) {
        // Check if this is our plugin update
        if (get_option('aqm_sitemap_is_updating', false)) {
            error_log('AQM Sitemap: Modifying package options to use correct directory name');
            
            // Set the destination directory name explicitly
            $options['destination_name'] = 'aqm-sitemap-enhanced';
            
            // Clear the flag after use
            delete_option('aqm_sitemap_is_updating');
        }
        
        return $options;
    }
    
    /**
     * Actions to perform after plugin update
     * 
     * @param bool $true Always true
     * @param array $hook_extra Extra data about the update
     * @param array $result Update result data
     * @return array Result data
     */
    public function post_install($true, $hook_extra, $result) {
        // Check if this is our plugin
        if (isset($hook_extra['plugin']) && $hook_extra['plugin'] === $this->slug) {
            // Make sure we have the necessary functions
            if (!function_exists('activate_plugin') || !function_exists('is_plugin_active')) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }
            
            // Check both the stored property and the option for reliability
            $was_active = $this->plugin_activated || get_option('aqm_sitemap_was_active', false);
            
            if ($was_active) {
                // Log the reactivation attempt
                error_log('AQM Sitemap: Attempting to reactivate plugin after update');
                
                // Reactivate plugin
                $activate_result = activate_plugin($this->slug);
                
                // Check for activation errors
                if (is_wp_error($activate_result)) {
                    error_log('AQM Sitemap: Error reactivating plugin: ' . $activate_result->get_error_message());
                } else {
                    error_log('AQM Sitemap: Plugin successfully reactivated after update');
                }
            }
        }
        
        return $result;
    }
}
