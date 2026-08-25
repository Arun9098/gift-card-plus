/**
 * Simple Accordion Toggle for Visual Composer Accordion
 * Toggles accordion panels on click
 */
(function($) {
    'use strict';

    $(document).ready(function() {
        // Wait a moment to ensure Visual Composer loads first, then override
        setTimeout(function() {
            // Unbind Visual Composer's default accordion handler
            $(document).off('click.vc.accordion.data-api', '[data-vc-accordion]');
            
            // Handle click on accordion panel titles
            $(document).on('click', '.vc_tta-panel-title a[data-vc-accordion]', function(e) {
                e.preventDefault();
                e.stopImmediatePropagation();
                
                var $clickedLink = $(this);
                var $panel = $clickedLink.closest('.vc_tta-panel');
                var $panelBody = $panel.find('.vc_tta-panel-body');
                var $container = $panel.closest('.vc_tta-container');
                var $accordionWrap = $panel.closest('.home-accordion-wrap');
                
                // Check if this panel is already active
                var isActive = $panel.hasClass('vc_active');
                
                // Stop any ongoing animations in all FAQ accordion wraps
                if ($accordionWrap.length) {
                    $('.home-accordion-wrap .vc_tta-panel-body').stop(true, true);
                } else {
                    $container.find('.vc_tta-panel-body').stop(true, true);
                }
                
                 // Close all other open panels: within same .home-accordion-wrap scope, or same container if not in a wrap
                if ($accordionWrap.length) {
                    $('.home-accordion-wrap .vc_tta-panel.vc_active').not($panel).each(function() {
                        var $otherPanel = $(this);
                        var $otherPanelBody = $otherPanel.find('.vc_tta-panel-body');
                        $otherPanel.removeClass('vc_active');
                        $otherPanelBody.slideUp(300);
                    });
                } else {
                    $container.find('.vc_tta-panel.vc_active').not($panel).each(function() {
                        var $otherPanel = $(this);
                        var $otherPanelBody = $otherPanel.find('.vc_tta-panel-body');
                        $otherPanel.removeClass('vc_active');
                        $otherPanelBody.slideUp(300);
                    });
                }
                
                // Toggle the clicked panel
                if (isActive) {
                    // Close the panel
                    $panel.removeClass('vc_active');
                    $panelBody.slideUp(300);
                } else {
                    // Open the panel
                    $panel.addClass('vc_active');
                    $panelBody.slideDown(300);
                }
                
                return false;
            });
        }, 200);
    });

})(jQuery);

