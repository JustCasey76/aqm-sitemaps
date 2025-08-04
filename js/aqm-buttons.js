/**
 * AQM Sitemaps Buttons JavaScript
 * Handles all button functionality for the AQM Sitemaps plugin
 */

jQuery(document).ready(function($) {
    console.log('AQM Sitemaps Buttons script loaded');

    // Copy button functionality
    $(document).on('click', '.copy-shortcode', function(e) {
        e.preventDefault();
        const shortcode = $(this).data('shortcode');
        console.log('Copy button clicked for shortcode:', shortcode);
        
        // Create a temporary textarea element to copy from
        const textarea = document.createElement('textarea');
        textarea.value = shortcode;
        textarea.style.position = 'fixed';
        document.body.appendChild(textarea);
        textarea.focus();
        textarea.select();
        
        try {
            // Execute the copy command
            const successful = document.execCommand('copy');
            const msg = successful ? 'Copied!' : 'Failed';
            $(this).text(msg);
            
            // Reset the button text after 2 seconds
            setTimeout(() => {
                $(this).text('Copy');
            }, 2000);
        } catch (err) {
            console.error('Error copying text:', err);
        }
        
        document.body.removeChild(textarea);
    });

    // Edit button functionality
    $(document).on('click', '.edit-shortcode', function(e) {
        e.preventDefault();
        const name = $(this).data('name');
        const shortcode = $(this).data('shortcode');
        console.log('Edit button clicked for shortcode:', name);
        
        // Set form to edit mode
        $('#edit_mode').val('1');
        $('#original_name').val(name);
        $('#shortcode_name').val(name);
        
        // Update submit button text
        $('#submit_button').text('Save Changes');
        
        try {
            // Parse the shortcode
            // Extract folder_slug or folder_slugs
            let folderSlugs = [];
            const folderSlugMatch = shortcode.match(/folder_slug=["']([^"']*)["']/);
            const folderSlugsMatch = shortcode.match(/folder_slugs=["']([^"']*)["']/);
            
            if (folderSlugsMatch && folderSlugsMatch[1]) {
                folderSlugs = folderSlugsMatch[1].split(',');
            } else if (folderSlugMatch && folderSlugMatch[1]) {
                folderSlugs = [folderSlugMatch[1]];
            }
            
            // Uncheck all folder checkboxes first
            $('.folder-checklist input[type="checkbox"]').prop('checked', false);
            
            // Check the appropriate folder checkboxes
            folderSlugs.forEach(function(slug) {
                $('#folder_' + slug).prop('checked', true);
            });
            
            // Helper function to extract attribute values
            function getAttributeValue(attrName) {
                const regex = new RegExp(attrName + '=["\'](.*?)["\']');
                const match = shortcode.match(regex);
                return match ? match[1] : null;
            }
            
            // Set display type
            const displayType = getAttributeValue('display_type') || 'columns';
            $('#display_type').val(displayType);
            
            // Set columns
            const columns = getAttributeValue('columns');
            if (columns) {
                $('#columns').val(columns);
            }
            
            // Set order
            const order = getAttributeValue('order') || 'menu_order';
            $('#order').val(order);
            
            // Set item margin
            const itemMargin = getAttributeValue('item_margin');
            if (itemMargin) {
                $('#item_margin').val(itemMargin);
            }
            
            // Set icon
            const icon = getAttributeValue('icon');
            if (icon) {
                $('#icon').val(icon);
            }
            
            // Set icon color
            const iconColor = getAttributeValue('icon_color');
            if (iconColor) {
                $('#icon_color').val(iconColor);
            }
            
            // Set debug
            const debug = getAttributeValue('debug') || 'no';
            $('#debug').val(debug);
            
            // Set use_divider
            const useDivider = getAttributeValue('use_divider') || 'yes';
            $('#use_divider').val(useDivider);
            
            // Set divider
            const divider = getAttributeValue('divider') || '|';
            $('#divider').val(divider);
            
            // Update display based on display type
            if (displayType === 'inline') {
                $('.columns-option').hide();
                $('.inline-options').show();
                
                // Show/hide divider options
                if (useDivider === 'yes') {
                    $('.divider-options').show();
                } else {
                    $('.divider-options').hide();
                }
            } else {
                $('.columns-option').show();
                $('.inline-options').hide();
                $('.divider-options').hide();
            }
            
            // Scroll to form
            $('#aqm-sitemap-form')[0].scrollIntoView({ behavior: 'smooth', block: 'start' });
        } catch (error) {
            console.error('Error parsing shortcode:', error);
        }
    });

    // Delete button functionality
    $(document).on('click', '.delete-shortcode', function(e) {
        e.preventDefault();
        const name = $(this).data('name');
        console.log('Delete button clicked for shortcode:', name);
        
        if (!confirm('Are you sure you want to delete the shortcode "' + name + '"?')) {
            return;
        }
        
        // Show loading message
        const button = $(this);
        const originalText = button.text();
        button.text('Deleting...').prop('disabled', true);
        
        // Send AJAX request
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'aqm_delete_shortcode',
                nonce: aqm_sitemaps_data.nonce,
                name: name
            },
            success: function(response) {
                console.log('Delete response:', response);
                alert('Shortcode "' + name + '" deleted successfully!');
                window.location.reload();
            },
            error: function(xhr, status, error) {
                console.error('Delete error:', error);
                alert('Error deleting shortcode. Please try again.');
                button.text(originalText).prop('disabled', false);
            }
        });
    });

    // Add excluded page button
    $('#add_excluded_page').on('click', function() {
        const pageSelector = $('#page_to_exclude');
        const pageId = pageSelector.val();
        const pageTitle = pageSelector.find('option:selected').text();
        
        if (!pageId) {
            alert('Please select a page to exclude');
            return;
        }
        
        // Check if this page is already excluded
        if ($('.excluded-page[data-id="' + pageId + '"]').length > 0) {
            alert('This page is already excluded');
            return;
        }
        
        // Add the page to the excluded pages list
        const excludedPage = $('<div class="excluded-page" data-id="' + pageId + '">' + 
                              '<span>' + pageTitle + '</span>' + 
                              '<button type="button" class="remove-excluded-page">×</button>' + 
                              '</div>');
        
        $('#excluded_pages_list').append(excludedPage);
        
        // Reset the selector
        pageSelector.val('');
        
        // Update the hidden field
        updateExcludedPages();
    });
    
    // Remove excluded page button
    $(document).on('click', '.remove-excluded-page', function() {
        $(this).parent().remove();
        updateExcludedPages();
    });
    
    // Function to update the excluded pages hidden field
    function updateExcludedPages() {
        const excludedIds = [];
        $('.excluded-page').each(function() {
            excludedIds.push($(this).data('id'));
        });
        $('#exclude_ids').val(excludedIds.join(','));
    }
    
    // Generate Shortcode button functionality
    $('#submit_button').on('click', function(e) {
        e.preventDefault();
        console.log('Generate Shortcode button clicked');
        
        // Update the exclude_ids hidden field
        updateExcludedPages();
        
        // Get the shortcode name
        const shortcodeName = $('#shortcode_name').val();
        console.log('Shortcode name:', shortcodeName);
        
        if (!shortcodeName) {
            alert('Please enter a shortcode name');
            return;
        }
        
        // Get selected folders
        const selectedFolders = [];
        $('input[name="folder[]"]:checked').each(function() {
            selectedFolders.push($(this).val());
        });
        
        if (selectedFolders.length === 0) {
            alert('Please select at least one folder');
            return;
        }
        
        // Show loading message on the submit button
        const submitButton = $(this);
        const originalText = submitButton.text();
        submitButton.text('Saving...').prop('disabled', true);
        
        // Generate the shortcode
        let shortcode = '[sitemap_page';
        
        if (selectedFolders.length === 1) {
            shortcode += ' folder_slug="' + selectedFolders[0] + '"';
        } else {
            shortcode += ' folder_slugs="' + selectedFolders.join(',') + '"';
        }
        
        // Add display type
        const displayType = $('#display_type').val();
        shortcode += ' display_type="' + displayType + '"';
        
        // Add columns if display type is columns
        if (displayType === 'columns') {
            shortcode += ' columns="' + $('#columns').val() + '"';
        }
        
        // Add inline options if display type is inline
        if (displayType === 'inline') {
            shortcode += ' use_divider="' + $('#use_divider').val() + '"';
            if ($('#use_divider').val() === 'yes') {
                shortcode += ' divider="' + $('#divider').val() + '"';
            }
        }
        
        // Add order
        shortcode += ' order="' + $('#order').val() + '"';
        
        // Add item margin if provided
        const itemMargin = $('#item_margin').val();
        if (itemMargin) {
            shortcode += ' item_margin="' + itemMargin + '"';
        }
        
        // Add icon if provided
        const icon = $('#icon').val();
        if (icon) {
            shortcode += ' icon="' + icon + '"';
        }
        
        // Add icon color if provided
        const iconColor = $('#icon_color').val();
        if (iconColor) {
            shortcode += ' icon_color="' + iconColor + '"';
        }
        
        // Add debug mode
        shortcode += ' debug="' + $('#debug').val() + '"';
        
        // Add excluded pages if any
        const excludeIds = $('#exclude_ids').val();
        if (excludeIds) {
            shortcode += ' exclude_ids="' + excludeIds + '"';
        }
        
        // Close the shortcode
        shortcode += ']';
        
        console.log('Generated shortcode:', shortcode);
        
        // Create form data object
        const formData = {
            action: 'aqm_sitemaps_save_shortcode',
            nonce: aqm_sitemaps_data.nonce,
            name: shortcodeName,
            shortcode: shortcode,
            edit_mode: $('#edit_mode').val(),
            original_name: $('#original_name').val(),
            ajax_submit: 'true'
        };
        
        console.log('Sending form data:', formData);
        
        // Send AJAX request
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            success: function(response) {
                console.log('Save response:', response);
                if (response.success) {
                    alert('Shortcode saved successfully!');
                    window.location.reload();
                } else {
                    alert('Error saving shortcode: ' + (response.data || 'Unknown error'));
                    submitButton.text(originalText).prop('disabled', false);
                }
            },
            error: function(xhr, status, error) {
                console.error('Save error:', error);
                alert('Error saving shortcode. Please try again.');
                submitButton.text(originalText).prop('disabled', false);
            }
        });
    });
    
    // Update display options when display type changes
    $('#display_type').on('change', function() {
        const displayType = $(this).val();
        console.log('Display type changed to:', displayType);
        
        if (displayType === 'inline') {
            $('.columns-option').hide();
            $('.inline-options').show();
            
            // Show/hide divider options based on use_divider value
            const useDivider = $('#use_divider').val();
            if (useDivider === 'yes') {
                $('.divider-options').show();
            } else {
                $('.divider-options').hide();
            }
        } else {
            $('.columns-option').show();
            $('.inline-options').hide();
            $('.divider-options').hide();
        }
    });
    
    // Update divider options when use_divider changes
    $('#use_divider').on('change', function() {
        const useDivider = $(this).val();
        console.log('Use divider changed to:', useDivider);
        
        if (useDivider === 'yes') {
            $('.divider-options').show();
        } else {
            $('.divider-options').hide();
        }
    });
    
    // Initialize display options on page load
    const displayType = $('#display_type').val();
    if (displayType === 'inline') {
        $('.columns-option').hide();
        $('.inline-options').show();
        
        // Show/hide divider options based on use_divider value
        const useDivider = $('#use_divider').val();
        if (useDivider === 'yes') {
            $('.divider-options').show();
        } else {
            $('.divider-options').hide();
        }
    } else {
        $('.columns-option').show();
        $('.inline-options').hide();
        $('.divider-options').hide();
    }
});
