/**
 * AQM Sitemaps Admin JavaScript
 * Handles all admin functionality for the AQM Sitemaps plugin
 * Version: 2.2.2
 * Last updated: 2025-05-21
 */

jQuery(document).ready(function($) {
    console.log('AQM Sitemaps script loaded - Updated version'); // Debug log with version marker

    // Function to reset form to create mode
    function resetToCreateMode() {
        $('#edit_mode').val('0');
        $('#original_name').val('');
        $('#shortcode_name').val('');
        $('.aqm-sitemap-generator h2').text('Create New Sitemap');
        $('#submit_button').text('Generate Shortcode');
        $('#aqm-sitemap-form')[0].reset();
    }

    // Auto-fill shortcode name when folder checkboxes change
    jQuery('.folder-checklist input[type="checkbox"]').on('change', function() {
        // Only auto-fill if not in edit mode and shortcode name is empty
        if (jQuery('#edit_mode').val() !== '1' && !jQuery('#shortcode_name').val().trim()) {
            console.log('Folder selection changed'); // Debug log
            
            // Get all selected folders
            const selectedFolders = [];
            jQuery('.folder-checklist input[type="checkbox"]:checked').each(function() {
                const folderName = jQuery(this).next('label').text();
                selectedFolders.push(folderName);
            });
            
            console.log('Selected folders:', selectedFolders); // Debug log
            
            // If at least one folder is selected, set the shortcode name
            if (selectedFolders.length > 0) {
                // Use the first selected folder name or "Multi-Folder" if multiple folders selected
                let shortcodeName = selectedFolders.length === 1 ? 
                    selectedFolders[0] : 
                    "Multi-Folder Sitemap";
                
                // Remove any special characters but preserve spaces and capitalization
                shortcodeName = shortcodeName.replace(/[^a-zA-Z0-9\s]+/g, ' ').trim();
                jQuery('#shortcode_name').val(shortcodeName).trigger('change');
                console.log('Set shortcode name to:', shortcodeName); // Debug log
            } else {
                jQuery('#shortcode_name').val('');
                console.log('Cleared shortcode name'); // Debug log
            }
        }
    });

    // Toggle fields visibility based on display type
    jQuery('#display_type').on('change', function() {
        const displayType = jQuery(this).val();
        if (displayType === 'inline') {
            jQuery('.columns-option').hide();
            jQuery('.inline-options').show();
            // Check if divider is enabled
            toggleDividerOptions();
        } else {
            jQuery('.columns-option').show();
            jQuery('.inline-options').hide();
            jQuery('.divider-options').hide();
        }
    });
    
    // Toggle divider options based on use_divider selection
    jQuery('#use_divider').on('change', function() {
        toggleDividerOptions();
    });
    
    // Function to toggle divider options visibility
    function toggleDividerOptions() {
        if (jQuery('#display_type').val() === 'inline' && jQuery('#use_divider').val() === 'yes') {
            jQuery('.divider-options').show();
        } else {
            jQuery('.divider-options').hide();
        }
    }
    
    // Initialize visibility on page load
    function initializeFieldVisibility() {
        console.log('Initializing field visibility');
        const displayType = jQuery('#display_type').val();
        console.log('Current display type:', displayType);
        
        if (displayType === 'inline') {
            console.log('Setting up inline display options');
            jQuery('.columns-option').hide();
            jQuery('.inline-options').show();
            
            // Check use_divider value and show/hide divider options accordingly
            const useDivider = jQuery('#use_divider').val();
            console.log('Use divider value:', useDivider);
            
            if (useDivider === 'yes') {
                jQuery('.divider-options').show();
            } else {
                jQuery('.divider-options').hide();
            }
        } else {
            console.log('Setting up columns display options');
            jQuery('.columns-option').show();
            jQuery('.inline-options').hide();
            jQuery('.divider-options').hide();
        }
    }
    
    // Call initialization function immediately
    initializeFieldVisibility();
    
    // Also call it after a short delay to ensure DOM is fully loaded
    setTimeout(function() {
        console.log('Running delayed initialization');
        initializeFieldVisibility();
    }, 500);

    // Handle excluded pages
    jQuery(document).on('click', '#add_excluded_page', function(e) {
        e.preventDefault(); // Prevent any default action
        console.log('Add excluded page button clicked');
        
        const pageId = jQuery('#page_to_exclude').val();
        const pageTitle = jQuery('#page_to_exclude option:selected').text();
        
        console.log('Selected page:', pageId, pageTitle);
        
        if (!pageId) {
            console.log('No page selected');
            return;
        }
        
        // Check if already in list
        if (jQuery('#excluded_pages_list').find(`[data-id="${pageId}"]`).length > 0) {
            console.log('Page already in excluded list');
            return;
        }
        
        // Add to visual list
        const itemHtml = `
            <div class="excluded-page-item excluded-page" data-id="${pageId}">
                <span>${pageTitle}</span>
                <button type="button" class="remove-excluded-page button">×</button>
            </div>
        `;
        jQuery('#excluded_pages_list').append(itemHtml);
        console.log('Added page to excluded list');
        
        // Update hidden input
        updateExcludedIdsInput();
        
        // Reset dropdown
        jQuery('#page_to_exclude').val('');
    });
    
    // Remove excluded page
    jQuery(document).on('click', '.remove-excluded-page', function(e) {
        e.preventDefault(); // Prevent any default action
        console.log('Remove excluded page button clicked');
        
        // Remove the item from the list
        const $item = jQuery(this).closest('.excluded-page-item, .excluded-page');
        const pageId = $item.data('id');
        console.log('Removing excluded page ID:', pageId);
        
        $item.remove();
        
        // Update the hidden input
        updateExcludedIdsInput();
    });
    
    // Update hidden input with excluded IDs
    function updateExcludedIdsInput() {
        const excludedIds = [];
        jQuery('.excluded-page-item, .excluded-page').each(function() {
            const id = jQuery(this).data('id');
            if (id) {
                excludedIds.push(id);
                console.log('Adding excluded page ID:', id);
            }
        });
        
        const excludeIdsValue = excludedIds.join(',');
        jQuery('#exclude_ids').val(excludeIdsValue);
        console.log('Updated exclude_ids value:', excludeIdsValue);
    }

    // Generate and save shortcode
    jQuery('#aqm-sitemap-form').on('submit', function(e) {
        e.preventDefault();
        console.log('Form submitted');
        
        const shortcodeName = jQuery('#shortcode_name').val().trim();
        const selectedFolders = [];
        
        jQuery('.folder-checklist input[type="checkbox"]:checked').each(function() {
            selectedFolders.push(jQuery(this).val());
        });
        
        if (selectedFolders.length === 0) {
            alert('Please select at least one folder');
            return;
        }
        
        if (!shortcodeName) {
            alert('Please enter a shortcode name');
            return;
        }
        
        // Get all form field values
        const displayType = jQuery('#display_type').val();
        const columns = jQuery('#columns').val();
        const order = jQuery('#order').val();
        const excludeIds = jQuery('#exclude_ids').val();
        const debug = jQuery('#debug').val();
        const useDivider = jQuery('#use_divider').val();
        const divider = jQuery('#divider').val();
        
        // Log all form values for debugging
        console.log('Form values:', {
            displayType,
            columns,
            order,
            excludeIds,
            debug,
            useDivider,
            divider
        });
        
        // Get the margin, icon, and icon color values directly from the form fields
        let itemMargin = '';
        if (jQuery('#item_margin').length) {
            itemMargin = jQuery('#item_margin').val();
        }
        
        // Get icon value - ensure we're getting the actual value from the input field
        let icon = '';
        if (jQuery('#icon').length) {
            icon = jQuery('#icon').val();
            console.log('Retrieved icon value from field:', icon);
        }
        
        // Get icon_color value - ensure we're getting the actual value from the input field
        let iconColor = '';
        if (jQuery('#icon_color').length) {
            iconColor = jQuery('#icon_color').val();
            console.log('Retrieved icon_color value from field:', iconColor);
        }
        
        const editMode = jQuery('#edit_mode').val() === '1';
        const originalName = jQuery('#original_name').val();
        
        // Debug log all field values
        console.log('Form field values:', {
            shortcodeName,
            selectedFolders,
            displayType,
            columns,
            order,
            excludeIds,
            itemMargin,
            icon,
            iconColor,
            editMode,
            originalName
        });

        // Start building the shortcode
        let shortcode = '[sitemap_page';
        
        // Add display type
        shortcode += ` display_type="${displayType}"`;
        
        // Add columns if display type is columns
        if (displayType === 'columns') {
            shortcode += ` columns="${columns}"`;
        }
        
        // Add order
        shortcode += ` order="${order}"`;
        
        // Use folder_slug for single folder (backward compatibility) 
        // or folder_slugs for multiple folders
        if (selectedFolders.length === 1) {
            shortcode += ` folder_slug="${selectedFolders[0]}"`;
        } else {
            shortcode += ` folder_slugs="${selectedFolders.join(',')}"`;
        }
        
        if (excludeIds) {
            shortcode += ` exclude_ids="${excludeIds}"`;
        }
        
        // Process item_margin - use default only if completely empty
        const marginValue = (itemMargin && itemMargin.trim() !== '') ? itemMargin.trim() : '10px';
        shortcode += ` item_margin="${marginValue}"`;
        console.log('Using margin value in shortcode:', marginValue);
        
        // Always add icon parameter regardless of value
        console.log('Icon value before processing:', icon);
        // Always include the icon parameter even if empty
        shortcode += ` icon="${icon ? icon.trim() : ''}"`;
        console.log('Adding icon to shortcode:', icon ? icon.trim() : '');
        
        // Always add icon_color parameter regardless of value
        console.log('Icon color value before processing:', iconColor);
        // Always include the icon_color parameter even if empty
        shortcode += ` icon_color="${iconColor ? iconColor.trim() : ''}"`;
        console.log('Adding icon_color to shortcode:', iconColor ? iconColor.trim() : '');
        
        // Always include icon and icon_color values from the form fields
        // This ensures they're included in the shortcode regardless of their value
        shortcode += ` icon="${icon}"`;
        console.log('Adding icon to shortcode regardless of value:', icon);
        
        shortcode += ` icon_color="${iconColor}"`;
        console.log('Adding icon_color to shortcode regardless of value:', iconColor);
        
        // Add the new parameters to the shortcode
        shortcode += ` debug="${debug}"`;
        
        // Add divider parameters if display type is inline
        if (displayType === 'inline') {
            shortcode += ` use_divider="${useDivider}"`;
            if (useDivider === 'yes') {
                shortcode += ` divider="${divider}"`;
            }
        }
        
        // Log the complete shortcode before closing it
        console.log('Shortcode before closing bracket:', shortcode);
        
        // Close the shortcode
        shortcode += `]`;
        
        // Log the data being sent
        console.log('Sending shortcode data:', {
            name: shortcodeName,
            shortcode: shortcode,
            edit_mode: editMode ? '1' : '0',
            original_name: originalName,
            debug: debug,
            use_divider: useDivider,
            divider: divider
        });

        // Log the final shortcode being sent
        console.log('Final shortcode being sent to server:', shortcode);
        
        // Add form field values to the data object for debugging
        const formData = {
            action: 'aqm_save_shortcode',
            name: shortcodeName,
            shortcode: shortcode,
            edit_mode: editMode ? '1' : '0',
            original_name: originalName,
            nonce: aqmSitemaps.nonce,
            // Add these for debugging
            debug_icon: icon,
            debug_icon_color: iconColor
        };
        
        console.log('Form data being sent:', formData);
        
        jQuery.ajax({
            url: aqmSitemaps.ajaxurl,
            type: 'POST',
            data: formData,
            success: function(response) {
                console.log('Server response:', response);
                if (response.success) {
                    location.reload();
                } else {
                    alert('Error saving shortcode: ' + response.data);
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error('AJAX error:', {
                    status: jqXHR.status,
                    textStatus: textStatus,
                    errorThrown: errorThrown,
                    responseText: jqXHR.responseText
                });
                alert('Error saving shortcode. Please try again. Error: ' + textStatus);
            }
        });
    });

    // Edit shortcode - use document delegation to handle dynamically added elements
    jQuery(document).on('click', '.edit-shortcode', function() {
        console.log('Edit button clicked');
        
        const $button = jQuery(this);
        const name = $button.data('name');
        const shortcode = $button.data('shortcode');
        
        console.log('Editing shortcode:', shortcode);
        
        // Parse the shortcode attributes
        const attributes = {};
        try {
            // Extract everything between [ and ]
            const shortcodeContent = shortcode.match(/\[(.*?)\]/)[1];
            // Remove the shortcode name
            const attributesString = shortcodeContent.replace(/^sitemap_page\s+/, '');
            
            // Log the raw attributesString for debugging
            console.log('Raw attributes string:', attributesString);
            
            // Extract all attributes with a regex
            const attrRegex = /([\w_]+)=["']([^"']*)["']/g;
            let match;
            while ((match = attrRegex.exec(attributesString)) !== null) {
                const key = match[1];
                const value = match[2];
                attributes[key] = value;
                console.log(`Extracted attribute: ${key} = ${value}`);
            }
            
            // Log all found attributes
            console.log('All parsed attributes:', attributes);
        } catch (error) {
            console.error('Error parsing shortcode:', error);
            alert('Error parsing shortcode. Please try again.');
            return;
        }
        
        // Set default values if not present
        const defaultValues = {
            display_type: 'columns',
            columns: '2',
            order: 'menu_order',
            exclude_ids: '',
            item_margin: '10px',
            icon: '',
            icon_color: '',
            debug: 'no',
            use_divider: 'yes',
            divider: '|'
        };

        // Merge defaults with parsed attributes
        const finalAttributes = {};
        
        // First add all parsed attributes
        Object.keys(attributes).forEach(key => {
            finalAttributes[key] = attributes[key];
        });
        
        // Then add defaults only for missing keys
        Object.keys(defaultValues).forEach(key => {
            if (finalAttributes[key] === undefined) {
                finalAttributes[key] = defaultValues[key];
                console.log(`Using default for ${key}: ${defaultValues[key]}`);
            }
        });
        
        console.log('Final attributes:', finalAttributes);
        
        // Check if we have folder data (either folder_slug or folder_slugs)
        if (finalAttributes.folder_slug || finalAttributes.folder_slugs) {
            // Set edit mode
            $('#edit_mode').val('1');
            $('#original_name').val(name);
            
            // Clear existing excluded pages
            $('#excluded_pages_list').empty();
            
            // Add excluded pages if any
            if (finalAttributes.exclude_ids) {
                const excludedIds = finalAttributes.exclude_ids.split(',');
                
                // For each excluded ID, fetch the page title and add to the list
                excludedIds.forEach(id => {
                    // Find page title from the dropdown
                    const pageTitle = $(`#page_to_exclude option[value="${id}"]`).text();
                    if (pageTitle) {
                        const itemHtml = `
                            <div class="excluded-page-item" data-id="${id}">
                                <span>${pageTitle}</span>
                                <button type="button" class="remove-excluded-page button">×</button>
                            </div>
                        `;
                        jQuery('#excluded_pages_list').append(itemHtml);
                    }
                });
                
                // Update hidden input
                updateExcludedIdsInput();
            }
            
            // Uncheck all folder checkboxes first
            jQuery('.folder-checklist input[type="checkbox"]').prop('checked', false);
            
            // Check the appropriate folder checkboxes
            let folderSlugs = [];
            if (finalAttributes.folder_slugs) {
                // Multiple folders
                folderSlugs = finalAttributes.folder_slugs.split(',');
            } else if (finalAttributes.folder_slug) {
                // Single folder
                folderSlugs = [finalAttributes.folder_slug];
            }
            
            // Check each folder checkbox that matches our slugs
            folderSlugs.forEach(slug => {
                jQuery(`#folder_${slug}`).prop('checked', true);
            });
            
            // Set form fields
            jQuery('#shortcode_name').val(name);
            jQuery('#display_type').val(finalAttributes.display_type);
            jQuery('#columns').val(finalAttributes.columns);
            jQuery('#order').val(finalAttributes.order);
            jQuery('#item_margin').val(finalAttributes.item_margin);
            jQuery('#icon').val(finalAttributes.icon);
            jQuery('#icon_color').val(finalAttributes.icon_color);
            
            // Set the new fields
            jQuery('#debug').val(finalAttributes.debug || 'no');
            jQuery('#use_divider').val(finalAttributes.use_divider || 'yes');
            jQuery('#divider').val(finalAttributes.divider || '|');
            
            // Trigger display type change to show/hide appropriate fields
            jQuery('#display_type').trigger('change');
            
            // Scroll to form
            jQuery('html, body').animate({
                scrollTop: jQuery('#aqm-sitemap-form').offset().top - 50
            }, 500);
            
            // Update form title and submit button
            jQuery('#submit_button').text('Save Changes');
            
            console.log('Form populated with values:', finalAttributes);
        } else {
            console.error('Failed to parse shortcode attributes: folder_slug/folder_slugs is missing');
            alert('Error: Could not parse the shortcode attributes. Please try again.');
        }
    });

    // Delete shortcode - use document delegation to handle dynamically added elements
    jQuery(document).on('click', '.delete-shortcode', function() {
    if (!confirm('Are you sure you want to delete this shortcode?')) {
        return;
    }

    const name = jQuery(this).data('name');
    console.log('Deleting shortcode:', name);

    jQuery.ajax({
        url: aqmSitemaps.ajaxurl,
        type: 'POST',
        data: {
            action: 'aqm_delete_shortcode',
            nonce: aqmSitemaps.nonce,
            name: name
        },
        success: function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert('Error deleting shortcode');
            }
        },
        error: function(jqXHR, textStatus, errorThrown) {
            console.error('AJAX error:', {
                status: jqXHR.status,
                textStatus: textStatus,
                errorThrown: errorThrown,
                responseText: jqXHR.responseText
            });
            alert('Error deleting shortcode. Please try again. Error: ' + textStatus);
        }
    });
});

    // Copy shortcode - use document delegation to handle dynamically added elements
    jQuery(document).on('click', '.copy-shortcode', function() {
        const shortcode = jQuery(this).data('shortcode');
        const $button = jQuery(this);
        const originalText = $button.text();
        console.log('Copying shortcode:', shortcode);

        // Use modern clipboard API if available, fallback to execCommand
        if (navigator.clipboard && window.isSecureContext) {
            // Modern approach - Clipboard API
            navigator.clipboard.writeText(shortcode)
                .then(() => {
                    // Show success message
                    $button.text('Copied!');
                    setTimeout(() => {
                        $button.text(originalText);
                    }, 2000);
                })
                .catch(err => {
                    console.error('Failed to copy with Clipboard API:', err);
                    fallbackCopyToClipboard(shortcode, $button, originalText);
                });
        } else {
            // Fallback for older browsers
            fallbackCopyToClipboard(shortcode, $button, originalText);
        }
    });

    // Fallback copy function for browsers that don't support Clipboard API
    function fallbackCopyToClipboard(text, $button, originalText) {
        // Create a temporary textarea
        const $temp = jQuery('<textarea>');
        jQuery('body').append($temp);
        $temp.val(text).select();

        try {
            // Copy the text
            const successful = document.execCommand('copy');
            // Show success message
            if (successful) {
                $button.text('Copied!');
            } else {
                $button.text('Failed to copy');
            }
        } catch (err) {
            console.error('Failed to copy with execCommand:', err);
            $button.text('Failed to copy');
        }

        // Remove the temporary textarea
        $temp.remove();
        
        // Reset button text after delay
        setTimeout(() => {
            $button.text(originalText);
        }, 2000);
    }

    // Theme Toggle
    const themeSwitch = jQuery('#theme-switch');
    const body = jQuery('body');
    
    // Check for saved theme preference
    const savedTheme = localStorage.getItem('aqm-theme');
    if (savedTheme === 'dark') {
        body.attr('data-theme', 'dark');
        themeSwitch.prop('checked', true);
    }
    
    // Handle theme toggle
    themeSwitch.on('change', function() {
        if (jQuery(this).is(':checked')) {
            body.attr('data-theme', 'dark');
            localStorage.setItem('aqm-theme', 'dark');
        } else {
            body.attr('data-theme', 'light');
            localStorage.setItem('aqm-theme', 'light');
        }
    });
});
