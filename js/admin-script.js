jQuery(document).ready(function($) {
    console.log('AQM Sitemaps script loaded'); // Debug log

    // Function to reset form to create mode
    function resetToCreateMode() {
        $('#edit_mode').val('0');
        $('#original_name').val('');
        $('#shortcode_name').val('');
        $('.aqm-sitemaps-generator h2').text('Create New Sitemap');
        $('#submit_button').text('Generate Shortcode');
        $('#aqm-sitemaps-form')[0].reset();
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

    // Toggle columns field visibility based on display type
    $('#display_type').on('change', function() {
        const displayType = $(this).val();
        if (displayType === 'inline') {
            $('.columns-option').hide();
        } else {
            $('.columns-option').show();
        }
    });

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
        
        // Log the complete shortcode before closing it
        console.log('Shortcode before closing bracket:', shortcode);
        
        
        shortcode += `]`;

        // Log the data being sent
        console.log('Sending shortcode data:', {
            name: shortcodeName,
            shortcode: shortcode,
            edit_mode: editMode ? '1' : '0',
            original_name: originalName
        });

        // Log the final shortcode being sent
        console.log('Final shortcode being sent to server:', shortcode);
        
        // Add form field values to the data object for debugging
        const formData = {
            action: 'aqm_sitemaps_save_shortcode',
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

    // Edit shortcode - use document delegation to handle dynamically added elements
    $(document).on('click', '.edit-shortcode', function() {
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
            
            // First, extract icon and icon_color directly with a more specific regex
            // This handles the case where these attributes might have special characters
            const iconMatch = attributesString.match(/icon=["']([^"']*)["']/);
            if (iconMatch && iconMatch[1] !== undefined) {
                attributes['icon'] = iconMatch[1];
                console.log('Extracted icon directly:', iconMatch[1]);
            }
            
            const iconColorMatch = attributesString.match(/icon_color=["']([^"']*)["']/);
            if (iconColorMatch && iconColorMatch[1] !== undefined) {
                attributes['icon_color'] = iconColorMatch[1];
                console.log('Extracted icon_color directly:', iconColorMatch[1]);
            }
            
            // Match all other attributes, handling both single and double quotes
            // Use a more robust regex that can handle all attribute formats
            const matches = attributesString.match(/(\w+)=["']([^"']+)["']/g);
            
            if (matches) {
                matches.forEach(match => {
                    try {
                        const [_, key, value] = match.match(/(\w+)=["']([^"']+)["']/);
                        attributes[key] = value;
                        console.log(`Parsed attribute: ${key} = ${value}`);
                    } catch (e) {
                        console.error('Error parsing attribute:', match, e);
                    }
                });
            }
            
            // Ensure all expected attributes are present and log missing ones
            const expectedAttributes = ['display_type', 'columns', 'order', 'item_margin', 'icon', 'icon_color'];
            expectedAttributes.forEach(attr => {
                if (attributes[attr] === undefined) {
                    console.log(`Attribute ${attr} not found in shortcode`);
                }
            });
            
            // Log all found attributes
            console.log('All parsed attributes:', attributes);
        } catch (error) {
            console.error('Error parsing shortcode:', error);
            alert('Error parsing shortcode. Please try again.');
            return;
        }
        
        console.log('Parsed attributes:', attributes);
        
        // Set default values if not present
        const defaultValues = {
            display_type: 'columns',
            columns: '2',
            order: 'menu_order',
            exclude_ids: '',
            item_margin: '10px',
            icon: '',
            icon_color: ''
        };

        // Merge defaults with parsed attributes
        // Make sure to only use defaults if the attribute is completely missing
        const finalAttributes = {};
        
        // First add all parsed attributes
        Object.keys(attributes).forEach(key => {
            // Preserve empty strings as they are intentional values
            finalAttributes[key] = attributes[key];
        });
        
        // Then add defaults only for missing keys (not for empty strings)
        Object.keys(defaultValues).forEach(key => {
            if (finalAttributes[key] === undefined) {
                finalAttributes[key] = defaultValues[key];
                console.log(`Using default for ${key}: ${defaultValues[key]}`);
            }
        });
        
        console.log('Final attributes after merging with defaults:', finalAttributes);
        
        // Helper function to update field with animation
        function updateFieldWithAnimation($field, value) {
            const $formGroup = $field.closest('.form-group');
            $formGroup.css('z-index', '1'); // Ensure the "Updated" text shows above other fields
            $field.val(value);
            $field.addClass('highlight-edit');
            setTimeout(() => {
                $field.removeClass('highlight-edit');
                $formGroup.css('z-index', ''); // Reset z-index
            }, 2000);
        }
        
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
            
            // Scroll to form first
            $('html, body').animate({
                scrollTop: $('#aqm-sitemap-form').offset().top - 50
            }, 500, function() {
                // After scrolling, update fields with staggered animations
                updateFieldWithAnimation($('#shortcode_name'), name);
                // Ensure we're using the actual values from the shortcode, not defaults
                setTimeout(() => {
                    updateFieldWithAnimation($('#display_type'), finalAttributes.display_type);
                    console.log('Set display_type to:', finalAttributes.display_type);
                }, 300);
                
                setTimeout(() => {
                    updateFieldWithAnimation($('#columns'), finalAttributes.columns);
                    console.log('Set columns to:', finalAttributes.columns);
                }, 600);
                
                setTimeout(() => {
                    updateFieldWithAnimation($('#order'), finalAttributes.order);
                    console.log('Set order to:', finalAttributes.order);
                }, 900);
                
                setTimeout(() => {
                    updateFieldWithAnimation($('#item_margin'), finalAttributes.item_margin);
                    console.log('Set item_margin to:', finalAttributes.item_margin);
                }, 1200);
                
                setTimeout(() => {
                    updateFieldWithAnimation($('#icon'), finalAttributes.icon);
                    console.log('Set icon to:', finalAttributes.icon);
                }, 1500);
                
                setTimeout(() => {
                    updateFieldWithAnimation($('#icon_color'), finalAttributes.icon_color);
                    console.log('Set icon_color to:', finalAttributes.icon_color);
                }, 1800);
                
                // Log the values being set for each field
                console.log('Setting field values:', {
                    display_type: finalAttributes.display_type,
                    columns: finalAttributes.columns,
                    order: finalAttributes.order,
                    item_margin: finalAttributes.item_margin,
                    icon: finalAttributes.icon,
                    icon_color: finalAttributes.icon_color
                });
            });
            
            // Show/hide columns field based on display type
            if (finalAttributes.display_type === 'inline') {
                $('.columns-option').hide();
            } else {
                $('.columns-option').show();
            }
            
            // Update form title and submit button
            $('#submit_button').text('Save Changes');
            
            console.log('Form populated with values:', finalAttributes);
        } else {
            console.error('Failed to parse shortcode attributes: folder_slug/folder_slugs is missing');
            alert('Error: Could not parse the shortcode attributes. Please try again.');
        }
    });

    // Delete shortcode - use document delegation to handle dynamically added elements
    $(document).on('click', '.delete-shortcode', function() {
        if (!confirm('Are you sure you want to delete this shortcode?')) {
            return;
        }

        const name = $(this).data('name');
        console.log('Deleting shortcode:', name);

        $.ajax({
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
            }
        });
    });

    // Copy shortcode - use document delegation to handle dynamically added elements
    $(document).on('click', '.copy-shortcode', function() {
        const shortcode = $(this).data('shortcode');
        const $button = $(this);
        const originalText = $button.text();
        console.log('Copying shortcode:', shortcode);

        // Create a temporary textarea
        const $temp = $('<textarea>');
        $('body').append($temp);
        $temp.val(shortcode).select();

        try {
            // Copy the text
            document.execCommand('copy');
            // Show success message
            $button.text('Copied!');
            setTimeout(() => {
                $button.text(originalText);
            }, 2000);
        } catch (err) {
            console.error('Failed to copy:', err);
            $button.text('Failed to copy');
            setTimeout(() => {
                $button.text(originalText);
            }, 2000);
        }

        // Remove the temporary textarea
        $temp.remove();
    });

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
