/**
 * AQM Sitemaps Admin Updates JavaScript
 * 
 * Handles the "Check for Updates" functionality on the plugins page.
 */
(function($) {
    'use strict';

    $(document).ready(function() {
        // Add click handler for the "Check for Updates" link
        $('.aqm-check-updates').on('click', function(e) {
            e.preventDefault();
            
            var $link = $(this);
            var originalText = $link.text();
            
            // Show checking message
            $link.text(aqmSitemapsData.checkingText);
            $link.css('cursor', 'wait');
            
            // Make the AJAX request
            $.ajax({
                url: aqmSitemapsData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aqm_sitemaps_check_updates',
                    nonce: aqmSitemapsData.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Show success message
                        $link.text(aqmSitemapsData.successText);
                        
                        // Reload the page after a short delay to show any updates
                        setTimeout(function() {
                            window.location.reload();
                        }, 1500);
                    } else {
                        // Show error message
                        $link.text(aqmSitemapsData.errorText);
                        
                        // Reset to original text after a delay
                        setTimeout(function() {
                            $link.text(originalText);
                            $link.css('cursor', 'pointer');
                        }, 2000);
                    }
                },
                error: function() {
                    // Show error message
                    $link.text(aqmSitemapsData.errorText);
                    
                    // Reset to original text after a delay
                    setTimeout(function() {
                        $link.text(originalText);
                        $link.css('cursor', 'pointer');
                    }, 2000);
                }
            });
        });
    });
})(jQuery);
