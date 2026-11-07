jQuery(document).ready(function($) {
    'use strict';

    // License Activation Form Handler
    $('#rs-license-activation-form').on('submit', function(e) {
        e.preventDefault();

        var $form = $(this);
        var $submitBtn = $form.find('input[type="submit"]');
        var $licenseInput = $('#royalsentinel-license-key');
        var licenseKey = $licenseInput.val().trim();

        // Disable submit button
        $submitBtn.prop('disabled', true).val('Activating...');

        // AJAX Request
        $.ajax({
            url: rsLicenseData.ajaxurl, // [BUG FIX #4] global ajaxurl এর বদলে localized data ব্যবহার
            type: 'POST',
            data: {
                action: 'rs_activate_license',
                license_key: licenseKey,
                nonce: rsLicenseData.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Show success message with SweetAlert2
                    Swal.fire({
                        title: 'Success!',
                        text: response.data.message || 'License activated successfully!',
                        icon: 'success',
                        timer: 1500,
                        showConfirmButton: false
                    });
                    
                    // Redirect to dashboard after 1.5 seconds
                    setTimeout(function() {
                        window.location.href = response.data.redirect;
                    }, 1500);
                } else {
                    // Show error message with SweetAlert2
                    Swal.fire({
                        title: 'Activation Failed',
                        text: response.data.message || 'Invalid license key.',
                        icon: 'error'
                    });
                    
                    // Re-enable submit button
                    $submitBtn.prop('disabled', false).val('Activate License');
                    $licenseInput.val('').focus();
                }
            },
            error: function() {
                Swal.fire({
                    title: 'System Error',
                    text: 'An error occurred. Please try again.',
                    icon: 'error'
                });
                $submitBtn.prop('disabled', false).val('Activate License');
            }
        });
    });
});
