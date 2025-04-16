# AQM Enhanced Sitemap Changelog

## 2.1.1 - April 16, 2025
- Fixed GitHub API integration to use latest release endpoint directly
- Improved update detection with enhanced logging
- Added more robust transient clearing for manual update checks

## 2.1.0 - April 16, 2025
- Major version update for GitHub release structure compatibility
- Implemented proper release asset handling for more reliable updates
- Improved directory structure handling during updates

## 2.0.9 - April 16, 2025
- Fixed download URL construction in GitHub updater
- Resolved "Download failed. Not Found" error during updates

## 2.0.8 - April 16, 2025
- Test release to verify GitHub updater functionality
- No functional changes

## 2.0.7 - April 16, 2025
- Added "Check for Updates" button on Plugins page
- Fixed GitHub updater to properly display and process updates
- Improved update notification and one-click update functionality

## 2.0.6 - April 16, 2025
- Fixes PHP parse error (missing constructor bracket) for compatibility with WordPress plugin loader.

## 2.0.5 - April 16, 2025
- One-click update from Plugins page (native WordPress update integration, no custom update page).

## 2.0.4 - April 16, 2025
- Improved GitHub updater logic for robust folder renaming and update reliability.

## 2.0.3 - April 16, 2025
- Fixed random <p> tags by cleaning output and removing wpautop/shortcode_unautop filters in the shortcode output function.

## 2.0.2 - April 16, 2025
- Ensured plugin header and packaging are correct for GitHub Updater.
- Clarified release process in code comments.
- No functional code changes.

## 1.3.9 - April 15, 2025
- Added compatibility with GitHub Updater plugin
- Enhanced plugin headers for better update handling
- Added version requirements for WordPress and PHP
- Removed custom GitHub updater in favor of specialized solution

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
