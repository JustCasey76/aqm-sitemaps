<?php
/**
 * AQM GitHub Updater Class
 * 
 * This class handles auto-updates for the AQM Sitemap plugin from GitHub releases.
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class AQM_Sitemap_GitHub_Updater {
    private $slug;
    private $plugin_data;
    private $username;
    private $repository;
    private $plugin_file;
    private $github_api_result;
    private $access_token;
    private $plugin_activated;
    private $github_response;

    /**
     * Class constructor
     * 
     * @param string $plugin_file Path to the plugin file
     * @param string $github_username GitHub username
     * @param string $github_repository GitHub repository name
     * @param string $access_token GitHub access token (optional)
     */
    public function __construct($plugin_file, $github_username, $github_repository, $access_token = '') {
        $this->plugin_file = $plugin_file;
        $this->username = $github_username;
        $this->repository = $github_repository;
        $this->access_token = $access_token;
        
        add_filter('pre_set_site_transient_update_plugins', array($this, 'set_transient'));
        add_filter('plugins_api', array($this, 'set_plugin_info'), 10, 3);
        add_filter('upgrader_post_install', array($this, 'post_install'), 10, 3);
        
        // Add filters to handle directory renaming during extraction
        add_filter('upgrader_source_selection', array($this, 'rename_github_folder'), 10, 4);
        add_filter('upgrader_pre_download', array($this, 'modify_download_package'), 10, 4);
        add_filter('upgrader_package_options', array($this, 'modify_package_options'));
        
        // Get plugin data
        if (function_exists('get_plugin_data')) {
            $this->plugin_data = get_plugin_data($this->plugin_file);
        } else {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
            $this->plugin_data = get_plugin_data($this->plugin_file);
        }
        
        $this->slug = plugin_basename($this->plugin_file);
        
        // Check if plugin is active - ensure function exists first
        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $this->plugin_activated = is_plugin_active($this->slug);
        
        // Store activation status in an option for reliability
        if ($this->plugin_activated) {
            update_option('aqm_sitemap_was_active', true);
        }
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
        $url = "https://api.github.com/repos/{$this->username}/{$this->repository}/releases";
        
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
                
                // Prepare the update object
                $obj = new stdClass();
                $obj->slug = $this->slug;
                $obj->plugin = $this->slug;
                $obj->new_version = $latest_version;
                $obj->url = $this->plugin_data['PluginURI'];
                $obj->package = $github_data['zipball_url'];
                $obj->tested = '6.5'; // Tested up to this WordPress version
                
                // This is critical - it tells WordPress what the source directory name will be
                // and ensures it will properly replace the existing plugin instead of creating a new directory
                $obj->source_name = 'aqm-sitemap-enhanced';
                
                // Add it to the response
                $transient->response[$this->slug] = $obj;
            } else {
                // No update needed, just add to no_update
                $obj = new stdClass();
                $obj->slug = $this->slug;
                $obj->plugin = $this->slug;
                $obj->new_version = $current_version;
                $obj->url = $this->plugin_data['PluginURI'];
                $obj->package = '';
                $obj->tested = '6.5';
                
                $transient->no_update[$this->slug] = $obj;
            }
            
            // Add our update information to the transient
            $transient->last_checked = time();
        }
        
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
     * Format GitHub release notes as changelog
     * 
     * @param string $release_notes GitHub release notes
     * @return string Formatted HTML changelog
     */
    private function format_github_changelog($release_notes) {
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
        
        // Check if this is a plugin update
        if (!empty($args['hook_extra']['plugin']) || !empty($args['plugin'])) {
            // Get the plugin slug from args
            $plugin_slug = !empty($args['hook_extra']['plugin']) ? $args['hook_extra']['plugin'] : $args['plugin'];
            error_log('AQM Sitemap Debug - Plugin Slug: ' . $plugin_slug);
            
            // Check if it's our plugin or contains our plugin name
            if ($plugin_slug === $this->slug || strpos($source, 'JustCasey76-aqm-sitemap-enhanced') !== false || basename($source) === 'aqm-sitemap-enhanced') {
                // Ensure WordPress filesystem is initialized
                if (!$wp_filesystem) {
                    require_once ABSPATH . 'wp-admin/includes/file.php';
                    WP_Filesystem();
                }
                
                // Get the correct target directory name
                $correct_directory = 'aqm-sitemap-enhanced';
                $target_dir = trailingslashit($remote_source) . $correct_directory;
                
                error_log('AQM Sitemap: Attempting to rename folder from ' . $source . ' to ' . $target_dir);
                
                // If the target directory already exists, remove it
                if ($wp_filesystem->exists($target_dir)) {
                    error_log('AQM Sitemap: Target directory already exists, removing it');
                    $wp_filesystem->delete($target_dir, true);
                }
                
                // Rename the directory
                if ($wp_filesystem->move($source, $target_dir)) {
                    error_log('AQM Sitemap: Successfully renamed GitHub folder to ' . $correct_directory);
                    return $target_dir;
                } else {
                    error_log('AQM Sitemap: Failed to rename folder - WP Filesystem error');
                }
            }
        } else {
            // For theme updates or other types
            $basename = basename($source);
            error_log('AQM Sitemap Debug - Basename: ' . $basename);
            
            // Check if this is our plugin based on the basename
            if (strpos($basename, 'JustCasey76-aqm-sitemap-enhanced') !== false) {
                // Ensure WordPress filesystem is initialized
                if (!$wp_filesystem) {
                    require_once ABSPATH . 'wp-admin/includes/file.php';
                    WP_Filesystem();
                }
                
                // Get the correct target directory name
                $correct_directory = 'aqm-sitemap-enhanced';
                $target_dir = trailingslashit($remote_source) . $correct_directory;
                
                error_log('AQM Sitemap: Attempting to rename folder from ' . $source . ' to ' . $target_dir);
                
                // If the target directory already exists, remove it
                if ($wp_filesystem->exists($target_dir)) {
                    error_log('AQM Sitemap: Target directory already exists, removing it');
                    $wp_filesystem->delete($target_dir, true);
                }
                
                // Rename the directory
                if ($wp_filesystem->move($source, $target_dir)) {
                    error_log('AQM Sitemap: Successfully renamed GitHub folder to ' . $correct_directory);
                    return $target_dir;
                } else {
                    error_log('AQM Sitemap: Failed to rename folder - WP Filesystem error');
                }
            }
        }
        
        return $source;
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
