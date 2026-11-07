<?php
/**
 * Royal Sentinel - Shared Countries List
 * 
 * Used by: Cloudflare Geo-Blocking, Advance Security Geo-Blocking
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Get list of countries with emoji flags
 * 
 * @return array Country code => Country name with flag
 */
function rs_get_countries() {
    return [
        'AF' => '🇦🇫 Afghanistan',
        'AL' => '🇦🇱 Albania',
        'DZ' => '🇩🇿 Algeria',
        'AR' => '🇦🇷 Argentina',
        'AU' => '🇦🇺 Australia',
        'AT' => '🇦🇹 Austria',
        'BD' => '🇧🇩 Bangladesh',
        'BY' => '🇧🇾 Belarus',
        'BE' => '🇧🇪 Belgium',
        'BR' => '🇧🇷 Brazil',
        'BG' => '🇧🇬 Bulgaria',
        'CA' => '🇨🇦 Canada',
        'CN' => '🇨🇳 China',
        'CO' => '🇨🇴 Colombia',
        'HR' => '🇭🇷 Croatia',
        'CZ' => '🇨🇿 Czech Republic',
        'DK' => '🇩🇰 Denmark',
        'EG' => '🇪🇬 Egypt',
        'FI' => '🇫🇮 Finland',
        'FR' => '🇫🇷 France',
        'DE' => '🇩🇪 Germany',
        'GR' => '🇬🇷 Greece',
        'HK' => '🇭🇰 Hong Kong',
        'HU' => '🇭🇺 Hungary',
        'IN' => '🇮🇳 India',
        'ID' => '🇮🇩 Indonesia',
        'IR' => '🇮🇷 Iran',
        'IQ' => '🇮🇶 Iraq',
        'IE' => '🇮🇪 Ireland',
        'IL' => '🇮🇱 Israel',
        'IT' => '🇮🇹 Italy',
        'JP' => '🇯🇵 Japan',
        'KZ' => '🇰🇿 Kazakhstan',
        'KE' => '🇰🇪 Kenya',
        'KR' => '🇰🇷 South Korea',
        'KW' => '🇰🇼 Kuwait',
        'MY' => '🇲🇾 Malaysia',
        'MX' => '🇲🇽 Mexico',
        'MA' => '🇲🇦 Morocco',
        'NL' => '🇳🇱 Netherlands',
        'NZ' => '🇳🇿 New Zealand',
        'NG' => '🇳🇬 Nigeria',
        'NO' => '🇳🇴 Norway',
        'PK' => '🇵🇰 Pakistan',
        'PH' => '🇵🇭 Philippines',
        'PL' => '🇵🇱 Poland',
        'PT' => '🇵🇹 Portugal',
        'QA' => '🇶🇦 Qatar',
        'RO' => '🇷🇴 Romania',
        'RU' => '🇷🇺 Russia',
        'SA' => '🇸🇦 Saudi Arabia',
        'SG' => '🇸🇬 Singapore',
        'ZA' => '🇿🇦 South Africa',
        'ES' => '🇪🇸 Spain',
        'SE' => '🇸🇪 Sweden',
        'CH' => '🇨🇭 Switzerland',
        'TW' => '🇹🇼 Taiwan',
        'TH' => '🇹🇭 Thailand',
        'TR' => '🇹🇷 Turkey',
        'UA' => '🇺🇦 Ukraine',
        'AE' => '🇦🇪 United Arab Emirates',
        'GB' => '🇬🇧 United Kingdom',
        'US' => '🇺🇸 United States',
        'VN' => '🇻🇳 Vietnam',
    ];
}
