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
