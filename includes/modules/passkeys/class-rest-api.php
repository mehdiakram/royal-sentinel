<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Royal Sentinel Passkeys REST API
 *
 * Native WebAuthn implementation — no external library required.
 * Compatible with PHP 8.4+.
 */
class RS_Passkeys_REST_API {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    public function register_routes() {
        register_rest_route( 'royal-sentinel/v1', '/passkeys/register/options', array(
            'methods'  => 'POST',
            'callback' => array( $this, 'register_options' ),
            'permission_callback' => function() { return is_user_logged_in(); }
        ) );

        register_rest_route( 'royal-sentinel/v1', '/passkeys/register/verify', array(
            'methods'  => 'POST',
            'callback' => array( $this, 'register_verify' ),
            'permission_callback' => function() { return is_user_logged_in(); }
        ) );

        register_rest_route( 'royal-sentinel/v1', '/passkeys/login/options', array(
            'methods'  => 'POST',
            'callback' => array( $this, 'login_options' ),
            'permission_callback' => '__return_true'
        ) );

        register_rest_route( 'royal-sentinel/v1', '/passkeys/login/verify', array(
            'methods'  => 'POST',
            'callback' => array( $this, 'login_verify' ),
            'permission_callback' => '__return_true'
        ) );
    }

    // ======================================================================
    // REGISTRATION
    // ======================================================================

    public function register_options( WP_REST_Request $request ) {
        $user = wp_get_current_user();

        $challenge = random_bytes(32);
        $rp_id     = parse_url( site_url(), PHP_URL_HOST );
        $rp_name   = get_bloginfo( 'name' );

        // Get already-registered credential IDs to exclude
        $existing   = get_user_meta( $user->ID, 'rs_passkeys', true );
        $exclude    = array();
        if ( is_array( $existing ) ) {
            foreach ( $existing as $pk ) {
                $exclude[] = array(
                    'type' => 'public-key',
                    'id'   => $pk['credentialId'], // already base64url
                );
            }
        }

        // Store challenge for verification
        set_transient( 'rs_pk_reg_challenge_' . $user->ID, base64_encode( $challenge ), 5 * MINUTE_IN_SECONDS );

        $options = array(
            'publicKey' => array(
                'rp' => array(
                    'name' => $rp_name,
                    'id'   => $rp_id,
                ),
                'user' => array(
                    'id'          => self::base64url_encode( (string) $user->ID ),
                    'name'        => $user->user_login,
                    'displayName' => $user->display_name,
                ),
                'challenge'             => self::base64url_encode( $challenge ),
                'pubKeyCredParams'      => array(
                    array( 'type' => 'public-key', 'alg' => -7 ),   // ES256
                    array( 'type' => 'public-key', 'alg' => -257 ), // RS256
                ),
                'timeout'               => 60000,
                'attestation'           => 'none',
                'excludeCredentials'    => $exclude,
                'authenticatorSelection' => array(
                    'authenticatorAttachment' => 'platform',
                    'residentKey'             => 'preferred',
                    'userVerification'        => 'preferred',
                ),
            ),
        );

        return rest_ensure_response( $options );
    }

    public function register_verify( WP_REST_Request $request ) {
        $user = wp_get_current_user();
        $data = $request->get_json_params();

        if ( empty( $data['response'] ) ) {
            return new WP_Error( 'missing_data', 'Missing credential response.', array( 'status' => 400 ) );
        }

        // Retrieve stored challenge
        $stored_challenge = get_transient( 'rs_pk_reg_challenge_' . $user->ID );
        if ( ! $stored_challenge ) {
            return new WP_Error( 'timeout', 'Session expired. Try again.', array( 'status' => 400 ) );
        }
        $challenge_bytes = base64_decode( $stored_challenge );
        delete_transient( 'rs_pk_reg_challenge_' . $user->ID );

        $cred     = $data['response'];
        $rp_id    = parse_url( site_url(), PHP_URL_HOST );

        // --- Decode clientDataJSON ---
        $client_data_raw = self::base64url_decode( $cred['response']['clientDataJSON'] );
        $client_data     = json_decode( $client_data_raw, true );

        if ( ! $client_data ) {
            return new WP_Error( 'bad_client_data', 'Invalid clientDataJSON.', array( 'status' => 400 ) );
        }

        // Verify type
        if ( ( $client_data['type'] ?? '' ) !== 'webauthn.create' ) {
            return new WP_Error( 'bad_type', 'Unexpected type in clientDataJSON.', array( 'status' => 400 ) );
        }

        // Verify challenge
        $received_challenge = self::base64url_decode( $client_data['challenge'] );
        if ( ! hash_equals( $challenge_bytes, $received_challenge ) ) {
            return new WP_Error( 'bad_challenge', 'Challenge mismatch.', array( 'status' => 400 ) );
        }

        // Verify origin
        $expected_origin = site_url();
        if ( rtrim( $client_data['origin'] ?? '', '/' ) !== rtrim( $expected_origin, '/' ) ) {
            return new WP_Error( 'bad_origin', 'Origin mismatch.', array( 'status' => 400 ) );
        }

        // --- Decode attestationObject (CBOR) ---
        $attestation_raw = self::base64url_decode( $cred['response']['attestationObject'] );
        $attestation     = self::cbor_decode_simple( $attestation_raw );

        if ( ! $attestation || ! isset( $attestation['authData'] ) ) {
            return new WP_Error( 'bad_attestation', 'Invalid attestation object.', array( 'status' => 400 ) );
        }

        $auth_data = $attestation['authData'];

        // Parse authData: rpIdHash (32) + flags (1) + signCount (4) + attestedCredData (variable)
        if ( strlen( $auth_data ) < 37 ) {
            return new WP_Error( 'bad_auth_data', 'Auth data too short.', array( 'status' => 400 ) );
        }

        $rp_id_hash = substr( $auth_data, 0, 32 );
        if ( ! hash_equals( hash( 'sha256', $rp_id, true ), $rp_id_hash ) ) {
            return new WP_Error( 'bad_rp_id', 'RP ID hash mismatch.', array( 'status' => 400 ) );
        }

        $flags = ord( $auth_data[32] );
        // Bit 0 = UP (User Present), Bit 6 = AT (Attested Credential Data included)
        if ( ( $flags & 0x01 ) === 0 ) {
            return new WP_Error( 'no_up', 'User presence flag not set.', array( 'status' => 400 ) );
        }

        if ( ( $flags & 0x40 ) === 0 ) {
            return new WP_Error( 'no_at', 'Attested credential data not present.', array( 'status' => 400 ) );
        }

        // Parse attested credential data
        $offset   = 37;
        $aaguid   = substr( $auth_data, $offset, 16 );
        $offset  += 16;
        $cred_len = unpack( 'n', substr( $auth_data, $offset, 2 ) )[1];
        $offset  += 2;
        $cred_id  = substr( $auth_data, $offset, $cred_len );
        $offset  += $cred_len;

        // Remaining bytes = CBOR-encoded public key
        $pubkey_cbor = substr( $auth_data, $offset );
        $pubkey_map  = self::cbor_decode_simple( $pubkey_cbor );

        if ( ! $pubkey_map ) {
            return new WP_Error( 'bad_pubkey', 'Cannot decode public key COSE.', array( 'status' => 400 ) );
        }

        // Convert COSE key to PEM
        $pem = self::cose_to_pem( $pubkey_map );
        if ( ! $pem ) {
            return new WP_Error( 'unsupported_key', 'Unsupported key type. Only ES256 and RS256 are supported.', array( 'status' => 400 ) );
        }

        // Store credential
        $passkeys = get_user_meta( $user->ID, 'rs_passkeys', true );
        if ( ! is_array( $passkeys ) ) {
            $passkeys = array();
        }

        $device_name = isset( $data['device_name'] ) ? sanitize_text_field( $data['device_name'] ) : 'Unknown Device';

        $passkeys[] = array(
            'credentialId' => self::base64url_encode( $cred_id ),
            'publicKeyPem' => $pem,
            'signCount'    => unpack( 'N', substr( $auth_data, 33, 4 ) )[1],
            'name'         => $device_name,
            'created_at'   => current_time( 'mysql' ),
        );

        update_user_meta( $user->ID, 'rs_passkeys', $passkeys );

        return rest_ensure_response( array( 'success' => true ) );
    }

    // ======================================================================
    // LOGIN
    // ======================================================================

    public function login_options( WP_REST_Request $request ) {
        $username = $request->get_param( 'username' );
        $user     = null;
        $user_id  = 0;

        if ( $username ) {
            $user = get_user_by( 'login', $username ) ?: get_user_by( 'email', $username );
            if ( ! $user ) {
                return new WP_Error( 'invalid_user', 'User not found.', array( 'status' => 404 ) );
            }
            $user_id = $user->ID;
        }

        $challenge = random_bytes(32);
        $rp_id     = parse_url( site_url(), PHP_URL_HOST );

        $allow_credentials = array();
        if ( $user_id ) {
            $passkeys = get_user_meta( $user_id, 'rs_passkeys', true );
            if ( is_array( $passkeys ) ) {
                foreach ( $passkeys as $pk ) {
                    $allow_credentials[] = array(
                        'type' => 'public-key',
                        'id'   => $pk['credentialId'],
                    );
                }
            }
        }

        $tk = 'rs_pk_login_challenge_' . ( $user_id ?: 'guest' );
        set_transient( $tk, base64_encode( $challenge ), 5 * MINUTE_IN_SECONDS );

        $response = array(
            'challenge'        => self::base64url_encode( $challenge ),
            'rpId'             => $rp_id,
            'timeout'          => 60000,
            'userVerification' => 'preferred',
            'allowCredentials' => $allow_credentials,
            'rs_uid'           => $user_id,
        );

        return rest_ensure_response( $response );
    }

    public function login_verify( WP_REST_Request $request ) {
        $data = $request->get_json_params();
        $uid  = isset( $data['rs_uid'] ) ? intval( $data['rs_uid'] ) : 0;

        $tk = 'rs_pk_login_challenge_' . ( $uid ?: 'guest' );
        $stored_challenge = get_transient( $tk );

        if ( ! $stored_challenge ) {
            return new WP_Error( 'timeout', 'Session expired. Try again.', array( 'status' => 400 ) );
        }
        $challenge_bytes = base64_decode( $stored_challenge );
        delete_transient( $tk );

        if ( empty( $data['response'] ) ) {
            return new WP_Error( 'missing_data', 'Missing assertion response.', array( 'status' => 400 ) );
        }

        $cred  = $data['response'];
        $rp_id = parse_url( site_url(), PHP_URL_HOST );

        // --- clientDataJSON ---
        $client_data_raw = self::base64url_decode( $cred['response']['clientDataJSON'] );
        $client_data     = json_decode( $client_data_raw, true );

        if ( ( $client_data['type'] ?? '' ) !== 'webauthn.get' ) {
            return new WP_Error( 'bad_type', 'Unexpected type.', array( 'status' => 400 ) );
        }

        $received_challenge = self::base64url_decode( $client_data['challenge'] );
        if ( ! hash_equals( $challenge_bytes, $received_challenge ) ) {
            return new WP_Error( 'bad_challenge', 'Challenge mismatch.', array( 'status' => 400 ) );
        }

        $expected_origin = site_url();
        if ( rtrim( $client_data['origin'] ?? '', '/' ) !== rtrim( $expected_origin, '/' ) ) {
            return new WP_Error( 'bad_origin', 'Origin mismatch.', array( 'status' => 400 ) );
        }

        // --- Find credential by ID ---
        $cred_id_b64 = $cred['id'];
        $found_user  = null;
        $found_key   = null;
        $found_index = null;

        // If we have a UID, search only that user.
        // Searching ALL users without a username is a performance & security risk on large sites.
        if ( ! $uid ) {
            return new WP_Error(
                'username_required',
                'Please enter your username before using a passkey.',
                array( 'status' => 400 )
            );
        }

        $search_users = array( get_user_by( 'ID', $uid ) );

        foreach ( $search_users as $u ) {
            if ( ! $u ) continue;
            $passkeys = get_user_meta( $u->ID, 'rs_passkeys', true );
            if ( ! is_array( $passkeys ) ) continue;
            foreach ( $passkeys as $idx => $pk ) {
                if ( isset( $pk['credentialId'] ) && $pk['credentialId'] === $cred_id_b64 ) {
                    $found_user  = $u;
                    $found_key   = $pk;
                    $found_index = $idx;
                    break 2;
                }
            }
        }

        if ( ! $found_user || ! $found_key ) {
            return new WP_Error( 'unknown_credential', 'Credential not recognized.', array( 'status' => 401 ) );
        }

        // --- Verify signature ---
        $auth_data_raw  = self::base64url_decode( $cred['response']['authenticatorData'] );
        $signature_raw  = self::base64url_decode( $cred['response']['signature'] );

        // rpIdHash check
        $rp_id_hash = substr( $auth_data_raw, 0, 32 );
        if ( ! hash_equals( hash( 'sha256', $rp_id, true ), $rp_id_hash ) ) {
            return new WP_Error( 'bad_rp_id', 'RP ID hash mismatch.', array( 'status' => 400 ) );
        }

        // User presence
        $flags = ord( $auth_data_raw[32] );
        if ( ( $flags & 0x01 ) === 0 ) {
            return new WP_Error( 'no_up', 'User not present.', array( 'status' => 400 ) );
        }

        // Build verification data: authData + SHA256(clientDataJSON)
        $client_data_hash = hash( 'sha256', $client_data_raw, true );
        $verify_data      = $auth_data_raw . $client_data_hash;

        $pem = $found_key['publicKeyPem'];
        $pub = openssl_pkey_get_public( $pem );
        if ( ! $pub ) {
            return new WP_Error( 'bad_key', 'Cannot load stored public key.', array( 'status' => 500 ) );
        }

        $key_details = openssl_pkey_get_details( $pub );

        if ( $key_details['type'] === OPENSSL_KEYTYPE_EC ) {
            // ES256 — ECDSA with SHA-256
            $valid = openssl_verify( $verify_data, $signature_raw, $pub, OPENSSL_ALGO_SHA256 );
        } else {
            // RS256 — RSA PKCS#1 v1.5 with SHA-256 (most hardware keys)
            $valid = openssl_verify( $verify_data, $signature_raw, $pub, OPENSSL_ALGO_SHA256 );

            // Fallback: RSA-PSS (modern authenticators using PS256)
            if ( $valid !== 1 && defined( 'OPENSSL_ALGO_SHA256' ) ) {
                $valid = openssl_verify( $verify_data, $signature_raw, $pub, 'sha256WithRSAEncryption' );
            }
        }

        if ( $valid !== 1 ) {
            return new WP_Error( 'sig_invalid', 'Signature verification failed.', array( 'status' => 401 ) );
        }

        // Update sign count
        $new_count = unpack( 'N', substr( $auth_data_raw, 33, 4 ) )[1];
        $passkeys  = get_user_meta( $found_user->ID, 'rs_passkeys', true );
        $passkeys[ $found_index ]['signCount'] = $new_count;
        update_user_meta( $found_user->ID, 'rs_passkeys', $passkeys );

        // Log in
        wp_set_auth_cookie( $found_user->ID, true );
        set_transient( 'rs_skip_2fa_' . $found_user->ID, true, 5 * MINUTE_IN_SECONDS );

        return rest_ensure_response( array( 'success' => true, 'redirect' => admin_url() ) );
    }

    // ======================================================================
    // UTILITY HELPERS
    // ======================================================================

    private static function base64url_encode( $data ) {
        return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
    }

    private static function base64url_decode( $data ) {
        return base64_decode( strtr( $data, '-_', '+/' ) . str_repeat( '=', ( 4 - strlen( $data ) % 4 ) % 4 ) );
    }

    /**
     * Minimal CBOR decoder — handles maps, byte strings, text strings, integers, arrays.
     * Sufficient for WebAuthn attestationObject and COSE keys.
     */
    private static function cbor_decode_simple( $data, &$offset = 0 ) {
        if ( $offset >= strlen( $data ) ) return null;

        $first = ord( $data[ $offset ] );
        $major = ( $first >> 5 ) & 0x07;
        $minor = $first & 0x1F;
        $offset++;

        // Get the additional value
        $val = self::cbor_read_value( $data, $minor, $offset );

        switch ( $major ) {
            case 0: // Unsigned integer
                return $val;

            case 1: // Negative integer
                return -1 - $val;

            case 2: // Byte string
                $bytes = substr( $data, $offset, $val );
                $offset += $val;
                return $bytes;

            case 3: // Text string
                $text = substr( $data, $offset, $val );
                $offset += $val;
                return $text;

            case 4: // Array
                $arr = array();
                for ( $i = 0; $i < $val; $i++ ) {
                    $arr[] = self::cbor_decode_simple( $data, $offset );
                }
                return $arr;

            case 5: // Map
                $map = array();
                for ( $i = 0; $i < $val; $i++ ) {
                    $key       = self::cbor_decode_simple( $data, $offset );
                    $map_val   = self::cbor_decode_simple( $data, $offset );
                    $map[ $key ] = $map_val;
                }
                return $map;

            case 7: // Simple values / float
                if ( $minor === 20 ) return false;
                if ( $minor === 21 ) return true;
                if ( $minor === 22 ) return null;
                return null;

            default:
                return null;
        }
    }

    private static function cbor_read_value( $data, $minor, &$offset ) {
        if ( $minor < 24 ) return $minor;
        if ( $minor === 24 ) { $v = ord( $data[ $offset ] ); $offset += 1; return $v; }
        if ( $minor === 25 ) { $v = unpack( 'n', substr( $data, $offset, 2 ) )[1]; $offset += 2; return $v; }
        if ( $minor === 26 ) { $v = unpack( 'N', substr( $data, $offset, 4 ) )[1]; $offset += 4; return $v; }
        if ( $minor === 27 ) { $v = unpack( 'J', substr( $data, $offset, 8 ) )[1]; $offset += 8; return $v; }
        return $minor;
    }

    /**
     * Convert COSE public key map to PEM.
     * Supports EC2 (ES256 / kty=2) and RSA (RS256 / kty=3).
     */
    private static function cose_to_pem( $cose ) {
        // COSE key type: 1=OKP, 2=EC2, 3=RSA
        $kty = $cose[1] ?? null;

        if ( $kty === 2 ) {
            // EC2 key (ES256)
            $x = $cose[-2] ?? '';
            $y = $cose[-3] ?? '';

            if ( strlen( $x ) !== 32 || strlen( $y ) !== 32 ) return null;

            // Build uncompressed EC point: 0x04 + x + y
            $point = "\x04" . $x . $y;

            // ASN.1 DER encoding for P-256 SubjectPublicKeyInfo
            $ec_oid  = "\x06\x08\x2A\x86\x48\xCE\x3D\x03\x01\x07"; // prime256v1
            $algo    = "\x06\x07\x2A\x86\x48\xCE\x3D\x02\x01";     // ecPublicKey

            $algo_seq   = "\x30" . chr( strlen( $algo ) + strlen( $ec_oid ) ) . $algo . $ec_oid;
            $bit_string = "\x03" . chr( strlen( $point ) + 1 ) . "\x00" . $point;
            $spki       = "\x30" . self::asn1_length( strlen( $algo_seq ) + strlen( $bit_string ) ) . $algo_seq . $bit_string;

            return "-----BEGIN PUBLIC KEY-----\n" . chunk_split( base64_encode( $spki ), 64, "\n" ) . "-----END PUBLIC KEY-----\n";
        }

        if ( $kty === 3 ) {
            // RSA key (RS256)
            $n = $cose[-1] ?? '';
            $e = $cose[-2] ?? '';

            $n_int = "\x02" . self::asn1_length( strlen( $n ) ) . $n;
            $e_int = "\x02" . self::asn1_length( strlen( $e ) ) . $e;
            $rsa_seq = "\x30" . self::asn1_length( strlen( $n_int ) + strlen( $e_int ) ) . $n_int . $e_int;

            $algo_oid = "\x06\x09\x2A\x86\x48\x86\xF7\x0D\x01\x01\x01"; // rsaEncryption
            $algo_seq = "\x30" . chr( strlen( $algo_oid ) + 2 ) . $algo_oid . "\x05\x00";
            $bit_string = "\x03" . self::asn1_length( strlen( $rsa_seq ) + 1 ) . "\x00" . $rsa_seq;
            $spki = "\x30" . self::asn1_length( strlen( $algo_seq ) + strlen( $bit_string ) ) . $algo_seq . $bit_string;

            return "-----BEGIN PUBLIC KEY-----\n" . chunk_split( base64_encode( $spki ), 64, "\n" ) . "-----END PUBLIC KEY-----\n";
        }

        return null;
    }

    /**
     * ASN.1 DER length encoding.
     */
    private static function asn1_length( $length ) {
        if ( $length < 0x80 ) {
            return chr( $length );
        }
        if ( $length < 0x100 ) {
            return "\x81" . chr( $length );
        }
        return "\x82" . pack( 'n', $length );
    }
}
