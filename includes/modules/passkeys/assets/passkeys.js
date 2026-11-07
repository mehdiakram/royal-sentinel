/**
 * Royal Sentinel Passkeys Authentication
 */
jQuery(document).ready(function($) {

    const opts = window.rsPasskeysOpts || {};

    // Helper: deeply convert base64url strings to Uint8Array
    function recursiveBase64ToArrayBuffer(obj) {
        if (typeof obj === 'string') return rsBase64url.decode(obj);
        if (Array.isArray(obj)) return obj.map(recursiveBase64ToArrayBuffer);
        if (obj !== null && typeof obj === 'object') {
            const res = {};
            for (const key in obj) {
                if (['id','rawId','userHandle','challenge'].includes(key)) {
                    res[key] = (typeof obj[key] === 'string') ? rsBase64url.decode(obj[key]) : obj[key];
                } else {
                    res[key] = recursiveBase64ToArrayBuffer(obj[key]);
                }
            }
            return res;
        }
        return obj;
    }

    // SweetAlert loading helper
    function swalLoading(title, text) {
        if (typeof Swal === 'undefined') return;
        Swal.fire({
            title: title,
            text: text || '',
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            didOpen: () => Swal.showLoading()
        });
    }

    function swalError(title, text) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({ icon: 'error', title: title, text: text });
        } else {
            alert(text);
        }
    }

    /**
     * ── Registration Flow ──────────────────────────────────
     */
    $('#rs-register-passkey').on('click', async function() {
        if (!window.PublicKeyCredential) {
            swalError('Not Supported', opts.i18n.notSupported);
            return;
        }

        const btn = $(this);
        const nameInput = $('#rs-new-passkey-name').val() || 'Primary Passkey';

        btn.prop('disabled', true).text('Registering...');
        swalLoading('Registering Device...', 'Please complete the biometric prompt on your device.');

        try {
            // 1. Get Options
            const optionsRes = await fetch(opts.restUrl + 'royal-sentinel/v1/passkeys/register/options', {
                method: 'POST',
                headers: { 'X-WP-Nonce': opts.nonce, 'Content-Type': 'application/json' }
            });
            const options = await optionsRes.json();
            if (!optionsRes.ok) throw new Error(options.message || 'Options failed');

            // 2. Create challenge
            options.publicKey.challenge = rsBase64url.decode(options.publicKey.challenge);
            options.publicKey.user.id   = rsBase64url.decode(options.publicKey.user.id);
            if (options.publicKey.excludeCredentials) {
                options.publicKey.excludeCredentials.forEach(c => c.id = rsBase64url.decode(c.id));
            }

            const credential = await navigator.credentials.create({ publicKey: options.publicKey });

            // 3. Verify
            const responseData = {
                id:     credential.id,
                rawId:  rsBase64url.encode(credential.rawId),
                type:   credential.type,
                response: {
                    attestationObject: rsBase64url.encode(credential.response.attestationObject),
                    clientDataJSON:    rsBase64url.encode(credential.response.clientDataJSON),
                }
            };

            const verifyRes  = await fetch(opts.restUrl + 'royal-sentinel/v1/passkeys/register/verify', {
                method: 'POST',
                headers: { 'X-WP-Nonce': opts.nonce, 'Content-Type': 'application/json' },
                body: JSON.stringify({ response: responseData, device_name: nameInput })
            });
            const verifyJson = await verifyRes.json();

            if (verifyRes.ok && verifyJson.success) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Device Registered! 🎉',
                        text: opts.i18n.successReg || 'Your passkey has been saved successfully.',
                        showConfirmButton: false,
                        timer: 2000,
                        timerProgressBar: true
                    }).then(() => location.reload());
                } else {
                    alert(opts.i18n.successReg);
                    location.reload();
                }
            } else {
                throw new Error(verifyJson.message || 'Verification failed');
            }

        } catch (err) {
            console.error(err);
            swalError('Registration Failed', err.message || opts.i18n.error);
        } finally {
            btn.prop('disabled', false).text('Register New Passkey');
        }
    });

    /**
     * ── Login Flow ─────────────────────────────────────────
     */
    $('.rs-login-passkey-btn').on('click', async function(e) {
        e.preventDefault();

        if (!window.PublicKeyCredential) {
            swalError('Not Supported', opts.i18n.notSupported);
            return;
        }

        const btn          = $(this);
        const status       = btn.siblings('.rs-passkey-status');
        const usernameField = $('#user_login').val() || '';

        // Require username before starting — server now enforces this too.
        if ( ! usernameField.trim() ) {
            swalError( 'Username Required', 'Please enter your username first, then click Sign in with a Passkey.' );
            $('#user_login').focus();
            return;
        }

        btn.prop('disabled', true);

        // Show "Connecting" popup
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: '🔐 Connecting Securely...',
                text: 'Touch your fingerprint sensor or use Windows Hello',
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                didOpen: () => Swal.showLoading()
            });
        } else {
            status.text('Connecting securely...');
        }

        try {
            // 1. Get Request Options
            const optionsRes = await fetch(opts.restUrl + 'royal-sentinel/v1/passkeys/login/options', {
                method: 'POST',
                headers: { 'X-WP-Nonce': opts.nonce, 'Content-Type': 'application/json' },
                body: JSON.stringify({ username: usernameField })
            });
            
            // Check if response is HTML (Error page) before parsing JSON
            const contentType = optionsRes.headers.get("content-type");
            if (contentType && contentType.indexOf("application/json") === -1) {
                throw new Error("Server returned an invalid response. Please check your firewall or REST API settings.");
            }
            const options = await optionsRes.json();
            if (!optionsRes.ok) throw new Error(options.message || 'Options failed');

            options.challenge = rsBase64url.decode(options.challenge);
            if (options.allowCredentials) {
                options.allowCredentials.forEach(c => c.id = rsBase64url.decode(c.id));
            }

            // 2. Biometric assertion
            const assertion = await navigator.credentials.get({ publicKey: options });

            // 3. Verify
            const responseData = {
                id:     assertion.id,
                rawId:  rsBase64url.encode(assertion.rawId),
                type:   assertion.type,
                response: {
                    authenticatorData: rsBase64url.encode(assertion.response.authenticatorData),
                    clientDataJSON:    rsBase64url.encode(assertion.response.clientDataJSON),
                    signature:         rsBase64url.encode(assertion.response.signature),
                    userHandle:        assertion.response.userHandle ? rsBase64url.encode(assertion.response.userHandle) : null
                }
            };

            const verifyRes  = await fetch(opts.restUrl + 'royal-sentinel/v1/passkeys/login/verify', {
                method: 'POST',
                headers: { 'X-WP-Nonce': opts.nonce, 'Content-Type': 'application/json' },
                body: JSON.stringify({ response: responseData, rs_uid: options.rs_uid })
            });
            
            // Check if response is HTML (Error page) before parsing JSON
            const verifyContentType = verifyRes.headers.get("content-type");
            if (verifyContentType && verifyContentType.indexOf("application/json") === -1) {
                throw new Error("Server returned an invalid response during verification.");
            }
            const verifyJson = await verifyRes.json();

            if (verifyRes.ok && verifyJson.success) {
                const redirectUrl = verifyJson.redirect || '/wp-admin/';
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Login Successful! ✅',
                        text: 'Redirecting to your dashboard...',
                        showConfirmButton: false,
                        timer: 1500,
                        timerProgressBar: true
                    }).then(() => { window.location.href = redirectUrl; });
                } else {
                    status.text(opts.i18n.successLogin);
                    window.location.href = redirectUrl;
                }
            } else {
                throw new Error(verifyJson.message || 'Verification failed');
            }

        } catch (err) {
            console.error(err);
            const msg = err.message || opts.i18n.error;
            if (typeof Swal !== 'undefined') {
                Swal.fire({ icon: 'error', title: 'Authentication Failed', text: msg });
            } else {
                status.text(msg);
            }
        } finally {
            btn.prop('disabled', false);
        }
    });

});
