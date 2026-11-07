/**
 * Royal Sentinel Honeypot - Frontend Script
 * Injects honeypot fields and timestamp into forms
 */
(function ($) {
    'use strict';

    // Get field info from localized script
    var fieldName = rs_honeypot_info.field_name || 'rs_hp_field';
    var fieldValue = rs_honeypot_info.field_value || '';
    var timeCheck = rs_honeypot_info.time_check === 'yes';

    // Current timestamp
    var formLoadTime = Math.floor(Date.now() / 1000);

    // Hidden honeypot field HTML
    var honeypotField = '<div class="rs-hp-container" aria-hidden="true">' +
        '<label for="rs_alt_email">Alternative Email:</label>' +
        '<input type="text" id="rs_alt_email" name="rs_alt_email" autocomplete="off" tabindex="-1">' +
        '</div>' +
        '<input type="hidden" name="' + fieldName + '" value="' + fieldValue + '">';

    // Add timestamp field if time check is enabled
    if (timeCheck) {
        honeypotField += '<input type="hidden" name="rs_form_time" value="' + formLoadTime + '">';
    }

    // Form selectors to inject honeypot
    var formSelectors = [
        // WordPress Core
        'form#commentform',
        'form.comment-form',
        'form#registerform',
        // Note: form#loginform excluded — no server-side honeypot validation for login
        'form#lostpasswordform',

        // Contact Form 7
        'form.wpcf7-form',
        '.wpcf7 form',

        // WPForms
        'form.wpforms-form',

        // Gravity Forms
        '.gform_wrapper form',

        // Formidable Forms
        '.frm_forms form',

        // Caldera Forms
        '.caldera-grid form',

        // Toolset Forms
        '.wp-block-toolset-cred-form form',
        'form.cred-user-form',
        'form.cred-form',

        // Elementor Form
        'form.elementor-form',

        // Fluent Forms
        'form.frm-fluent-form',
        '.ff_conv_app',

        // Divi Theme
        'form.et_pb_contact_form',
        '.et_pb_login_form form',

        // WooCommerce
        'form.woocommerce-checkout',
        'form.woocommerce-form-login',
        'form.woocommerce-form-register',
        'form.woocommerce-ResetPassword',

        // BuddyPress/BuddyBoss
        'form#buddypress',
        '.bp-login-widget-form form',

        // BBPress
        '.bbp-topic-form form',
        '.bbp-reply-form form',

        // Ultimate Member
        '.um-form form',

        // Theme My Login
        'form.tml-form',

        // General forms
        'form.rs-honeypot-protect',

        // Ninja Forms (handled via specific hook)
        // Bricks Builder
        '.brxe-form form',

        // Sure Forms
        '.sureforms-block form',

        // Everest Forms
        '.everest-forms form',
    ];

    /**
     * Add honeypot to all matching forms
     */
    function addHoneypotToForms() {
        $(formSelectors.join(', ')).each(function () {
            var $form = $(this);

            // Don't add if already has honeypot
            if ($form.find('input[name="' + fieldName + '"]').length > 0) {
                return;
            }

            // Don't add to search forms
            if ($form.attr('role') === 'search' || $form.hasClass('search-form')) {
                return;
            }

            // Append honeypot field
            $form.append(honeypotField);
        });
    }

    /**
     * Add test widget (only for admins)
     * Uses AJAX to check admin status in real-time to bypass page caching
     */
    function addTestWidget() {
        // Skip if no ajax_url available
        if (!rs_honeypot_info.ajax_url) return;

        // Don't re-check if already checked
        if (window.rs_honeypot_admin_checked) {
            if (window.rs_honeypot_is_admin) {
                renderTestWidget();
            }
            return;
        }

        // Prevent concurrent/duplicate AJAX calls (avoids 429 loop)
        if (window.rs_honeypot_checking_admin) return;
        window.rs_honeypot_checking_admin = true;

        // Make AJAX call to check admin status (bypasses cache)
        $.ajax({
            url: rs_honeypot_info.ajax_url,
            type: 'POST',
            data: {
                action: 'rs_honeypot_check_admin'
            },
            success: function (response) {
                window.rs_honeypot_admin_checked = true;
                window.rs_honeypot_checking_admin = false;
                window.rs_honeypot_is_admin = (response && response.show_test === 'yes');

                if (window.rs_honeypot_is_admin) {
                    renderTestWidget();
                }
            },
            error: function () {
                window.rs_honeypot_admin_checked = true;
                window.rs_honeypot_checking_admin = false;
                window.rs_honeypot_is_admin = false;
            }
        });
    }

    /**
     * Render the test widget (called after admin verification)
     */
    function renderTestWidget() {
        var testWidget = '<div class="rs-hp-test-widget" style="margin-top:15px;padding:10px;background:#fff3cd;border:1px solid #ffc107;border-radius:5px;font-size:12px;">' +
            '<strong>🍯 Royal Sentinel Honeypot</strong> (Visible to admins only)<br>' +
            '<span style="color:#666;">This form is protected. </span>' +
            '<button type="button" class="rs-hp-test-btn" style="margin-left:10px;cursor:pointer;background:#dc3545;color:#fff;border:none;padding:2px 8px;border-radius:3px;">Test as Bot</button>' +
            '</div>';

        $('input[name="' + fieldName + '"]').each(function () {
            var $input = $(this);
            if ($input.siblings('.rs-hp-test-widget').length === 0) {
                $input.after(testWidget);
            }
        });

        // Test button click
        $(document).off('click', '.rs-hp-test-btn').on('click', '.rs-hp-test-btn', function () {
            var $btn = $(this);
            var $form = $btn.closest('form');

            if ($btn.data('testing')) {
                // Restore honeypot
                $form.find('input[name="' + fieldName + '"]').val(fieldValue);
                $btn.text('Test as Bot').css('background', '#dc3545');
                $btn.data('testing', false);
            } else {
                // Remove honeypot to simulate bot
                $form.find('input[name="' + fieldName + '"]').val('');
                $btn.text('Acting as Bot').css('background', '#28a745');
                $btn.data('testing', true);
            }
        });
    }

    // Initialize on DOM ready
    $(document).ready(function () {
        addHoneypotToForms();
        addTestWidget();
    });

    // Re-run for dynamically loaded forms (AJAX) - only honeypot injection, not test widget
    $(document).ajaxComplete(function () {
        setTimeout(function () {
            addHoneypotToForms();
        }, 500);
    });

    // MutationObserver for dynamic content - only honeypot injection, not test widget
    if (typeof MutationObserver !== 'undefined') {
        var observer = new MutationObserver(function (mutations) {
            var shouldCheck = false;
            mutations.forEach(function (mutation) {
                if (mutation.addedNodes.length > 0) {
                    shouldCheck = true;
                }
            });
            if (shouldCheck) {
                setTimeout(function () {
                    addHoneypotToForms();
                }, 100);
            }
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }

})(jQuery);
