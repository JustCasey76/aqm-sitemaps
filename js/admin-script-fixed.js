/**
 * AQM Sitemaps Admin JavaScript
 * Handles all admin functionality for the AQM Sitemaps plugin
 * Version: 2.2.1
 * Last updated: 2025-05-16
 */

jQuery(document).ready(function($) {
    console.log('AQM Sitemaps script loaded - Fixed version'); // Debug log with version marker

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
    $('.folder-checklist input[type="checkbox"]').on('change', function() {
        // Only auto-fill if not in edit mode and shortcode name is empty
        if ($('#edit_mode').val() !== '1' && !$('#shortcode_name').val().trim()) {
            console.log('Folder selection changed'); // Debug log
            
            // Get all selected folders
            const selectedFolders = [];
            $('.folder-checklist input[type="checkbox"]:checked').each(function() {
                const folderName = $(this).next('label').text();
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
                $('#shortcode_name').val(shortcodeName).trigger('change');
                console.log('Set shortcode name to:', shortcodeName); // Debug log
            } else {
                $('#shortcode_name').val('');
                console.log('Cleared shortcode name'); // Debug log
            }
        }
    });

    // Toggle fields visibility based on display type
    $('#display_type').on('change', function() {
        const displayType = $(this).val();
        if (displayType === 'inline') {
            $('.columns-option').hide();
            $('.inline-options').show();
            // Check if divider is enabled
            toggleDividerOptions();
        } else {
            $('.columns-option').show();
            $('.inline-options').hide();
            $('.divider-options').hide();
        }
    });
    
    // Toggle divider options based on use_divider selection
    $('#use_divider').on('change', function() {
        toggleDividerOptions();
    });
    
    // Function to toggle divider options visibility
    function toggleDividerOptions() {
        if ($('#display_type').val() === 'inline' && $('#use_divider').val() === 'yes') {
            $('.divider-options').show();
        } else {
            $('.divider-options').hide();
        }
    }
    
    // Initialize visibility on page load
    function initializeFieldVisibility() {
        console.log('Initializing field visibility');
        const displayType = $('#display_type').val();
        console.log('Current display type:', displayType);
        
        if (displayType === 'inline') {
            console.log('Setting up inline display options');
            $('.columns-option').hide();
            $('.inline-options').show();
            
            // Check use_divider value and show/hide divider options accordingly
            const useDivider = $('#use_divider').val();
            console.log('Use divider value:', useDivider);
            
            if (useDivider === 'yes') {
                $('.divider-options').show();
            } else {
                $('.divider-options').hide();
            }
        } else {
            console.log('Setting up columns display options');
            $('.columns-option').show();
            $('.inline-options').hide();
            $('.divider-options').hide();
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
    $('#add_excluded_page').on('click', function() {
        const pageId = $('#page_to_exclude').val();
        const pageTitle = $('#page_to_exclude option:selected').text();
        
        if (!pageId) return;
        
        // Check if already in list
        if ($('#excluded_pages_list').find(`[data-id="${pageId}"]`).length > 0) {
            return;
        }
        
        // Add to visual list
        const itemHtml = `
            <div class="excluded-page-item" data-id="${pageId}">
                <span>${pageTitle}</span>
                <button type="button" class="remove-excluded-page button">×</button>
            </div>
        `;
        $('#excluded_pages_list').append(itemHtml);
        
        // Update hidden input
        updateExcludedIdsInput();
        
        // Reset dropdown
        $('#page_to_exclude').val('');
    });
    
    // Remove excluded page
    $(document).on('click', '.remove-excluded-page', function() {
        $(this).closest('.excluded-page-item').remove();
        updateExcludedIdsInput();
    });
    
    // Update hidden input with excluded IDs
    function updateExcludedIdsInput() {
        const excludedIds = [];
        $('.excluded-page-item').each(function() {
            excludedIds.push($(this).data('id'));
        });
        $('#exclude_ids').val(excludedIds.join(','));
    }

    // Generate and save shortcode
    $('#aqm-sitemap-form').on('submit', function(e) {
        e.preventDefault();
        console.log('Form submitted');
        
        const shortcodeName = $('#shortcode_name').val().trim();
        const selectedFolders = [];
        
        $('.folder-checklist input[type="checkbox"]:checked').each(function() {
            selectedFolders.push($(this).val());
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
        const displayType = $('#display_type').val();
        const columns = $('#columns').val();
        const order = $('#order').val();
        const excludeIds = $('#exclude_ids').val();
        const debug = $('#debug').val();
        const useDivider = $('#use_divider').val();
        const divider = $('#divider').val();
        
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
        if ($('#item_margin').length) {
            itemMargin = $('#item_margin').val();
        }
        
        // Get icon value - ensure we're getting the actual value from the input field
        let icon = '';
        if ($('#icon').length) {
            icon = $('#icon').val();
            console.log('Retrieved icon value from field:', icon);
        }
        
        // Get icon_color value - ensure we're getting the actual value from the input field
        let iconColor = '';
        if ($('#icon_color').length) {
            iconColor = $('#icon_color').val();
            console.log('Retrieved icon_color value from field:', iconColor);
        }
        
        const editMode = $('#edit_mode').val() === '1';
        const originalName = $('#original_name').val();
        
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
        
        // Always include the icon and icon_color values from the form fields
        shortcode += ` icon="${icon}"`;
        shortcode += ` icon_color="${iconColor}"`;
        
        // Add the new parameters to the shortcode
        shortcode += ` debug="${debug}"`;
        
        // Add divider parameters if display type is inline
        if (displayType === 'inline') {
            shortcode += ` use_divider="${useDivider}"`;
            if (useDivider === 'yes') {
                shortcode += ` divider="${divider}"`;
            }
        }
        
        // Close the shortcode
        shortcode += `]`;
        
        // Log the data being sent
        console.log('Sending shortcode data:', {
            name: shortcodeName,
            shortcode: shortcode,
            edit_mode: editMode ? '1' : '0',
            original_name: originalName
        });
        
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
        
        $.ajax({
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

    // COPY SHORTCODE BUTTON
    // Using direct event binding for better reliability
    $('.copy-shortcode').on('click', function() {
        const shortcode = $(this).data('shortcode');
        const $button = $(this);
        const originalText = $button.text();
        console.log('Copy button clicked. Copying shortcode:', shortcode);

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

    // EDIT SHORTCODE BUTTON
    // Using direct event binding for better reliability
    $('.edit-shortcode').on('click', function() {
        console.log('Edit button clicked');
        
        const $button = $(this);
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
                        $('#excluded_pages_list').append(itemHtml);
                    }
                });
                
                // Update hidden input
                updateExcludedIdsInput();
            }
            
            // Uncheck all folder checkboxes first
            $('.folder-checklist input[type="checkbox"]').prop('checked', false);
            
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
                $(`#folder_${slug}`).prop('checked', true);
            });
            
            // Set form fields
            $('#shortcode_name').val(name);
            $('#display_type').val(finalAttributes.display_type);
            $('#columns').val(finalAttributes.columns);
            $('#order').val(finalAttributes.order);
            $('#item_margin').val(finalAttributes.item_margin);
            $('#icon').val(finalAttributes.icon);
            $('#icon_color').val(finalAttributes.icon_color);
            
            // Set the new fields
            $('#debug').val(finalAttributes.debug || 'no');
            $('#use_divider').val(finalAttributes.use_divider || 'yes');
            $('#divider').val(finalAttributes.divider || '|');
            
            // Trigger display type change to show/hide appropriate fields
            $('#display_type').trigger('change');
            
            // Scroll to form
            $('html, body').animate({
                scrollTop: $('#aqm-sitemap-form').offset().top - 50
            }, 500);
            
            // Update form title and submit button
            $('#submit_button').text('Save Changes');
            
            console.log('Form populated with values:', finalAttributes);
        } else {
            console.error('Failed to parse shortcode attributes: folder_slug/folder_slugs is missing');
            alert('Error: Could not parse the shortcode attributes. Please try again.');
        }
    });

    // DELETE SHORTCODE BUTTON
    // Using direct event binding for better reliability
    $('.delete-shortcode').on('click', function() {
        if (!confirm('Are you sure you want to delete this shortcode?')) {
            return;
        }

        const name = $(this).data('name');
        console.log('Delete button clicked. Deleting shortcode:', name);

        $.ajax({
            url: aqmSitemaps.ajaxurl,
            type: 'POST',
            data: {
                action: 'aqm_delete_shortcode',
                nonce: aqmSitemaps.nonce,
                name: name
            },
            success: function(response) {
                console.log('Delete response:', response);
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

    // Fallback copy function for browsers that don't support Clipboard API
    function fallbackCopyToClipboard(text, $button, originalText) {
        // Create a temporary textarea
        const $temp = $('<textarea>');
        $('body').append($temp);
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
    const themeSwitch = $('#theme-switch');
    const body = $('body');
    
    // Check for saved theme preference
    const savedTheme = localStorage.getItem('aqm-theme');
    if (savedTheme === 'dark') {
        body.attr('data-theme', 'dark');
        themeSwitch.prop('checked', true);
    }
    
    // Handle theme toggle
    themeSwitch.on('change', function() {
        if ($(this).is(':checked')) {
            body.attr('data-theme', 'dark');
            localStorage.setItem('aqm-theme', 'dark');
        } else {
            body.attr('data-theme', 'light');
            localStorage.setItem('aqm-theme', 'light');
        }
    });
});
