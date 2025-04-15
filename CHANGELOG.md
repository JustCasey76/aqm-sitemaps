# AQM Enhanced Sitemap Changelog

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
