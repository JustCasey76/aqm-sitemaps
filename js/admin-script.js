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

    // Auto-fill shortcode name when folder is selected
    $('#folder').on('change', function() {
        // Only auto-fill if not in edit mode and shortcode name is empty
        if ($('#edit_mode').val() !== '1' && !$('#shortcode_name').val().trim()) {
            console.log('Folder changed'); // Debug log
            
            const selectedOption = $(this).find('option:selected');
            const folderName = selectedOption.text();
            console.log('Selected folder name:', folderName); // Debug log
            
            // Set the shortcode name to match the selected folder name
            if (selectedOption.val()) {
                // Remove any special characters but preserve spaces and capitalization
                const shortcodeName = folderName.replace(/[^a-zA-Z0-9\s]+/g, ' ').trim();
                $('#shortcode_name').val(shortcodeName).trigger('change');
                console.log('Set shortcode name to:', shortcodeName); // Debug log
            } else {
                $('#shortcode_name').val('');
                console.log('Cleared shortcode name'); // Debug log
            }
        }
    });

    // Also trigger on page load if a folder is already selected
    if ($('#folder').val()) {
        $('#folder').trigger('change');
    }

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
        
        const folder = $('#folder').val();
        const displayType = $('#display_type').val();
        const columns = $('#columns').val();
        const order = $('#order').val();
        const excludeIds = $('#exclude_ids').val();
        const editMode = $('#edit_mode').val() === '1';
        const originalName = $('#original_name').val();

        if (!folder) {
            alert('Please select a folder');
            return;
        }

        // Get shortcode name from input or generate from folder
        let shortcodeName = $('#shortcode_name').val().trim();
        if (!shortcodeName) {
            const folderName = $('#folder option:selected').text().trim();
            shortcodeName = folderName.replace(/[^a-zA-Z0-9\s]+/g, ' ').trim();
        }

        let shortcode = '[sitemap_page';
        shortcode += ` display_type="${displayType}"`;
        if (displayType === 'columns') {
            shortcode += ` columns="${columns}"`;
        }
        shortcode += ` order="${order}"`;
        shortcode += ` folder_slug="${folder}"`;
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
        
        if (finalAttributes.folder_slug) {
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
            
            // Scroll to form first
            $('html, body').animate({
                scrollTop: $('#aqm-sitemap-form').offset().top - 50
            }, 500, function() {
                // After scrolling, update fields with staggered animations
                updateFieldWithAnimation($('#shortcode_name'), name);
                setTimeout(() => updateFieldWithAnimation($('#folder'), finalAttributes.folder_slug), 300);
                setTimeout(() => updateFieldWithAnimation($('#display_type'), finalAttributes.display_type), 600);
                setTimeout(() => updateFieldWithAnimation($('#columns'), finalAttributes.columns), 900);
                setTimeout(() => updateFieldWithAnimation($('#order'), finalAttributes.order), 1200);
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
            console.error('Failed to parse shortcode attributes: folder_slug is missing');
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
});
