jQuery(document).ready(function($) {
    console.log('AQM Sitemap script loaded'); // Debug log

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
        
        // Get all selected folders
        const selectedFolders = [];
        $('.folder-checklist input[type="checkbox"]:checked').each(function() {
            selectedFolders.push($(this).val());
        });
        
        const displayType = $('#display_type').val();
        const columns = $('#columns').val();
        const order = $('#order').val();
        const excludeIds = $('#exclude_ids').val();
        const editMode = $('#edit_mode').val() === '1';
        const originalName = $('#original_name').val();

        if (selectedFolders.length === 0) {
            alert('Please select at least one folder');
            return;
        }

        // Get shortcode name from input or generate from folders
        let shortcodeName = $('#shortcode_name').val().trim();
        if (!shortcodeName) {
            if (selectedFolders.length === 1) {
                const folderLabel = $(`label[for="folder_${selectedFolders[0]}"]`).text().trim();
                shortcodeName = folderLabel.replace(/[^a-zA-Z0-9\s]+/g, ' ').trim();
            } else {
                shortcodeName = "Multi-Folder Sitemap";
            }
        }

        let shortcode = '[sitemap_page';
        shortcode += ` display_type="${displayType}"`;
        if (displayType === 'columns') {
            shortcode += ` columns="${columns}"`;
        }
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
        shortcode += `]`;

        // Log the data being sent
        console.log('Sending shortcode data:', {
            name: shortcodeName,
            shortcode: shortcode,
            edit_mode: editMode ? '1' : '0',
            original_name: originalName
        });

        $.ajax({
            url: aqmSitemap.ajaxurl,
            type: 'POST',
            data: {
                action: 'save_sitemap_shortcode',
                name: shortcodeName,
                shortcode: shortcode,
                edit_mode: editMode ? '1' : '0',
                original_name: originalName,
                nonce: aqmSitemap.nonce
            },
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

    // Edit shortcode
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
            // Match all attributes, handling both single and double quotes
            const matches = attributesString.match(/(\w+)=["']([^"']+)["']/g);
            
            if (matches) {
                matches.forEach(match => {
                    const [_, key, value] = match.match(/(\w+)=["']([^"']+)["']/);
                    attributes[key] = value;
                });
            }
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
            exclude_ids: ''
        };

        // Merge defaults with parsed attributes
        const finalAttributes = { ...defaultValues, ...attributes };
        
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
                setTimeout(() => updateFieldWithAnimation($('#display_type'), finalAttributes.display_type), 300);
                setTimeout(() => updateFieldWithAnimation($('#columns'), finalAttributes.columns), 600);
                setTimeout(() => updateFieldWithAnimation($('#order'), finalAttributes.order), 900);
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

    // Delete shortcode
    $('.delete-shortcode').on('click', function() {
        if (!confirm('Are you sure you want to delete this shortcode?')) {
            return;
        }

        const name = $(this).data('name');

        $.ajax({
            url: aqmSitemap.ajaxurl,
            type: 'POST',
            data: {
                action: 'aqm_delete_shortcode',
                nonce: aqmSitemap.nonce,
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

    // Copy shortcode
    $('.copy-shortcode').on('click', function() {
        const shortcode = $(this).data('shortcode');
        const $button = $(this);
        const originalText = $button.text();

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
    
    // Handle "Check for Updates" button
    $('#check-for-updates').on('click', function() {
        const $button = $(this);
        const $status = $('#update-check-status');
        const originalText = $button.text();
        
        // Disable button and show loading state
        $button.prop('disabled', true).text('Checking...');
        $status.hide();
        
        // Make AJAX request to trigger update check
        $.ajax({
            url: aqmSitemap.ajaxurl,
            type: 'POST',
            data: {
                action: 'aqm_force_update_check',
                nonce: aqmSitemap.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Update the last check time display
                    $('#last-update-check').text(response.data.last_check);
                    
                    // Show success message with link to plugins page
                    $status.removeClass('error').addClass('success')
                           .html('Update check complete. <a href="' + 
                                 window.location.origin + '/wp-admin/plugins.php' + 
                                 '">Go to Plugins page</a> to see if updates are available.')
                           .show();
                    
                    // Log success
                    console.log('Update check complete', response);
                } else {
                    // Show error message
                    $status.removeClass('success').addClass('error').text('Error: ' + response.data).show();
                    
                    // Log error
                    console.error('Update check failed', response);
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                // Show error message
                $status.removeClass('success').addClass('error').text('Error: ' + textStatus).show();
                
                // Log error details
                console.error('AJAX error:', {
                    status: jqXHR.status,
                    textStatus: textStatus,
                    errorThrown: errorThrown,
                    responseText: jqXHR.responseText
                });
            },
            complete: function() {
                // Re-enable button and restore original text
                $button.prop('disabled', false).text(originalText);
            }
        });
    });
});
