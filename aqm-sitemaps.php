<?php
/**
 * Plugin Name: AQM Sitemaps
 * Description: Enhanced sitemap plugin with folder selection and shortcode management
 * Version: 2.2.2
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
define('AQM_SITEMAPS_VERSION', '2.2.2');

// Set up text domain for translations
function aqm_sitemaps_load_textdomain() {
    load_plugin_textdomain('aqm-sitemaps', false, dirname(plugin_basename(__FILE__)) . '/languages');
}
add_action('init', 'aqm_sitemaps_load_textdomain');

// Include the GitHub Updater class
require_once plugin_dir_path(__FILE__) . 'includes/class-aqmsm-updater.php';

// Initialize the GitHub Updater
function aqm_sitemaps_init_github_updater() {
    // Log that we're initializing the updater
    error_log('=========================================================');
    error_log('[AQM SITEMAPS v' . AQM_SITEMAPS_VERSION . '] USING CUSTOM UPDATER CLASS');
    error_log('=========================================================');
    
    if (class_exists('AQMSM_Updater')) {
        try {
            new AQMSM_Updater(
                __FILE__,                // Plugin File
                'JustCasey76',           // GitHub username
                'aqm-sitemaps',          // GitHub repository name
                ''                       // Optional GitHub access token (for private repos)
            );
            
            // Set last update check time
            update_option('aqm_sitemaps_last_update_check', time());
        } catch (Exception $e) {
            error_log('[AQM SITEMAPS] Error initializing updater: ' . $e->getMessage());
        }
    } else {
        error_log('[AQM SITEMAPS] Updater class not found');
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
    
    error_log('[AQM SITEMAPS] Plugin update detected, checking activation state');
    
    // Check if the plugin was active before the update
    if (get_option('aqm_sitemaps_was_active', false)) {
        // Make sure plugin functions are loaded
        if (!function_exists('is_plugin_active') || !function_exists('activate_plugin')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        
        // If plugin is not active, reactivate it
        if (!is_plugin_active($plugin_basename)) {
            error_log('[AQM SITEMAPS] Plugin was active before update but is now inactive, reactivating');
            
            // Reactivate the plugin
            $result = activate_plugin($plugin_basename);
            
            if (is_wp_error($result)) {
                error_log('[AQM SITEMAPS] Reactivation failed: ' . $result->get_error_message());
            } else {
                error_log('[AQM SITEMAPS] Plugin successfully reactivated');
                
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
        AQM_SITEMAPS_VERSION . '.' . time(), // Add timestamp for cache busting
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
    error_log('[AQM SITEMAPS] Manual update check triggered');
    
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

    // Only enqueue jQuery, we'll use inline JavaScript instead
    wp_enqueue_script('jquery');
    
    // Don't load the external script file since it has syntax errors
    // We'll pass the necessary data directly to the inline script
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
        
        error_log('AQM Sitemaps: Migrated ' . count($old_shortcodes) . ' shortcodes to new option name');
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
    error_log('=========================================================');
    error_log('[AQM SITEMAPS] Plugin activated, version ' . AQM_SITEMAPS_VERSION);
    error_log('=========================================================');
}
register_activation_hook(__FILE__, 'aqm_sitemaps_activate');

// Handle plugin deactivation
function aqm_sitemaps_deactivate() {
    // Mark plugin as inactive
    update_option('aqm_sitemaps_was_active', false);
    
    // Log deactivation
    error_log('=========================================================');
    error_log('[AQM SITEMAPS] Plugin deactivated');
    error_log('=========================================================');
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
    error_log('AQM Sitemaps: Number of saved shortcodes: ' . count($saved_shortcodes));
    
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
        
        <?php
        // Display success or error messages
        if (isset($_GET['shortcode_saved']) && $_GET['shortcode_saved'] === '1') {
            echo '<div class="notice notice-success is-dismissible"><p><strong>Success!</strong> Your shortcode has been saved successfully.</p></div>';
        }
        
        if (isset($_GET['shortcode_error']) && $_GET['shortcode_error'] === '1') {
            echo '<div class="notice notice-error is-dismissible"><p><strong>Error!</strong> There was a problem saving your shortcode. Please try again.</p></div>';
        }
        ?>
        
        <!-- Inline JavaScript for button functionality -->
        <script type="text/javascript">
            console.log('Inline script loaded');
            
            // Function to copy text to clipboard
            function copyToClipboard(text, button) {
                console.log('Copying to clipboard:', text);
                
                // Store the original button text
                const originalText = button.textContent;
                
                // Use modern clipboard API if available, fallback to execCommand
                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(text)
                        .then(function() {
                            button.textContent = 'Copied!';
                            setTimeout(function() {
                                button.textContent = originalText;
                            }, 2000);
                        })
                        .catch(function(err) {
                            console.error('Failed to copy with Clipboard API:', err);
                            fallbackCopyMethod(text, button, originalText);
                        });
                } else {
                    fallbackCopyMethod(text, button, originalText);
                }
            }
            
            // Fallback copy method using execCommand
            function fallbackCopyMethod(text, button, originalText) {
                const textarea = document.createElement('textarea');
                textarea.value = text;
                textarea.style.position = 'fixed'; // Prevent scrolling to bottom
                document.body.appendChild(textarea);
                textarea.focus();
                textarea.select();
                
                try {
                    const successful = document.execCommand('copy');
                    button.textContent = successful ? 'Copied!' : 'Failed';
                } catch (err) {
                    console.error('Error copying text:', err);
                    button.textContent = 'Failed';
                }
                
                document.body.removeChild(textarea);
                
                setTimeout(function() {
                    button.textContent = originalText;
                }, 2000);
            }
            
            // Function to edit a shortcode
            function editShortcode(name, shortcode) {
                console.log('Editing shortcode:', name);
                console.log('Shortcode content:', shortcode);
                
                // Set edit mode immediately
                document.getElementById('edit_mode').value = '1';
                document.getElementById('original_name').value = name;
                document.getElementById('shortcode_name').value = name;
                
                // Scroll to form
                document.getElementById('aqm-sitemap-form').scrollIntoView({ behavior: 'smooth', block: 'start' });
                
                // Update submit button text
                document.getElementById('submit_button').textContent = 'Save Changes';
                
                try {
                    // Clear existing excluded pages
                    document.getElementById('excluded_pages_list').innerHTML = '';
                    
                    // Uncheck all folder checkboxes first
                    document.querySelectorAll('.folder-checklist input[type="checkbox"]').forEach(function(checkbox) {
                        checkbox.checked = false;
                    });
                    
                    // Parse the shortcode using regex patterns
                    // Extract folder_slug or folder_slugs
                    let folderSlugs = [];
                    const folderSlugMatch = shortcode.match(/folder_slug=["']([^"']*)["']/);
                    const folderSlugsMatch = shortcode.match(/folder_slugs=["']([^"']*)["']/);
                    
                    if (folderSlugsMatch && folderSlugsMatch[1]) {
                        folderSlugs = folderSlugsMatch[1].split(',');
                        console.log('Found folder_slugs:', folderSlugs);
                    } else if (folderSlugMatch && folderSlugMatch[1]) {
                        folderSlugs = [folderSlugMatch[1]];
                        console.log('Found folder_slug:', folderSlugs);
                    }
                    
                    // Check the appropriate folder checkboxes
                    folderSlugs.forEach(function(slug) {
                        const checkbox = document.getElementById('folder_' + slug);
                        if (checkbox) {
                            checkbox.checked = true;
                            console.log('Checked folder:', slug);
                        } else {
                            console.log('Folder checkbox not found for:', slug);
                        }
                    });
                    
                    // Extract other attributes
                    const getAttributeValue = function(attrName) {
                        const regex = new RegExp(attrName + '=["\']([^"\']*)["\'']');
                        const match = shortcode.match(regex);
                        return match ? match[1] : null;
                    };
                    
                    // Set display type
                    const displayType = getAttributeValue('display_type') || 'columns';
                    document.getElementById('display_type').value = displayType;
                    console.log('Set display_type:', displayType);
                    
                    // Set columns
                    const columns = getAttributeValue('columns');
                    if (columns) {
                        document.getElementById('columns').value = columns;
                        console.log('Set columns:', columns);
                    }
                    
                    // Set order
                    const order = getAttributeValue('order') || 'menu_order';
                    document.getElementById('order').value = order;
                    console.log('Set order:', order);
                    
                    // Set item margin
                    const itemMargin = getAttributeValue('item_margin');
                    if (itemMargin) {
                        document.getElementById('item_margin').value = itemMargin;
                        console.log('Set item_margin:', itemMargin);
                    }
                    
                    // Set icon
                    const icon = getAttributeValue('icon');
                    if (icon) {
                        document.getElementById('icon').value = icon;
                        console.log('Set icon:', icon);
                    }
                    
                    // Set icon color
                    const iconColor = getAttributeValue('icon_color');
                    if (iconColor) {
                        document.getElementById('icon_color').value = iconColor;
                        console.log('Set icon_color:', iconColor);
                    }
                    
                    // Set debug
                    const debug = getAttributeValue('debug') || 'no';
                    document.getElementById('debug').value = debug;
                    console.log('Set debug:', debug);
                    
                    // Set use_divider
                    const useDivider = getAttributeValue('use_divider') || 'yes';
                    document.getElementById('use_divider').value = useDivider;
                    console.log('Set use_divider:', useDivider);
                    
                    // Set divider
                    const divider = getAttributeValue('divider') || '|';
                    document.getElementById('divider').value = divider;
                    console.log('Set divider:', divider);
                    
                    // Update display based on display type
                    if (displayType === 'inline') {
                        document.querySelectorAll('.columns-option').forEach(function(el) { el.style.display = 'none'; });
                        document.querySelectorAll('.inline-options').forEach(function(el) { el.style.display = 'block'; });
                        
                        // Show/hide divider options
                        if (useDivider === 'yes') {
                            document.querySelectorAll('.divider-options').forEach(function(el) { el.style.display = 'block'; });
                        } else {
                            document.querySelectorAll('.divider-options').forEach(function(el) { el.style.display = 'none'; });
                        }
                    } else {
                        document.querySelectorAll('.columns-option').forEach(function(el) { el.style.display = 'block'; });
                        document.querySelectorAll('.inline-options').forEach(function(el) { el.style.display = 'none'; });
                        document.querySelectorAll('.divider-options').forEach(function(el) { el.style.display = 'none'; });
                    }
                } catch (error) {
                    console.error('Error parsing shortcode:', error);
                    // Don't show an error message, just log it
                    // The form is already in edit mode with the correct name
                }
                
                // Scroll to form
                document.getElementById('aqm-sitemap-form').scrollIntoView({ behavior: 'smooth', block: 'start' });
                
                // Update submit button text
                document.getElementById('submit_button').textContent = 'Save Changes';
            }
            
            // Set up event handlers for the buttons
            jQuery(document).ready(function() {
                console.log('Setting up button event handlers');
                
                // Use event delegation for delete buttons
                jQuery(document).on('click', '.delete-shortcode', function(e) {
                    e.preventDefault();
                    
                    // Get the shortcode name from the data attribute
                    const name = jQuery(this).data('name');
                    console.log('Delete button clicked for shortcode:', name);
                    
                    if (!name) {
                        console.error('No shortcode name found');
                        alert('Error: Could not determine which shortcode to delete');
                        return;
                    }
                    
                    // Confirm deletion
                    if (!confirm('Are you sure you want to delete the shortcode "' + name + '"?')) {
                        return;
                    }
                    
                    // Show loading message
                    const originalText = jQuery(this).text();
                    jQuery(this).text('Deleting...').prop('disabled', true);
                    
                    // Send AJAX request directly
                    jQuery.post({
                        url: '<?php echo admin_url('admin-ajax.php'); ?>',
                        data: {
                            action: 'aqm_delete_shortcode',
                            nonce: '<?php echo wp_create_nonce('aqm_sitemaps_nonce'); ?>',
                            name: name
                        },
                        success: function(response) {
                            console.log('Delete response:', response);
                            alert('Shortcode "' + name + '" deleted successfully!');
                            window.location.reload();
                        },
                        error: function(xhr, status, error) {
                            console.error('Delete error:', error);
                            alert('Error deleting shortcode. Please try again.');
                            jQuery(this).text(originalText).prop('disabled', false);
                        }
                    });
                });
                
                // Handle form submission via AJAX
                jQuery('#aqm-sitemap-form').on('submit', function(e) {
                    e.preventDefault();
                    console.log('Form submitted');
                    
                    // Update the exclude_ids hidden field with the excluded pages
                    const excludedIds = [];
                    jQuery('.excluded-page').each(function() {
                        excludedIds.push(jQuery(this).data('id'));
                    });
                    jQuery('#exclude_ids').val(excludedIds.join(','));
                    
                    // Get the shortcode name
                    const shortcodeName = jQuery('#shortcode_name').val();
                    console.log('Shortcode name:', shortcodeName);
                    
                    if (!shortcodeName) {
                        alert('Please enter a shortcode name');
                        return;
                    }
                    
                    // Show loading message on the submit button
                    const submitButton = jQuery('#submit_button');
                    const originalText = submitButton.text();
                    submitButton.text('Saving...').prop('disabled', true);
                    
                    // Create form data object
                    const formData = jQuery(this).serializeArray();
                    const formDataObj = {};
                    
                    // Convert form data to object
                    jQuery.each(formData, function(i, field) {
                        formDataObj[field.name] = field.value;
                    });
                    
                    // Generate the shortcode
                    let shortcode = '[sitemap_page';
                    
                    // Get selected folders
                    const selectedFolders = [];
                    jQuery('input[name="folder[]"]:checked').each(function() {
                        selectedFolders.push(jQuery(this).val());
                    });
                    
                    if (selectedFolders.length === 0) {
                        alert('Please select at least one folder');
                        submitButton.text(originalText).prop('disabled', false);
                        return;
                    }
                    
                    if (selectedFolders.length === 1) {
                        shortcode += ' folder_slug="' + selectedFolders[0] + '"';
                    } else {
                        shortcode += ' folder_slugs="' + selectedFolders.join(',') + '"';
                    }
                    
                    // Add display type
                    const displayType = jQuery('#display_type').val();
                    shortcode += ' display_type="' + displayType + '"';
                    
                    // Add columns if display type is columns
                    if (displayType === 'columns') {
                        shortcode += ' columns="' + jQuery('#columns').val() + '"';
                    }
                    
                    // Add inline options if display type is inline
                    if (displayType === 'inline') {
                        shortcode += ' use_divider="' + jQuery('#use_divider').val() + '"';
                        if (jQuery('#use_divider').val() === 'yes') {
                            shortcode += ' divider="' + jQuery('#divider').val() + '"';
                        }
                    }
                    
                    // Add order
                    shortcode += ' order="' + jQuery('#order').val() + '"';
                    
                    // Add item margin if provided
                    const itemMargin = jQuery('#item_margin').val();
                    if (itemMargin) {
                        shortcode += ' item_margin="' + itemMargin + '"';
                    }
                    
                    // Add icon if provided
                    const icon = jQuery('#icon').val();
                    if (icon) {
                        shortcode += ' icon="' + icon + '"';
                    }
                    
                    // Add icon color if provided
                    const iconColor = jQuery('#icon_color').val();
                    if (iconColor) {
                        shortcode += ' icon_color="' + iconColor + '"';
                    }
                    
                    // Add debug mode
                    shortcode += ' debug="' + jQuery('#debug').val() + '"';
                    
                    // Add excluded pages if any
                    const excludeIds = jQuery('#exclude_ids').val();
                    if (excludeIds) {
                        shortcode += ' exclude_ids="' + excludeIds + '"';
                    }
                    
                    // Close the shortcode
                    shortcode += ']';
                    
                    console.log('Generated shortcode:', shortcode);
                    
                    // Add required fields
                    formDataObj.action = 'aqm_sitemaps_save_shortcode';
                    formDataObj.nonce = '<?php echo wp_create_nonce('aqm_sitemaps_nonce'); ?>';
                    formDataObj.name = shortcodeName; // Explicitly set the name field
                    formDataObj.shortcode = shortcode; // Add the generated shortcode
                    
                    console.log('Sending form data:', formDataObj);
                    
                    // Log the form data for debugging
                    console.log('Form data to be submitted:', formDataObj);
                    
                    // Create a form and submit it directly instead of using AJAX
                    // This helps avoid session issues that can occur with AJAX
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = '<?php echo admin_url('admin-post.php'); ?>';
                    
                    // Add action field
                    const actionInput = document.createElement('input');
                    actionInput.type = 'hidden';
                    actionInput.name = 'action';
                    actionInput.value = 'aqm_sitemaps_save_shortcode';
                    form.appendChild(actionInput);
                    
                    // Add all form fields
                    for (const key in formDataObj) {
                        if (formDataObj.hasOwnProperty(key)) {
                            const input = document.createElement('input');
                            input.type = 'hidden';
                            input.name = key;
                            input.value = formDataObj[key];
                            form.appendChild(input);
                        }
                    }
                    
                    // Add a redirect field
                    const redirectInput = document.createElement('input');
                    redirectInput.type = 'hidden';
                    redirectInput.name = 'redirect';
                    redirectInput.value = '<?php echo admin_url('admin.php?page=aqm-sitemaps'); ?>';
                    form.appendChild(redirectInput);
                    
                    // Append the form to the body and submit it
                    document.body.appendChild(form);
                    form.submit();
                });
                
                // Log display type on page load and after a delay
                console.log('Display type:', jQuery('#display_type').val());
                setTimeout(function() {
                    console.log('Display type:', jQuery('#display_type').val());
                }, 500);
            });
            
            // Copy shortcode function
            function copyShortcode(button, shortcode) {
                console.log('Copy button clicked for:', shortcode);
                
                // Use modern clipboard API if available, fallback to execCommand
                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(shortcode)
                        .then(function() {
                            button.textContent = 'Copied!';
                            setTimeout(function() {
                                button.textContent = 'Copy';
                            }, 2000);
                        })
                        .catch(function(err) {
                            console.error('Failed to copy with Clipboard API:', err);
                            fallbackCopyToClipboard(button, shortcode);
                        });
                } else {
                    fallbackCopyToClipboard(button, shortcode);
                }
            }
            
            // Fallback copy function
            function fallbackCopyToClipboard(button, text) {
                const textarea = document.createElement('textarea');
                textarea.value = text;
                document.body.appendChild(textarea);
                textarea.select();
                
                try {
                    const successful = document.execCommand('copy');
                    button.textContent = successful ? 'Copied!' : 'Failed to copy';
                } catch (err) {
                    console.error('Failed to copy with execCommand:', err);
                    button.textContent = 'Failed to copy';
                }
                
                document.body.removeChild(textarea);
                
                setTimeout(function() {
                    button.textContent = 'Copy';
                }, 2000);
            }
            
            // Edit shortcode function
            function editShortcode(button, name, shortcode) {
                console.log('Edit button clicked for:', name, shortcode);
                
                // Parse the shortcode attributes
                const attributes = {};
                try {
                    // Extract everything between [ and ]
                    const shortcodeContent = shortcode.match(/\[(.*?)\]/)[1];
                    // Remove the shortcode name
                    const attributesString = shortcodeContent.replace(/^sitemap_page\s+/, '');
                    
                    // Extract all attributes with a regex
                    const attrRegex = /([\w_]+)=["']([^"']*)["']/g;
                    let match;
                    while ((match = attrRegex.exec(attributesString)) !== null) {
                        const key = match[1];
                        const value = match[2];
                        attributes[key] = value;
                    }
                } catch (error) {
                    console.error('Error parsing shortcode:', error);
                    alert('Error parsing shortcode. Please try again.');
                    return;
                }
                
                // Set edit mode
                document.getElementById('edit_mode').value = '1';
                document.getElementById('original_name').value = name;
                document.getElementById('shortcode_name').value = name;
                
                // Clear existing excluded pages
                document.getElementById('excluded_pages_list').innerHTML = '';
                
                // Uncheck all folder checkboxes first
                document.querySelectorAll('.folder-checklist input[type="checkbox"]').forEach(function(checkbox) {
                    checkbox.checked = false;
                });
                
                // Check the appropriate folder checkboxes
                let folderSlugs = [];
                if (attributes.folder_slugs) {
                    folderSlugs = attributes.folder_slugs.split(',');
                } else if (attributes.folder_slug) {
                    folderSlugs = [attributes.folder_slug];
                }
                
                folderSlugs.forEach(function(slug) {
                    const checkbox = document.getElementById('folder_' + slug);
                    if (checkbox) checkbox.checked = true;
                });
                
                // Set form fields
                if (attributes.display_type) document.getElementById('display_type').value = attributes.display_type;
                if (attributes.columns) document.getElementById('columns').value = attributes.columns;
                if (attributes.order) document.getElementById('order').value = attributes.order;
                if (attributes.item_margin) document.getElementById('item_margin').value = attributes.item_margin;
                if (attributes.icon) document.getElementById('icon').value = attributes.icon;
                if (attributes.icon_color) document.getElementById('icon_color').value = attributes.icon_color;
                if (attributes.debug) document.getElementById('debug').value = attributes.debug;
                if (attributes.use_divider) document.getElementById('use_divider').value = attributes.use_divider;
                if (attributes.divider) document.getElementById('divider').value = attributes.divider;
                
                // Update display based on display type
                const displayType = document.getElementById('display_type').value;
                if (displayType === 'inline') {
                    document.querySelectorAll('.columns-option').forEach(function(el) { el.style.display = 'none'; });
                    document.querySelectorAll('.inline-options').forEach(function(el) { el.style.display = 'block'; });
                    
                    // Show/hide divider options
                    const useDivider = document.getElementById('use_divider').value;
                    if (useDivider === 'yes') {
                        document.querySelectorAll('.divider-options').forEach(function(el) { el.style.display = 'block'; });
                    } else {
                        document.querySelectorAll('.divider-options').forEach(function(el) { el.style.display = 'none'; });
                    }
                } else {
                    document.querySelectorAll('.columns-option').forEach(function(el) { el.style.display = 'block'; });
                    document.querySelectorAll('.inline-options').forEach(function(el) { el.style.display = 'none'; });
                    document.querySelectorAll('.divider-options').forEach(function(el) { el.style.display = 'none'; });
                }
                
                // Scroll to form
                document.getElementById('aqm-sitemap-form').scrollIntoView({ behavior: 'smooth', block: 'start' });
                
                // Update submit button text
                document.getElementById('submit_button').textContent = 'Save Changes';
            }
            
            // Delete shortcode function
            function deleteShortcode(button, name) {
                console.log('Delete button clicked for:', name);
                
                if (!confirm('Are you sure you want to delete this shortcode?')) {
                    return;
                }
                
                // Create and submit a form to delete the shortcode
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '<?php echo admin_url('admin-ajax.php'); ?>';
                form.style.display = 'none';
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'aqm_delete_shortcode';
                form.appendChild(actionInput);
                
                const nonceInput = document.createElement('input');
                nonceInput.type = 'hidden';
                nonceInput.name = 'nonce';
                nonceInput.value = '<?php echo wp_create_nonce('aqm_sitemaps_nonce'); ?>';
                form.appendChild(nonceInput);
                
                const nameInput = document.createElement('input');
                nameInput.type = 'hidden';
                nameInput.name = 'name';
                nameInput.value = name;
                form.appendChild(nameInput);
                
                document.body.appendChild(form);
                form.submit();
            }
        </script>
        

        
        <div class="aqm-main-content">
            <div class="aqm-left-column">
                <div class="aqm-sitemap-generator">
                    <form id="aqm-sitemap-form">
                    <script type="text/javascript">
                    // Inline script to ensure options are displayed properly
                    jQuery(document).ready(function($) {
                        console.log('Inline script loaded');
                        
                        // Function to toggle visibility based on display type
                        function updateDisplayOptions() {
                            var displayType = $('#display_type').val();
                            console.log('Display type:', displayType);
                            
                            if (displayType === 'inline') {
                                $('.columns-option').hide();
                                $('.inline-options').show();
                                
                                // Check use_divider value
                                var useDivider = $('#use_divider').val();
                                if (useDivider === 'yes') {
                                    $('.divider-options').show();
                                } else {
                                    $('.divider-options').hide();
                                }
                            } else {
                                $('.columns-option').show();
                                $('.inline-options').hide();
                                $('.divider-options').hide();
                            }
                        }
                        
                        // Set up event handlers
                        $('#display_type').on('change', updateDisplayOptions);
                        $('#use_divider').on('change', updateDisplayOptions);
                        
                        // Run on page load
                        updateDisplayOptions();
                        
                        // Also run after a short delay
                        setTimeout(updateDisplayOptions, 500);
                    });
                    </script>
                        <input type="hidden" id="edit_mode" name="edit_mode" value="0">
                        <input type="hidden" id="original_name" name="original_name" value="">
                        
                        <div class="form-grid">
                            <!-- Content Settings Section -->
                            <div class="form-section content-settings">
                                <h3>Content Settings</h3>
                                <div class="form-group">
                                    <label>Select Folders:</label>
                                    <div class="folder-checklist">
                                        <?php foreach ($folders as $folder): ?>
                                            <?php 
                                            $folder_name = esc_html($folder->name);
                                            $folder_name = str_replace('-', ' ', $folder_name);
                                            $folder_name = ucwords($folder_name);
                                            ?>
                                            <div class="folder-checkbox-item">
                                                <input type="checkbox" 
                                                       id="folder_<?php echo esc_attr($folder->slug); ?>" 
                                                       name="folder[]" 
                                                       value="<?php echo esc_attr($folder->slug); ?>">
                                                <label for="folder_<?php echo esc_attr($folder->slug); ?>"><?php echo $folder_name; ?></label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
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
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="display_type">Display Type:</label>
                                    <select id="display_type" name="display_type">
                                        <option value="columns">Display in Columns</option>
                                        <option value="inline">Display Inline with Separators</option>
                                    </select>
                                </div>
                                
                                <!-- Inline Options Section -->
                                <div class="form-group inline-options" style="display: none;">
                                    <label for="use_divider">Use Divider:</label>
                                    <select id="use_divider" name="use_divider">
                                        <option value="yes">Yes</option>
                                        <option value="no">No</option>
                                    </select>
                                    <div class="form-help">Choose whether to show dividers between items in inline mode</div>
                                </div>
                                
                                <div class="form-group divider-options" style="display: none;">
                                    <label for="divider">Divider Character:</label>
                                    <input type="text" id="divider" name="divider" value="|" maxlength="5">
                                    <div class="form-help">Character(s) to use as divider between items in inline mode</div>
                                </div>
                                
                                <!-- Debug Mode Option -->
                                <div class="form-group">
                                    <label for="debug">Enable Debug Mode:</label>
                                    <select id="debug" name="debug">
                                        <option value="no">No</option>
                                        <option value="yes">Yes</option>
                                    </select>
                                    <div class="form-help">Show debugging information when shortcode is displayed</div>
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
                                    <input type="text" id="item_margin" name="item_margin" placeholder="e.g., 10px, 0.5em, etc.">
                                    <div class="form-help">Set the bottom margin for each list item (e.g., 10px, 0.5em)</div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="icon">Font Awesome Icon:</label>
                                    <input type="text" id="icon" name="icon" placeholder="e.g., fa fa-arrow-right">
                                    <div class="form-help">Add a Font Awesome icon before each list item (e.g., fa fa-arrow-right)</div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="icon_color">Icon Color:</label>
                                    <input type="text" id="icon_color" name="icon_color" placeholder="e.g., #ff0000 or red">
                                    <div class="form-help">Set the color of the icon (hex code or color name)</div>
                                </div>
                                
                                <div class="form-group page-exclusions">
                                    <label for="exclude_ids">Exclude Pages:</label>
                                    <div class="excluded-pages-container">
                                        <select id="page_to_exclude" class="page-selector">
                                            <option value="">Select a page to exclude</option>
                                            <?php 
                                            $all_pages = get_pages();
                                            foreach ($all_pages as $page): ?>
                                                <option value="<?php echo esc_attr($page->ID); ?>"><?php echo esc_html($page->post_title); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="button" id="add_excluded_page" class="button">Add</button>
                                    </div>
                                    <div id="excluded_pages_list" class="excluded-pages-list"></div>
                                    <input type="hidden" id="exclude_ids" name="exclude_ids" value="">
                                    <div class="form-help">Select pages to exclude from the sitemap</div>
                                </div>
                            </div>

                            <div class="form-footer">
                                <button type="submit" id="submit_button" class="button button-primary">Generate Shortcode</button>
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
                                    <button class="button edit-shortcode" data-name="<?php echo esc_attr($name); ?>" data-shortcode="<?php echo esc_attr(wp_unslash($shortcode)); ?>">Edit</button>
                                    <button class="button delete-shortcode" data-name="<?php echo esc_attr($name); ?>">Delete</button>
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
            error_log('Added ' . $param . ' parameter to shortcode: ' . $shortcode);
        } else {
            // Parameter exists but might have an empty value, update it if value is provided
            if (!empty($value)) {
                // Use regex to replace the existing parameter value
                $pattern = '/(' . $param . '=)["\']([^"\']*)["\']/i';
                $replacement = '$1"' . $value . '"';
                $shortcode = preg_replace($pattern, $replacement, $shortcode);
                error_log('Updated ' . $param . ' parameter in shortcode: ' . $shortcode);
            }
        }
    }
    
    return $shortcode;
}

// Save shortcode
function aqm_save_shortcode() {
    // Enable error reporting for debugging
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    
    // Log the raw POST data
    error_log('AQM Sitemaps Raw POST: ' . print_r($_POST, true));
    
    // Debug the icon and icon_color parameters
    if (isset($_POST['debug_icon'])) {
        error_log('Icon from debug: ' . $_POST['debug_icon']);
    }
    
    if (isset($_POST['debug_icon_color'])) {
        error_log('Icon color from debug: ' . $_POST['debug_icon_color']);
    }

    // Check if this is an AJAX request and log the request method
    if (!wp_doing_ajax()) {
        error_log('AQM Sitemaps: Not an AJAX request. Request method: ' . $_SERVER['REQUEST_METHOD']);
        die('Invalid request method');
    }
    
    // Log the user's authentication status
    error_log('User logged in: ' . (is_user_logged_in() ? 'Yes' : 'No'));
    error_log('User ID: ' . get_current_user_id());
    error_log('User can manage options: ' . (current_user_can('manage_options') ? 'Yes' : 'No'));

    // Verify nonce first
    if (!isset($_POST['nonce'])) {
        error_log('AQM Sitemaps: Nonce is missing');
        wp_send_json_error('Security token is missing');
        wp_die();
    }

    if (!wp_verify_nonce($_POST['nonce'], 'aqm_sitemaps_nonce')) {
        error_log('AQM Sitemaps: Invalid nonce');
        wp_send_json_error('Invalid security token');
        wp_die();
    }

    // Check user capabilities
    if (!current_user_can('manage_options')) {
        error_log('AQM Sitemaps: Insufficient permissions');
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
    error_log('AQM Sitemaps: Icon value: ' . $icon);
    error_log('AQM Sitemaps: Icon color value: ' . $icon_color);
    
    // Log the sanitized data
    error_log('AQM Sitemaps: Sanitized data - ' . print_r([
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

        // Get form field values for icon, icon_color, and item_margin
        $icon_value = isset($_POST['icon']) ? sanitize_text_field($_POST['icon']) : '';
        $icon_color_value = isset($_POST['icon_color']) ? sanitize_text_field($_POST['icon_color']) : '';
        $item_margin_value = isset($_POST['item_margin']) ? sanitize_text_field($_POST['item_margin']) : '10px';
        
        // Get values for the new parameters
        $debug_value = isset($_POST['debug']) ? sanitize_text_field($_POST['debug']) : 'no';
        $use_divider_value = isset($_POST['use_divider']) ? sanitize_text_field($_POST['use_divider']) : 'yes';
        $divider_value = isset($_POST['divider']) ? sanitize_text_field($_POST['divider']) : '|';
        
        // If item_margin is empty, set default value
        if (empty($item_margin_value)) {
            $item_margin_value = '10px';
        }
        
        // If divider is empty, set default value
        if (empty($divider_value)) {
            $divider_value = '|';
        }
        
        // Log the values we're going to use
        error_log('Using values for shortcode parameters:');
        error_log('icon: ' . $icon_value);
        error_log('icon_color: ' . $icon_color_value);
        error_log('item_margin: ' . $item_margin_value);
        error_log('debug: ' . $debug_value);
        error_log('use_divider: ' . $use_divider_value);
        error_log('divider: ' . $divider_value);
        
        // Use our helper function to ensure parameters are included
        $shortcode = aqm_ensure_shortcode_params($shortcode, array(
            'icon' => $icon_value,
            'icon_color' => $icon_color_value,
            'item_margin' => $item_margin_value,
            'debug' => $debug_value,
            'use_divider' => $use_divider_value,
            'divider' => $divider_value
        ));
        
        error_log('Final shortcode after ensuring parameters: ' . $shortcode);
        
        // Save the shortcode
        $saved_shortcodes[$name] = $shortcode;

        // Update option with error checking
        $update_result = update_option('aqm_sitemaps_shortcodes', $saved_shortcodes);
        
        if ($update_result) {
            error_log('AQM Sitemaps: Shortcode saved successfully - ' . $name);
            wp_send_json_success(array(
                'message' => 'Shortcode saved successfully',
                'name' => $name
            ));
        } else {
            error_log('AQM Sitemaps: Failed to update option');
            wp_send_json_error('Failed to save shortcode');
        }
    } catch (Exception $e) {
        error_log('AQM Sitemaps Exception: ' . $e->getMessage());
        wp_send_json_error('An unexpected error occurred: ' . $e->getMessage());
    }

    // Ensure we always die at the end
    wp_die();
}

// Remove any existing action to prevent duplicates
remove_action('wp_ajax_save_sitemaps_shortcode', 'aqm_save_shortcode');
remove_action('wp_ajax_nopriv_save_sitemaps_shortcode', 'aqm_save_shortcode');
remove_action('wp_ajax_aqm_sitemaps_save_shortcode', 'aqm_save_shortcode');
remove_action('admin_post_aqm_sitemaps_save_shortcode', 'aqm_save_shortcode_post');

// Add the action for the AJAX handler
add_action('wp_ajax_aqm_sitemaps_save_shortcode', 'aqm_save_shortcode');

// Add the action for the admin-post.php handler
add_action('admin_post_aqm_sitemaps_save_shortcode', 'aqm_save_shortcode_post');

/**
 * Handle shortcode saving through admin-post.php
 * This avoids AJAX-related session issues
 */
function aqm_save_shortcode_post() {
    // Enable error reporting for debugging
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    
    // Log the raw POST data
    error_log('AQM Sitemaps admin-post Raw POST: ' . print_r($_POST, true));
    
    // Check user capabilities
    if (!current_user_can('manage_options')) {
        wp_die('You do not have permission to perform this action');
    }
    
    // Get and validate required data
    $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
    $shortcode = isset($_POST['shortcode']) ? sanitize_text_field($_POST['shortcode']) : '';
    $edit_mode = isset($_POST['edit_mode']) ? $_POST['edit_mode'] === '1' : false;
    $original_name = isset($_POST['original_name']) ? sanitize_text_field($_POST['original_name']) : '';
    
    // Get redirect URL
    $redirect_url = isset($_POST['redirect']) ? $_POST['redirect'] : admin_url('admin.php?page=aqm-sitemaps');
    
    // Get icon and icon_color values
    $icon = isset($_POST['icon']) ? sanitize_text_field($_POST['icon']) : '';
    $icon_color = isset($_POST['icon_color']) ? sanitize_text_field($_POST['icon_color']) : '';
    
    // Validate required fields
    if (empty($name)) {
        wp_die('Shortcode name is required', 'Error', array('back_link' => true));
    }

    if (empty($shortcode)) {
        wp_die('Shortcode content is required', 'Error', array('back_link' => true));
    }

    try {
        // Get existing shortcodes with error checking
        $saved_shortcodes = get_option('aqm_sitemaps_shortcodes', array());
        if (!is_array($saved_shortcodes)) {
            $saved_shortcodes = array();
        }

        // Check for duplicates in create mode
        if (!$edit_mode && isset($saved_shortcodes[$name])) {
            wp_die('A shortcode with this name already exists', 'Error', array('back_link' => true));
        }

        // Handle edit mode name changes
        if ($edit_mode && $name !== $original_name && isset($saved_shortcodes[$name])) {
            wp_die('Cannot rename: a shortcode with this name already exists', 'Error', array('back_link' => true));
        }

        // Remove old shortcode in edit mode
        if ($edit_mode && $name !== $original_name && isset($saved_shortcodes[$original_name])) {
            unset($saved_shortcodes[$original_name]);
        }

        // Get form field values for icon, icon_color, and item_margin
        $icon_value = isset($_POST['icon']) ? sanitize_text_field($_POST['icon']) : '';
        $icon_color_value = isset($_POST['icon_color']) ? sanitize_text_field($_POST['icon_color']) : '';
        $item_margin_value = isset($_POST['item_margin']) ? sanitize_text_field($_POST['item_margin']) : '10px';
        
        // Get values for the new parameters
        $debug_value = isset($_POST['debug']) ? sanitize_text_field($_POST['debug']) : 'no';
        $use_divider_value = isset($_POST['use_divider']) ? sanitize_text_field($_POST['use_divider']) : 'yes';
        $divider_value = isset($_POST['divider']) ? sanitize_text_field($_POST['divider']) : '|';
        
        // If item_margin is empty, set default value
        if (empty($item_margin_value)) {
            $item_margin_value = '10px';
        }
        
        // If divider is empty, set default value
        if (empty($divider_value)) {
            $divider_value = '|';
        }
        
        // Use our helper function to ensure parameters are included
        $shortcode = aqm_ensure_shortcode_params($shortcode, array(
            'icon' => $icon_value,
            'icon_color' => $icon_color_value,
            'item_margin' => $item_margin_value,
            'debug' => $debug_value,
            'use_divider' => $use_divider_value,
            'divider' => $divider_value
        ));
        
        // Save the shortcode
        $saved_shortcodes[$name] = $shortcode;

        // Update option
        $update_result = update_option('aqm_sitemaps_shortcodes', $saved_shortcodes);
        
        if ($update_result) {
            // Add success parameter to the redirect URL
            $redirect_url = add_query_arg('shortcode_saved', '1', $redirect_url);
        } else {
            // Add error parameter to the redirect URL
            $redirect_url = add_query_arg('shortcode_error', '1', $redirect_url);
        }
    } catch (Exception $e) {
        error_log('AQM Sitemaps Exception: ' . $e->getMessage());
        $redirect_url = add_query_arg('shortcode_error', '1', $redirect_url);
    }

    // Redirect back to the admin page
    wp_redirect($redirect_url);
    exit;
}

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
add_action('admin_post_aqm_delete_shortcode', 'aqm_delete_shortcode_post');

/**
 * Handle delete shortcode request from admin-post.php
 */
function aqm_delete_shortcode_post() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'aqm_sitemaps_nonce')) {
        wp_die('Security check failed');
    }
    
    // Get the shortcode name
    $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
    if (empty($name)) {
        wp_die('No shortcode name provided');
    }
    
    // Get saved shortcodes
    $saved_shortcodes = get_option('aqm_sitemaps_shortcodes', array());
    
    // Remove the shortcode
    if (isset($saved_shortcodes[$name])) {
        unset($saved_shortcodes[$name]);
        update_option('aqm_sitemaps_shortcodes', $saved_shortcodes);
    }
    
    // Check if we should redirect back
    if (isset($_POST['redirect']) && $_POST['redirect']) {
        // Just output success message for the iframe
        echo '<html><body><script>window.parent.console.log("Delete successful");</script></body></html>';
        exit;
    } else {
        // Redirect back to the settings page
        wp_redirect(admin_url('admin.php?page=aqm-sitemaps&deleted=1'));
        exit;
    }
}



// The actual shortcode function
function display_enhanced_page_sitemap($atts) {
    global $wpdb;
    
    // Fix for Divi and other page builders that pass WP_Term objects
    // Store the original term value before it gets processed by shortcode_atts
    $original_term = isset($atts['term']) ? $atts['term'] : '';
    
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
        'display_type' => 'columns',
        'columns' => '2',
        'order' => 'menu_order',
        'exclude_ids' => '', // Parameter to exclude pages by ID
        'show_all' => 'no', // New parameter to show all pages
        'item_margin' => '10px', // New parameter for item bottom margin
        'icon' => '', // New parameter for Font Awesome icon
        'icon_color' => '', // New parameter for icon color
        'disable_links' => 'no', // New parameter to disable links and show only titles
        'post_type' => 'page', // New parameter for custom post type support
        'taxonomy' => '', // New parameter for custom taxonomy support
        'term' => '', // New parameter for taxonomy term
        'debug' => 'no', // New parameter to enable/disable debug mode
        'divider' => '|', // New parameter for custom divider character in inline mode
        'use_divider' => 'yes' // New parameter to enable/disable divider in inline mode
    ), $atts, 'sitemap_page');
    
    // Term object handling is now done at the beginning of the function

    // Sanitize attributes
    $folder_slug = sanitize_text_field($atts['folder_slug']);
    $folder_slugs = sanitize_text_field($atts['folder_slugs']);
    $display_type = in_array($atts['display_type'], array('columns', 'inline')) ? $atts['display_type'] : 'columns';
    $columns = intval($atts['columns']);
    $columns = $columns > 0 && $columns <= 6 ? $columns : 2;
    $order = in_array($atts['order'], array('menu_order', 'title', 'date')) ? $atts['order'] : 'menu_order';
    $show_all = in_array(strtolower($atts['show_all']), array('yes', 'true', '1')) ? true : false;
    $item_margin = sanitize_text_field($atts['item_margin']);
    $icon = sanitize_text_field($atts['icon']);
    $icon_color = sanitize_hex_color($atts['icon_color']) ?: sanitize_text_field($atts['icon_color']);
    $disable_links = in_array(strtolower($atts['disable_links']), array('yes', 'true', '1')) ? true : false;
    $post_type = sanitize_text_field($atts['post_type']);
    $taxonomy = sanitize_text_field($atts['taxonomy']);
    $term = sanitize_text_field($atts['term']);
    $show_debug = in_array(strtolower($atts['debug']), array('yes', 'true', '1')) ? true : false;
    $divider = sanitize_text_field($atts['divider']);
    $use_divider = in_array(strtolower($atts['use_divider']), array('yes', 'true', '1')) ? true : false;
    
    // Debug information about post types and taxonomies
    $debug = '';
    if ($show_debug) {
        $debug .= '<div style="background:#f5f5f5;border:1px solid #ccc;padding:10px;margin-bottom:20px;font-family:monospace;">';
        $debug .= '<p><strong>Post Type & Taxonomy Check:</strong></p>';
        
        // Check if post type exists
        $post_type_exists = post_type_exists($post_type);
        $debug .= '<p>Post Type: ' . esc_html($post_type) . ' - ' . ($post_type_exists ? 'EXISTS' : 'DOES NOT EXIST') . '</p>';
        
        // List all registered post types
        $post_types = get_post_types(array('public' => true), 'names');
        $debug .= '<p><strong>Available Post Types:</strong></p><ul>';
        foreach ($post_types as $pt) {
            $debug .= '<li>' . esc_html($pt) . '</li>';
        }
        $debug .= '</ul>';
        
        // Check if taxonomy exists
        if (!empty($taxonomy)) {
            $taxonomy_exists = taxonomy_exists($taxonomy);
            $debug .= '<p>Taxonomy: ' . esc_html($taxonomy) . ' - ' . ($taxonomy_exists ? 'EXISTS' : 'DOES NOT EXIST') . '</p>';
            
            // List all registered taxonomies
            $taxonomies = get_taxonomies(array('public' => true), 'names');
            $debug .= '<p><strong>Available Taxonomies:</strong></p><ul>';
            foreach ($taxonomies as $tax) {
                $debug .= '<li>' . esc_html($tax) . '</li>';
            }
            $debug .= '</ul>';
        }
        
        $debug .= '</div>';
    }
    
    // Ensure item_margin is not empty
    if (empty($item_margin)) {
        $item_margin = '10px';
    }
    
    // Process exclude IDs
    $exclude_ids = array();
    if (!empty($atts['exclude_ids'])) {
        $exclude_ids_raw = trim($atts['exclude_ids']);
        if (!empty($exclude_ids_raw)) {
            $exclude_ids = array_map('intval', explode(',', $exclude_ids_raw));
            $exclude_ids = array_filter($exclude_ids); // Remove any zero/invalid IDs
        }
    }
    
    // Debug information
    $debug = '';
    if ($show_debug) {
        $debug .= '<div style="background:#f5f5f5;border:1px solid #ccc;padding:10px;margin-bottom:20px;font-family:monospace;">';
        $debug .= '<p><strong>Debug Info:</strong></p>';
        $debug .= '<p>Shortcode used: ' . current_filter() . '</p>';
        $debug .= '<p>Post Type: ' . esc_html($post_type) . '</p>';
        if (!empty($taxonomy)) {
            $debug .= '<p>Taxonomy: ' . esc_html($taxonomy) . '</p>';
            $debug .= '<p>Term: ' . esc_html($term) . '</p>';
        }
        $debug .= '<p>Show All Items: ' . ($show_all ? 'Yes' : 'No') . '</p>';
        $debug .= '<p>Disable Links: ' . ($disable_links ? 'Yes' : 'No') . '</p>';
        if (!$show_all && empty($taxonomy) && ($post_type === 'page')) {
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
    
    // Validate post type exists
    if (!post_type_exists($post_type)) {
        if ($show_debug) {
            return $debug . '<p>Error: The post type "' . esc_html($post_type) . '" does not exist. Please check your shortcode parameters.</p>';
        }
        return '<p>No items found. Invalid post type.</p>';
    }
    
    // If taxonomy is specified, validate it exists
    if (!empty($taxonomy) && !taxonomy_exists($taxonomy)) {
        if ($show_debug) {
            return $debug . '<p>Error: The taxonomy "' . esc_html($taxonomy) . '" does not exist. Please check your shortcode parameters.</p>';
        }
        return '<p>No items found. Invalid taxonomy.</p>';
    }
    
    // If show_all is true, get all published items of the specified post type
    if ($show_all) {
        $all_items_query = "SELECT ID FROM {$wpdb->posts} WHERE post_type = '{$post_type}' AND post_status = 'publish'";
        
        // Add exclude IDs if any
        if (!empty($exclude_ids)) {
            $exclude_ids_str = implode(',', array_map('intval', $exclude_ids));
            $all_items_query .= " AND ID NOT IN ({$exclude_ids_str})";
        }
        
        // Add ordering
        if ($order === 'title') {
            $all_items_query .= " ORDER BY post_title ASC";
        } elseif ($order === 'date') {
            $all_items_query .= " ORDER BY post_date DESC";
        } else {
            $all_items_query .= " ORDER BY menu_order ASC, post_title ASC";
        }
        
        // Get the final list of item IDs
        $page_ids = $wpdb->get_col($all_items_query);
        
        if ($show_debug) {
            $debug .= '<div style="background:#f5f5f5;border:1px solid #ccc;padding:10px;margin-bottom:20px;font-family:monospace;">';
            $debug .= '<p><strong>Show All Items Mode:</strong> Getting all published ' . esc_html($post_type) . ' items except excluded IDs</p>';
            $debug .= '</div>';
        }
    } else {
        // Check if we're using taxonomy/term or folder
        if (!empty($taxonomy) && (!empty($term) || !empty($original_term))) {
            // Using custom taxonomy and term
            $term_obj = null;
            
            // First check if original_term is a WP_Term object (from Divi)
            if (is_object($original_term) && is_a($original_term, 'WP_Term')) {
                // Debug info
                if ($show_debug) {
                    $debug .= '<div style="background:#f5f5f5;border:1px solid #ccc;padding:10px;margin-bottom:20px;font-family:monospace;">';
                    $debug .= '<p><strong>Using Original Term Object:</strong></p>';
                    $debug .= '<p>Term Name: ' . esc_html($original_term->name) . '</p>';
                    $debug .= '<p>Term Slug: ' . esc_html($original_term->slug) . '</p>';
                    $debug .= '<p>Term Taxonomy: ' . esc_html($original_term->taxonomy) . '</p>';
                    $debug .= '</div>';
                }
                
                $term_obj = $original_term;
                
                // Check if the taxonomy matches what we expect
                if ($term_obj->taxonomy !== $taxonomy) {
                    if ($show_debug) {
                        $debug .= '<div style="background:#f5f5f5;border:1px solid #ccc;padding:10px;margin-bottom:20px;font-family:monospace;">';
                        $debug .= '<p><strong>Warning: Taxonomy Mismatch</strong></p>';
                        $debug .= '<p>Term object taxonomy: ' . esc_html($term_obj->taxonomy) . '</p>';
                        $debug .= '<p>Expected taxonomy: ' . esc_html($taxonomy) . '</p>';
                        $debug .= '</div>';
                    }
                }
            } else {
                // Use the term string to find the term
                $term_string = !empty($term) ? $term : '';
                
                if ($show_debug) {
                    $debug .= '<div style="background:#f5f5f5;border:1px solid #ccc;padding:10px;margin-bottom:20px;font-family:monospace;">';
                    $debug .= '<p><strong>Looking up term:</strong> ' . esc_html($term_string) . '</p>';
                    $debug .= '<p>In taxonomy: ' . esc_html($taxonomy) . '</p>';
                    $debug .= '</div>';
                }
                
                // First try by slug (most specific)
                $term_obj = get_term_by('slug', $term_string, $taxonomy);
                
                // If that fails, try by name
                if (!$term_obj) {
                    $term_obj = get_term_by('name', $term_string, $taxonomy);
                }
                
                // If that fails, try by ID if the term is numeric
                if (!$term_obj && is_numeric($term_string)) {
                    $term_obj = get_term_by('id', intval($term_string), $taxonomy);
                }
            }
            
            // Debug term lookup
            if ($show_debug) {
                $debug .= '<div style="background:#f5f5f5;border:1px solid #ccc;padding:10px;margin-bottom:20px;font-family:monospace;">';
                $debug .= '<p><strong>Term Lookup:</strong></p>';
                $debug .= '<p>Taxonomy: ' . esc_html($taxonomy) . '</p>';
                $debug .= '<p>Term Parameter Type: ' . gettype($term) . '</p>';
                $debug .= '<p>Term Parameter: ' . (is_object($term) ? 'Object of class ' . get_class($term) : esc_html($term)) . '</p>';
                
                // List all terms in this taxonomy for debugging
                $all_terms = get_terms(array('taxonomy' => $taxonomy, 'hide_empty' => false));
                if (!is_wp_error($all_terms) && !empty($all_terms)) {
                    $debug .= '<p><strong>Available Terms in ' . esc_html($taxonomy) . ':</strong></p><ul>';
                    foreach ($all_terms as $t) {
                        $debug .= '<li>ID: ' . $t->term_id . ', Name: ' . $t->name . ', Slug: ' . $t->slug . '</li>';
                    }
                    $debug .= '</ul>';
                } else {
                    $debug .= '<p>No terms found in taxonomy or taxonomy does not exist.</p>';
                }
                
                $debug .= '</div>';
            }
            
            if (!$term_obj) {
                if ($show_debug) {
                    return $debug . '<p>Error: Term "' . esc_html($term) . '" not found in taxonomy "' . esc_html($taxonomy) . '".</p>';
                }
                return '<p>No items found.</p>';
            }
            
            // Additional debug info about the selected taxonomy/term
            if ($show_debug) {
                $debug .= '<div style="background:#f5f5f5;border:1px solid #ccc;padding:10px;margin-bottom:20px;font-family:monospace;">';
                $debug .= '<p><strong>Selected Taxonomy/Term Details:</strong></p>';
                $debug .= '<div style="margin-bottom:10px;padding-bottom:10px;border-bottom:1px solid #ddd;">';
                $debug .= '<p>Taxonomy: ' . esc_html($taxonomy) . '</p>';
                $debug .= '<p>Term Name: ' . esc_html($term_obj->name) . '</p>';
                $debug .= '<p>Term Slug: ' . esc_html($term_obj->slug) . '</p>';
                $debug .= '<p>Term ID: ' . esc_html($term_obj->term_id) . '</p>';
                $debug .= '</div>';
                $debug .= '</div>';
            }
            
            // Get items from the selected term
            if ($show_debug) {
                $debug .= '<div style="background:#f5f5f5;border:1px solid #ccc;padding:10px;margin-bottom:20px;font-family:monospace;">';
                $debug .= '<p><strong>Running WP_Query with:</strong></p>';
                $debug .= '<p>Post Type: ' . esc_html($post_type) . '</p>';
                $debug .= '<p>Taxonomy: ' . esc_html($taxonomy) . '</p>';
                $debug .= '<p>Term ID: ' . esc_html($term_obj->term_id) . '</p>';
                $debug .= '<p>Term Name: ' . esc_html($term_obj->name) . '</p>';
                $debug .= '</div>';
            }
            
            $args = array(
                'post_type' => $post_type,
                'post_status' => 'publish',
                'posts_per_page' => -1,
                'tax_query' => array(
                    array(
                        'taxonomy' => $taxonomy,
                        'field' => 'term_id',
                        'terms' => $term_obj->term_id,
                    ),
                ),
                'fields' => 'ids', // Just get IDs for efficiency
            );
            
            // Add ordering
            if ($order === 'title') {
                $args['orderby'] = 'title';
                $args['order'] = 'ASC';
            } elseif ($order === 'date') {
                $args['orderby'] = 'date';
                $args['order'] = 'DESC';
            } else {
                $args['orderby'] = array('menu_order' => 'ASC', 'title' => 'ASC');
            }
            
            // Add exclude IDs if any
            if (!empty($exclude_ids)) {
                $args['post__not_in'] = $exclude_ids;
            }
            
            // Run the query
            $query = new WP_Query($args);
            $page_ids = $query->posts; // Already IDs because of 'fields' => 'ids'
            
            if ($show_debug) {
                $debug .= '<div style="background:#f5f5f5;border:1px solid #ccc;padding:10px;margin-bottom:20px;font-family:monospace;">';
                $debug .= '<p><strong>WP_Query Results:</strong></p>';
                $debug .= '<p>Found Posts: ' . count($page_ids) . '</p>';
                $debug .= '<p>SQL Query: ' . esc_html($query->request) . '</p>';
                $debug .= '</div>';
            }
            
        } else {
            // Using Premio Folders (original functionality)
            // Check if folder slug or slugs are empty when not showing all
            if (empty($folder_slug) && empty($folder_slugs)) {
                if ($show_debug) {
                    return $debug . '<p>Error: No folder_slug(s) provided in shortcode and show_all is not enabled.</p>';
                }
                return '<p>No pages found.</p>';
            }
            
            $folder_terms = array();
            $all_page_ids = array();
            
            // Handle multiple folder slugs (comma separated)
            if (!empty($folder_slugs)) {
                $slug_array = array_map('trim', explode(',', $folder_slugs));
                
                foreach ($slug_array as $slug) {
                    $term = get_term_by('slug', $slug, 'folder');
                    if ($term) {
                        $folder_terms[] = $term;
                    }
                }
            }
            // Handle single folder slug for backward compatibility
            elseif (!empty($folder_slug)) {
                $term = get_term_by('slug', $folder_slug, 'folder');
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
            
            // Get pages from all selected folders
            foreach ($folder_terms as $folder_term) {
                $folder_page_ids = get_objects_in_term($folder_term->term_id, 'folder');
                $all_page_ids = array_merge($all_page_ids, $folder_page_ids);
            }
            
            // Remove duplicates
            $all_page_ids = array_unique($all_page_ids);
            
            // Filter to only include published pages
            if (!empty($all_page_ids)) {
                $page_ids_str = implode(',', array_map('intval', $all_page_ids));
                $published_query = "SELECT ID FROM {$wpdb->posts} WHERE ID IN ({$page_ids_str}) AND post_type = '{$post_type}' AND post_status = 'publish'";
                
                // Add exclude IDs if any
                if (!empty($exclude_ids)) {
                    $exclude_ids_str = implode(',', array_map('intval', $exclude_ids));
                    $published_query .= " AND ID NOT IN ({$exclude_ids_str})";
                }
                
                // Add ordering
                if ($order === 'title') {
                    $published_query .= " ORDER BY post_title ASC";
                } elseif ($order === 'date') {
                    $published_query .= " ORDER BY post_date DESC";
                } else {
                    $published_query .= " ORDER BY menu_order ASC, post_title ASC";
                }
                
                // Get the final list of page IDs
                $page_ids = $wpdb->get_col($published_query);
            }
        }
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
    $posts = array();
    if (!empty($page_ids)) {
        if ($show_debug) {
            $debug .= '<div style="background:#f5f5f5;border:1px solid #ccc;padding:10px;margin-bottom:20px;font-family:monospace;">';
            $debug .= '<p><strong>Post IDs Found:</strong> ' . implode(', ', $page_ids) . '</p>';
            $debug .= '</div>';
        }
        
        foreach ($page_ids as $page_id) {
            $post = get_post($page_id);
            if ($post && $post->post_status == 'publish') {
                // Check if post type matches or if we're using taxonomy (which already filtered by post type)
                if ($post->post_type == $post_type) {
                    $posts[] = $post;
                    
                    if ($show_debug) {
                        $debug .= '<div style="background:#f5f5f5;border:1px solid #ccc;padding:10px;margin-bottom:20px;font-family:monospace;">';
                        $debug .= '<p><strong>Added Post:</strong> ID: ' . $post->ID . ', Title: ' . $post->post_title . ', Type: ' . $post->post_type . '</p>';
                        $debug .= '</div>';
                    }
                } else if ($show_debug) {
                    $debug .= '<div style="background:#f5f5f5;border:1px solid #ccc;padding:10px;margin-bottom:20px;font-family:monospace;">';
                    $debug .= '<p><strong>Skipped Post (Wrong Type):</strong> ID: ' . $post->ID . ', Title: ' . $post->post_title . ', Type: ' . $post->post_type . ' (Expected: ' . $post_type . ')</p>';
                    $debug .= '</div>';
                }
            } else if ($show_debug) {
                $debug .= '<div style="background:#f5f5f5;border:1px solid #ccc;padding:10px;margin-bottom:20px;font-family:monospace;">';
                $debug .= '<p><strong>Invalid Post:</strong> ID: ' . $page_id . ' (not found or not published)</p>';
                $debug .= '</div>';
            }
        }
    } else if ($show_debug) {
        $debug .= '<div style="background:#f5f5f5;border:1px solid #ccc;padding:10px;margin-bottom:20px;font-family:monospace;">';
        $debug .= '<p><strong>No Post IDs Found</strong> for post_type: ' . $post_type . '</p>';
        if (!empty($taxonomy)) {
            $debug .= '<p>Taxonomy: ' . $taxonomy . ', Term: ' . $term . '</p>';
        }
        $debug .= '</div>';
    }
    
    if (empty($posts)) {
        if ($show_debug) {
            return $debug . '<p>No ' . esc_html($post_type) . ' items found' . ($show_all ? '' : ' in the selected ' . (!empty($taxonomy) ? 'taxonomy term' : 'folder')) . '.</p>';
        }
        return '<p>No items found.</p>';
    }
    
    // Build output
    $output = '';
    if ($show_debug) {
        $output .= $debug;
    }
    
    // Create wrapper with classes
    $classes = array('aqm-sitemap');
    if ($display_type === 'columns') {
        $classes[] = 'columns-' . esc_attr($columns);
    } else {
        $classes[] = 'inline';
    }
    
    $output .= '<div class="' . esc_attr(implode(' ', $classes)) . '">';
    
    // For inline display
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
        
        // Use custom divider if enabled, otherwise no divider
        if ($use_divider) {
            // Ensure divider has spaces around it for better readability
            $divider_with_spaces = ' ' . $divider . ' ';
            $output .= implode($divider_with_spaces, $links);
        } else {
            // No divider, just use a space
            $output .= implode(' ', $links);
        }
    } 
    // For column display
    else {
        // Calculate items per column for balanced distribution
        $total_items = count($posts);
        $items_per_column = ceil($total_items / $columns);
        
        // Create column layout container
        $output .= '<div class="sitemap-columns-container">';
        
        // Distribute pages across columns (top to bottom ordering)
        for ($col = 0; $col < $columns; $col++) {
            // Calculate start index for this column
            $start_idx = $col * $items_per_column;
            
            // Skip if no more pages
            if ($start_idx >= $total_items) continue;
            
            // Column width
            $col_width = (100 / $columns);
            
            // Start column
            $output .= '<div class="sitemap-column">';
            // Start unordered list
            $output .= '<ul>';
            
            // Add items for this column
            for ($i = 0; $i < $items_per_column; $i++) {
                $idx = $start_idx + $i;
                if ($idx < $total_items) {
                    // Prepare icon HTML if an icon is specified
                    $icon_html = '';
                    if (!empty($icon)) {
                        $icon_style = 'margin-right:5px;';
                        
                        // Add color if specified
                        if (!empty($icon_color)) {
                            $icon_style .= 'color:' . esc_attr($icon_color) . ';';
                        }
                        
                        // Use an i tag with fa-solid class prefix
                        // Make sure we have the fa-solid prefix for Font Awesome 6 compatibility
                        $icon_class = $icon;
                        if (strpos($icon, 'fa-solid') === false && strpos($icon, 'fas') === false) {
                            // Only add fa-solid if it doesn't already have a Font Awesome prefix
                            if (strpos($icon, 'fa-') === 0) {
                                $icon_class = 'fa-solid ' . $icon;
                            }
                        }
                        $icon_html = sprintf('<i class="%s" style="%s"></i>', esc_attr($icon_class), $icon_style);
                    }
                    
                    if ($disable_links) {
                        $output .= sprintf(
                            '<li style="margin-bottom:%s;"><span class="aqm-sitemap-item">%s%s</span></li>',
                            esc_attr($item_margin),
                            $icon_html,
                            esc_html($posts[$idx]->post_title)
                        );
                    } else {
                        $output .= sprintf(
                            '<li style="margin-bottom:%s;"><a href="%s">%s%s</a></li>',
                            esc_attr($item_margin),
                            esc_url(get_permalink($posts[$idx]->ID)),
                            $icon_html,
                            esc_html($posts[$idx]->post_title)
                        );
                    }
                }
            }
            
            // End unordered list
            $output .= '</ul>';
            // End column
            $output .= '</div>';
        }
        
        // End column layout
        $output .= '</div>';
    }
    
    // Close main wrapper
    $output .= '</div>';
    
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

// Add inline script to ensure buttons work correctly
add_action('admin_footer', 'aqm_sitemaps_admin_footer_script');

function aqm_sitemaps_admin_footer_script() {
    // Only add on the plugin admin page
    $screen = get_current_screen();
    if (!$screen || $screen->id !== 'settings_page_aqm-sitemaps') {
        return;
    }
    
    ?>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        console.log('AQM Sitemaps: Initializing button handlers');
        
        // Function to copy text to clipboard
        function copyToClipboard(text, button) {
            console.log('Copying to clipboard:', text);
            
            // Store the original button text
            const originalText = button.textContent;
            
            // Use modern clipboard API if available, fallback to execCommand
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text)
                    .then(function() {
                        button.textContent = 'Copied!';
                        setTimeout(function() {
                            button.textContent = originalText;
                        }, 2000);
                    })
                    .catch(function(err) {
                        console.error('Failed to copy with Clipboard API:', err);
                        fallbackCopyMethod(text, button, originalText);
                    });
            } else {
                fallbackCopyMethod(text, button, originalText);
            }
        }
        
        // Fallback copy method using execCommand
        function fallbackCopyMethod(text, button, originalText) {
            const textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.style.position = 'fixed'; // Prevent scrolling to bottom
            document.body.appendChild(textarea);
            textarea.focus();
            textarea.select();
            
            try {
                const successful = document.execCommand('copy');
                button.textContent = successful ? 'Copied!' : 'Failed';
            } catch (err) {
                console.error('Error copying text:', err);
                button.textContent = 'Failed';
            }
            
            document.body.removeChild(textarea);
            
            setTimeout(function() {
                button.textContent = originalText;
            }, 2000);
        }
        
        // Function to edit a shortcode
        function editShortcode(name, shortcode) {
            console.log('Editing shortcode:', name);
            console.log('Shortcode content:', shortcode);
            
            // Set edit mode immediately
            document.getElementById('edit_mode').value = '1';
            document.getElementById('original_name').value = name;
            document.getElementById('shortcode_name').value = name;
            
            // Scroll to form
            document.getElementById('aqm-sitemap-form').scrollIntoView({ behavior: 'smooth', block: 'start' });
            
            // Update submit button text
            document.getElementById('submit_button').textContent = 'Save Changes';
            
            try {
                // Clear existing excluded pages
                document.getElementById('excluded_pages_list').innerHTML = '';
                
                // Uncheck all folder checkboxes first
                document.querySelectorAll('.folder-checklist input[type="checkbox"]').forEach(function(checkbox) {
                    checkbox.checked = false;
                });
                
                // Parse the shortcode using regex patterns
                // Extract folder_slug or folder_slugs
                let folderSlugs = [];
                const folderSlugMatch = shortcode.match(/folder_slug=["']([^"']*)["']/);
                const folderSlugsMatch = shortcode.match(/folder_slugs=["']([^"']*)["']/);
                
                if (folderSlugsMatch && folderSlugsMatch[1]) {
                    folderSlugs = folderSlugsMatch[1].split(',');
                    console.log('Found folder_slugs:', folderSlugs);
                } else if (folderSlugMatch && folderSlugMatch[1]) {
                    folderSlugs = [folderSlugMatch[1]];
                    console.log('Found folder_slug:', folderSlugs);
                }
                
                // Check the appropriate folder checkboxes
                folderSlugs.forEach(function(slug) {
                    const checkbox = document.getElementById('folder_' + slug);
                    if (checkbox) {
                        checkbox.checked = true;
                        console.log('Checked folder:', slug);
                    } else {
                        console.log('Folder checkbox not found for:', slug);
                    }
                });
                
                // Extract other attributes
                const getAttributeValue = function(attrName) {
                    const regex = new RegExp(attrName + '=["\']([^"\']*)["\'']');
                    const match = shortcode.match(regex);
                    return match ? match[1] : null;
                };
                
                // Set display type
                const displayType = getAttributeValue('display_type') || 'columns';
                document.getElementById('display_type').value = displayType;
                console.log('Set display_type:', displayType);
                
                // Set columns
                const columns = getAttributeValue('columns');
                if (columns) {
                    document.getElementById('columns').value = columns;
                    console.log('Set columns:', columns);
                }
                
                // Set order
                const order = getAttributeValue('order') || 'menu_order';
                document.getElementById('order').value = order;
                console.log('Set order:', order);
                
                // Set item margin
                const itemMargin = getAttributeValue('item_margin');
                if (itemMargin) {
                    document.getElementById('item_margin').value = itemMargin;
                    console.log('Set item_margin:', itemMargin);
                }
                
                // Set icon
                const icon = getAttributeValue('icon');
                if (icon) {
                    document.getElementById('icon').value = icon;
                    console.log('Set icon:', icon);
                }
                
                // Set icon color
                const iconColor = getAttributeValue('icon_color');
                if (iconColor) {
                    document.getElementById('icon_color').value = iconColor;
                    console.log('Set icon_color:', iconColor);
                }
                
                // Set debug
                const debug = getAttributeValue('debug') || 'no';
                document.getElementById('debug').value = debug;
                console.log('Set debug:', debug);
                
                // Set use_divider
                const useDivider = getAttributeValue('use_divider') || 'yes';
                document.getElementById('use_divider').value = useDivider;
                console.log('Set use_divider:', useDivider);
                
                // Set divider
                const divider = getAttributeValue('divider') || '|';
                document.getElementById('divider').value = divider;
                console.log('Set divider:', divider);
                
                // Update display based on display type
                if (displayType === 'inline') {
                    $('.columns-option').hide();
                    $('.inline-options').show();
                    
                    // Show/hide divider options
                    if (useDivider === 'yes') {
                        $('.divider-options').show();
                    } else {
                        $('.divider-options').hide();
                    }
                } else {
                    $('.columns-option').show();
                    $('.inline-options').hide();
                    $('.divider-options').hide();
                }
            } catch (error) {
                console.error('Error parsing shortcode:', error);
                // Don't show an error message, just log it
            }
        }
        
        // Function to delete a shortcode
        function deleteShortcode(name) {
            console.log('Delete button clicked for:', name);
            
            if (!confirm('Are you sure you want to delete the shortcode "' + name + '"?')) {
                return;
            }
            
            // Show loading message
            const button = $('button.delete-shortcode[data-name="' + name + '"]');
            const originalText = button.text();
            button.text('Deleting...').prop('disabled', true);
            
            // Send AJAX request
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'aqm_delete_shortcode',
                    nonce: '<?php echo wp_create_nonce('aqm_sitemaps_nonce'); ?>',
                    name: name
                },
                success: function(response) {
                    console.log('Delete response:', response);
                    alert('Shortcode "' + name + '" deleted successfully!');
                    window.location.reload();
                },
                error: function(xhr, status, error) {
                    console.error('Delete error:', error);
                    alert('Error deleting shortcode. Please try again.');
                    button.text(originalText).prop('disabled', false);
                }
            });
        }
        
        // Set up button handlers
        // Copy button
        $(document).on('click', '.copy-shortcode', function(e) {
            e.preventDefault();
            const shortcode = $(this).data('shortcode');
            copyToClipboard(shortcode, this);
        });
        
        // Edit button
        $(document).on('click', '.edit-shortcode', function(e) {
            e.preventDefault();
            const name = $(this).data('name');
            const shortcode = $(this).data('shortcode');
            editShortcode(name, shortcode);
        });
        
        // Delete button
        $(document).on('click', '.delete-shortcode', function(e) {
            e.preventDefault();
            const name = $(this).data('name');
            deleteShortcode(name);
        });
        
        // Handle form submission
        $('#aqm-sitemap-form').on('submit', function(e) {
            e.preventDefault();
            console.log('Form submitted');
            
            // Update the exclude_ids hidden field with the excluded pages
            const excludedIds = [];
            $('.excluded-page').each(function() {
                excludedIds.push($(this).data('id'));
            });
            $('#exclude_ids').val(excludedIds.join(','));
            
            // Get the shortcode name
            const shortcodeName = $('#shortcode_name').val();
            console.log('Shortcode name:', shortcodeName);
            
            if (!shortcodeName) {
                alert('Please enter a shortcode name');
                return;
            }
            
            // Show loading message on the submit button
            const submitButton = $('#submit_button');
            const originalText = submitButton.text();
            submitButton.text('Saving...').prop('disabled', true);
            
            // Generate the shortcode
            let shortcode = '[sitemap_page';
            
            // Get selected folders
            const selectedFolders = [];
            $('input[name="folder[]"]:checked').each(function() {
                selectedFolders.push($(this).val());
            });
            
            if (selectedFolders.length === 0) {
                alert('Please select at least one folder');
                submitButton.text(originalText).prop('disabled', false);
                return;
            }
            
            if (selectedFolders.length === 1) {
                shortcode += ' folder_slug="' + selectedFolders[0] + '"';
            } else {
                shortcode += ' folder_slugs="' + selectedFolders.join(',') + '"';
            }
            
            // Add display type
            const displayType = $('#display_type').val();
            shortcode += ' display_type="' + displayType + '"';
            
            // Add columns if display type is columns
            if (displayType === 'columns') {
                shortcode += ' columns="' + $('#columns').val() + '"';
            }
            
            // Add inline options if display type is inline
            if (displayType === 'inline') {
                shortcode += ' use_divider="' + $('#use_divider').val() + '"';
                if ($('#use_divider').val() === 'yes') {
                    shortcode += ' divider="' + $('#divider').val() + '"';
                }
            }
            
            // Add order
            shortcode += ' order="' + $('#order').val() + '"';
            
            // Add item margin if provided
            const itemMargin = $('#item_margin').val();
            if (itemMargin) {
                shortcode += ' item_margin="' + itemMargin + '"';
            }
            
            // Add icon if provided
            const icon = $('#icon').val();
            if (icon) {
                shortcode += ' icon="' + icon + '"';
            }
            
            // Add icon color if provided
            const iconColor = $('#icon_color').val();
            if (iconColor) {
                shortcode += ' icon_color="' + iconColor + '"';
            }
            
            // Add debug mode
            shortcode += ' debug="' + $('#debug').val() + '"';
            
            // Add excluded pages if any
            const excludeIds = $('#exclude_ids').val();
            if (excludeIds) {
                shortcode += ' exclude_ids="' + excludeIds + '"';
            }
            
            // Close the shortcode
            shortcode += ']';
            
            console.log('Generated shortcode:', shortcode);
            
            // Create form data object
            const formData = {
                action: 'aqm_sitemaps_save_shortcode',
                nonce: '<?php echo wp_create_nonce('aqm_sitemaps_nonce'); ?>',
                name: shortcodeName,
                shortcode: shortcode,
                edit_mode: $('#edit_mode').val(),
                original_name: $('#original_name').val()
            };
            
            console.log('Sending form data:', formData);
            
            // Send AJAX request
            $.post({
                url: ajaxurl,
                data: formData,
                success: function(response) {
                    console.log('Save response:', response);
                    if (response.success) {
                        alert('Shortcode saved successfully!');
                        window.location.reload();
                    } else {
                        alert('Error saving shortcode: ' + (response.data || 'Unknown error'));
                        submitButton.text(originalText).prop('disabled', false);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Save error:', error);
                    alert('Error saving shortcode. Please try again.');
                    submitButton.text(originalText).prop('disabled', false);
                }
            });
        });
    });
    </script>
    <?php
}
