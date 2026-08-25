jQuery(document).ready(function ($) {

    /***** HIGHLIGHT ACTIVE MY ACCOUNT MENU ITEM *****/
    // Get current URL and remove query strings like ?v=1 and trailing slashes
    var currentUrl = window.location.href.split('?')[0].replace(/\/$/, "");

    // Loop through all My Account navigation links
    $('.woocommerce-MyAccount-navigation-link a').each(function() {
        
        // Get the link href (clean it up similarly)
        var linkUrl = $(this).attr('href').split('?')[0].replace(/\/$/, "");

        // Check for an Exact Match
        if (currentUrl === linkUrl) {
            
            // Remove 'is-active' from all other items to prevent duplicates
            $('.woocommerce-MyAccount-navigation-link').removeClass('is-active');
            
            // Add 'is-active' to the parent <li> of the current link
            $(this).parent('li').addClass('is-active');
        }
    });

    // Special Fallback for the main "My Account" Dashboard
    // This fixes the specific issue where the dashboard URL is ".../my-account/" 
    // but your menu link is improperly generated as ".../my-account/my-account/"
    var pathname = window.location.pathname; // e.g., "/my-account/"

    // If the path ends strictly with /my-account/ (ignoring sub-pages like /my-wallet/)
    if (pathname.replace(/\/$/, "").endsWith("my-account")) {
        // Force the active class on the specific My Account list item
        $('.woocommerce-MyAccount-navigation-link--my-account').addClass('is-active');
    }


    const btn = document.querySelector(".gc-complete-btn");

    if (btn) {
        btn.addEventListener("click", function (e) {
            console.log("hello");
            e.preventDefault();

            let firstPending = document.querySelector(".gc-check-item.pending");

            if (!firstPending) {
                return;
            }

            let labelEl = firstPending.querySelector(".pending-text");

            if (!labelEl) {
                return;
            }

            let label = labelEl.textContent.trim();

            switch (label) {
                case "Basic details completed":
                    validateAndScroll([
                        "#user-first_name",
                        "#user-last_name",
                        "#user-phone_number",
                        "#user-email",
                        "#user-dob",
                        "#billing_state"
                    ]);
                    break;

                case "Email verified":
                    highlightAndScroll("#user-email");
                    break;

                case "Add phone number":
                    highlightAndScroll("#user-phone_number");
                    break; 
                case "Add date of birth":
                    highlightAndScroll("#user-dob");
                    break;
                case "Add Hobbies":
                case "Add Interested Events":
                    window.location.href = window.location.origin + "/my-account/my-preferences/";
                    break;
                default:
                    break;
            }

        });
    }

    // Scroll + highlight multiple fields until first empty found
    function validateAndScroll(selectorList) {
        for (let sel of selectorList) {
            let field = document.querySelector(sel);
            if (field && field.value.trim() === "") {
                highlightAndScroll(sel);
                return;
            }
        }
    }

    // Scroll and highlight ANY field
    function highlightAndScroll(selector) {
        let el = document.querySelector(selector);
        if (!el) return;

        el.classList.add("invalid-data");
        el.scrollIntoView({ behavior: "smooth", block: "center" });

        setTimeout(() => {
            el.classList.remove("invalid-data");
        }, 5000);
    }
});