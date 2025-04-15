# AQM Enhanced Sitemap Changelog

## 1.3.7 - April 15, 2025
- Complete rewrite of the update mechanism to fix directory name issues
- Changed to use direct GitHub tag downloads instead of API downloads
- Added fallback copy method if directory renaming fails
- Enhanced error logging and debugging for update process

## 1.3.6 - April 15, 2025
- Comprehensive fix for GitHub update directory name issue
- Added multiple layers of directory name handling during updates
- Implemented package options modification to ensure correct directory name
- Enhanced debug logging for update process troubleshooting

## 1.3.5 - April 15, 2025
- Fixed issue with updates creating a new directory with GitHub format name instead of updating existing plugin
- Added directory name handling during plugin updates
- Improved update process to ensure proper plugin directory structure

## 1.3.4 - April 15, 2025
- Fixed issue with plugin getting deactivated after updates
- Improved GitHub updater to ensure plugin stays activated
- Added automatic reactivation mechanism for better reliability
- Fixed admin page access permissions

## 1.3.3 - April 15, 2025
- Improved dark mode styling for all text elements
- Enhanced visibility of update instructions and status messages in dark mode
- Fixed admin notices styling in dark mode
- Ensured proper contrast for form labels and descriptions

## 1.3.2 - April 15, 2025
- Fixed "Check for Updates" button to use a direct link approach instead of AJAX to prevent 502 errors on some hosting environments
- Added proper textdomain loading to fix WordPress 6.7+ warning about _load_textdomain_just_in_time
- Improved error handling in the update check process
- Added languages directory for future translations

## 1.3.1 - April 11, 2025
- Fixed issue with class name conflict in GitHub Updater by renaming to AQM_Sitemap_GitHub_Updater
- Fixed column layout issues to ensure proper display of 3 columns on desktop
- Improved mobile responsiveness to ensure columns collapse to a single column on mobile devices
- Completely revised CSS structure for better maintainability and compatibility

## 1.3.0
- Added Font Awesome icon support with customizable color
- Converted sitemap output from divs to unordered lists (UL/LI)
- Added customizable bottom margin for list items
- Added item_margin parameter to shortcode generator UI (default: 10px)
