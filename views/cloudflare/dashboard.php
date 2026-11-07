<?php if ( ! defined( 'ABSPATH' ) ) exit;

// জোন ডেটা এবং এরর চেক
$zones_res = function_exists('rs_get_cf_zones') ? rs_get_cf_zones() : ['items' => [], 'error' => 'Function Missing'];
$zones = $zones_res['items'];
$error = $zones_res['error'];
$total_zones = count($zones);

// বর্তমান ডোমেইন ডিটেকশন (অটো সিলেক্টের জন্য)
$current_domain = strtolower(parse_url(get_site_url(), PHP_URL_HOST));
$current_domain = str_replace('www.', '', $current_domain);
?>

<div class="rs-cf-wrapper">

    <?php if ( empty($zones) ): ?>
        <div class="notice notice-error inline" style="padding: 15px; background: #fff; border-left: 4px solid #dc3232; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
            <h3>⚠️ Cloudflare Not Connected or No Domains Found</h3>
            <p>
                <?php if($error): ?>
                    <strong>API Error:</strong> <?php echo esc_html($error); ?><br>
                <?php else: ?>
                    We could not find any zones associated with your account.<br>
                <?php endif; ?>
                Please go to the <strong><a href="?page=rs-cloudflare&tab=settings">Settings Tab</a></strong> and enter your Cloudflare API Key & Email correctly.
            </p>
        </div>
    <?php else: ?>

        <div class="rs-global-selector">
            <div class="rs-selector-left">
                <label for="rs-active-zone">Select Scope:</label>
                <select id="rs-active-zone" style="width: 300px;">
                    <option value="all">All Domains (Global Account)</option>
                    <?php foreach($zones as $z): ?>
                        <option value="<?php echo esc_attr($z['id']); ?>" <?php selected($z['name'], $current_domain); ?>>
                            <?php echo esc_html($z['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="rs-selector-right">
                 <strong>Viewing:</strong> <span id="rs-view-label" style="color:#0051c3;">All Domains</span>
                 <span class="rs-badge"><?php echo $total_zones; ?> Zones</span>
            </div>
        </div>

        <div id="rs-main-dashboard">
            <div class="rs-dashboard-template">
                
                <div class="rs-grid-4">
                    <div class="rs-card">
                        <span class="rs-label">Requests</span>
                        <div class="rs-big-num val-req">0</div>
                    </div>
                    <div class="rs-card">
                        <span class="rs-label">Bandwidth</span>
                        <div class="rs-big-num val-bw">0</div>
                    </div>
                    <div class="rs-card">
                        <span class="rs-label">Visits (Est.)</span>
                        <div class="rs-big-num val-visits">0</div>
                    </div>
                    <div class="rs-card">
                        <span class="rs-label">Page Views</span>
                        <div class="rs-big-num val-pv">0</div>
                    </div>
                </div>

                <div class="rs-card" style="margin-top:20px;">
                    <h3 class="rs-section-title">Traffic & Threats (Last 3 Days)</h3>
                    <div style="height: 300px; position: relative;">
                        <canvas class="traffic-chart"></canvas>
                    </div>
                </div>

                <div class="rs-grid-2" style="margin-top:20px;">
                    <div class="rs-card">
                        <h3 class="rs-section-title">Security</h3>
                        <div class="rs-stat-row">
                            <div><div class="rs-sub-label">Encrypted Requests</div><div class="rs-med-num val-sec-req">0</div></div>
                            <div><div class="rs-sub-label">Rate</div><div class="rs-med-num text-blue val-sec-req-rate">0%</div></div>
                        </div>
                        <hr>
                        <div class="rs-stat-row">
                            <div><div class="rs-sub-label">Encrypted Bandwidth</div><div class="rs-med-num val-sec-bw">0</div></div>
                            <div><div class="rs-sub-label">Rate</div><div class="rs-med-num text-blue val-sec-bw-rate">0%</div></div>
                        </div>
                    </div>

                    <div class="rs-card">
                        <h3 class="rs-section-title">Cache</h3>
                        <div class="rs-stat-row">
                            <div><div class="rs-sub-label">Cached Requests</div><div class="rs-med-num val-cache-req">0</div></div>
                            <div><div class="rs-sub-label">Rate</div><div class="rs-med-num text-green val-cache-req-rate">0%</div></div>
                        </div>
                        <hr>
                        <div class="rs-stat-row">
                            <div><div class="rs-sub-label">Cached Bandwidth</div><div class="rs-med-num val-cache-bw">0</div></div>
                            <div><div class="rs-sub-label">Rate</div><div class="rs-med-num text-green val-cache-bw-rate">0%</div></div>
                        </div>
                    </div>
                </div>

            </div> 
        </div>

    <?php endif; ?>
</div>

<div id="rs-domain-modal" class="rs-modal" style="display:none;">
    <div class="rs-modal-content">
        <span class="rs-close-modal">&times;</span>
        <h2 id="modal-title" style="margin-bottom:20px; border-bottom:1px solid #ccc; padding-bottom:10px;">Domain Analytics</h2>
        <div id="modal-body">
            </div>
    </div>
</div>

<style>
    .rs-cf-wrapper { font-family: -apple-system, system-ui, sans-serif; background: #f0f2f5; padding: 20px; }
    .rs-global-selector { background: #fff; padding: 15px; border-radius: 5px; display: flex; justify-content: space-between; margin-bottom: 20px; border: 1px solid #ddd; }
    .rs-grid-4 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; }
    .rs-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
    .rs-card { background: #fff; padding: 20px; border-radius: 8px; border: 1px solid #e0e0e0; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
    .rs-label { font-size: 13px; color: #555; font-weight: 600; text-transform: uppercase; }
    .rs-big-num { font-size: 28px; font-weight: 700; color: #333; margin-top: 5px; }
    .rs-med-num { font-size: 20px; font-weight: 700; color: #333; margin-top: 5px; }
    .rs-sub-label { font-size: 12px; color: #777; }
    .rs-section-title { margin: 0 0 15px 0; font-size: 16px; border-bottom: 1px solid #eee; padding-bottom: 10px; }
    .rs-stat-row { display: flex; justify-content: space-between; padding: 5px 0; }
    .text-blue { color: #0051c3; } .text-green { color: #2d7b1a; } .text-red { color: #b00020; }
    
    /* Modal CSS */
    .rs-modal { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 99999; }
    .rs-modal-content { background: #f4f6f8; width: 90%; max-width: 1200px; margin: 2% auto; height: 90%; overflow-y: auto; padding: 25px; border-radius: 8px; box-shadow: 0 10px 25px rgba(0,0,0,0.2); }
    .rs-close-modal { float: right; font-size: 30px; cursor: pointer; color: #666; }
    .rs-close-modal:hover { color: #000; }
</style>

<script>
jQuery(document).ready(function($) {
    // সিলেক্ট ২ ইনিশিয়ালাইজেশন
    if($('#rs-active-zone').length > 0) {
        $('#rs-active-zone').select2({ width: 'resolve' });

        // ১. পেজ লোড হলে গ্লোবাল ডাটা (All Domains) লোড হবে মেইন ড্যাশবোর্ডে
        // যদি সিলেক্ট বক্সে আগে থেকেই কিছু সিলেক্ট করা থাকে (যেমন অটো ডিটেক্ট)
        let initialZone = $('#rs-active-zone').val();
        let initialName = $("#rs-active-zone option:selected").text();

        if (initialZone && initialZone !== 'all') {
            // যদি স্পেসিফিক ডোমেইন অটো সিলেক্ট হয়, তবে পপআপ খুলুন
            openPopup(initialZone, initialName);
        } else {
            // অন্যথায় মেইন ড্যাশবোর্ডে সব ডাটা দেখান
            $('#rs-view-label').text('All Domains');
            fetchData('all', $('#rs-main-dashboard'));
        }

        // ২. ড্রপডাউন চেঞ্জ ইভেন্ট
        $('#rs-active-zone').on('change', function() {
            let val = $(this).val();
            let name = $("#rs-active-zone option:selected").text();

            if (val === 'all') {
                // পপআপ বন্ধ করুন এবং মেইন ড্যাশবোর্ড আপডেট করুন
                $('#rs-domain-modal').fadeOut();
                $('#rs-view-label').text('All Domains');
                fetchData('all', $('#rs-main-dashboard'));
            } else {
                // পপআপ খুলুন
                openPopup(val, name);
            }
        });
    }

    // পপআপ খোলার ফাংশন
    function openPopup(zoneId, zoneName) {
        $('#modal-title').text('Analytics: ' + zoneName);
        
        // মেইন টেমপ্লেট ক্লোন করে মডালের ভেতর ঢুকানো
        let template = $('#rs-main-dashboard .rs-dashboard-template').clone();
        $('#modal-body').html(template);
        
        $('#rs-domain-modal').fadeIn();

        // মডালের ভেতর ডাটা ফেচ করা
        fetchData(zoneId, $('#modal-body'));
    }

    // মডাল ক্লোজ বাটন
    $('.rs-close-modal').click(function() {
        $('#rs-domain-modal').fadeOut();
        // সিলেকশন আবার 'All' এ রিসেট করা
        $('#rs-active-zone').val('all').trigger('change');
    });

    // ডাটা ফেচিং ফাংশন
    function fetchData(zoneId, $container) {
        $container.css('opacity', '0.5'); // লোডিং ইফেক্ট
        
        $.post(ajaxurl, { nonce: '<?php echo wp_create_nonce("rs_cf_tabs"); ?>',
            action: 'rs_cf_load_dashboard',
            zone_id: zoneId,
            nonce: '<?php echo wp_create_nonce("rs_cf_tabs"); ?>'
        }, function(res) {
            $container.css('opacity', '1');
            
            if(res.success) {
                renderData(res.data, $container);
            } else {
                $container.html('<div style="padding:20px; color:red; text-align:center;"><h3>Error</h3><p>' + res.data + '</p></div>');
            }
        });
    }

    // ডাটা রেন্ডারিং ফাংশন
    function renderData(data, $scope) {
        $scope.find('.val-req').text(data.top.requests);
        $scope.find('.val-bw').text(data.top.bandwidth);
        $scope.find('.val-visits').text(data.top.visits);
        $scope.find('.val-pv').text(data.top.pageviews);

        $scope.find('.val-sec-req').text(data.security.enc_req);
        $scope.find('.val-sec-req-rate').text(data.security.enc_req_rate);
        $scope.find('.val-sec-bw').text(data.security.enc_bw);
        $scope.find('.val-sec-bw-rate').text(data.security.enc_bw_rate);

        $scope.find('.val-cache-req').text(data.cache.cached_req);
        $scope.find('.val-cache-req-rate').text(data.cache.cached_req_rate);
        $scope.find('.val-cache-bw').text(data.cache.cached_bw);
        $scope.find('.val-cache-bw-rate').text(data.cache.cached_bw_rate);
        
        // চার্ট রেন্ডার (আগের চার্ট থাকলে ডিলিট করা হবে)
        let canvas = $scope.find('.traffic-chart');
        if (canvas.length) {
            let ctx = canvas[0].getContext('2d');
            if (canvas.data('chartInstance')) {
                canvas.data('chartInstance').destroy();
            }
            let newChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.chart.labels,
                    datasets: [
                        { label: 'Requests', data: data.chart.requests, borderColor: '#0051c3', backgroundColor: 'rgba(0, 81, 195, 0.1)', fill: true, tension: 0.3 },
                        { label: 'Threats', data: data.chart.threats, borderColor: '#b00020', backgroundColor: 'rgba(176, 0, 32, 0.1)', fill: true, tension: 0.3 }
                    ]
                },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'top' } }, scales: { y: { beginAtZero: true } } }
            });
            canvas.data('chartInstance', newChart);
        }
    }
});
</script>
