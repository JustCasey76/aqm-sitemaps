# AQM Sitemaps

Enhanced WordPress sitemap plugin with folder selection and shortcode management.

## Description

AQM Sitemaps is a powerful WordPress plugin that generates customizable sitemaps for your website. It allows you to create sitemaps for specific page folders, customize the display with various options, and manage your sitemaps through an intuitive admin interface.

## Features

- Create sitemaps for specific page folders or multiple folders
- Choose between column or inline display layouts
- Customize the number of columns (1-4)
- Set custom margins for list items
- Add Font Awesome icons with custom colors
- Debug mode for administrators
- Shortcode management system
- Translation-ready

## Installation

1. Upload the `aqm-sitemaps` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to the 'AQM Sitemaps' menu in your WordPress admin to create and manage sitemaps

## Usage

### Creating a Sitemap

1. Go to the AQM Sitemaps admin page
2. Select the folder(s) you want to include in your sitemap
3. Choose your display options (columns, inline, etc.)
4. Set any additional styling options (margins, icons, etc.)
5. Generate the shortcode
6. Add the generated shortcode to any page or post where you want the sitemap to appear

### Example Shortcode

```
[aqm_sitemap folder_slug="services" display_type="columns" columns="2" order="menu_order" icon_class="fas fa-check" icon_color="#007bff" item_margin="10px"]
```

## Updating

The plugin includes an automatic update system that checks for new versions from the GitHub repository. You can manually check for updates by clicking the "Check for Updates" link on the Plugins page.

## Requirements

- WordPress 5.2 or higher
- PHP 7.2 or higher

## Changelog

### 2.2.3
- Fixed redirection issue after shortcode creation on different WordPress installations
- Improved form submission handling with proper URL redirection
- Enhanced logging for troubleshooting redirection issues

### 2.2.2
- Fixed issue with shortcode creation process that was causing session timeouts
- Changed form submission method from AJAX to traditional form submission to avoid session issues
- Added better error handling and success notifications for shortcode creation
- Improved logging for troubleshooting authentication issues

### 1.0.12
- Test version for GitHub updater functionality
- No functional changes from 1.0.11

### 1.0.11
- Enhanced GitHub updater to fix "The package could not be installed" error
- Improved directory structure handling during plugin updates
- Now using GitHub's zipball_url for more reliable updates
- Added fallback mechanisms for update package retrieval
- Enhanced logging for update troubleshooting

### 1.0.10
- Fixed critical issue with shortcode saving functionality
- Corrected AJAX action name mismatch in JavaScript
- Fixed option name inconsistency for saved shortcodes
- Resolved issues with creating, editing, and deleting shortcodes

### 1.0.9
- Fixed shortcode generator buttons not functioning correctly
- Improved JavaScript event handling for dynamically added elements
- Added enhanced debugging and logging for troubleshooting
- Fixed event delegation for edit, copy, and delete buttons

### 1.0.8
- Testing GitHub update mechanism
- Minor performance improvements
- Additional update process enhancements

### 1.0.7
- Fixed update download URL issue that was causing 404 errors
- Improved reliability of the update process
- Enhanced logging for update troubleshooting

### 1.0.6
- Stability improvements
- Minor code optimizations
- Testing GitHub update mechanism

### 1.0.5
- Fixed duplicate method declaration that was causing a fatal error
- Improved code structure and organization

### 1.0.4
- Added back "Check for Updates" link on plugins page
- Improved update success notification with version information
- Fixed update check success notice display

### 1.0.3
- Fixed GitHub update process to properly handle directory structure
- Implemented improved update mechanism using GitHub's zipball URL
- Added detailed logging for troubleshooting update issues

### 1.0.2
- Improved GitHub updater functionality
- Fixed update process to work seamlessly with WordPress
- Enhanced error handling during updates

### 1.0.1
- Removed debug settings section from admin interface
- Simplified plugin settings page
- Code cleanup and optimization

### 1.0.0
- Initial release with GitHub updater integration
- Converted sitemap output from divs to unordered lists
- Added Font Awesome icon support with customizable color
- Added customizable bottom margin for list items
- Fixed WordPress 6.7+ warning for textdomain loading

## License

This plugin is licensed under the GPL v2 or later.

## Credits

Developed by AQ Marketing
