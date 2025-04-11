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
        
        // Get plugin data
        $this->plugin_data = get_plugin_data($this->plugin_file);
        $this->slug = plugin_basename($this->plugin_file);
        $this->plugin_activated = is_plugin_active($this->slug);
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
            // Reactivate plugin if it was active before update
            if ($this->plugin_activated) {
                activate_plugin($this->slug);
            }
        }
        
        return $result;
    }
}
