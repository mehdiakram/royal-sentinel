<?php
// File: includes/royalsentinel-license-functions.php
// Implemented as per: License_Integration_Guide.md (RoyalTechBD)

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ============================================================
// CONSTANTS (Guide: Step 3a — config/license.php equivalent)
// ============================================================

define( 'RS_LICENSE_SOFTWARE',      'royalsentinel' );
define( 'RS_LICENSE_PUBLIC_KEY',    'vE7PkX4ho5A4ZVWH5KwToDcv1cPIY6FJsNU4tDjbNIw=' );
define( 'RS_LICENSE_VERIFY_URL',    'https://auth.royaltechbd.com/api/verify' );
define( 'RS_LICENSE_ACTIVITY_URL',  'https://auth.royaltechbd.com/activities' );

// ============================================================
// HELPER: Base Domain Extractor
// ============================================================

/**
 * Get the base domain name from a host string.
 * Handles ccSLDs (e.g. .co.uk, .com.bd) using ccsld.json.
 */
function royalsentinel_get_base_domain( $host ) {
    static $all_ccslds = null;

    if ( $all_ccslds === null ) {
        $all_ccslds = [];
        $ccsld_path = ROYALSENTINEL_PLUGIN_DIR . 'includes/ccsld.json';

        if ( file_exists( $ccsld_path ) ) {
            $ccsld_json = file_get_contents( $ccsld_path );
            $ccsld_data = json_decode( $ccsld_json, true );

            if ( is_array( $ccsld_data ) ) {
                foreach ( $ccsld_data as $country_slds ) {
                    if ( is_array( $country_slds ) ) {
                        $all_ccslds = array_merge( $all_ccslds, $country_slds );
                    }
                }
                usort( $all_ccslds, function( $a, $b ) {
                    return strlen( $b ) - strlen( $a );
                } );
            }
        }
    }

    $host = strtolower( trim( $host ) );

    if ( ! empty( $all_ccslds ) ) {
        foreach ( $all_ccslds as $sld ) {
            if ( preg_match( '/\\.' . preg_quote( $sld, '/' ) . '$/i', $host ) ) {
                $host_without_sld = substr( $host, 0, strlen( $host ) - strlen( $sld ) - 1 );
                $parts            = explode( '.', $host_without_sld );
                $domain_part      = end( $parts );
                return $domain_part . '.' . $sld;
            }
        }
    }

    $host  = preg_replace( '/^www\./', '', $host );
    $parts = explode( '.', $host );
    $count = count( $parts );

    if ( $count >= 2 ) {
        return $parts[ $count - 2 ] . '.' . $parts[ $count - 1 ];
    }

    return $host;
}

// ============================================================
// HELPER: Expiry Encryption (offline fallback for non-lifetime)
// ============================================================

/**
 * Encrypt an expiry date string for secure offline storage.
 */
function royalsentinel_encrypt_expiry( $plain ) {
    if ( ! function_exists( 'openssl_encrypt' ) ) {
        return base64_encode( $plain );
    }
    $key = substr( wp_salt( 'auth' ), 0, 32 );
    $iv  = substr( wp_salt( 'secure_auth' ), 0, 16 );
    $enc = openssl_encrypt( $plain, 'AES-256-CBC', $key, 0, $iv );
    return $enc ? base64_encode( $enc ) : false;
}

/**
 * Decrypt an expiry date string.
 */
function royalsentinel_decrypt_expiry( $encrypted ) {
    if ( ! function_exists( 'openssl_decrypt' ) ) {
        return base64_decode( $encrypted );
    }
    $key = substr( wp_salt( 'auth' ), 0, 32 );
    $iv  = substr( wp_salt( 'secure_auth' ), 0, 16 );
    return openssl_decrypt( base64_decode( $encrypted ), 'AES-256-CBC', $key, 0, $iv );
}

// ============================================================
// CORE: Ed25519 Signature Verification Engine
// (License_Integration_Guide.md — Step 3b: verifyRoyalLicenseData)
// ============================================================

/**
 * Core License Verification Engine — Ed25519.
 *
 * This function is PURE LOCAL — no HTTP calls, no DB writes.
 * It only reads the cached response and verifies the cryptographic signature.
 *
 * Verifies:
 *  1. Ed25519 signature  (tamper prevention)
 *  2. Domain lock        (cryptographically bound to signature)
 *  3. Expiry + grace     (offline support — no server needed)
 *
 * @param array|null $localCache  The cached response from auth.royaltechbd.com
 * @return bool
 */
function royalsentinel_verify_license_cache( $localCache ) {
    if ( ! $localCache || ! isset( $localCache['signature'], $localCache['license_data'] ) ) {
        return false;
    }

    // 1. Ed25519 Signature Verification — prevents tampering and domain forgery
    try {
        $publicKey = base64_decode( RS_LICENSE_PUBLIC_KEY );
        $signature = base64_decode( $localCache['signature'] );
        $message   = json_encode( $localCache['license_data'] );

        if ( ! sodium_crypto_sign_verify_detached( $signature, $message, $publicKey ) ) {
            return false; // Signature invalid — data tampered or wrong key!
        }
    } catch ( \Exception $e ) {
        return false;
    }

    $data = $localCache['license_data'];

    // 2. Domain Lock — domain is cryptographically bound to signature
    $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
    if ( strpos( $host, ':' ) !== false ) {
        $host = explode( ':', $host )[0];
    }
    $currentDomain       = royalsentinel_get_base_domain( $host );
    $allowedLocalDomains = [ 'localhost', '127.0.0.1' ];

    if (
        ! in_array( $currentDomain, $allowedLocalDomains ) &&
        strpos( $currentDomain, '.test' ) === false &&
        strpos( $currentDomain, '.local' ) === false &&
        $currentDomain !== ( $data['domain'] ?? '' )
    ) {
        return false; // Domain mismatch — DB copied to another domain!
    }

    // 3. Expiry Check with Grace Period
    $now       = time();
    $expiresAt = ! empty( $data['expires_at'] ) ? strtotime( $data['expires_at'] ) : null;
    $type      = $data['type'] ?? 'lifetime';

    // Lifetime: only check status (works forever, even without server)
    if ( $type === 'lifetime' ) {
        return ( $data['status'] ?? '' ) === 'active';
    }

    if ( $expiresAt && $now > $expiresAt ) {
        $graceDays = ( $type === 'yearly' ) ? 30 : 3;
        return $now <= ( $expiresAt + ( $graceDays * 24 * 60 * 60 ) );
    }

    return ( $data['status'] ?? '' ) === 'active';
}

// ============================================================
// MAIN: royalsentinel_is_license_valid()
// (License_Integration_Guide.md — Step 3c: is_licensed())
//
// ⚡ ZERO HTTP on normal page loads — pure local verification only.
//    HTTP re-validation happens ONLY in the daily WP-Cron job (Step 4).
// ============================================================

/**
 * Main License Validity Check.
 *
 * Flow (Guide Step 3c — adapted for WordPress, 524-safe):
 *  1. Fast-path: check stored status flag (DB option read — microseconds)
 *  2. Load locally cached signed response from DB
 *  3. Verify Ed25519 signature locally — NO server needed
 *  4. Return result immediately
 *
 * HTTP re-validation is intentionally NOT done here.
 * It runs in royalsentinel_cron_license_ping() via WP-Cron (Step 4).
 *
 * @return bool
 */
function royalsentinel_is_license_valid() {

    // --- Fast path: check status flag set during activation / cron sync ---
    if ( get_option( 'royalsentinel_license_status', 'inactive' ) !== 'active' ) {
        return false;
    }

    // --- Load license key ---
    $saved_license = get_option( 'royalsentinel_license_key', '' );
    if ( empty( $saved_license ) ) {
        $license_settings = get_option( 'royalsentinel_license_settings', [] );
        $saved_license    = $license_settings['royalsentinel_license_key'] ?? '';
        if ( empty( $saved_license ) ) return false;
    }

    // --- Load locally cached signed response ---
    $localCache = get_option( 'royalsentinel_license_cache', [] );
    if ( empty( $localCache ) ) {
        return false;
    }

    // --- Verify Ed25519 signature locally — no HTTP, no blocking ---
    return royalsentinel_verify_license_cache( $localCache );
}

// ============================================================
// CRON: Daily License Ping
// (License_Integration_Guide.md — Step 4: Cron Job — Daily Ping)
//
// This is the ONLY place where HTTP calls to auth.royaltechbd.com happen.
// Runs in background via WP-Cron — never blocks a page request.
// ============================================================

/**
 * Perform the daily license ping to the auth server.
 * Called exclusively by WP-Cron — never on page load.
 *
 * @return void
 */
function royalsentinel_cron_license_ping() {

    $saved_license = get_option( 'royalsentinel_license_key', '' );
    if ( empty( $saved_license ) ) {
        $license_settings = get_option( 'royalsentinel_license_settings', [] );
        $saved_license    = $license_settings['royalsentinel_license_key'] ?? '';
    }

    // Nothing to ping if no license key is saved
    if ( empty( $saved_license ) ) {
        return;
    }

    // Determine domain
    $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? home_url();
    $host = parse_url( $host, PHP_URL_HOST ) ?: $host;
    if ( strpos( $host, ':' ) !== false ) {
        $host = explode( ':', $host )[0];
    }
    $domain = royalsentinel_get_base_domain( $host );

    // Build user-agent
    global $wp_version;
    $active_theme = wp_get_theme();
    $theme_name   = $active_theme ? $active_theme->get( 'Name' ) : 'Unknown';
    $user_agent   = sprintf(
        'WordPress/%s; PHP/%s; Theme/%s; Url/%s',
        $wp_version, PHP_VERSION, $theme_name, home_url()
    );

    // --- HTTP POST to auth server (Guide Step 4) ---
    $response = wp_remote_post( RS_LICENSE_VERIFY_URL, [
        'timeout'    => 15,
        'user-agent' => $user_agent,
        'sslverify'  => false,
        'body'       => [
            'software'    => RS_LICENSE_SOFTWARE,
            'license_key' => $saved_license,
            'domain'      => $domain,
        ],
    ] );

    // Server unreachable — fall back to local cache (Guide Step 4 fallback)
    if ( is_wp_error( $response ) ) {
        $localCache = get_option( 'royalsentinel_license_cache', [] );
        $localValid = ! empty( $localCache ) ? royalsentinel_verify_license_cache( $localCache ) : false;

        if ( ! $localValid ) {
            // Last resort: encrypted expiry for offline grace (non-lifetime licenses)
            $encExpiry = get_option( 'royalsentinel_license_expiry_enc' );
            if ( $encExpiry ) {
                $expiryStr   = royalsentinel_decrypt_expiry( $encExpiry );
                $expiryTime  = $expiryStr ? strtotime( $expiryStr ) : 0;
                $graceExpiry = $expiryTime + ( 3 * 24 * 60 * 60 ); // 3-day grace

                if ( time() > $graceExpiry ) {
                    update_option( 'royalsentinel_license_status', 'inactive' );
                    return;
                }
            }
            // No expiry = lifetime license; allow offline forever
        }
        return; // Keep current status as-is
    }

    $body = json_decode( wp_remote_retrieve_body( $response ), true );

    // Server explicitly rejected the license
    if ( isset( $body['success'] ) && $body['success'] === false ) {
        update_option( 'royalsentinel_license_status', 'inactive' );
        delete_option( 'royalsentinel_license_cache' );
        return;
    }

    // Server returned a valid, signed response
    if ( isset( $body['success'] ) && $body['success'] === true && isset( $body['signature'] ) ) {

        // Verify Ed25519 signature before trusting (Guide Step 4)
        if ( ! royalsentinel_verify_license_cache( $body ) ) {
            // Signature verification failed — data tampered or key mismatch
            update_option( 'royalsentinel_license_status', 'inactive' );
            delete_option( 'royalsentinel_license_cache' );
            return;
        }

        // Update local cache with fresh signed response (Guide Step 4)
        update_option( 'royalsentinel_license_cache',      $body );
        update_option( 'royalsentinel_license_last_check', gmdate( 'Y-m-d H:i:s' ) );
        update_option( 'royalsentinel_license_status',     'active' );

        // Store encrypted expiry for offline fallback (non-lifetime licenses)
        $expiresAt = $body['license_data']['expires_at'] ?? null;
        if ( $expiresAt ) {
            $enc = royalsentinel_encrypt_expiry( $expiresAt );
            if ( $enc ) {
                update_option( 'royalsentinel_license_expiry_enc', $enc );
            }
        } else {
            delete_option( 'royalsentinel_license_expiry_enc' ); // lifetime — no expiry
        }

        // Non-critical activity ping (fire-and-forget, non-blocking)
        wp_remote_post( RS_LICENSE_ACTIVITY_URL, [
            'timeout'    => 5,
            'blocking'   => false,
            'sslverify'  => false,
            'body'       => [
                'software'    => RS_LICENSE_SOFTWARE,
                'license_key' => $saved_license,
                'domain'      => $domain,
                'action'      => 'ping',
            ],
        ] );
    }
}
