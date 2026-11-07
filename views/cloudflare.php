<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// ১. API ডিটেইলস এবং এনাবেল স্ট্যাটাস চেক করুন
$is_cf_enabled = get_option('rs_cf_enable', 0);
$auth_mode     = get_option('rs_cf_auth_mode', 'global');
$cf_email      = get_option('rs_cf_email');
$cf_key        = get_option('rs_cf_api_key');

// ২. কানেকশন ভ্যালিডেশন
$is_connected = false;
if ($is_cf_enabled && !empty($cf_key)) {
    if ($auth_mode === 'global') {
        if (!empty($cf_email)) {
            $is_connected = true;
        }
    } else {
        // Single mode (API Token)
        $is_connected = true;
    }
}

// বর্তমান ট্যাব ধরুন
$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'dashboard';
$api_mode = get_option('rs_cf_auth_mode', 'global');
$is_global = ($api_mode === 'global');
?>

<div class="wrap">
    <div class="rs-page-header">
        <div class="rs-header-content">
            <h1 class="rs-page-title">
                <div class="rs-process-icon">☁️</div>
                Cloudflare Manager
            </h1>
            <p class="rs-page-subtitle">Manage Cloudflare integration, rules, cache, and domains.</p>
        </div>
    </div>

    <?php if ( ! $is_connected ) : ?>
        <div style="background: #fff; padding: 40px; border: 1px solid #ccd0d4; border-radius: 4px; text-align: center; margin-top: 20px; box-shadow: 0 1px 3px rgba(0,0,0,.04);">
            <div style="font-size: 50px; margin-bottom: 20px;">☁️</div>
            <h2 style="margin-bottom: 15px;">Cloudflare API Not Connected</h2>
            <p style="font-size: 16px; color: #666; max-width: 600px; margin: 0 auto 25px;">
                You haven't connected your Cloudflare API yet. To use the Cloudflare Manager tools, please provide your API credentials in the settings page.
            </p>
            <a href="<?php echo admin_url('admin.php?page=rs-settings&tab=cloudflare'); ?>" class="button button-primary button-large" style="padding: 5px 30px; height: auto; font-size: 16px;">
                ⚙️ Go to Settings
            </a>
        </div>
    <?php else : ?>

    <h2 class="nav-tab-wrapper" style="margin-bottom: 20px;">
        <a href="?page=rs-cloudflare&tab=dashboard" class="nav-tab <?php echo $active_tab == 'dashboard' ? 'nav-tab-active' : ''; ?>">
            📊 Dashboard
        </a>
        <a href="?page=rs-cloudflare&tab=iplist" class="nav-tab <?php echo $active_tab == 'iplist' ? 'nav-tab-active' : ''; ?>">
            🚫 IP List
        </a>
        <a href="?page=rs-cloudflare&tab=domains" class="nav-tab <?php echo $active_tab == 'domains' ? 'nav-tab-active' : ''; ?>">
            🌐 Domains
        </a>
        <a href="?page=rs-cloudflare&tab=events" class="nav-tab <?php echo $active_tab == 'events' ? 'nav-tab-active' : ''; ?>">
            🔥 Events
        </a>
        <a href="?page=rs-cloudflare&tab=rules" class="nav-tab <?php echo $active_tab == 'rules' ? 'nav-tab-active' : ''; ?>">
            🛡️ Rules
        </a>
        <a href="?page=rs-cloudflare&tab=geoblocking" class="nav-tab <?php echo $active_tab == 'geoblocking' ? 'nav-tab-active' : ''; ?>">
            🌍 Geo-Blocking
        </a>
        <a href="?page=rs-cloudflare&tab=aicontrol" class="nav-tab <?php echo $active_tab == 'aicontrol' ? 'nav-tab-active' : ''; ?>">
            🤖 AI Control
        </a>
        <a href="?page=rs-cloudflare&tab=ratelimit" class="nav-tab <?php echo $active_tab == 'ratelimit' ? 'nav-tab-active' : ''; ?>">
            ⏱️ Rate Limiting
        </a>
        <?php if ($is_global) : ?>
        <a href="?page=rs-cloudflare&tab=cacherules" class="nav-tab <?php echo $active_tab == 'cacherules' ? 'nav-tab-active' : ''; ?>">
            📑 Cache Rules
        </a>
        <?php endif; ?>
        <a href="?page=rs-cloudflare&tab=cache" class="nav-tab <?php echo $active_tab == 'cache' ? 'nav-tab-active' : ''; ?>">
            🚀 Cache
        </a>
        <a href="?page=rs-cloudflare&tab=settings" class="nav-tab <?php echo $active_tab == 'settings' ? 'nav-tab-active' : ''; ?>">
            ⚙️ Settings
        </a>
        <a href="?page=rs-cloudflare&tab=dns" class="nav-tab <?php echo $active_tab == 'dns' ? 'nav-tab-active' : ''; ?>">
            📡 DNS
        </a>
        <a href="?page=rs-cloudflare&tab=tools" class="nav-tab <?php echo $active_tab == 'tools' ? 'nav-tab-active' : ''; ?>">
            🔧 Tools
        </a>
    </h2>

    <div class="rs-tab-content">
        <?php
        // ফাইল পাথ ডিফাইন করা
        $base_view_path = ROYALSENTINEL_PLUGIN_DIR . 'views/cloudflare/';
        
        switch ( $active_tab ) {
            case 'dashboard':
                require_once $base_view_path . 'dashboard.php';
                break;
            case 'iplist':
                require_once $base_view_path . 'iplist.php';
                break;
            case 'domains':
                require_once $base_view_path . 'domains.php';
                break;
            case 'events':
                require_once $base_view_path . 'events.php';
                break;
            case 'rules':
                require_once $base_view_path . 'rules.php';
                break;
            case 'geoblocking':
                require_once $base_view_path . 'geo-blocking.php';
                break;
            case 'aicontrol':
                require_once $base_view_path . 'ai-control.php';
                break;
            case 'ratelimit':
                require_once $base_view_path . 'rate-limiting.php';
                break;
            case 'cacherules':
                require_once $base_view_path . 'cache-rules.php';
                break;
            case 'cache':
                require_once $base_view_path . 'cache.php';
                break;
            case 'settings':
                require_once $base_view_path . 'settings.php';
                break;
            case 'dns':
                require_once $base_view_path . 'dns.php';
                break;
            case 'tools':
                require_once $base_view_path . 'tools.php';
                break;
            default:
                require_once $base_view_path . 'dashboard.php';
                break;
        }
        ?>
    </div>
    <?php endif; ?>
</div>
