<?php
/**
 * Plugin Name: AQM Sitemaps
 * Description: Enhanced sitemap plugin with folder selection and shortcode management
 * Version: 3.8.4
 * Author: AQ Marketing
 * Plugin URI: https://github.com/JustCasey76/aqm-sitemaps
 * GitHub Plugin URI: https://github.com/JustCasey76/aqm-sitemaps
 * Primary Branch: main
 * Requires at least: 5.2
 * Requires PHP: 7.2
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Version for cache busting
define('AQM_SITEMAPS_VERSION', '3.8.4');

// Set up text domain for translations
function aqm_sitemaps_load_textdomain() {
    load_plugin_textdomain('aqm-sitemaps', false, dirname(plugin_basename(__FILE__)) . '/languages');
}
add_action('init', 'aqm_sitemaps_load_textdomain');

/**
 * Lightweight debug logger.
 *
 * All plugin logging routes through here so production sites stay quiet by
 * default. To enable verbose logging, add define('AQM_SITEMAPS_DEBUG', true);
 * to wp-config.php.
 *
 * @param string $message Message to write to the PHP error log.
 */
function aqm_sitemaps_log($message) {
    if (defined('AQM_SITEMAPS_DEBUG') && AQM_SITEMAPS_DEBUG) {
        call_user_func('error_log', '[AQM Sitemaps] ' . $message);
    }
}

// Include the GitHub Updater class (guarded so a missing file can never fatal).
$aqm_sitemaps_updater_class = plugin_dir_path(__FILE__) . 'includes/class-aqm-github-updater.php';
if (file_exists($aqm_sitemaps_updater_class)) {
    require_once $aqm_sitemaps_updater_class;
}

// Check if plugin needs reactivation after update
function aqm_sitemaps_check_reactivation() {
    // Get the plugin basename
    $plugin_basename = plugin_basename(__FILE__);
    
    // Check if the plugin was active before an update
    if (get_option('aqm_sitemaps_was_active', false)) {
        // Make sure plugin functions are loaded
        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        
        // If plugin is not active, reactivate it
        if (!is_plugin_active($plugin_basename)) {
            aqm_sitemaps_log('[AQM SITEMAPS] Plugin was active before update but is now inactive, reactivating');
            
            // Reactivate the plugin
            $result = activate_plugin($plugin_basename);
            
            if (is_wp_error($result)) {
                aqm_sitemaps_log('[AQM SITEMAPS] Reactivation failed: ' . $result->get_error_message());
            } else {
                aqm_sitemaps_log('[AQM SITEMAPS] Plugin successfully reactivated');
                
                // Set a transient to show a notice
                set_transient('aqmsm_reactivated', true, 30);
            }
            
            // Clear plugin cache
            wp_clean_plugins_cache(true);
        }
    }
}

// Initialize the GitHub Updater
function aqm_sitemaps_init_github_updater() {
    // Initialize the GitHub updater when its class is available.
    
    if (class_exists('AQM_Sitemaps\Updater\GitHub_Updater')) {
        try {
            new AQM_Sitemaps\Updater\GitHub_Updater(
                __FILE__,                // Plugin File
                'JustCasey76',           // GitHub username
                'aqm-sitemaps'           // GitHub repository name
            );
            
            // Set last update check time
            update_option('aqm_sitemaps_last_update_check', time());
            
            // Check if we need to reactivate the plugin after an update
            aqm_sitemaps_check_reactivation();
        } catch (Exception $e) {
            aqm_sitemaps_log('[AQM SITEMAPS] Error initializing updater: ' . $e->getMessage());
        }
    } else {
        aqm_sitemaps_log('[AQM SITEMAPS] Updater class not found');
    }
}
add_action('admin_init', 'aqm_sitemaps_init_github_updater');

// Show update success message
function aqm_sitemaps_show_update_success() {
    // Only show on plugins page
    $screen = get_current_screen();
    if (!$screen || $screen->id !== 'plugins') {
        return;
    }
    
    // Check if we're coming from an update
    if (isset($_GET['aqm_updated']) && $_GET['aqm_updated'] === '1') {
        echo '<div class="notice notice-success is-dismissible">
            <p><strong>AQM Sitemaps Updated Successfully!</strong> The plugin has been updated to version ' . AQM_SITEMAPS_VERSION . '.</p>
        </div>';
    }
    
    // Check if we're showing a reactivation notice
    if (get_transient('aqmsm_reactivated')) {
        // Delete the transient
        delete_transient('aqmsm_reactivated');
        
        echo '<div class="notice notice-success is-dismissible">
            <p><strong>AQM Sitemaps Reactivated!</strong> The plugin has been reactivated after an update.</p>
        </div>';
    }
}
add_action('admin_notices', 'aqm_sitemaps_show_update_success');

/**
 * Attempts to reactivate the plugin after an update is complete.
 * Hooks into 'upgrader_process_complete'.
 * 
 * @param WP_Upgrader $upgrader_object WP_Upgrader instance.
 * @param array       $options         Array of bulk item update data.
 */
function aqm_sitemaps_reactivate_on_update($upgrader_object, $options) {
    // Check if this is a plugin update
    if ($options['action'] !== 'update' || $options['type'] !== 'plugin') {
        return;
    }
    
    // Get the plugin basename
    $plugin_basename = plugin_basename(__FILE__);
    
    // Check if our plugin was updated
    if (!isset($options['plugins']) || !in_array($plugin_basename, $options['plugins'])) {
        return;
    }
    
    aqm_sitemaps_log('[AQM SITEMAPS] Plugin update detected, checking activation state');
    
    // Check if the plugin was active before the update
    if (get_option('aqm_sitemaps_was_active', false)) {
        // Make sure plugin functions are loaded
        if (!function_exists('is_plugin_active') || !function_exists('activate_plugin')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        
        // If plugin is not active, reactivate it
        if (!is_plugin_active($plugin_basename)) {
            aqm_sitemaps_log('[AQM SITEMAPS] Plugin was active before update but is now inactive, reactivating');
            
            // Reactivate the plugin
            $result = activate_plugin($plugin_basename);
            
            if (is_wp_error($result)) {
                aqm_sitemaps_log('[AQM SITEMAPS] Reactivation failed: ' . $result->get_error_message());
            } else {
                aqm_sitemaps_log('[AQM SITEMAPS] Plugin successfully reactivated');
                
                // Set a transient to show a notice
                set_transient('aqmsm_reactivated', true, 30);
            }
            
            // Clear plugin cache
            wp_clean_plugins_cache(true);
        }
    }
}
add_action('upgrader_process_complete', 'aqm_sitemaps_reactivate_on_update', 10, 2);

/**
 * Add custom action links to the plugin entry on the plugins page.
 *
 * @param array $links An array of plugin action links.
 * @return array An array of plugin action links.
 */
function aqm_sitemaps_add_action_links($links) {
    // Add 'Check for Updates' link
    $check_update_link = '<a href="' . wp_nonce_url(admin_url('admin-ajax.php?action=aqm_sitemaps_check_updates'), 'aqm-sitemaps-check-updates') . '" class="aqm-check-updates">Check for Updates</a>';
    array_unshift($links, $check_update_link);
    
    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'aqm_sitemaps_add_action_links');

/**
 * Enqueue admin scripts specifically for the plugins page.
 *
 * @param string $hook The current admin page.
 */
function aqm_sitemaps_enqueue_admin_scripts($hook) {
    if ($hook !== 'plugins.php') {
        return;
    }
    
    // Enqueue the script
    wp_enqueue_script(
        'aqm-sitemaps-admin-js',
        plugins_url('js/admin-updates.js', __FILE__),
        array('jquery'),
        AQM_SITEMAPS_VERSION,
        true
    );
    
    // Localize the script with our data
    wp_localize_script(
        'aqm-sitemaps-admin-js',
        'aqmSitemapsData',
        array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('aqm-sitemaps-check-updates'),
            'checkingText' => 'Checking for updates...',
            'successText' => 'Update check complete!',
            'errorText' => 'Error checking for updates.'
        )
    );
}
add_action('admin_enqueue_scripts', 'aqm_sitemaps_enqueue_admin_scripts');

/**
 * Handle the AJAX request to check for plugin updates.
 */
function aqm_sitemaps_handle_check_updates_ajax() {
    // Verify nonce
    check_ajax_referer('aqm-sitemaps-check-updates', 'nonce');
    
    // Clear update transients to force a fresh check
    delete_transient('aqmsm_github_data_' . md5('JustCasey76' . 'aqm-sitemaps'));
    delete_site_transient('update_plugins');
    
    // Force WordPress to check for updates
    wp_clean_plugins_cache(true);
    
    // Log the manual update check
    aqm_sitemaps_log('[AQM SITEMAPS] Manual update check triggered');
    
    // Send success response
    wp_send_json_success(array('message' => 'Update check complete'));
}
add_action('wp_ajax_aqm_sitemaps_check_updates', 'aqm_sitemaps_handle_check_updates_ajax');

// Update existing shortcodes to include new parameters
function aqm_update_shortcodes_with_margin() {
    // Get saved shortcodes
    $saved_shortcodes = get_option('aqm_sitemaps_shortcodes', array());
    $updated = false;
    
    if (!empty($saved_shortcodes) && is_array($saved_shortcodes)) {
        foreach ($saved_shortcodes as $name => $shortcode) {
            $shortcode_updated = false;
            
            // Check if shortcode doesn't already have item_margin parameter
            if (strpos($shortcode, 'item_margin=') === false) {
                // Add item_margin parameter before the closing bracket
                $shortcode = str_replace(']', ' item_margin="10px"]', $shortcode);
                $shortcode_updated = true;
            }
            
            // If the shortcode was updated, save it
            if ($shortcode_updated) {
                $saved_shortcodes[$name] = $shortcode;
                $updated = true;
            }
        }
        
        // Save updated shortcodes if changes were made
        if ($updated) {
            update_option('aqm_sitemaps_shortcodes', $saved_shortcodes);
        }
    }
}
// Run this function when the admin page loads to ensure all saved shortcodes are updated
add_action('admin_init', 'aqm_update_shortcodes_with_margin');

// We're removing this function as we want to respect user input
// and not automatically add parameters to existing shortcodes

// Add menu item
function aqm_sitemaps_menu() {
    // Use edit_posts capability which is available to editors and administrators
    // This is less restrictive than manage_options (admin only)
    add_menu_page(
        'AQM Sitemaps',
        'AQM Sitemaps',
        'edit_posts',
        'aqm-sitemaps',
        'aqm_sitemaps_page',
        'dashicons-layout'
    );
}
add_action('admin_menu', 'aqm_sitemaps_menu');

// Register scripts and styles
function aqm_sitemaps_admin_scripts($hook) {
    // Only load on our plugin page
    if ('toplevel_page_aqm-sitemaps' !== $hook) {
        return;
    }

    wp_enqueue_script('jquery');
    // Enqueue our script
    wp_enqueue_script(
        'aqm-sitemaps-admin-script', 
        plugins_url('js/admin-script.js', __FILE__), 
        array('jquery'), 
        AQM_SITEMAPS_VERSION, 
        true
    );
    
    wp_localize_script('aqm-sitemaps-admin-script', 'aqmSitemaps', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('aqm_sitemaps_nonce')
    ));
}
add_action('admin_enqueue_scripts', 'aqm_sitemaps_admin_scripts');

// Register and enqueue frontend styles
function aqm_sitemaps_enqueue_styles() {
    wp_register_style(
        'aqm-sitemaps-frontend',
        plugins_url('css/frontend-style.css', __FILE__),
        array(),
        AQM_SITEMAPS_VERSION
    );
}
add_action('wp_enqueue_scripts', 'aqm_sitemaps_enqueue_styles');

// Register frontend pagination script (don't enqueue or localize here)
function aqm_sitemaps_register_scripts() {
    // Just register the pagination script
    wp_register_script(
        'aqm-sitemaps-pagination',
        plugins_url('js/pagination.js', __FILE__),
        array('jquery'),
        AQM_SITEMAPS_VERSION,
        true
    );
}
add_action('wp_enqueue_scripts', 'aqm_sitemaps_register_scripts');

// Register and enqueue admin styles
function aqm_sitemaps_admin_styles($hook) {
    if ('toplevel_page_aqm-sitemaps' !== $hook) {
        return;
    }
    
    wp_enqueue_style(
        'aqm-sitemaps-admin',
        plugins_url('css/admin-style.css', __FILE__),
        array(),
        AQM_SITEMAPS_VERSION
    );
}
add_action('admin_enqueue_scripts', 'aqm_sitemaps_admin_styles');

// Migrate shortcodes from old option names
function migrate_old_shortcodes() {
    // Get shortcodes from all possible old option names
    $old_shortcodes = array_merge(
        get_option('aqm_saved_shortcodes', array()),
        get_option('aqm_saved_sitemap_shortcodes', array())
    );

    if (!empty($old_shortcodes)) {
        // Save to new option name
        update_option('aqm_sitemaps_shortcodes', $old_shortcodes);
        
        // Clean up old options
        delete_option('aqm_saved_shortcodes');
        delete_option('aqm_saved_sitemap_shortcodes');
        
        aqm_sitemaps_log('AQM Sitemaps: Migrated ' . count($old_shortcodes) . ' shortcodes to new option name');
    }
}

// Add plugin options on activation
function aqm_sitemaps_activate() {
    // Set default options if they don't exist
    if (get_option('aqm_sitemaps_show_debug') === false) {
        add_option('aqm_sitemaps_show_debug', 1); // Default to showing debug info
    }
    
    // Set the last update check time
    if (get_option('aqm_sitemaps_last_update_check') === false) {
        add_option('aqm_sitemaps_last_update_check', time());
    }
    
    // Mark plugin as active for reactivation after updates
    update_option('aqm_sitemaps_was_active', true);
    
    // Clear any update transients to force a fresh check
    delete_transient('aqmsm_github_data_' . md5('JustCasey76' . 'aqm-sitemaps'));
    delete_site_transient('update_plugins');
    
    // Log activation
    aqm_sitemaps_log('=========================================================');
    aqm_sitemaps_log('[AQM SITEMAPS] Plugin activated, version ' . AQM_SITEMAPS_VERSION);
    aqm_sitemaps_log('=========================================================');
}
register_activation_hook(__FILE__, 'aqm_sitemaps_activate');

// Handle plugin deactivation
function aqm_sitemaps_deactivate() {
    // Mark plugin as inactive
    update_option('aqm_sitemaps_was_active', false);
    
    // Log deactivation
    aqm_sitemaps_log('=========================================================');
    aqm_sitemaps_log('[AQM SITEMAPS] Plugin deactivated');
    aqm_sitemaps_log('=========================================================');
}
register_deactivation_hook(__FILE__, 'aqm_sitemaps_deactivate');

// Main admin page
function aqm_sitemaps_page() {
    // Changed from manage_options to edit_posts to match menu registration
    if (!current_user_can('edit_posts')) {
        return;
    }
    


    // Migrate old shortcodes if needed
    migrate_old_shortcodes();

    // Get all folders
    $folders = get_terms(array(
        'taxonomy' => 'folder',
        'hide_empty' => false,
    ));

    // Get saved shortcodes
    $saved_shortcodes = get_option('aqm_sitemaps_shortcodes', array());
    
    // Debug log
    aqm_sitemaps_log('AQM Sitemaps: Number of saved shortcodes: ' . count($saved_shortcodes));
    
    // Debug setting is now controlled through code only
    $show_debug = false;
    

    
    // Get current plugin version
    $plugin_data = get_plugin_data(__FILE__);
    $current_version = $plugin_data['Version'];
    ?>
    <div class="wrap">
        <div class="aqm-header">
            <h1>AQM Sitemaps Generator</h1>
            <div class="theme-toggle">
                <label class="switch">
                    <input type="checkbox" id="theme-switch">
                    <span class="slider round"></span>
                </label>
                <span class="theme-label">Dark Mode</span>
            </div>
        </div>
        
        <div class="aqm-main-content">
            <div class="aqm-left-column">
                <div class="aqm-sitemap-generator">
                    <form id="aqm-sitemap-form">
                        <input type="hidden" id="edit_mode" name="edit_mode" value="0">
                        <input type="hidden" id="original_name" name="original_name" value="">
                        
                        <div class="form-grid">
                            <!-- Content Settings Section -->
                            <div class="form-section content-settings">
                                <h3>Content Settings</h3>
                                <div class="form-group">
                                    <label>Select Folders (Optional):</label>
                                    <div class="folder-checklist">
                                        <p style="color:#666;font-style:italic;">Loading folders...</p>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="post_type">Post Type:</label>
                                    <select id="post_type" name="post_type">
                                        <option value="page">Pages</option>
                                        <option value="post">Posts</option>
                                        <?php 
                                        // Get all public custom post types
                                        $post_types = get_post_types(array('public' => true, '_builtin' => false), 'objects');
                                        foreach ($post_types as $post_type): ?>
                                            <option value="<?php echo esc_attr($post_type->name); ?>"><?php echo esc_html($post_type->labels->name); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-help">Select the post type to display in the sitemap</div>
                                </div>

                                <div class="form-group">
                                    <label for="shortcode_name">Shortcode Name:</label>
                                    <input type="text" id="shortcode_name" name="shortcode_name" placeholder="Enter shortcode name" required>
                                    <div class="form-help">Leave empty to auto-generate from folder name</div>
                                </div>

                                <div class="form-group">
                                    <label for="order">Sort Order:</label>
                                    <select id="order" name="order">
                                        <option value="menu_order">Menu Order</option>
                                        <option value="title">Title</option>
                                        <option value="date">Date</option>
                                        <option value="custom_field">Custom Field (ACF)</option>
                                    </select>
                                </div>

                                <div class="form-group custom-field-option" style="display:none;">
                                    <label for="custom_field_name">Custom Field Name:</label>
                                    <input type="text" id="custom_field_name" name="custom_field_name" placeholder="e.g., custom_order">
                                    <div class="form-help">Enter the ACF field name to sort by (field must exist on the post type)</div>
                                </div>

                                <div class="form-group custom-field-option" style="display:none;">
                                    <label for="custom_field_type">Custom Field Type:</label>
                                    <select id="custom_field_type" name="custom_field_type">
                                        <option value="CHAR">Text/String</option>
                                        <option value="NUMERIC">Number</option>
                                        <option value="DATE">Date</option>
                                        <option value="DATETIME">DateTime</option>
                                    </select>
                                    <div class="form-help">Select the data type of your custom field for proper sorting</div>
                                </div>

                                <div class="form-group custom-field-option" style="display:none;">
                                    <label for="custom_field_order">Custom Field Order:</label>
                                    <select id="custom_field_order" name="custom_field_order">
                                        <option value="ASC">Ascending (A-Z, 0-9, oldest first)</option>
                                        <option value="DESC">Descending (Z-A, 9-0, newest first)</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="display_type">Display Type:</label>
                                    <select id="display_type" name="display_type">
                                        <option value="columns">Display in Columns</option>
                                        <option value="inline">Display Inline with Separators</option>
                                    </select>
                                </div>

                                <div class="form-group columns-option">
                                    <label for="columns">Number of Columns:</label>
                                    <select id="columns" name="columns">
                                        <?php for ($i = 1; $i <= 6; $i++): ?>
                                            <option value="<?php echo $i; ?>"<?php echo $i === 2 ? ' selected' : ''; ?>><?php echo $i; ?> Column<?php echo $i > 1 ? 's' : ''; ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="item_margin">Item Bottom Margin:</label>
                                    <input type="text" id="item_margin" name="item_margin" value="10px" placeholder="e.g., 10px, 0.5em, etc.">
                                    <div class="form-help">Set the bottom margin for each list item (e.g., 10px, 0.5em)</div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="item_padding">Item Bottom Padding:</label>
                                    <input type="text" id="item_padding" name="item_padding" value="10px" placeholder="e.g., 10px, 0.5em, etc.">
                                    <div class="form-help">Set the bottom padding for each list item (e.g., 10px, 0.5em)</div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="border_color">Border Color:</label>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <input type="color" id="border_color" name="border_color" value="#dddddd">
                                        <input type="text" id="border_color_hex" value="#dddddd" readonly style="width: 80px; font-family: monospace;">
                                    </div>
                                    <div class="form-help">Set the color of the border between items</div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="icon">Font Awesome Icon:</label>
                                    <input type="text" id="icon" name="icon" placeholder="e.g., fa fa-arrow-right">
                                    <div class="form-help">Add a Font Awesome icon before each list item (e.g., fa fa-arrow-right)</div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="icon_color">Icon Color:</label>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <input type="color" id="icon_color" name="icon_color" value="#000000">
                                        <input type="text" id="icon_color_hex" value="#000000" readonly style="width: 80px; font-family: monospace;">
                                    </div>
                                    <div class="form-help">Set the color of the icon</div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="heading">Heading (Optional):</label>
                                    <input type="text" id="heading" name="heading" placeholder="e.g., Quick Links">
                                    <div class="form-help">Add an H6 heading before the list (leave empty for no heading)</div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="exclude_current">
                                        <input type="checkbox" id="exclude_current" name="exclude_current" value="yes">
                                        Exclude Current Page
                                    </label>
                                    <div class="form-help">Hide the current page from the list when viewing it</div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="active_color">Active Page Color:</label>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <input type="color" id="active_color" name="active_color" value="#ff6600">
                                        <input type="text" id="active_color_hex" value="#ff6600" readonly style="width: 80px; font-family: monospace;">
                                    </div>
                                    <div class="form-help">Highlight color for the current page link (only if not excluded)</div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="pagination">Pagination:</label>
                                    <select id="pagination" name="pagination">
                                        <option value="none">None (Show All)</option>
                                        <option value="load_more">Load More Button</option>
                                        <option value="infinite_scroll">Infinite Scroll</option>
                                    </select>
                                    <div class="form-help">Choose how to display posts (useful for large lists)</div>
                                </div>
                                
                                <div class="form-group pagination-option" style="display:none;">
                                    <label for="posts_per_page">Posts Per Page:</label>
                                    <input type="number" id="posts_per_page" name="posts_per_page" value="10" min="1">
                                    <div class="form-help">Number of posts to show initially</div>
                                </div>
                                
                                <div class="form-group pagination-option load-more-option" style="display:none;">
                                    <label for="load_more_text">Load More Button Text:</label>
                                    <input type="text" id="load_more_text" name="load_more_text" value="Load More">
                                    <div class="form-help">Text for the load more button</div>
                                </div>
                                
                                <div class="form-group pagination-option load-more-option" style="display:none;">
                                    <label for="load_more_bg_color">Button Background Color:</label>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <input type="color" id="load_more_bg_color" name="load_more_bg_color" value="#0073aa">
                                        <input type="text" id="load_more_bg_color_hex" value="#0073aa" readonly style="width: 80px; font-family: monospace;">
                                    </div>
                                    <div class="form-help">Background color for the load more button</div>
                                </div>
                                
                                <div class="form-group pagination-option load-more-option" style="display:none;">
                                    <label for="load_more_text_color">Button Text Color:</label>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <input type="color" id="load_more_text_color" name="load_more_text_color" value="#ffffff">
                                        <input type="text" id="load_more_text_color_hex" value="#ffffff" readonly style="width: 80px; font-family: monospace;">
                                    </div>
                                    <div class="form-help">Text color for the load more button</div>
                                </div>
                                
                                <div class="form-group page-exclusions">
                                    <label for="exclude_ids">Exclude Items:</label>
                                    <div class="excluded-pages-container">
                                        <select id="page_to_exclude" class="page-selector">
                                            <option value="">Select an item to exclude</option>
                                            <?php 
                                            $all_pages = get_pages();
                                            foreach ($all_pages as $page): ?>
                                                <option value="<?php echo esc_attr($page->ID); ?>" data-post-type="page"><?php echo esc_html($page->post_title); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="button" id="add_excluded_page" class="button">Add</button>
                                    </div>
                                    <div id="excluded_pages_list" class="excluded-pages-list"></div>
                                    <input type="hidden" id="exclude_ids" name="exclude_ids" value="">
                                    <div class="form-help">Select items to exclude from the sitemap</div>
                                </div>
                            </div>

                            <div class="form-footer">
                                <button type="submit" id="submit_button" class="button button-primary">Generate Shortcode</button>
                                <button type="button" id="create_new_button" class="button" style="display:none;">Create New</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="aqm-right-column">
                <div class="aqm-saved-shortcodes">
                    <h2>Saved Shortcodes</h2>
                    <div class="shortcodes-list" id="shortcodes-list">
                        <?php foreach ($saved_shortcodes as $name => $shortcode): ?>
                            <div class="shortcode-card">
                                <div class="shortcode-header">
                                    <h3><?php echo esc_html($name); ?></h3>
                                </div>
                                <div class="shortcode-content">
                                    <code><?php echo esc_html(wp_unslash($shortcode)); ?></code>
                                </div>
                                <div class="shortcode-actions">
                                    <button class="button copy-shortcode" data-shortcode="<?php echo esc_attr(wp_unslash($shortcode)); ?>">Copy</button>
                                    <button class="button edit-shortcode" 
                                            data-name="<?php echo esc_attr($name); ?>"
                                            data-shortcode="<?php echo esc_attr(wp_unslash($shortcode)); ?>">Edit</button>
                                    <button class="button delete-shortcode" 
                                            data-name="<?php echo esc_attr($name); ?>">Delete</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
}

// Helper function to ensure shortcode parameters are included
function aqm_ensure_shortcode_params($shortcode, $params = array()) {
    // For each parameter, check if it exists in the shortcode
    foreach ($params as $param => $value) {
        // Check if parameter already exists in shortcode
        if (strpos($shortcode, $param . '=') === false) {
            // Parameter doesn't exist, add it
            $shortcode = str_replace(']', ' ' . $param . '="' . $value . '"]', $shortcode);
            aqm_sitemaps_log('Added ' . $param . ' parameter to shortcode: ' . $shortcode);
        } else {
            // Parameter exists but might have an empty value, update it if value is provided
            if (!empty($value)) {
                // Use regex to replace the existing parameter value
                $pattern = '/(' . $param . '=)["\']([^"\']*)["\']/i';
                $replacement = '$1"' . $value . '"';
                $shortcode = preg_replace($pattern, $replacement, $shortcode);
                aqm_sitemaps_log('Updated ' . $param . ' parameter in shortcode: ' . $shortcode);
            }
        }
    }
    
    return $shortcode;
}

// Save shortcode
function aqm_save_shortcode() {
    // Debugging output is gated behind the AQM_SITEMAPS_DEBUG constant.
    
    // Log the raw POST data
    aqm_sitemaps_log('AQM Sitemaps Raw POST: ' . print_r($_POST, true));
    
    // Debug the icon and icon_color parameters
    if (isset($_POST['debug_icon'])) {
        aqm_sitemaps_log('Icon from debug: ' . $_POST['debug_icon']);
    }
    
    if (isset($_POST['debug_icon_color'])) {
        aqm_sitemaps_log('Icon color from debug: ' . $_POST['debug_icon_color']);
    }

    // Check if this is an AJAX request
    if (!wp_doing_ajax()) {
        aqm_sitemaps_log('AQM Sitemaps: Not an AJAX request');
        die('Invalid request method');
    }

    // Verify nonce first
    if (!isset($_POST['nonce'])) {
        aqm_sitemaps_log('AQM Sitemaps: Nonce is missing');
        wp_send_json_error('Security token is missing');
        wp_die();
    }

    if (!wp_verify_nonce($_POST['nonce'], 'aqm_sitemaps_nonce')) {
        aqm_sitemaps_log('AQM Sitemaps: Invalid nonce');
        wp_send_json_error('Invalid security token');
        wp_die();
    }

    // Check user capabilities
    if (!current_user_can('manage_options')) {
        aqm_sitemaps_log('AQM Sitemaps: Insufficient permissions');
        wp_send_json_error('You do not have permission to perform this action');
        wp_die();
    }

    // Get and validate required data
    $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
    $shortcode = isset($_POST['shortcode']) ? sanitize_text_field($_POST['shortcode']) : '';
    $edit_mode = isset($_POST['edit_mode']) ? $_POST['edit_mode'] === '1' : false;
    $original_name = isset($_POST['original_name']) ? sanitize_text_field($_POST['original_name']) : '';
    
    // Get icon and icon_color values directly from the form fields
    $icon = isset($_POST['icon']) ? sanitize_text_field($_POST['icon']) : '';
    $icon_color = isset($_POST['icon_color']) ? sanitize_text_field($_POST['icon_color']) : '';
    
    // Also check debug parameters as a fallback
    if (empty($icon) && isset($_POST['debug_icon'])) {
        $icon = sanitize_text_field($_POST['debug_icon']);
    }
    
    if (empty($icon_color) && isset($_POST['debug_icon_color'])) {
        $icon_color = sanitize_text_field($_POST['debug_icon_color']);
    }
    
    // Log the icon and icon_color values
    aqm_sitemaps_log('AQM Sitemaps: Icon value: ' . $icon);
    aqm_sitemaps_log('AQM Sitemaps: Icon color value: ' . $icon_color);
    
    // Log the sanitized data
    aqm_sitemaps_log('AQM Sitemaps: Sanitized data - ' . print_r([
        'name' => $name,
        'shortcode' => $shortcode,
        'edit_mode' => $edit_mode,
        'original_name' => $original_name
    ], true));

    // Validate required fields
    if (empty($name)) {
        wp_send_json_error('Shortcode name is required');
        wp_die();
    }

    if (empty($shortcode)) {
        wp_send_json_error('Shortcode content is required');
        wp_die();
    }

    try {
        // Get existing shortcodes with error checking
        $saved_shortcodes = get_option('aqm_sitemaps_shortcodes', array());
        if (!is_array($saved_shortcodes)) {
            $saved_shortcodes = array();
        }

        // Check for duplicates in create mode
        if (!$edit_mode && isset($saved_shortcodes[$name])) {
            wp_send_json_error('A shortcode with this name already exists');
            wp_die();
        }

        // Handle edit mode name changes
        if ($edit_mode && $name !== $original_name && isset($saved_shortcodes[$name])) {
            wp_send_json_error('Cannot rename: a shortcode with this name already exists');
            wp_die();
        }

        // Remove old shortcode in edit mode
        if ($edit_mode && $name !== $original_name && isset($saved_shortcodes[$original_name])) {
            unset($saved_shortcodes[$original_name]);
        }

        // Get form field values for all parameters that need to be ensured
        $icon_value = isset($_POST['icon']) ? sanitize_text_field($_POST['icon']) : '';
        $icon_color_value = isset($_POST['icon_color']) ? sanitize_text_field($_POST['icon_color']) : '';
        $item_margin_value = isset($_POST['item_margin']) ? sanitize_text_field($_POST['item_margin']) : '10px';
        $heading_value = isset($_POST['heading']) ? sanitize_text_field($_POST['heading']) : '';
        $exclude_current_value = isset($_POST['exclude_current']) ? 'yes' : 'no';
        $active_color_value = isset($_POST['active_color']) ? sanitize_text_field($_POST['active_color']) : '#ff6600';
        
        // If item_margin is empty, set default value
        if (empty($item_margin_value)) {
            $item_margin_value = '10px';
        }
        
        // Use our helper function to ensure parameters are included
        $params_to_ensure = array(
            'icon' => $icon_value,
            'icon_color' => $icon_color_value,
            'item_margin' => $item_margin_value,
            'exclude_current' => $exclude_current_value,
            'active_color' => $active_color_value
        );
        
        // Only add heading if it's not empty
        if (!empty($heading_value)) {
            $params_to_ensure['heading'] = $heading_value;
        }
        
        $shortcode = aqm_ensure_shortcode_params($shortcode, $params_to_ensure);
        
        // Save the shortcode
        $saved_shortcodes[$name] = $shortcode;

        // Update option with error checking
        // Note: update_option returns false if the value hasn't changed, so we need to check differently
        $update_result = update_option('aqm_sitemaps_shortcodes', $saved_shortcodes);
        
        // Verify the shortcode was actually saved by checking if it exists
        $verify_saved = get_option('aqm_sitemaps_shortcodes', array());
        if (isset($verify_saved[$name]) && $verify_saved[$name] === $shortcode) {
            aqm_sitemaps_log('AQM Sitemaps: Shortcode saved successfully - ' . $name);
            wp_send_json_success(array(
                'message' => 'Shortcode saved successfully',
                'name' => $name
            ));
        } else {
            aqm_sitemaps_log('AQM Sitemaps: Failed to verify saved shortcode');
            wp_send_json_error('Failed to save shortcode');
        }
    } catch (Exception $e) {
        aqm_sitemaps_log('AQM Sitemaps Exception: ' . $e->getMessage());
        wp_send_json_error('An unexpected error occurred: ' . $e->getMessage());
    }

    // Ensure we always die at the end
    wp_die();
}

// Remove any existing action to prevent duplicates
remove_action('wp_ajax_save_sitemaps_shortcode', 'aqm_save_shortcode');
remove_action('wp_ajax_nopriv_save_sitemaps_shortcode', 'aqm_save_shortcode');
remove_action('wp_ajax_aqm_sitemaps_save_shortcode', 'aqm_save_shortcode');

// Add our action
add_action('wp_ajax_aqm_save_shortcode', 'aqm_save_shortcode');

// Delete shortcode
function aqm_delete_shortcode() {
    if (!check_ajax_referer('aqm_sitemaps_nonce', 'nonce', false)) {
        wp_send_json_error('Invalid nonce');
    }

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
    }

    $name = sanitize_text_field($_POST['name']);
    
    $saved_shortcodes = get_option('aqm_sitemaps_shortcodes', array());
    unset($saved_shortcodes[$name]);
    
    update_option('aqm_sitemaps_shortcodes', $saved_shortcodes);
    
    wp_send_json_success();
}
add_action('wp_ajax_aqm_delete_shortcode', 'aqm_delete_shortcode');

// AJAX handler for loading more posts
function aqm_load_more_posts() {
    // Simple verification - check that required parameters exist
    if (!isset($_POST['page_ids']) || !isset($_POST['offset'])) {
        wp_send_json_error('Missing required parameters');
        return;
    }
    
    // Get parameters
    $page_ids = isset($_POST['page_ids']) ? json_decode(stripslashes($_POST['page_ids']), true) : array();
    $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
    $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 10;
    $post_type = isset($_POST['post_type']) ? sanitize_text_field($_POST['post_type']) : 'page';
    $display_type = isset($_POST['display_type']) ? sanitize_text_field($_POST['display_type']) : 'columns';
    $columns = isset($_POST['columns']) ? intval($_POST['columns']) : 2;
    $icon = isset($_POST['icon']) ? sanitize_text_field($_POST['icon']) : '';
    $icon_color = isset($_POST['icon_color']) ? sanitize_text_field($_POST['icon_color']) : '';
    $item_margin = isset($_POST['item_margin']) ? sanitize_text_field($_POST['item_margin']) : '10px';
    $disable_links = isset($_POST['disable_links']) ? $_POST['disable_links'] === '1' : false;
    
    // Get the posts for this batch
    $batch_ids = array_slice($page_ids, $offset, $limit);
    $posts = array();
    
    foreach ($batch_ids as $page_id) {
        $post = get_post($page_id);
        if ($post && $post->post_type == $post_type && $post->post_status == 'publish') {
            $posts[] = $post;
        }
    }
    
    if (empty($posts)) {
        wp_send_json_success(array(
            'html' => '',
            'has_more' => false
        ));
    }
    
    // Build HTML for the posts
    $html = '';
    
    if ($display_type === 'inline') {
        $links = array();
        foreach ($posts as $post) {
            if ($disable_links) {
                $links[] = sprintf(
                    '<span class="aqm-sitemap-item">%s</span>',
                    esc_html($post->post_title)
                );
            } else {
                $links[] = sprintf(
                    '<a href="%s">%s</a>',
                    esc_url(get_permalink($post->ID)),
                    esc_html($post->post_title)
                );
            }
        }
        $html = implode(' ', $links);
    } else {
        // Column display - return items that will be distributed across columns
        foreach ($posts as $post) {
            // Prepare icon HTML if an icon is specified
            $icon_html = '';
            if (!empty($icon)) {
                $icon_class = $icon;
                if (strpos($icon, 'fa-solid') === false && strpos($icon, 'fas') === false) {
                    if (strpos($icon, 'fa-') === 0) {
                        $icon_class = 'fa-solid ' . $icon;
                    }
                }
                $icon_html = sprintf('<i class="%s"></i>', esc_attr($icon_class));
            }
            
            $item_style = '';
            if (!empty($item_margin)) {
                $item_style = ' style="margin-bottom: ' . esc_attr($item_margin) . ';"';
            }
            
            if ($disable_links) {
                $html .= sprintf(
                    '<li%s>%s<span>%s</span></li>',
                    $item_style,
                    $icon_html,
                    esc_html($post->post_title)
                );
            } else {
                $html .= sprintf(
                    '<li%s>%s<a href="%s">%s</a></li>',
                    $item_style,
                    $icon_html,
                    esc_url(get_permalink($post->ID)),
                    esc_html($post->post_title)
                );
            }
        }
    }
    
    $has_more = ($offset + $limit) < count($page_ids);
    
    wp_send_json_success(array(
        'html' => $html,
        'has_more' => $has_more,
        'loaded' => $offset + count($posts)
    ));
}
add_action('wp_ajax_aqm_load_more_posts', 'aqm_load_more_posts');
add_action('wp_ajax_nopriv_aqm_load_more_posts', 'aqm_load_more_posts');

// Get posts by post type for exclusion dropdown
function aqm_get_posts_by_type() {
    if (!check_ajax_referer('aqm_sitemaps_nonce', 'nonce', false)) {
        wp_send_json_error('Invalid nonce');
    }

    if (!current_user_can('edit_posts')) {
        wp_send_json_error('Insufficient permissions');
    }

    $post_type = isset($_POST['post_type']) ? sanitize_text_field($_POST['post_type']) : 'page';
    
    // Validate post type
    $valid_post_types = get_post_types(array('public' => true), 'names');
    if (!in_array($post_type, $valid_post_types)) {
        wp_send_json_error('Invalid post type');
    }
    
    // Get posts of the specified type
    $posts = get_posts(array(
        'post_type' => $post_type,
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'orderby' => 'title',
        'order' => 'ASC'
    ));
    
    $options = array();
    foreach ($posts as $post) {
        $options[] = array(
            'id' => $post->ID,
            'title' => $post->post_title
        );
    }
    
    wp_send_json_success($options);
}
add_action('wp_ajax_aqm_get_posts_by_type', 'aqm_get_posts_by_type');

// Get folders that contain posts of a specific post type
function aqm_get_folders_by_post_type() {
    global $wpdb;
    
    if (!check_ajax_referer('aqm_sitemaps_nonce', 'nonce', false)) {
        wp_send_json_error('Invalid nonce');
    }

    if (!current_user_can('edit_posts')) {
        wp_send_json_error('Insufficient permissions');
    }

    $post_type = isset($_POST['post_type']) ? sanitize_text_field($_POST['post_type']) : 'page';
    
    // Validate post type
    $valid_post_types = get_post_types(array('public' => true), 'names');
    if (!in_array($post_type, $valid_post_types)) {
        wp_send_json_error('Invalid post type');
    }
    
    // DIAGNOSTIC: Find all options that might contain folder data
    $all_folder_options = $wpdb->get_results(
        "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '%folder%' LIMIT 20"
    );
    
    $folders_with_posts = array();
    
    // Premio Folders might use different taxonomy names for different post types
    // Try both 'folder' and post-type-specific taxonomy names
    $possible_taxonomies = array(
        $post_type . '_folder',  // e.g., team-member_folder
        str_replace('-', '_', $post_type) . '_folder',  // e.g., team_member_folder
        'folder',
        'folders_' . $post_type
    );
    
    $all_folders = array();
    $used_taxonomy = '';
    
    foreach ($possible_taxonomies as $taxonomy) {
        if (taxonomy_exists($taxonomy)) {
            $terms = get_terms(array(
                'taxonomy' => $taxonomy,
                'hide_empty' => false,
            ));
            
            if (!empty($terms) && !is_wp_error($terms)) {
                $all_folders = $terms;
                $used_taxonomy = $taxonomy;
                break;
            }
        }
    }
    
    // If no specific taxonomy found, fall back to 'folder'
    if (empty($all_folders)) {
        $all_folders = get_terms(array(
            'taxonomy' => 'folder',
            'hide_empty' => false,
        ));
        $used_taxonomy = 'folder';
    }
    
    $debug_folders = array();
    
    if (!empty($all_folders) && !is_wp_error($all_folders)) {
        foreach ($all_folders as $folder) {
            // Check if this folder has any posts of the specified type
            $args = array(
                'post_type' => $post_type,
                'post_status' => 'publish',
                'posts_per_page' => 1,
                'fields' => 'ids',
                'tax_query' => array(
                    array(
                        'taxonomy' => $used_taxonomy,
                        'field' => 'term_id',
                        'terms' => $folder->term_id,
                    ),
                ),
            );
            
            $posts_in_folder = get_posts($args);
            
            // Debug info for each folder
            $debug_folders[] = array(
                'name' => $folder->name,
                'slug' => $folder->slug,
                'term_id' => $folder->term_id,
                'post_count' => count($posts_in_folder),
                'has_posts' => !empty($posts_in_folder)
            );
            
            // If this folder has posts of this type, include it
            if (!empty($posts_in_folder)) {
                $folders_with_posts[] = array(
                    'slug' => $folder->slug,
                    'name' => $folder->name,
                    'term_id' => $folder->term_id
                );
            }
        }
    }
    
    wp_send_json_success(array(
        'folders' => $folders_with_posts,
        'post_type' => $post_type,
        'debug' => array(
            'used_taxonomy' => $used_taxonomy,
            'all_folders_count' => count($all_folders),
            'folders_with_posts_count' => count($folders_with_posts),
            'folder_details' => $debug_folders
        )
    ));
}
add_action('wp_ajax_aqm_get_folders_by_post_type', 'aqm_get_folders_by_post_type');



// Helper function to build ORDER BY clause
function aqm_build_order_clause($order, $custom_field_name = '', $custom_field_type = 'CHAR', $custom_field_order = 'ASC') {
    global $wpdb;
    
    if ($order === 'custom_field' && !empty($custom_field_name)) {
        // Build meta query for custom field ordering
        // We'll use a LEFT JOIN with wp_postmeta to order by custom field
        return array(
            'join' => " LEFT JOIN {$wpdb->postmeta} AS mt1 ON ({$wpdb->posts}.ID = mt1.post_id AND mt1.meta_key = '" . esc_sql($custom_field_name) . "')",
            'orderby' => " ORDER BY CAST(mt1.meta_value AS {$custom_field_type}) {$custom_field_order}, {$wpdb->posts}.post_title ASC"
        );
    } elseif ($order === 'title') {
        return array(
            'join' => '',
            'orderby' => " ORDER BY {$wpdb->posts}.post_title ASC"
        );
    } elseif ($order === 'date') {
        return array(
            'join' => '',
            'orderby' => " ORDER BY {$wpdb->posts}.post_date DESC"
        );
    } else {
        return array(
            'join' => '',
            'orderby' => " ORDER BY {$wpdb->posts}.menu_order ASC, {$wpdb->posts}.post_title ASC"
        );
    }
}

// The actual shortcode function
function display_enhanced_page_sitemap($atts) {
    global $wpdb;
    
    // Debug functionality has been removed
    $show_debug = false;
    
    // Ensure our styles are loaded with forced cache busting
    $css_version = AQM_SITEMAPS_VERSION . '.' . time(); // Ultra-aggressive cache busting
    wp_enqueue_style(
        'aqm-sitemaps-frontend',
        plugins_url('css/frontend-style.css', __FILE__),
        array(),
        $css_version
    );

    // Extract shortcode attributes with defaults
    $atts = shortcode_atts(array(
        'folder_slug' => '',
        'folder_slugs' => '', // New parameter for multiple folders
        'post_type' => 'page', // New parameter for post type
        'display_type' => 'columns',
        'columns' => '2',
        'order' => 'menu_order',
        'custom_field_name' => '', // ACF custom field name for ordering
        'custom_field_type' => 'CHAR', // ACF custom field type (CHAR, NUMERIC, DATE, DATETIME)
        'custom_field_order' => 'ASC', // Order direction for custom field (ASC or DESC)
        'exclude_ids' => '', // Parameter to exclude pages by ID
        'show_all' => 'no', // New parameter to show all pages
        'item_margin' => '10px', // New parameter for item bottom margin
        'item_padding' => '10px', // New parameter for item bottom padding
        'border_color' => '#dddddd', // New parameter for border color
        'icon' => '', // New parameter for Font Awesome icon
        'icon_color' => '', // New parameter for icon color
        'heading' => '', // New parameter for optional H6 heading
        'exclude_current' => 'no', // Exclude current page from list
        'active_color' => '#ff6600', // Color for current/active page link
        'disable_links' => 'no', // New parameter to disable links and show only titles
        'pagination' => 'none', // Pagination type: none, load_more, infinite_scroll
        'posts_per_page' => '-1', // Number of posts to show initially (-1 for all)
        'load_more_text' => 'Load More', // Text for load more button
        'load_more_bg_color' => '#0073aa', // Background color for load more button
        'load_more_text_color' => '#ffffff' // Text color for load more button
    ), $atts, 'sitemap_page');

    // Sanitize attributes
    $folder_slug = sanitize_text_field($atts['folder_slug']);
    $folder_slugs = sanitize_text_field($atts['folder_slugs']);
    $post_type = sanitize_text_field($atts['post_type']);
    
    // Validate post type exists and is public
    $valid_post_types = get_post_types(array('public' => true), 'names');
    if (!in_array($post_type, $valid_post_types)) {
        $post_type = 'page'; // Fallback to page if invalid
    }
    
    $display_type = in_array($atts['display_type'], array('columns', 'inline')) ? $atts['display_type'] : 'columns';
    $columns = intval($atts['columns']);
    $columns = $columns > 0 && $columns <= 6 ? $columns : 2;
    $order = in_array($atts['order'], array('menu_order', 'title', 'date', 'custom_field')) ? $atts['order'] : 'menu_order';
    $custom_field_name = sanitize_text_field($atts['custom_field_name']);
    $custom_field_type = in_array($atts['custom_field_type'], array('CHAR', 'NUMERIC', 'DATE', 'DATETIME')) ? $atts['custom_field_type'] : 'CHAR';
    $custom_field_order = in_array(strtoupper($atts['custom_field_order']), array('ASC', 'DESC')) ? strtoupper($atts['custom_field_order']) : 'ASC';
    $show_all = in_array(strtolower($atts['show_all']), array('yes', 'true', '1')) ? true : false;
    $item_margin = sanitize_text_field($atts['item_margin']);
    $item_padding = sanitize_text_field($atts['item_padding']);
    $border_color = sanitize_hex_color($atts['border_color']) ?: sanitize_text_field($atts['border_color']);
    $icon = sanitize_text_field($atts['icon']);
    $icon_color = sanitize_hex_color($atts['icon_color']) ?: sanitize_text_field($atts['icon_color']);
    $heading = sanitize_text_field($atts['heading']);
    $exclude_current = in_array(strtolower($atts['exclude_current']), array('yes', 'true', '1')) ? true : false;
    // Sanitize active_color - try hex first, then text field, then default
    $active_color_raw = !empty($atts['active_color']) ? $atts['active_color'] : '#ff6600';
    $active_color = sanitize_hex_color($active_color_raw);
    if (!$active_color) {
        $active_color = sanitize_text_field($active_color_raw);
    }
    if (empty($active_color)) {
        $active_color = '#ff6600';
    }
    $disable_links = in_array(strtolower($atts['disable_links']), array('yes', 'true', '1')) ? true : false;
    $pagination = in_array($atts['pagination'], array('none', 'load_more', 'infinite_scroll')) ? $atts['pagination'] : 'none';
    $posts_per_page = intval($atts['posts_per_page']);
    $load_more_text = sanitize_text_field($atts['load_more_text']);
    $load_more_bg_color = sanitize_hex_color($atts['load_more_bg_color']) ?: '#0073aa';
    $load_more_text_color = sanitize_hex_color($atts['load_more_text_color']) ?: '#ffffff';
    
    // Ensure item_margin is not empty
    if (empty($item_margin)) {
        $item_margin = '10px';
    }
    
    // Ensure load_more_text is not empty
    if (empty($load_more_text)) {
        $load_more_text = 'Load More';
    }
    
    // Get current page ID
    $current_page_id = get_queried_object_id();
    
    // Process exclude IDs
    $exclude_ids = array();
    if (!empty($atts['exclude_ids'])) {
        $exclude_ids_raw = trim($atts['exclude_ids']);
        if (!empty($exclude_ids_raw)) {
            $exclude_ids = array_map('intval', explode(',', $exclude_ids_raw));
            $exclude_ids = array_filter($exclude_ids); // Remove any zero/invalid IDs
        }
    }
    
    // Add current page to exclude list if exclude_current is enabled
    if ($exclude_current && $current_page_id) {
        $exclude_ids[] = intval($current_page_id);
        $exclude_ids = array_unique($exclude_ids);
        $exclude_ids = array_values($exclude_ids); // Re-index array
    }
    
    // Debug information
    $debug = '';
    if ($show_debug) {
        $debug .= '<div style="background:#f5f5f5;border:1px solid #ccc;padding:10px;margin-bottom:20px;font-family:monospace;">';
        $debug .= '<p><strong>Debug Info:</strong></p>';
        $debug .= '<p>Shortcode used: ' . current_filter() . '</p>';
        $debug .= '<p>Show All Pages: ' . ($show_all ? 'Yes' : 'No') . '</p>';
        $debug .= '<p>Disable Links: ' . ($disable_links ? 'Yes' : 'No') . '</p>';
        if (!$show_all) {
            $debug .= '<p>Folder: ' . esc_html($folder_slug) . '</p>';
        }
        $debug .= '<p>Excluded IDs: ' . (!empty($exclude_ids) ? esc_html(implode(', ', $exclude_ids)) : 'None') . '</p>';
        
        // Add Premio Folders debugging
        $terms = get_terms(array(
            'taxonomy' => 'folder',
            'hide_empty' => false,
        ));
        if (!empty($terms) && !is_wp_error($terms)) {
            $debug .= '<p><strong>Available Folders:</strong></p><ul>';
            foreach ($terms as $term) {
                $debug .= '<li>' . esc_html($term->name) . ' [' . esc_html($term->slug) . '] (ID: ' . esc_html($term->term_id) . ')</li>';
            }
            $debug .= '</ul>';
        } else {
            $debug .= '<p>No folder terms found. Check if Premio Folders is active.</p>';
        }
        
        $debug .= '</div>';
    }
    
    // Initialize page_ids array
    $page_ids = array();
    
    // If show_all is true, get all published posts of the specified type
    if ($show_all) {
        // Get order clause
        $order_clause = aqm_build_order_clause($order, $custom_field_name, $custom_field_type, $custom_field_order);
        
        $all_pages_query = $wpdb->prepare("SELECT {$wpdb->posts}.ID FROM {$wpdb->posts}", '');
        $all_pages_query .= $order_clause['join'];
        $all_pages_query .= $wpdb->prepare(" WHERE {$wpdb->posts}.post_type = %s AND {$wpdb->posts}.post_status = 'publish'", $post_type);
        
        // Add exclude IDs if any
        if (!empty($exclude_ids)) {
            $exclude_ids_str = implode(',', array_map('intval', $exclude_ids));
            $all_pages_query .= " AND {$wpdb->posts}.ID NOT IN ({$exclude_ids_str})";
        }
        
        // Add ordering
        $all_pages_query .= $order_clause['orderby'];
        
        // Get the final list of page IDs
        $page_ids = $wpdb->get_col($all_pages_query);
        
        if ($show_debug) {
            $debug .= '<div style="background:#f5f5f5;border:1px solid #ccc;padding:10px;margin-bottom:20px;font-family:monospace;">';
            $debug .= '<p><strong>Show All Pages Mode:</strong> Getting all published pages except excluded IDs</p>';
            $debug .= '</div>';
        }
    } else {
        // Check if folder slug or slugs are empty when not showing all
        // If no folders provided, show all posts of the specified type
        if (empty($folder_slug) && empty($folder_slugs)) {
            // Get order clause
            $order_clause = aqm_build_order_clause($order, $custom_field_name, $custom_field_type, $custom_field_order);
            
            // Query for all published posts of the specified type
            $all_posts_query = "SELECT {$wpdb->posts}.ID FROM {$wpdb->posts}";
            $all_posts_query .= $order_clause['join'];
            $all_posts_query .= $wpdb->prepare(" WHERE {$wpdb->posts}.post_type = %s AND {$wpdb->posts}.post_status = 'publish'", $post_type);
            
            // Add exclude IDs if any
            if (!empty($exclude_ids)) {
                $exclude_ids_str = implode(',', array_map('intval', $exclude_ids));
                $all_posts_query .= " AND {$wpdb->posts}.ID NOT IN ({$exclude_ids_str})";
            }
            
            // Add ordering
            $all_posts_query .= $order_clause['orderby'];
            
            // Get the final list of post IDs
            $page_ids = $wpdb->get_col($all_posts_query);
            
            if ($show_debug) {
                $debug .= '<div style="background:#f5f5f5;border:1px solid #ccc;padding:10px;margin-bottom:20px;font-family:monospace;">';
                $debug .= '<p><strong>No Folders Mode:</strong> Getting all published ' . esc_html($post_type) . ' posts</p>';
                $debug .= '</div>';
            }
        } else {
            // Folders are provided, process them
            $folder_terms = array();
            $all_page_ids = array();
            
            // Determine the correct taxonomy to use based on post type
            $folder_taxonomy = 'folder'; // default for pages
            if ($post_type !== 'page') {
                // Try multiple possible taxonomy formats for custom post types
                $possible_taxonomies = array(
                    $post_type . '_folder',  // e.g., product_folder
                    str_replace('-', '_', $post_type) . '_folder',  // e.g., custom_post_folder
                    'folder_' . $post_type,  // e.g., folder_product
                    'folders_' . $post_type,  // e.g., folders_product
                    $post_type . '-category',  // e.g., product-category
                    $post_type . '_category',  // e.g., product_category
                );
                
                foreach ($possible_taxonomies as $taxonomy) {
                    if (taxonomy_exists($taxonomy)) {
                        $folder_taxonomy = $taxonomy;
                        break;
                    }
                }
            }
            
            // Handle multiple folder slugs (comma separated)
            if (!empty($folder_slugs)) {
            $slug_array = array_map('trim', explode(',', $folder_slugs));
            
            foreach ($slug_array as $slug) {
                $term = get_term_by('slug', $slug, $folder_taxonomy);
                if ($term) {
                    $folder_terms[] = $term;
                }
            }
        }
        // Handle single folder slug for backward compatibility
        elseif (!empty($folder_slug)) {
            $term = get_term_by('slug', $folder_slug, $folder_taxonomy);
            if ($term) {
                $folder_terms[] = $term;
            }
        }
        
        // If no valid folders found
        if (empty($folder_terms)) {
            if ($show_debug) {
                return $debug . '<p>No valid folders found.</p>';
            }
            return '<p>No pages found.</p>';
        }
        
        // Additional debug info about the selected folders
        if ($show_debug) {
            $debug .= '<div style="background:#f5f5f5;border:1px solid #ccc;padding:10px;margin-bottom:20px;font-family:monospace;">';
            $debug .= '<p><strong>Selected Folder Details:</strong></p>';
            
            foreach ($folder_terms as $folder_term) {
                $debug .= '<div style="margin-bottom:10px;padding-bottom:10px;border-bottom:1px solid #ddd;">';
                $debug .= '<p>Name: ' . esc_html($folder_term->name) . '</p>';
                $debug .= '<p>Slug: ' . esc_html($folder_term->slug) . '</p>';
                $debug .= '<p>Term ID: ' . esc_html($folder_term->term_id) . '</p>';
                $debug .= '</div>';
            }
            
            $debug .= '</div>';
        }
        
        // Determine the correct taxonomy to use based on post type
        $folder_taxonomy = 'folder'; // default for pages
        if ($post_type !== 'page') {
            // Try multiple possible taxonomy formats for custom post types
            $possible_taxonomies = array(
                $post_type . '_folder',  // e.g., product_folder
                str_replace('-', '_', $post_type) . '_folder',  // e.g., custom_post_folder
                'folder_' . $post_type,  // e.g., folder_product
                'folders_' . $post_type,  // e.g., folders_product
                $post_type . '-category',  // e.g., product-category
                $post_type . '_category',  // e.g., product_category
            );
            
            foreach ($possible_taxonomies as $taxonomy) {
                if (taxonomy_exists($taxonomy)) {
                    $folder_taxonomy = $taxonomy;
                    break;
                }
            }
        }
        
        // Get pages from all selected folders
        foreach ($folder_terms as $folder_term) {
            $folder_page_ids = get_objects_in_term($folder_term->term_id, $folder_taxonomy);
            $all_page_ids = array_merge($all_page_ids, $folder_page_ids);
        }
        
        // Remove duplicates
        $all_page_ids = array_unique($all_page_ids);
        
        // Filter to only include published posts of the specified type
        if (!empty($all_page_ids)) {
            // Get order clause
            $order_clause = aqm_build_order_clause($order, $custom_field_name, $custom_field_type, $custom_field_order);
            
            $page_ids_str = implode(',', array_map('intval', $all_page_ids));
            $published_query = "SELECT {$wpdb->posts}.ID FROM {$wpdb->posts}";
            $published_query .= $order_clause['join'];
            $published_query .= $wpdb->prepare(" WHERE {$wpdb->posts}.ID IN ({$page_ids_str}) AND {$wpdb->posts}.post_type = %s AND {$wpdb->posts}.post_status = 'publish'", $post_type);
            
            // Add exclude IDs if any
            if (!empty($exclude_ids)) {
                $exclude_ids_str = implode(',', array_map('intval', $exclude_ids));
                $published_query .= " AND {$wpdb->posts}.ID NOT IN ({$exclude_ids_str})";
            }
            
            // Add ordering
            $published_query .= $order_clause['orderby'];
            
            // Get the final list of page IDs
            $page_ids = $wpdb->get_col($published_query);
        }
        } // End of else block for folder processing
    }
    
    // Debug page IDs
    if ($show_debug) {
        $debug .= '<div style="background:#f5f5f5;border:1px solid #ccc;padding:10px;margin-bottom:20px;font-family:monospace;">';
        $debug .= '<p><strong>Page IDs ' . ($show_all ? '(All Pages)' : 'in Folder') . ':</strong></p>';
        if (!empty($page_ids)) {
            $debug .= '<p>' . implode(', ', $page_ids) . '</p>';
            
            // Add page titles for easier identification
            $debug .= '<p><strong>Pages with titles:</strong></p><ul>';
            foreach ($page_ids as $page_id) {
                $title = get_the_title($page_id);
                $permalink = get_permalink($page_id);
                $debug .= '<li>ID ' . $page_id . ': <a href="' . esc_url($permalink) . '" target="_blank">' . esc_html($title) . '</a></li>';
            }
            $debug .= '</ul>';
        } else {
            $debug .= '<p>No page IDs found' . ($show_all ? '' : ' in this folder') . '.</p>';
        }
        $debug .= '</div>';
    }
    
    // Get posts by ID
    $pages = array();
    if (!empty($page_ids)) {
        foreach ($page_ids as $page_id) {
            $page = get_post($page_id);
            if ($page && $page->post_type == $post_type && $page->post_status == 'publish') {
                $pages[] = $page;
            }
        }
    }
    
    if (empty($pages)) {
        if ($show_debug) {
            return $debug . '<p>No pages found' . ($show_all ? '' : ' in the selected folder') . '.</p>';
        }
        return '<p>No pages found.</p>';
    }
    
    // Store total count before pagination
    $total_posts = count($pages);
    $all_page_ids_json = json_encode($page_ids);
    
    // Apply pagination if enabled
    $has_more = false;
    if ($pagination !== 'none' && $posts_per_page > 0) {
        $pages = array_slice($pages, 0, $posts_per_page);
        $has_more = $total_posts > $posts_per_page;
    }
    
    // Generate unique ID for this sitemap instance
    $sitemap_id = 'aqm-sitemap-' . uniqid();
    
    // Build output
    $output = '';
    if ($show_debug) {
        $output .= $debug;
    }
    
    // Create wrapper with classes
    $classes = array('aqm-sitemap');
    if ($display_type === 'columns') {
        $classes[] = 'aqm-columns-' . esc_attr($columns);
    } else {
        $classes[] = 'inline';
    }
    
    // Add inline CSS custom properties for styling
    $style_attr = '';
    $styles = array();
    if (!empty($icon_color)) {
        $styles[] = '--icon-color: ' . esc_attr($icon_color);
    }
    if (!empty($item_margin)) {
        $styles[] = '--item-margin: ' . esc_attr($item_margin);
    }
    if (!empty($item_padding)) {
        $styles[] = '--item-padding: ' . esc_attr($item_padding);
    }
    if (!empty($border_color)) {
        $styles[] = '--border-color: ' . esc_attr($border_color);
    }
    if (!empty($active_color)) {
        $styles[] = '--active-color: ' . esc_attr($active_color);
    }
    if (!empty($styles)) {
        $style_attr = ' style="' . implode('; ', $styles) . ';"';
    }
    
    // Add data attributes
    $data_attrs = '';
    // Always add post-type for CSS targeting
    $data_attrs .= ' data-post-type="' . esc_attr($post_type) . '"';
    
    // Add pagination data attributes if enabled
    if ($pagination !== 'none') {
        $data_attrs .= ' id="' . esc_attr($sitemap_id) . '"';
        $data_attrs .= ' data-pagination="' . esc_attr($pagination) . '"';
        $data_attrs .= ' data-loaded="' . esc_attr(count($pages)) . '"';
        $data_attrs .= ' data-total="' . esc_attr($total_posts) . '"';
        $data_attrs .= ' data-page-ids="' . esc_attr($all_page_ids_json) . '"';
        $data_attrs .= ' data-display-type="' . esc_attr($display_type) . '"';
        $data_attrs .= ' data-columns="' . esc_attr($columns) . '"';
        $data_attrs .= ' data-icon="' . esc_attr($icon) . '"';
        $data_attrs .= ' data-icon-color="' . esc_attr($icon_color) . '"';
        $data_attrs .= ' data-item-margin="' . esc_attr($item_margin) . '"';
        $data_attrs .= ' data-disable-links="' . esc_attr($disable_links ? '1' : '0') . '"';
    }
    
    $output .= '<div class="' . esc_attr(implode(' ', $classes)) . '"' . $style_attr . $data_attrs . '>';
    
    // Add optional H6 heading before the list
    if (!empty($heading)) {
        $output .= '<h6 class="aqm-sitemap-heading">' . esc_html($heading) . '</h6>';
    }
    
    // For inline display
    if ($display_type === 'inline') {
        $links = array();
        foreach ($pages as $page) {
            $is_current = (intval($page->ID) === intval($current_page_id));
            $active_style = $is_current ? ' style="color: ' . esc_attr($active_color) . ' !important;"' : '';
            $active_class = $is_current ? ' class="aqm-active-page"' : '';
            
            if ($disable_links) {
                $links[] = sprintf(
                    '<span class="aqm-sitemap-item"%s%s>%s</span>',
                    $active_class,
                    $active_style,
                    esc_html($page->post_title)
                );
            } else {
                $links[] = sprintf(
                    '<a href="%s"%s%s>%s</a>',
                    esc_url(get_permalink($page->ID)),
                    $active_class,
                    $active_style,
                    esc_html($page->post_title)
                );
            }
        }
        $output .= implode(' ', $links);
    } else {
        // Column display
        $total_items = count($pages);
        $items_per_column = ceil($total_items / $columns);
        
        $output .= '<div class="aqm-sitemap-columns-container">';
        
        for ($col = 0; $col < $columns; $col++) {
            $start = $col * $items_per_column;
            $column_pages = array_slice($pages, $start, $items_per_column);
            
            if (!empty($column_pages)) {
                $output .= '<div class="aqm-sitemap-column">';
                $output .= '<ul>';
                
                foreach ($column_pages as $page) {
                    // Check if this is the current page
                    $is_current = (intval($page->ID) === intval($current_page_id));
                    $active_style = $is_current ? ' style="color: ' . esc_attr($active_color) . ' !important;"' : '';
                    $active_class = $is_current ? ' class="aqm-active-page"' : '';
                    
                    // Prepare icon HTML if an icon is specified
                    $icon_html = '';
                    if (!empty($icon)) {
                        // Use an i tag with fa-solid class prefix
                        // Make sure we have the fa-solid prefix for Font Awesome 6 compatibility
                        $icon_class = $icon;
                        if (strpos($icon, 'fa-solid') === false && strpos($icon, 'fas') === false) {
                            // Only add fa-solid if it doesn't already have a Font Awesome prefix
                            if (strpos($icon, 'fa-') === 0) {
                                $icon_class = 'fa-solid ' . $icon;
                            }
                        }
                        $icon_html = sprintf('<i class="%s"></i>', esc_attr($icon_class));
                    }
                    
                    if ($disable_links) {
                        $output .= sprintf(
                            '<li><span class="aqm-sitemap-item"%s%s>%s%s</span></li>',
                            $active_class,
                            $active_style,
                            $icon_html,
                            esc_html($page->post_title)
                        );
                    } else {
                        $output .= sprintf(
                            '<li><a href="%s"%s%s>%s%s</a></li>',
                            esc_url(get_permalink($page->ID)),
                            $active_class,
                            $active_style,
                            $icon_html,
                            esc_html($page->post_title)
                        );
                    }
                }
                
                $output .= '</ul>';
                $output .= '</div>';
            }
        }
        
        $output .= '</div>';
    }
    
    // Add pagination controls if needed
    if ($pagination !== 'none' && $has_more) {
        if ($pagination === 'load_more') {
            $button_style = sprintf(
                'background-color: %s; color: %s;',
                esc_attr($load_more_bg_color),
                esc_attr($load_more_text_color)
            );
            $output .= '<div class="aqm-load-more-container">';
            $output .= '<button class="aqm-load-more-btn" style="' . $button_style . '" data-sitemap-id="' . esc_attr($sitemap_id) . '">' . esc_html($load_more_text) . '</button>';
            $output .= '<span class="aqm-loading" style="display:none;">Loading...</span>';
            $output .= '</div>';
        } elseif ($pagination === 'infinite_scroll') {
            $output .= '<div class="aqm-infinite-scroll-trigger" data-sitemap-id="' . esc_attr($sitemap_id) . '" style="height:1px;"></div>';
            $output .= '<div class="aqm-loading-indicator" style="display:none;text-align:center;padding:20px;">Loading more...</div>';
        }
    }
    
    // Close main wrapper
    $output .= '</div>';
    
    // Enqueue and localize frontend pagination script if pagination is enabled
    if ($pagination !== 'none') {
        wp_enqueue_script('aqm-sitemaps-pagination');
        
        wp_localize_script('aqm-sitemaps-pagination', 'aqmSitemapsPagination', array(
            'ajaxurl' => admin_url('admin-ajax.php')
        ));
    }
    
    // Remove wpautop and shortcode_unautop filters to prevent unwanted <p> tags
    remove_filter('the_content', 'wpautop');
    remove_filter('the_content', 'shortcode_unautop');
    // Clean up output: trim, collapse whitespace, and remove empty paragraphs
    $output = trim(preg_replace('/\s+/', ' ', $output));
    $output = preg_replace('#<p>\s*</p>#', '', $output);
    return $output;
}

// Also register the plural version of the shortcode for consistency
add_shortcode('sitemap_pages', 'display_enhanced_page_sitemap');
add_shortcode('sitemap_page', 'display_enhanced_page_sitemap');
