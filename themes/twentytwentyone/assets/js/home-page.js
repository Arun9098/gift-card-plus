jQuery(document).ready(function ($) {


    jQuery('.header-search').on('keypress', function (e) {
        if (e.which === 13) {
            e.preventDefault();
            jQuery(this).closest('form').submit();
        }
    });

    $(".hamburger-icon").on("click", function (e) {
        e.stopPropagation();
        $("html").toggleClass("navigation-open");
    });

    $(".toggle-search").on("click", function (e) {
        e.stopPropagation();
        $("html").toggleClass("search-open");
    });

    $(document).on("click", function (e) {

        if (!$(e.target).closest(".nav-container, .hamburger-icon").length) {
            $("html").removeClass("navigation-open");
        }
        if (!$(e.target).closest(".font-end-user-header .search-form, .toggle-search").length) {
            $("html").removeClass("search-open");
        }
    });

    jQuery("#gc-search").on("input", function () {


        var $wrapper = $(this).closest(".brand-page-search-wrapper");
        var $clearBtn = $wrapper.find(".clear-search");
        var searchValue = $(this).val().trim();

        if (searchValue.length > 0) {
            $clearBtn.addClass("show");
        } else {
            $clearBtn.removeClass("show");
        }
        // if (jQuery(this).val().length > 0) {
        //     jQuery("#clear-search").show();
        //     console.log('7');
        // } else {
        //     console.log('8');
        //     jQuery("#clear-search").hide();
        // }
    });
    
    document.querySelectorAll(".egift-search").forEach(function(input) {

        input.addEventListener("input", function () {
            console.log('working');

            var wrapper   = this.closest(".gc-search-wrap");
            var clearBtn  = wrapper.querySelector("#egift-clear-search");
            var searchValue = this.value.trim();

            if (searchValue.length > 0) {
                jQuery('#egift-clear-search').show();
            } else {
                jQuery('#egift-clear-search').hide();
            }

        });

    });
    // jQuery("#clear-search").on("click", function() {
    //     console.log('9');
    //     var $search = jQuery("#gc-search");
    //     $search.val("").focus();

    //     // Trigger both input + keyup so all listeners fire
    //     $search.trigger("input");
    //     $search.trigger("keyup");

    //     jQuery(this).hide();
    // });

    jQuery(document).on("click", ".brand-page-search-wrapper .clear-search", function () {
        // console.log("Brand search clear clicked");

        var $wrapper = jQuery(this).closest(".brand-page-search-wrapper");
        var $search = $wrapper.find(".brand-page-search");

        $search.val("").focus().trigger("input").trigger("keyup");

        jQuery(this).removeClass("show").hide();
    });

    jQuery(document).on("click", ".giftcard-search .clear-search", function () {
        // console.log("Instant search clear clicked");

        var $form = jQuery(this).closest(".giftcard-search");
        var $search = $form.find(".giftcard-search-input");

        $search.val("").focus().trigger("input").trigger("keyup");

        jQuery(this).hide();
    });

    jQuery(document).on("click", ".wishlist-page-search .clear-search", function () {
        // console.log("Wishlist search clear clicked");

        var $wrapper = jQuery(this).closest(".wishlist-page-search");
        var $search = $wrapper.find(".brand-page-search");

        $search.val("").focus().trigger("input").trigger("keyup");

        jQuery(this).removeClass("show").hide();
    });

});

jQuery(function ($) {

    var $categoryWrapper = $('.giftcard-category-wrapper');
    var $carousel = $categoryWrapper;
    var slideInterval = null;

    if (!$categoryWrapper.length) return;

    const isMobile = window.matchMedia("(max-width: 768px)").matches;

    /* ==========================
       AUTO SLIDE (DESKTOP ONLY)
    ========================== */

    function startSliding(direction) {
        if (isMobile) return; // ❌ disable on mobile

        stopSliding();

        if (!$carousel.find('.owl-stage-outer').length) return;

        slideInterval = setInterval(function () {
            if (!$carousel.find('.owl-stage-outer').length) return;

            if (direction === 'left') {
                $carousel.trigger('prev.owl.carousel', [300]);
            } else {
                $carousel.trigger('next.owl.carousel', [300]);
            }
        }, 600);
    }

    function stopSliding() {
        if (slideInterval) {
            clearInterval(slideInterval);
            slideInterval = null;
        }
    }

    $carousel.on('mouseenter', function () {
        $carousel.trigger('stop.owl.autoplay'); // stop autoplay
    });

    $carousel.on('mouseleave', function () {
        $carousel.trigger('play.owl.autoplay'); // resume autoplay
        stopSliding();
    });

    $carousel.on('mousemove', function (e) {

        var offset = $(this).offset();
        var width = $(this).outerWidth();
        var xPos = e.pageX - offset.left;

        if (xPos < width * 0.3) {
            startSliding('left');
        } else if (xPos > width * 0.7) {
            startSliding('right');
        } else {
            stopSliding();
        }
    });
    /* ==========================
       SEARCH INPUT SAFE HANDLING
    ========================== */

    const $input = $('#instant-cat-search');
    const $clearBtn = $('#clear-search');

    function checkInputState() {
        if (!$input.length) return;

        let value = $input.val();
        if (value && value.trim() !== '') {
            $clearBtn.show();
        } else {
            $clearBtn.hide();
        }
    }

    if ($input.length) {

        checkInputState();

        $(window).on('pageshow', checkInputState);

        $input.on('input keyup', checkInputState);

        $clearBtn.on('click', function () {
            $input.val('');
            checkInputState();
            $input.focus();
        });

        $(document).on('click', '#clear-search', function () {
            $input.val('');
            $input.trigger('keyup');
            $(this).hide();
        });
    }

    /* ==========================
       OWL RESPONSIVE SETTINGS
    ========================== */

    const owlOptions = {
        loop: true,
        autoplayHoverPause: true,
        nav: true,
        dots: false,
        autoplay: true,
        autoplayTimeout: 3000,
        smartSpeed: 600,
        navText: [
            `<span class="owl-prev-btn">
                <svg width="21" height="14" viewBox="0 0 21 14" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M1.89844 6.63018C1.89844 6.37902 1.99821 6.13816 2.1758 5.96057C2.35339 5.78297 2.59426 5.6832 2.84541 5.6832L19.8909 5.6832C20.1421 5.6832 20.3829 5.78297 20.5605 5.96056C20.7381 6.13815 20.8379 6.37902 20.8379 6.63017C20.8379 6.88132 20.7381 7.12219 20.5605 7.29978C20.3829 7.47737 20.1421 7.57714 19.8909 7.57714L2.84541 7.57715C2.59426 7.57715 2.35339 7.47738 2.1758 7.29979C1.99821 7.12219 1.89844 6.88133 1.89844 6.63018Z" fill="black"/>
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M1.89844 6.63018C1.89844 6.37902 1.99821 6.13816 2.1758 5.96057C2.35339 5.78297 2.59426 5.6832 2.84541 5.6832L19.8909 5.6832C20.1421 5.6832 20.3829 5.78297 20.5605 5.96056C20.7381 6.13815 20.8379 6.37902 20.8379 6.63017C20.8379 6.88132 20.7381 7.12219 20.5605 7.29978C20.3829 7.47737 20.1421 7.57714 19.8909 7.57714L2.84541 7.57715C2.59426 7.57715 2.35339 7.47738 2.1758 7.29979C1.99821 7.12219 1.89844 6.88133 1.89844 6.63018Z" fill="black" fill-opacity="0.2"/>
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M1.89844 6.63018C1.89844 6.37902 1.99821 6.13816 2.1758 5.96057C2.35339 5.78297 2.59426 5.6832 2.84541 5.6832L19.8909 5.6832C20.1421 5.6832 20.3829 5.78297 20.5605 5.96056C20.7381 6.13815 20.8379 6.37902 20.8379 6.63017C20.8379 6.88132 20.7381 7.12219 20.5605 7.29978C20.3829 7.47737 20.1421 7.57714 19.8909 7.57714L2.84541 7.57715C2.59426 7.57715 2.35339 7.47738 2.1758 7.29979C1.99821 7.12219 1.89844 6.88133 1.89844 6.63018Z" fill="black" fill-opacity="0.2"/>
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M1.89844 6.63018C1.89844 6.37902 1.99821 6.13816 2.1758 5.96057C2.35339 5.78297 2.59426 5.6832 2.84541 5.6832L19.8909 5.6832C20.1421 5.6832 20.3829 5.78297 20.5605 5.96056C20.7381 6.13815 20.8379 6.37902 20.8379 6.63017C20.8379 6.88132 20.7381 7.12219 20.5605 7.29978C20.3829 7.47737 20.1421 7.57714 19.8909 7.57714L2.84541 7.57715C2.59426 7.57715 2.35339 7.47738 2.1758 7.29979C1.99821 7.12219 1.89844 6.88133 1.89844 6.63018Z" fill="black" fill-opacity="0.2"/>
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M1.89844 6.63018C1.89844 6.37902 1.99821 6.13816 2.1758 5.96057C2.35339 5.78297 2.59426 5.6832 2.84541 5.6832L19.8909 5.6832C20.1421 5.6832 20.3829 5.78297 20.5605 5.96056C20.7381 6.13815 20.8379 6.37902 20.8379 6.63017C20.8379 6.88132 20.7381 7.12219 20.5605 7.29978C20.3829 7.47737 20.1421 7.57714 19.8909 7.57714L2.84541 7.57715C2.59426 7.57715 2.35339 7.47738 2.1758 7.29979C1.99821 7.12219 1.89844 6.88133 1.89844 6.63018Z" fill="black" fill-opacity="0.2"/>
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M1.89844 6.63018C1.89844 6.37902 1.99821 6.13816 2.1758 5.96057C2.35339 5.78297 2.59426 5.6832 2.84541 5.6832L19.8909 5.6832C20.1421 5.6832 20.3829 5.78297 20.5605 5.96056C20.7381 6.13815 20.8379 6.37902 20.8379 6.63017C20.8379 6.88132 20.7381 7.12219 20.5605 7.29978C20.3829 7.47737 20.1421 7.57714 19.8909 7.57714L2.84541 7.57715C2.59426 7.57715 2.35339 7.47738 2.1758 7.29979C1.99821 7.12219 1.89844 6.88133 1.89844 6.63018Z" fill="black" fill-opacity="0.2"/>
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M1.89844 6.63018C1.89844 6.37902 1.99821 6.13816 2.1758 5.96057C2.35339 5.78297 2.59426 5.6832 2.84541 5.6832L19.8909 5.6832C20.1421 5.6832 20.3829 5.78297 20.5605 5.96056C20.7381 6.13815 20.8379 6.37902 20.8379 6.63017C20.8379 6.88132 20.7381 7.12219 20.5605 7.29978C20.3829 7.47737 20.1421 7.57714 19.8909 7.57714L2.84541 7.57715C2.59426 7.57715 2.35339 7.47738 2.1758 7.29979C1.99821 7.12219 1.89844 6.88133 1.89844 6.63018Z" fill="black" fill-opacity="0.2"/>
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M0.27821 7.30047C0.190022 7.2125 0.120054 7.108 0.0723148 6.99295C0.0245752 6.8779 1.47626e-06 6.75457 1.44904e-06 6.63001C1.42182e-06 6.50545 0.024575 6.38211 0.0723146 6.26707C0.120054 6.15202 0.190022 6.04752 0.27821 5.95955L5.96004 0.277719C6.13786 0.0999037 6.37903 8.14256e-06 6.6305 8.0876e-06C6.88197 8.03264e-06 7.12314 0.0999035 7.30095 0.277719C7.47877 0.455535 7.57867 0.696705 7.57867 0.948176C7.57867 1.19965 7.47877 1.44082 7.30095 1.61863L2.28768 6.63001L7.30095 11.6414C7.47877 11.8192 7.57867 12.0604 7.57867 12.3118C7.57867 12.5633 7.47877 12.8045 7.30096 12.9823C7.12314 13.1601 6.88197 13.26 6.6305 13.26C6.37903 13.26 6.13786 13.1601 5.96004 12.9823L0.27821 7.30047Z" fill="black"/>
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M0.27821 7.30047C0.190022 7.2125 0.120054 7.108 0.0723148 6.99295C0.0245752 6.8779 1.47626e-06 6.75457 1.44904e-06 6.63001C1.42182e-06 6.50545 0.024575 6.38211 0.0723146 6.26707C0.120054 6.15202 0.190022 6.04752 0.27821 5.95955L5.96004 0.277719C6.13786 0.0999037 6.37903 8.14256e-06 6.6305 8.0876e-06C6.88197 8.03264e-06 7.12314 0.0999035 7.30095 0.277719C7.47877 0.455535 7.57867 0.696705 7.57867 0.948176C7.57867 1.19965 7.47877 1.44082 7.30095 1.61863L2.28768 6.63001L7.30095 11.6414C7.47877 11.8192 7.57867 12.0604 7.57867 12.3118C7.57867 12.5633 7.47877 12.8045 7.30096 12.9823C7.12314 13.1601 6.88197 13.26 6.6305 13.26C6.37903 13.26 6.13786 13.1601 5.96004 12.9823L0.27821 7.30047Z" fill="black" fill-opacity="0.2"/>
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M0.27821 7.30047C0.190022 7.2125 0.120054 7.108 0.0723148 6.99295C0.0245752 6.8779 1.47626e-06 6.75457 1.44904e-06 6.63001C1.42182e-06 6.50545 0.024575 6.38211 0.0723146 6.26707C0.120054 6.15202 0.190022 6.04752 0.27821 5.95955L5.96004 0.277719C6.13786 0.0999037 6.37903 8.14256e-06 6.6305 8.0876e-06C6.88197 8.03264e-06 7.12314 0.0999035 7.30095 0.277719C7.47877 0.455535 7.57867 0.696705 7.57867 0.948176C7.57867 1.19965 7.47877 1.44082 7.30095 1.61863L2.28768 6.63001L7.30095 11.6414C7.47877 11.8192 7.57867 12.0604 7.57867 12.3118C7.57867 12.5633 7.47877 12.8045 7.30096 12.9823C7.12314 13.1601 6.88197 13.26 6.6305 13.26C6.37903 13.26 6.13786 13.1601 5.96004 12.9823L0.27821 7.30047Z" fill="black" fill-opacity="0.2"/>
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M0.27821 7.30047C0.190022 7.2125 0.120054 7.108 0.0723148 6.99295C0.0245752 6.8779 1.47626e-06 6.75457 1.44904e-06 6.63001C1.42182e-06 6.50545 0.024575 6.38211 0.0723146 6.26707C0.120054 6.15202 0.190022 6.04752 0.27821 5.95955L5.96004 0.277719C6.13786 0.0999037 6.37903 8.14256e-06 6.6305 8.0876e-06C6.88197 8.03264e-06 7.12314 0.0999035 7.30095 0.277719C7.47877 0.455535 7.57867 0.696705 7.57867 0.948176C7.57867 1.19965 7.47877 1.44082 7.30095 1.61863L2.28768 6.63001L7.30095 11.6414C7.47877 11.8192 7.57867 12.0604 7.57867 12.3118C7.57867 12.5633 7.47877 12.8045 7.30096 12.9823C7.12314 13.1601 6.88197 13.26 6.6305 13.26C6.37903 13.26 6.13786 13.1601 5.96004 12.9823L0.27821 7.30047Z" fill="black" fill-opacity="0.2"/>
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M0.27821 7.30047C0.190022 7.2125 0.120054 7.108 0.0723148 6.99295C0.0245752 6.8779 1.47626e-06 6.75457 1.44904e-06 6.63001C1.42182e-06 6.50545 0.024575 6.38211 0.0723146 6.26707C0.120054 6.15202 0.190022 6.04752 0.27821 5.95955L5.96004 0.277719C6.13786 0.0999037 6.37903 8.14256e-06 6.6305 8.0876e-06C6.88197 8.03264e-06 7.12314 0.0999035 7.30095 0.277719C7.47877 0.455535 7.57867 0.696705 7.57867 0.948176C7.57867 1.19965 7.47877 1.44082 7.30095 1.61863L2.28768 6.63001L7.30095 11.6414C7.47877 11.8192 7.57867 12.0604 7.57867 12.3118C7.57867 12.5633 7.47877 12.8045 7.30096 12.9823C7.12314 13.1601 6.88197 13.26 6.6305 13.26C6.37903 13.26 6.13786 13.1601 5.96004 12.9823L0.27821 7.30047Z" fill="black" fill-opacity="0.2"/>
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M0.27821 7.30047C0.190022 7.2125 0.120054 7.108 0.0723148 6.99295C0.0245752 6.8779 1.47626e-06 6.75457 1.44904e-06 6.63001C1.42182e-06 6.50545 0.024575 6.38211 0.0723146 6.26707C0.120054 6.15202 0.190022 6.04752 0.27821 5.95955L5.96004 0.277719C6.13786 0.0999037 6.37903 8.14256e-06 6.6305 8.0876e-06C6.88197 8.03264e-06 7.12314 0.0999035 7.30095 0.277719C7.47877 0.455535 7.57867 0.696705 7.57867 0.948176C7.57867 1.19965 7.47877 1.44082 7.30095 1.61863L2.28768 6.63001L7.30095 11.6414C7.47877 11.8192 7.57867 12.0604 7.57867 12.3118C7.57867 12.5633 7.47877 12.8045 7.30096 12.9823C7.12314 13.1601 6.88197 13.26 6.6305 13.26C6.37903 13.26 6.13786 13.1601 5.96004 12.9823L0.27821 7.30047Z" fill="black" fill-opacity="0.2"/>
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M0.27821 7.30047C0.190022 7.2125 0.120054 7.108 0.0723148 6.99295C0.0245752 6.8779 1.47626e-06 6.75457 1.44904e-06 6.63001C1.42182e-06 6.50545 0.024575 6.38211 0.0723146 6.26707C0.120054 6.15202 0.190022 6.04752 0.27821 5.95955L5.96004 0.277719C6.13786 0.0999037 6.37903 8.14256e-06 6.6305 8.0876e-06C6.88197 8.03264e-06 7.12314 0.0999035 7.30095 0.277719C7.47877 0.455535 7.57867 0.696705 7.57867 0.948176C7.57867 1.19965 7.47877 1.44082 7.30095 1.61863L2.28768 6.63001L7.30095 11.6414C7.47877 11.8192 7.57867 12.0604 7.57867 12.3118C7.57867 12.5633 7.47877 12.8045 7.30096 12.9823C7.12314 13.1601 6.88197 13.26 6.6305 13.26C6.37903 13.26 6.13786 13.1601 5.96004 12.9823L0.27821 7.30047Z" fill="black" fill-opacity="0.2"/>
                </svg>
            </span>`,
            
            `<span class="owl-next-btn">
                <svg width="21" height="14" viewBox="0 0 21 14" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M18.9375 6.62983C18.9375 6.88099 18.8377 7.12185 18.6601 7.29944C18.4825 7.47704 18.2417 7.57681 17.9905 7.57681L0.945032 7.57681C0.69388 7.57681 0.453013 7.47704 0.275421 7.29945C0.09783 7.12185 -0.00193962 6.88099 -0.00193965 6.62984C-0.00193968 6.37868 0.0978299 6.13782 0.275421 5.96023C0.453012 5.78263 0.69388 5.68286 0.945032 5.68286L17.9905 5.68286C18.2417 5.68286 18.4825 5.78263 18.6601 5.96022C18.8377 6.13781 18.9375 6.37868 18.9375 6.62983Z" fill="black"/>
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M18.9375 6.62983C18.9375 6.88099 18.8377 7.12185 18.6601 7.29944C18.4825 7.47704 18.2417 7.57681 17.9905 7.57681L0.945032 7.57681C0.69388 7.57681 0.453013 7.47704 0.275421 7.29945C0.09783 7.12185 -0.00193962 6.88099 -0.00193965 6.62984C-0.00193968 6.37868 0.0978299 6.13782 0.275421 5.96023C0.453012 5.78263 0.69388 5.68286 0.945032 5.68286L17.9905 5.68286C18.2417 5.68286 18.4825 5.78263 18.6601 5.96022C18.8377 6.13781 18.9375 6.37868 18.9375 6.62983Z" fill="black" fill-opacity="0.2"/>
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M18.9375 6.62983C18.9375 6.88099 18.8377 7.12185 18.6601 7.29944C18.4825 7.47704 18.2417 7.57681 17.9905 7.57681L0.945032 7.57681C0.69388 7.57681 0.453013 7.47704 0.275421 7.29945C0.09783 7.12185 -0.00193962 6.88099 -0.00193965 6.62984C-0.00193968 6.37868 0.0978299 6.13782 0.275421 5.96023C0.453012 5.78263 0.69388 5.68286 0.945032 5.68286L17.9905 5.68286C18.2417 5.68286 18.4825 5.78263 18.6601 5.96022C18.8377 6.13781 18.9375 6.37868 18.9375 6.62983Z" fill="black" fill-opacity="0.2"/>
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M18.9375 6.62983C18.9375 6.88099 18.8377 7.12185 18.6601 7.29944C18.4825 7.47704 18.2417 7.57681 17.9905 7.57681L0.945032 7.57681C0.69388 7.57681 0.453013 7.47704 0.275421 7.29945C0.09783 7.12185 -0.00193962 6.88099 -0.00193965 6.62984C-0.00193968 6.37868 0.0978299 6.13782 0.275421 5.96023C0.453012 5.78263 0.69388 5.68286 0.945032 5.68286L17.9905 5.68286C18.2417 5.68286 18.4825 5.78263 18.6601 5.96022C18.8377 6.13781 18.9375 6.37868 18.9375 6.62983Z" fill="black" fill-opacity="0.2"/>
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M18.9375 6.62983C18.9375 6.88099 18.8377 7.12185 18.6601 7.29944C18.4825 7.47704 18.2417 7.57681 17.9905 7.57681L0.945032 7.57681C0.69388 7.57681 0.453013 7.47704 0.275421 7.29945C0.09783 7.12185 -0.00193962 6.88099 -0.00193965 6.62984C-0.00193968 6.37868 0.0978299 6.13782 0.275421 5.96023C0.453012 5.78263 0.69388 5.68286 0.945032 5.68286L17.9905 5.68286C18.2417 5.68286 18.4825 5.78263 18.6601 5.96022C18.8377 6.13781 18.9375 6.37868 18.9375 6.62983Z" fill="black" fill-opacity="0.2"/>
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M18.9375 6.62983C18.9375 6.88099 18.8377 7.12185 18.6601 7.29944C18.4825 7.47704 18.2417 7.57681 17.9905 7.57681L0.945032 7.57681C0.69388 7.57681 0.453013 7.47704 0.275421 7.29945C0.09783 7.12185 -0.00193962 6.88099 -0.00193965 6.62984C-0.00193968 6.37868 0.0978299 6.13782 0.275421 5.96023C0.453012 5.78263 0.69388 5.68286 0.945032 5.68286L17.9905 5.68286C18.2417 5.68286 18.4825 5.78263 18.6601 5.96022C18.8377 6.13781 18.9375 6.37868 18.9375 6.62983Z" fill="black" fill-opacity="0.2"/>
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M18.9375 6.62983C18.9375 6.88099 18.8377 7.12185 18.6601 7.29944C18.4825 7.47704 18.2417 7.57681 17.9905 7.57681L0.945032 7.57681C0.69388 7.57681 0.453013 7.47704 0.275421 7.29945C0.09783 7.12185 -0.00193962 6.88099 -0.00193965 6.62984C-0.00193968 6.37868 0.0978299 6.13782 0.275421 5.96023C0.453012 5.78263 0.69388 5.68286 0.945032 5.68286L17.9905 5.68286C18.2417 5.68286 18.4825 5.78263 18.6601 5.96022C18.8377 6.13781 18.9375 6.37868 18.9375 6.62983Z" fill="black" fill-opacity="0.2"/>
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M20.5577 5.95954C20.6459 6.04751 20.7159 6.15201 20.7636 6.26706C20.8114 6.3821 20.8359 6.50544 20.8359 6.63C20.8359 6.75456 20.8114 6.8779 20.7636 6.99294C20.7159 7.10799 20.6459 7.21249 20.5577 7.30046L14.8759 12.9823C14.6981 13.1601 14.4569 13.26 14.2054 13.26C13.954 13.26 13.7128 13.1601 13.535 12.9823C13.3572 12.8045 13.2573 12.5633 13.2573 12.3118C13.2573 12.0604 13.3572 11.8192 13.535 11.6414L18.5483 6.63L13.535 1.61863C13.3572 1.44081 13.2573 1.19964 13.2573 0.94817C13.2573 0.6967 13.3572 0.455529 13.535 0.277713C13.7128 0.0998972 13.954 9.02461e-07 14.2054 8.69485e-07C14.4569 8.36508e-07 14.6981 0.099897 14.8759 0.277713L20.5577 5.95954Z" fill="black"/>
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M20.5577 5.95954C20.6459 6.04751 20.7159 6.15201 20.7636 6.26706C20.8114 6.3821 20.8359 6.50544 20.8359 6.63C20.8359 6.75456 20.8114 6.8779 20.7636 6.99294C20.7159 7.10799 20.6459 7.21249 20.5577 7.30046L14.8759 12.9823C14.6981 13.1601 14.4569 13.26 14.2054 13.26C13.954 13.26 13.7128 13.1601 13.535 12.9823C13.3572 12.8045 13.2573 12.5633 13.2573 12.3118C13.2573 12.0604 13.3572 11.8192 13.535 11.6414L18.5483 6.63L13.535 1.61863C13.3572 1.44081 13.2573 1.19964 13.2573 0.94817C13.2573 0.6967 13.3572 0.455529 13.535 0.277713C13.7128 0.0998972 13.954 9.02461e-07 14.2054 8.69485e-07C14.4569 8.36508e-07 14.6981 0.099897 14.8759 0.277713L20.5577 5.95954Z" fill="black" fill-opacity="0.2"/>
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M20.5577 5.95954C20.6459 6.04751 20.7159 6.15201 20.7636 6.26706C20.8114 6.3821 20.8359 6.50544 20.8359 6.63C20.8359 6.75456 20.8114 6.8779 20.7636 6.99294C20.7159 7.10799 20.6459 7.21249 20.5577 7.30046L14.8759 12.9823C14.6981 13.1601 14.4569 13.26 14.2054 13.26C13.954 13.26 13.7128 13.1601 13.535 12.9823C13.3572 12.8045 13.2573 12.5633 13.2573 12.3118C13.2573 12.0604 13.3572 11.8192 13.535 11.6414L18.5483 6.63L13.535 1.61863C13.3572 1.44081 13.2573 1.19964 13.2573 0.94817C13.2573 0.6967 13.3572 0.455529 13.535 0.277713C13.7128 0.0998972 13.954 9.02461e-07 14.2054 8.69485e-07C14.4569 8.36508e-07 14.6981 0.099897 14.8759 0.277713L20.5577 5.95954Z" fill="black" fill-opacity="0.2"/>
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M20.5577 5.95954C20.6459 6.04751 20.7159 6.15201 20.7636 6.26706C20.8114 6.3821 20.8359 6.50544 20.8359 6.63C20.8359 6.75456 20.8114 6.8779 20.7636 6.99294C20.7159 7.10799 20.6459 7.21249 20.5577 7.30046L14.8759 12.9823C14.6981 13.1601 14.4569 13.26 14.2054 13.26C13.954 13.26 13.7128 13.1601 13.535 12.9823C13.3572 12.8045 13.2573 12.5633 13.2573 12.3118C13.2573 12.0604 13.3572 11.8192 13.535 11.6414L18.5483 6.63L13.535 1.61863C13.3572 1.44081 13.2573 1.19964 13.2573 0.94817C13.2573 0.6967 13.3572 0.455529 13.535 0.277713C13.7128 0.0998972 13.954 9.02461e-07 14.2054 8.69485e-07C14.4569 8.36508e-07 14.6981 0.099897 14.8759 0.277713L20.5577 5.95954Z" fill="black" fill-opacity="0.2"/>
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M20.5577 5.95954C20.6459 6.04751 20.7159 6.15201 20.7636 6.26706C20.8114 6.3821 20.8359 6.50544 20.8359 6.63C20.8359 6.75456 20.8114 6.8779 20.7636 6.99294C20.7159 7.10799 20.6459 7.21249 20.5577 7.30046L14.8759 12.9823C14.6981 13.1601 14.4569 13.26 14.2054 13.26C13.954 13.26 13.7128 13.1601 13.535 12.9823C13.3572 12.8045 13.2573 12.5633 13.2573 12.3118C13.2573 12.0604 13.3572 11.8192 13.535 11.6414L18.5483 6.63L13.535 1.61863C13.3572 1.44081 13.2573 1.19964 13.2573 0.94817C13.2573 0.6967 13.3572 0.455529 13.535 0.277713C13.7128 0.0998972 13.954 9.02461e-07 14.2054 8.69485e-07C14.4569 8.36508e-07 14.6981 0.099897 14.8759 0.277713L20.5577 5.95954Z" fill="black" fill-opacity="0.2"/>
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M20.5577 5.95954C20.6459 6.04751 20.7159 6.15201 20.7636 6.26706C20.8114 6.3821 20.8359 6.50544 20.8359 6.63C20.8359 6.75456 20.8114 6.8779 20.7636 6.99294C20.7159 7.10799 20.6459 7.21249 20.5577 7.30046L14.8759 12.9823C14.6981 13.1601 14.4569 13.26 14.2054 13.26C13.954 13.26 13.7128 13.1601 13.535 12.9823C13.3572 12.8045 13.2573 12.5633 13.2573 12.3118C13.2573 12.0604 13.3572 11.8192 13.535 11.6414L18.5483 6.63L13.535 1.61863C13.3572 1.44081 13.2573 1.19964 13.2573 0.94817C13.2573 0.6967 13.3572 0.455529 13.535 0.277713C13.7128 0.0998972 13.954 9.02461e-07 14.2054 8.69485e-07C14.4569 8.36508e-07 14.6981 0.099897 14.8759 0.277713L20.5577 5.95954Z" fill="black" fill-opacity="0.2"/>
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M20.5577 5.95954C20.6459 6.04751 20.7159 6.15201 20.7636 6.26706C20.8114 6.3821 20.8359 6.50544 20.8359 6.63C20.8359 6.75456 20.8114 6.8779 20.7636 6.99294C20.7159 7.10799 20.6459 7.21249 20.5577 7.30046L14.8759 12.9823C14.6981 13.1601 14.4569 13.26 14.2054 13.26C13.954 13.26 13.7128 13.1601 13.535 12.9823C13.3572 12.8045 13.2573 12.5633 13.2573 12.3118C13.2573 12.0604 13.3572 11.8192 13.535 11.6414L18.5483 6.63L13.535 1.61863C13.3572 1.44081 13.2573 1.19964 13.2573 0.94817C13.2573 0.6967 13.3572 0.455529 13.535 0.277713C13.7128 0.0998972 13.954 9.02461e-07 14.2054 8.69485e-07C14.4569 8.36508e-07 14.6981 0.099897 14.8759 0.277713L20.5577 5.95954Z" fill="black" fill-opacity="0.2"/>
                </svg>
            </span>`
        ],
        responsive: {
            0: {
                items: 4,
                margin: 8
            },
            480: {
                items: 4,
                margin: 10
            },
            768: {
                items: 4,
                margin: 12
            },
            992: {
                items: 5,
                margin: 20
            },
            1200: {
                items: 8,
                margin: 30
            }
        }
    };

    function initCategoryCarousel() {
        if (typeof $.fn.owlCarousel === 'undefined') {
            console.log('Owl not loaded');
            return;
        }

        if ($categoryWrapper.hasClass('owl-loaded')) return;

        $categoryWrapper.addClass('owl-carousel');
        $categoryWrapper.owlCarousel(owlOptions);
    }

    initCategoryCarousel();

});

jQuery(function ($) {

    const $carousel = $('#gc-carousel');
    const $search = $('#gc-search');

    // Check if we're on brands page (has filters)
    const isBrandsPage = $('.gc-filters').length > 0;

    // On search - handle both regular page and brands page
    $search.on('keyup', function () {
        let value = $(this).val().trim();

        // For brands page, trigger filter update (which includes search)
        if (isBrandsPage) {
            // Trigger filter change to reload with search
            if (value.length >= 2 || value.length === 0) {
                $('.gc-filter-select').first().trigger('change');
            }
        } else {
            // Regular page search
            if (value.length >= 2 || value.length === 0) {
                loadProducts(value);
            }
        }
    });

    jQuery(document).on("click", "#egift-clear-search", function () {

        console.log("egift-search....");

        var $wrapper = jQuery(this).closest(".gc-search-wrap");
        var $search  = $wrapper.find(".egift-search");

        $search.val("").focus().trigger("input");

        jQuery(this).removeClass("show").hide();
        loadProducts();

    });

    // Handle search button click
    $('.search-submit').on('click', function (e) {
        // Only intercept on pages with the custom product carousel — let native WP search pages submit normally.
        if (!$carousel.length) {
            return;
        }
        e.preventDefault();
        let value = ($search.val() || '').trim();

        if (isBrandsPage) {
            // Trigger filter change to reload with search
            $('.gc-filter-select').first().trigger('change');
        } else {
            // Regular page search
            loadProducts(value);
        }
    });

    function loadProducts(query) {
        $.ajax({
            url: homePageData.ajax_url,
            method: "POST",
            data: {
                action: "gc_search_products",
                s: query,
                security: homePageData.nonce
            },
            success: function (data) {
                // clear and replace
                $carousel.trigger('destroy.owl.carousel');
                $carousel.html(data);
            }
        });
    }
});


jQuery(document).ready(function ($) {

    function equalHeightAllCarousels() {

        // ALL ITEMS FROM ALL CAROUSELS
        var $items = $(
            '.trending-carousel .owl-item,' +
            '.top-picks-carousel .owl-item,' +
            '.gc-carousel .gc-slide a.product-card-link'
        );

        if (!$items.length) return;

        // RESET HEIGHT
        $items.css('height', 'auto');

        var maxHeight = 0;

        // FIND MAX HEIGHT
        $items.each(function () {
            var h = $(this).outerHeight(true);
            if (h > maxHeight) {
                maxHeight = h;
            }
        });

        // APPLY SAME HEIGHT
        $items.height(maxHeight);
    }


    /* ==========================
       IMPORTANT TRIGGERS
    ========================== */

    // After everything loaded (images included)
    $(window).on('load', function () {
        //setTimeout(equalHeightAllCarousels, 500);
    });

    // Owl carousel ready / refreshed
    $('.trending-carousel, .top-picks-carousel, .gc-carousel')
        .on('initialized.owl.carousel refreshed.owl.carousel translated.owl.carousel', function () {

            //setTimeout(equalHeightAllCarousels, 300);
        });

    // Resize Fix
    $(window).on('resize', function () {
        //setTimeout(equalHeightAllCarousels, 300);
        truncateProductTags();
    });

    // Show .product-tag-tooltip on hover of .product-tag--more, positioned fixed
    $(document).on('mouseenter', '.product-tags-wrap .product-tag--more', function () {
        var tooltip = this.parentElement.querySelector('.product-tag-tooltip');
        if (!tooltip) return;
        var rect = this.getBoundingClientRect();
        tooltip.style.position  = 'fixed';
        tooltip.style.zIndex    = '999999';
        tooltip.style.left      = (rect.left + rect.width / 2) + 'px';
        tooltip.style.top       = rect.top + 'px';
        tooltip.style.transform = 'translateX(-50%) translateY(calc(-100% - 6px))';
        tooltip.style.display   = 'flex';
    });

    $(document).on('mouseleave', '.product-tags-wrap .product-tag--more', function (e) {
        var tooltip = this.parentElement.querySelector('.product-tag-tooltip');
        if (!tooltip) return;
        if (e.relatedTarget && tooltip.contains(e.relatedTarget)) return;
        tooltip.style.display = 'none';
    });

    $(document).on('mouseleave', '.product-tags-wrap .product-tag-tooltip', function (e) {
        var moreBtn = this.parentElement.querySelector('.product-tag--more');
        if (e.relatedTarget && moreBtn && moreBtn.contains(e.relatedTarget)) return;
        this.style.display = 'none';
    });

    function truncateProductTags() {
        document.querySelectorAll('.gc-product-tags.product-tags-wrap').forEach(function (wrap) {
            var moreBtn = wrap.querySelector('.product-tag--more');
            var tooltip = wrap.querySelector('.product-tag-tooltip');

            // Step 1: Move any tags sitting inside the tooltip back into the main list
            // (PHP splits tags across main list + tooltip; we need them all in one place to measure)
            if (tooltip) {
                var insertPoint = moreBtn || tooltip;
                Array.from(tooltip.querySelectorAll('.product-tag')).forEach(function (t) {
                    t.style.display = '';
                    wrap.insertBefore(t, insertPoint);
                });
                tooltip.innerHTML = '';
            }

            // Step 2: All main-list tags (direct children .product-tag, not the --more button)
            var tags = Array.from(wrap.children).filter(function (el) {
                return el.classList.contains('product-tag') && !el.classList.contains('product-tag--more');
            });
            if (!tags.length) return;

            // Step 3: Show all tags, hide more button for clean measurement
            tags.forEach(function (t) { t.style.display = ''; });
            if (moreBtn) moreBtn.style.display = 'none';

            var wrapRight = wrap.getBoundingClientRect().right;

            // Step 4: Find first tag whose right edge exceeds the container
            var overflowAt = -1;
            for (var i = 0; i < tags.length; i++) {
                if (tags[i].getBoundingClientRect().right > wrapRight + 1) {
                    overflowAt = i;
                    break;
                }
            }

            if (overflowAt === -1) return; // all tags fit

            // Step 5: Show the more button and check if it also overflows
            if (moreBtn) {
                moreBtn.style.display = '';
                if (moreBtn.getBoundingClientRect().right > wrapRight + 1 && overflowAt > 0) {
                    overflowAt--;
                }
            }

            // Step 6: Hide overflow tags in main list, move them into the tooltip
            tags.slice(overflowAt).forEach(function (t) {
                t.style.display = 'none';
                if (tooltip) {
                    t.style.display = '';
                    tooltip.appendChild(t);
                }
            });
        });
    }

    // Run after fonts load for accurate measurements
    if (document.fonts && document.fonts.ready) {
        document.fonts.ready.then(truncateProductTags);
    } else {
        setTimeout(truncateProductTags, 300);
    }

});