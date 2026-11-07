<?php
/**
 * Global IP Utility Functions
 * Royal Sentinel
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Safely resolve the client IP.
 * Mitigates HTTP_CF_CONNECTING_IP spoofing.
 */
function rs_get_real_ip() {
    $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field($_SERVER['REMOTE_ADDR']) : '127.0.0.1';
    
    if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
        // Only trust HTTP_CF_CONNECTING_IP if the actual REMOTE_ADDR is a known Cloudflare IP
        if ( rs_is_cloudflare_ip( $ip ) ) {
            $cf_ip = sanitize_text_field( $_SERVER['HTTP_CF_CONNECTING_IP'] );
            if ( filter_var( $cf_ip, FILTER_VALIDATE_IP ) ) {
                return $cf_ip;
            }
        }
    }
    
    // Fall back safely to REMOTE_ADDR
    return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '127.0.0.1';
}

/**
 * Checks if the IP belongs to Cloudflare's IPv4 or IPv6 ranges.
 */
function rs_is_cloudflare_ip( $ip ) {
    $cf_ipv4 = [
        '173.245.48.0/20',
        '103.21.244.0/22',
        '103.22.200.0/22',
        '103.31.4.0/22',
        '141.101.64.0/18',
        '108.162.192.0/18',
        '190.93.240.0/20',
        '188.114.96.0/20',
        '197.234.240.0/22',
        '198.41.128.0/17',
        '162.158.0.0/15',
        '104.16.0.0/13',
        '104.24.0.0/14',
        '172.64.0.0/13',
        '131.0.72.0/22'
    ];

    $cf_ipv6 = [
        '2400:cb00::/32',
        '2606:4700::/32',
        '2803:f800::/32',
        '2405:b500::/32',
        '2405:8100::/32',
        '2a06:98c0::/29',
        '2c0f:f248::/32'
    ];

    if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
        foreach ( $cf_ipv4 as $cidr ) {
            if ( rs_ip_in_cidr( $ip, $cidr ) ) return true;
        }
    } elseif ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
        foreach ( $cf_ipv6 as $cidr ) {
            if ( rs_ip_in_cidr( $ip, $cidr ) ) return true;
        }
    }

    return false;
}

/**
 * Validate IP or CIDR
 */
function rs_validate_ip_or_cidr( $input ) {
    $input = trim( $input );
    
    if ( strpos( $input, '/' ) !== false ) {
        $parts = explode( '/', $input, 2 );
        if ( count($parts) !== 2 ) return false;
        
        $ip = $parts[0];
        $mask = $parts[1];
        
        if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
            return false;
        }
        
        $mask = intval( $mask );
        if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
            return $mask >= 0 && $mask <= 32;
        } elseif ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
            return $mask >= 0 && $mask <= 128;
        }
        
        return false;
    }
    
    return filter_var( $input, FILTER_VALIDATE_IP ) !== false;
}

/**
 * Check if IP matches CIDR range
 */
function rs_ip_in_cidr( $ip, $cidr ) {
    if ( strpos( $cidr, '/' ) === false ) {
        return $ip === $cidr;
    }
    
    $parts = explode( '/', $cidr, 2 );
    if ( count($parts) !== 2 ) return false;
    
    $subnet = $parts[0];
    $mask = intval( $parts[1] );
    
    if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) && 
         filter_var( $subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
        $ip_long = ip2long( $ip );
        $subnet_long = ip2long( $subnet );
        $mask_long = -1 << (32 - $mask);
        
        return ($ip_long & $mask_long) === ($subnet_long & $mask_long);
    }
    
    if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) && 
         filter_var( $subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
        $ip_bin = inet_pton( $ip );
        $subnet_bin = inet_pton( $subnet );
        
        if ( $ip_bin === false || $subnet_bin === false ) return false;
        
        $ip_bits = '';
        $subnet_bits = '';
        
        for ( $i = 0; $i < 16; $i++ ) {
            $ip_bits .= str_pad( decbin( ord( $ip_bin[$i] ) ), 8, '0', STR_PAD_LEFT );
            $subnet_bits .= str_pad( decbin( ord( $subnet_bin[$i] ) ), 8, '0', STR_PAD_LEFT );
        }
        
        return substr( $ip_bits, 0, $mask ) === substr( $subnet_bits, 0, $mask );
    }
    
    return false;
}

/**
 * Checks if an IP is globally whitelisted/trusted.
 */
function rs_is_trusted_ip( $ip = null ) {
    if ( $ip === null ) {
        $ip = rs_get_real_ip();
    }
    
    if ( empty( $ip ) ) {
        return false;
    }
    
    // Always trust localhost
    $localhost = [ '127.0.0.1', '::1', 'localhost' ];
    if ( in_array( $ip, $localhost ) ) {
        return true;
    }

    // Always trust server's own public IP
    $server_ip = isset($_SERVER['SERVER_ADDR']) ? sanitize_text_field($_SERVER['SERVER_ADDR']) : '';
    if ( !empty($server_ip) && $ip === $server_ip ) {
        return true;
    }

    // Always trust currently logged-in administrator
    if ( is_user_logged_in() && current_user_can( 'manage_options' ) ) {
        $current_real_ip = rs_get_real_ip();
        if ( $ip === $current_real_ip ) {
            return true;
        }
    }

    // Check plugin global whitelist
    $settings = get_option( 'rs_security_settings', [] );
    $whitelist = isset( $settings['rt_ip_whitelist'] ) ? $settings['rt_ip_whitelist'] : [];
    
    foreach ( $whitelist as $entry ) {
        $entry_ip = isset( $entry['ip'] ) ? $entry['ip'] : '';
        if ( ! empty( $entry_ip ) && rs_ip_in_cidr( $ip, $entry_ip ) ) {
            return true;
        }
    }
    
    return false;
}
