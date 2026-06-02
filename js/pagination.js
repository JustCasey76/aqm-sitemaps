jQuery(document).ready(function($) {
    console.log('AQM Sitemaps Pagination loaded');
    console.log('aqmSitemapsPagination object:', typeof aqmSitemapsPagination !== 'undefined' ? aqmSitemapsPagination : 'NOT DEFINED');
    
    // Handle Load More button
    $(document).on('click', '.aqm-load-more-btn', function() {
        console.log('Load More button clicked');
        const $button = $(this);
        const sitemapId = $button.data('sitemap-id');
        console.log('Sitemap ID:', sitemapId);
        const $sitemap = $('#' + sitemapId);
        console.log('Sitemap element found:', $sitemap.length > 0);
        const $container = $button.closest('.aqm-load-more-container');
        const $loading = $container.find('.aqm-loading');
        
        // Get data from sitemap
        const pageIds = JSON.parse($sitemap.attr('data-page-ids'));
        const loaded = parseInt($sitemap.attr('data-loaded'));
        const total = parseInt($sitemap.attr('data-total'));
        const postType = $sitemap.attr('data-post-type');
        const displayType = $sitemap.attr('data-display-type');
        const columns = parseInt($sitemap.attr('data-columns'));
        const icon = $sitemap.attr('data-icon');
        const iconColor = $sitemap.attr('data-icon-color');
        const itemMargin = $sitemap.attr('data-item-margin');
        const disableLinks = $sitemap.attr('data-disable-links');
        
        console.log('Data:', {
            pageIds: pageIds,
            loaded: loaded,
            total: total,
            postType: postType,
            displayType: displayType,
            columns: columns
        });
        
        // Calculate how many to load (same as initial load)
        const limit = loaded;
        
        // Show loading state
        $button.prop('disabled', true);
        $loading.show();
        
        // Make AJAX request
        $.ajax({
            url: aqmSitemapsPagination.ajaxurl,
            type: 'POST',
            data: {
                action: 'aqm_load_more_posts',
                page_ids: JSON.stringify(pageIds),
                offset: loaded,
                limit: limit,
                post_type: postType,
                display_type: displayType,
                columns: columns,
                icon: icon,
                icon_color: iconColor,
                item_margin: itemMargin,
                disable_links: disableLinks
            },
            success: function(response) {
                console.log('AJAX response:', response);
                if (response.success) {
                    const html = response.data.html;
                    const hasMore = response.data.has_more;
                    const newLoaded = response.data.loaded;
                    
                    console.log('New HTML:', html);
                    console.log('Has more:', hasMore);
                    console.log('New loaded count:', newLoaded);
                    
                    // Append new content
                    if (displayType === 'inline') {
                        // For inline, just append to the end
                        $sitemap.append(' ' + html);
                    } else {
                        // For columns, distribute items across columns
                        const $newItems = $(html);
                        const $columns = $sitemap.find('.aqm-sitemap-column ul');
                        
                        console.log('Found columns:', $columns.length);
                        console.log('New items count:', $newItems.length);
                        
                        if ($columns.length > 0) {
                            // Distribute new items across existing columns
                            $newItems.each(function(index) {
                                const columnIndex = index % $columns.length;
                                $columns.eq(columnIndex).append(this);
                            });
                        }
                    }
                    
                    // Update loaded count
                    $sitemap.attr('data-loaded', newLoaded);
                    
                    // Hide button if no more posts
                    if (!hasMore) {
                        $container.hide();
                    }
                } else {
                    console.error('AJAX error:', response.data);
                    alert('Error: ' + response.data);
                }
                
                // Hide loading state
                $button.prop('disabled', false);
                $loading.hide();
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error('AJAX request failed:', textStatus, errorThrown);
                console.error('Response:', jqXHR.responseText);
                alert('Error loading more posts: ' + textStatus);
                $button.prop('disabled', false);
                $loading.hide();
            }
        });
    });
    
    // Handle Infinite Scroll
    const observerOptions = {
        root: null,
        rootMargin: '200px',
        threshold: 0.1
    };
    
    const observer = new IntersectionObserver(function(entries) {
        entries.forEach(function(entry) {
            if (entry.isIntersecting) {
                const $trigger = $(entry.target);
                const sitemapId = $trigger.data('sitemap-id');
                const $sitemap = $('#' + sitemapId);
                const $loadingIndicator = $trigger.next('.aqm-loading-indicator');
                
                // Check if already loading
                if ($trigger.data('loading')) {
                    return;
                }
                
                // Get data from sitemap
                const pageIds = JSON.parse($sitemap.attr('data-page-ids'));
                const loaded = parseInt($sitemap.attr('data-loaded'));
                const total = parseInt($sitemap.attr('data-total'));
                const postType = $sitemap.attr('data-post-type');
                const displayType = $sitemap.attr('data-display-type');
                const columns = parseInt($sitemap.attr('data-columns'));
                const icon = $sitemap.attr('data-icon');
                const iconColor = $sitemap.attr('data-icon-color');
                const itemMargin = $sitemap.attr('data-item-margin');
                const disableLinks = $sitemap.attr('data-disable-links');
                
                // Calculate how many to load
                const limit = loaded;
                
                // Show loading state
                $trigger.data('loading', true);
                $loadingIndicator.show();
                
                // Make AJAX request
                $.ajax({
                    url: aqmSitemapsPagination.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'aqm_load_more_posts',
                        nonce: aqmSitemapsPagination.nonce,
                        page_ids: JSON.stringify(pageIds),
                        offset: loaded,
                        limit: limit,
                        post_type: postType,
                        display_type: displayType,
                        columns: columns,
                        icon: icon,
                        icon_color: iconColor,
                        item_margin: itemMargin,
                        disable_links: disableLinks
                    },
                    success: function(response) {
                        if (response.success) {
                            const html = response.data.html;
                            const hasMore = response.data.has_more;
                            const newLoaded = response.data.loaded;
                            
                            // Append new content
                            if (displayType === 'inline') {
                                // For inline, just append to the end
                                $sitemap.append(' ' + html);
                            } else {
                                // For columns, distribute items across columns
                                const $newItems = $(html);
                                const $columns = $sitemap.find('.aqm-sitemap-column ul');
                                
                                if ($columns.length > 0) {
                                    // Distribute new items across existing columns
                                    $newItems.each(function(index) {
                                        const columnIndex = index % $columns.length;
                                        $columns.eq(columnIndex).append(this);
                                    });
                                }
                            }
                            
                            // Update loaded count
                            $sitemap.attr('data-loaded', newLoaded);
                            
                            // Hide trigger if no more posts
                            if (!hasMore) {
                                $trigger.hide();
                                $loadingIndicator.hide();
                                observer.unobserve(entry.target);
                            }
                        }
                        
                        // Reset loading state
                        $trigger.data('loading', false);
                        $loadingIndicator.hide();
                    },
                    error: function() {
                        console.error('Error loading more posts');
                        $trigger.data('loading', false);
                        $loadingIndicator.hide();
                    }
                });
            }
        });
    }, observerOptions);
    
    // Observe all infinite scroll triggers
    $('.aqm-infinite-scroll-trigger').each(function() {
        observer.observe(this);
    });
});
