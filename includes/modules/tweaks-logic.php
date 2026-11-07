<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class RS_Tweaks_Logic {
    private $settings;

    public function __construct() {
        $this->settings = get_option( 'rs_tweaks_settings', [] );

        add_action( 'init', [ $this, 'apply_tweaks' ] );
        add_action( 'admin_init', [ $this, 'admin_tweaks' ] );
    
        add_action( 'wp_ajax_rs_tweaks_action', [ $this, 'handle_ajax_action' ] );
        add_action( 'rs_daily_scheduled_tasks', [ $this, 'run_daily_maintenance' ] );
        if ( ! wp_next_scheduled( 'rs_daily_scheduled_tasks' ) ) {
            wp_schedule_event( time(), 'daily', 'rs_daily_scheduled_tasks' );
        }
        
        // Apply custom login styling
        add_action( 'login_enqueue_scripts', [ $this, 'custom_login_styles' ] );
        
        if ( !empty($this->settings['login_header_url']) ) {
            add_filter( 'login_headerurl', function() {
                return esc_url( $this->settings['login_header_url'] );
            });
        }
    }


    public function run_daily_maintenance() {
        global $wpdb;
        $days = isset($this->settings['auto_del_revisions_days']) ? intval($this->settings['auto_del_revisions_days']) : 0;
        
        if ( $days > 0 ) {
            $wpdb->query( $wpdb->prepare( 
                "DELETE FROM $wpdb->posts WHERE post_type = 'revision' AND post_date < DATE_SUB(NOW(), INTERVAL %d DAY)", 
                $days 
            ) );
        }
    }



    public function apply_tweaks() {
        // --- COMMENTS ---
        if ( !empty($this->settings['disable_comments']) ) {
            // ১. গ্লোবাল ডিজেবল থাকলে সব বন্ধ
            add_filter('comments_open', '__return_false', 20, 2);
            add_filter('pings_open', '__return_false', 20, 2);
            add_action('admin_init', [ $this, 'disable_comments_admin_init' ] );
        } else {
            // ২. গ্লোবাল অন না থাকলে স্পেসিফিক রুল চেক করুন
            add_filter('comments_open', [ $this, 'filter_specific_comments' ], 20, 2);
        }

        if ( !empty($this->settings['disable_pingbacks']) ) {
            add_filter( 'xmlrpc_methods', function( $methods ) {
                unset( $methods['pingback.ping'] );
                return $methods;
            });
        }
        
        // --- POSTS & REVISIONS ---
        if ( !empty($this->settings['disable_revisions']) ) {
            if (!defined('WP_POST_REVISIONS')) define('WP_POST_REVISIONS', false);
        } elseif ( !empty($this->settings['limit_revisions']) ) {
            if (!defined('WP_POST_REVISIONS')) define('WP_POST_REVISIONS', intval($this->settings['limit_revisions']));
        }


        // --- MEDIA TWEAKS ---
        // 1. Disable Scaled Image Creation
        if ( !empty($this->settings['disable_scaled_images']) ) {
            add_filter( 'big_image_size_threshold', '__return_false' );
        } 
        // 2. Custom Big Image Threshold (if not disabled)
        elseif ( !empty($this->settings['big_image_threshold']) ) {
            add_filter( 'big_image_size_threshold', function() {
                return intval($this->settings['big_image_threshold']);
            });
        }

        // 3. Disable Automatic Image Sizes
        if ( !empty($this->settings['disable_extra_sizes']) ) {
            add_filter( 'intermediate_image_sizes_advanced', function( $sizes ) {
                unset( $sizes['medium_large'] );
                unset( $sizes['1536x1536'] );
                unset( $sizes['2048x2048'] );
                return $sizes;
            });
        }

        // --- PERFORMANCE ---
        // 1. Disable Emojis
        if ( !empty($this->settings['disable_emojis']) ) {
            remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
            remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
            remove_action( 'wp_print_styles', 'print_emoji_styles' );
            remove_action( 'admin_print_styles', 'print_emoji_styles' ); 
            remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
            remove_filter( 'comment_text_rss', 'wp_staticize_emoji' ); 
            remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
        }

        // 2. Disable Embeds
        if ( !empty($this->settings['disable_embeds']) ) {
            remove_action( 'wp_head', 'wp_oembed_add_host_js' );
            // Remove oEmbed results filter
            remove_filter( 'oembed_dataparse', 'wp_filter_oembed_result', 10 );
        }

        // 3. Disable oEmbed Discovery
        // Note: Often part of Embeds, but separated here as requested
        if ( !empty($this->settings['disable_oembed_discovery']) || !empty($this->settings['disable_embeds']) ) {
            remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
        }

        // 4. Disable REST API for Public
        if ( !empty($this->settings['disable_rest_api_public']) ) {
            add_filter( 'rest_authentication_errors', function( $result ) {
                if ( ! empty( $result ) ) {
                    return $result;
                }
                if ( ! is_user_logged_in() ) {
                    return new WP_Error( 'rest_not_logged_in', 'You are not currently logged in.', array( 'status' => 401 ) );
                }
                return $result;
            });
        }

        // 5. Disable RSS Feeds
        if ( !empty($this->settings['disable_rss']) ) {
            $feeds = ['do_feed', 'do_feed_rdf', 'do_feed_rss', 'do_feed_rss2', 'do_feed_atom'];
            foreach($feeds as $feed) {
                add_action($feed, [$this, 'disable_feed'], 1);
            }
        }

        // 6. Heartbeat Control
        $hb_freq = isset($this->settings['heartbeat_freq']) ? $this->settings['heartbeat_freq'] : 'default';
        
        if ( $hb_freq === 'disable' ) {
            add_action( 'init', function() {
                wp_deregister_script( 'heartbeat' );
            });
        } elseif ( $hb_freq !== 'default' ) {
            add_filter( 'heartbeat_settings', function( $settings ) use ($hb_freq) {
                $settings['interval'] = intval($hb_freq);
                return $settings;
            });
        }


        
        // --- CRON ---
        if ( !empty($this->settings['disable_wp_cron']) ) {
            if ( ! defined('DISABLE_WP_CRON') ) {
                define('DISABLE_WP_CRON', true);
            }
        }
    }
    
    /**
     * COMMENTS: Handles redirect and menu removal on admin_init
     * [FIXED] Now checks if it's a Tweak Save action before redirecting.
     */
    public function disable_comments_admin_init() {
        global $pagenow;
        
        // Prevent redirect during settings save operation
        if ( isset($_POST['is_tweak_save']) && $_POST['is_tweak_save'] == '1' ) {
            // Hide the comments menu regardless of page
            remove_menu_page('edit-comments.php');
            return;
        }

        // 1. Redirect logic to prevent direct access to comments page
        if ($pagenow === 'edit-comments.php') { 
            wp_redirect(admin_url()); 
            exit; 
        }
        
        // 2. Hide the comments menu
        remove_menu_page('edit-comments.php');
    }


    public function admin_tweaks() {
        // 1. Disable Toolbar
        if ( !empty($this->settings['disable_admin_bar']) && !current_user_can('administrator') ) {
            show_admin_bar(false);
        }

        // 2. Hide Welcome Panel
        if ( !empty($this->settings['hide_welcome']) ) {
            remove_action('welcome_panel', 'wp_welcome_panel');
        }

        // 3. Disable Update Nags (For non-admins usually, or global)
        if ( !empty($this->settings['disable_update_nags']) ) {
            remove_action( 'admin_notices', 'update_nag', 3 );
        }

        // 4. Hide Dashboard Widgets
        if ( !empty($this->settings['hide_dash_widgets']) ) {
            add_action('wp_dashboard_setup', function() {
                remove_meta_box('dashboard_activity', 'dashboard', 'normal');
                remove_meta_box('dashboard_quick_press', 'dashboard', 'side');
                remove_meta_box('dashboard_primary', 'dashboard', 'side'); // Events & News
            });
        }

        // 5. Hide Menus for Non-Admins
        if ( !empty($this->settings['hide_non_admin_menus']) && !current_user_can('administrator') ) {
            add_action('admin_menu', function() {
                remove_menu_page('tools.php');
                remove_menu_page('options-general.php');
            });
        }
        
        // 6. Disable Email Verification Nag
        if ( !empty($this->settings['disable_email_nag']) ) {
            add_filter( 'admin_email_check_interval', '__return_false' );
        }



        // 8. Hide Admin Footer Text
        if ( !empty($this->settings['hide_footer_credit']) ) {
            add_filter( 'admin_footer_text', '__return_empty_string', 11 );
        }

    }


    public function disable_feed() {
        wp_die( sprintf( __( 'No feed available, please visit the <a href="%s">homepage</a>!', 'royal-sentinel' ), esc_url( home_url( '/' ) ) ) );
    }
    
    private function get_bg_css_value($prefix) {
        $type   = isset($this->settings[$prefix.'_type']) ? $this->settings[$prefix.'_type'] : 'solid';
        $color1 = isset($this->settings[$prefix.'_color1']) ? $this->settings[$prefix.'_color1'] : '';
        $color2 = isset($this->settings[$prefix.'_color2']) ? $this->settings[$prefix.'_color2'] : '';
        $angle  = isset($this->settings[$prefix.'_angle']) ? $this->settings[$prefix.'_angle'] : '90deg';
        
        if ( empty($color1) ) return '';
        
        if ( $type === 'gradient' && !empty($color2) ) {
            return "linear-gradient({$angle}, {$color1} 0%, {$color2} 100%)";
        }
        
        return $color1;
    }


    public function custom_login_styles() { 
        $logo_url    = isset($this->settings['login_logo_url']) ? $this->settings['login_logo_url'] : '';
        $bg_image    = isset($this->settings['login_bg_image']) ? $this->settings['login_bg_image'] : '';
        $text_color  = isset($this->settings['login_text_color']) ? $this->settings['login_text_color'] : '';
        $btn_text    = isset($this->settings['login_button_text_color']) ? $this->settings['login_button_text_color'] : '';

        $bg_color    = $this->get_bg_css_value('login_bg');
        $form_bg     = $this->get_bg_css_value('login_form_bg');
        $btn_bg      = $this->get_bg_css_value('login_btn_bg');
        $btn_hover   = $this->get_bg_css_value('login_btn_hover');

        ?>
        <style type="text/css">
            /* Background & Image */
            body.login {
                <?php if ( $bg_color ) : ?>background: <?php echo esc_attr($bg_color); ?> !important;<?php endif; ?>
                <?php if ( $bg_image ) : ?>
                background-image: url('<?php echo esc_url($bg_image); ?>') !important;
                background-size: cover !important;
                background-position: center !important;
                background-attachment: fixed !important;
                <?php endif; ?>
            }

            /* Custom Logo */
            <?php if ( $logo_url ) : ?>
            #login h1 a, .login h1 a {
                background-image: url('<?php echo esc_url($logo_url); ?>') !important;
                height: 80px;
                width: 320px;
                background-size: contain;
                background-repeat: no-repeat;
                margin-bottom: 20px;
            }
            <?php endif; ?>

            /* Form Background */
            <?php if ( $form_bg ) : ?>
            #login form {
                background: <?php echo esc_attr($form_bg); ?> !important;
                border: none;
                box-shadow: 0 4px 15px rgba(0,0,0,0.1);
                border-radius: 8px;
            }
            <?php endif; ?>

            /* Buttons */
            <?php if ( $btn_bg || $btn_text ) : ?>
            #login form .button-primary {
                <?php if ( $btn_bg ) : ?>background: <?php echo esc_attr($btn_bg); ?> !important;<?php endif; ?>
                <?php if ( $btn_text ) : ?>color: <?php echo esc_attr($btn_text); ?> !important;<?php endif; ?>
                border-color: transparent !important;
                box-shadow: none !important;
                text-shadow: none !important;
                transition: all 0.3s ease;
            }
            <?php endif; ?>
            
            <?php if ( $btn_hover ) : ?>
            #login form .button-primary:hover,
            #login form .button-primary:focus {
                background: <?php echo esc_attr($btn_hover); ?> !important;
            }
            <?php endif; ?>

            /* Text Colors */
            <?php if ( $text_color ) : ?>
            #login form label,
            #login .message,
            #login #login_error,
            #login #nav a, 
            #login #backtoblog a {
                color: <?php echo esc_attr($text_color); ?> !important;
            }
            #login #nav a:hover, 
            #login #backtoblog a:hover {
                filter: brightness(0.8);
            }
            <?php endif; ?>
        </style>
        <?php 
    }

    // --- AJAX HANDLER FOR ACTIONS ---
    public function handle_ajax_action() {
        check_ajax_referer('rs_tweaks_nonce');
        if(!current_user_can('manage_options')) wp_send_json_error('Forbidden');

        $action_type = sanitize_text_field($_POST['run_action']);
        global $wpdb;
        $msg = "Done";

        switch($action_type) {
            case 'delete_spam_comments':
                $wpdb->query("DELETE FROM $wpdb->comments WHERE comment_approved = 'spam'");
                $msg = "Spam comments deleted.";
                break;
            case 'delete_trash_comments':
                $wpdb->query("DELETE FROM $wpdb->comments WHERE comment_approved = 'trash'");
                $msg = "Trash comments deleted.";
                break;
            case 'delete_pending_comments':
                $wpdb->query("DELETE FROM $wpdb->comments WHERE comment_approved = '0'");
                $msg = "Pending comments deleted.";
                break;
            case 'empty_post_trash':
                $wpdb->query("DELETE FROM $wpdb->posts WHERE post_status = 'trash'");
                $msg = "Trash posts permanently deleted.";
                break;
            case 'purge_revisions':
                $wpdb->query("DELETE FROM $wpdb->posts WHERE post_type = 'revision'");
                $msg = "All post revisions deleted.";
                break;
            case 'optimize_db':
                $tables = $wpdb->get_results("SHOW TABLES", ARRAY_N);
                foreach ($tables as $table) {
                    $wpdb->query("OPTIMIZE TABLE $table[0]");
                }
                $msg = "Database tables optimized.";
                break;
            case 'delete_transients':
                $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE ('%\_transient\_%')");
                $msg = "All transients deleted.";
                break;
            case 'clear_cache':
                global $wp_object_cache;
                if(is_object($wp_object_cache)) wp_cache_flush();
                $msg = "Object cache flushed.";
                break;
            case 'clear_cron':
                $wpdb->query("DELETE FROM $wpdb->options WHERE option_name = 'cron'");
                $msg = "Scheduled tasks cleared.";
                break;
            case 'purge_revisions':
                $wpdb->query( "DELETE FROM $wpdb->posts WHERE post_type = 'revision'" );
                $msg = "All revisions have been permanently deleted.";
                break;

            case 'optimize_revisions_db':
                $wpdb->query( "OPTIMIZE TABLE $wpdb->posts" );
                $msg = "Posts table (containing revisions) has been optimized.";
                break;
            case 'delete_unused_media':
                // টাইম লিমিট বাড়ানো কারণ স্ক্যান করতে সময় লাগতে পারে
                set_time_limit(300);
                
                // ১. শুধুমাত্র post_parent = 0 এমন অ্যাটাচমেন্ট খোঁজা
                $attachments = $wpdb->get_results( "SELECT ID FROM $wpdb->posts WHERE post_type = 'attachment' AND post_parent = 0" );
                
                $count = 0;
                $skipped = 0;

                if ( $attachments ) {
                    foreach ( $attachments as $attachment ) {
                        $is_used = false;
                        $image_url = wp_get_attachment_url($attachment->ID);

                        if ( ! $image_url ) continue;

                        // ২. চেক: এই ইমেজটি ফিচার্ড ইমেজ হিসেবে আছে কিনা
                        $is_featured = $wpdb->get_var( $wpdb->prepare("SELECT meta_id FROM $wpdb->postmeta WHERE meta_key = '_thumbnail_id' AND meta_value = %d LIMIT 1", $attachment->ID) );
                        if ( $is_featured ) {
                            $is_used = true;
                        }

                        // ৩. চেক: এই ইমেজের URL টি অপশন টেবিলে (Settings/Theme Mods) আছে কিনা
                        if ( ! $is_used ) {
                            $in_options = $wpdb->get_var( $wpdb->prepare("SELECT option_id FROM $wpdb->options WHERE option_value LIKE %s LIMIT 1", '%' . $wpdb->esc_like($image_url) . '%') );
                            if ( $in_options ) $is_used = true;
                        }

                        // ৪. চেক: এই ইমেজের URL টি কোনো পোস্ট বা পেজের কন্টেন্টের ভেতরে আছে কিনা
                        if ( ! $is_used ) {
                            $in_content = $wpdb->get_var( $wpdb->prepare("SELECT ID FROM $wpdb->posts WHERE post_content LIKE %s LIMIT 1", '%' . $wpdb->esc_like($image_url) . '%') );
                            if ( $in_content ) $is_used = true;
                        }

                        // ৫. চেক: এই ইমেজের URL টি কোনো মেটা ফিল্ডে (Post Meta) আছে কিনা
                        if ( ! $is_used ) {
                            $in_meta = $wpdb->get_var( $wpdb->prepare("SELECT meta_id FROM $wpdb->postmeta WHERE meta_value LIKE %s LIMIT 1", '%' . $wpdb->esc_like($image_url) . '%') );
                            if ( $in_meta ) $is_used = true;
                        }

                        // ৬. সিদ্ধান্ত: যদি কোথাও না পাওয়া যায়, তবেই ডিলিট করো
                        if ( ! $is_used ) {
                            wp_delete_attachment( $attachment->ID, true );
                            $count++;
                        } else {
                            $skipped++;
                        }
                    }
                }
                $msg = "Cleaned up: $count files deleted. Safety Check: $skipped active files kept.";
                break;
            
            case 'delete_expired_transients':
                // শুধুমাত্র মেয়াদোত্তীর্ণ ট্রানজিয়েন্ট ডিলিট
                $wpdb->query("DELETE option_name, option_value FROM $wpdb->options WHERE option_name LIKE '_transient_timeout_%' AND option_value < '" . time() . "'");
                // সাথে সাথে মূল ডাটাও ডিলিট করা দরকার, কিন্তু WP ক্রন এটা হ্যান্ডেল করে। আমরা ফোর্স ক্লিন করছি:
                $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_%' AND option_name NOT LIKE '_transient_timeout_%' AND option_name NOT IN (SELECT CONCAT('_transient_', SUBSTRING(option_name, 20)) FROM $wpdb->options WHERE option_name LIKE '_transient_timeout_%')");
                
                $msg = "Expired transients deleted successfully.";
                break;

            case 'delete_all_system_cache':
                // ১. Transients ডিলিট
                $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '%_transient_%'");
                
                // ২. Object Cache ফ্লাশ
                global $wp_object_cache;
                if(is_object($wp_object_cache)) wp_cache_flush();
                
                // ৩. Rewrite Rules ফ্লাশ (Browser/Server Cache এর জন্য সহায়ক)
                flush_rewrite_rules();
                
                $msg = "System cache cleared & rewrite rules flushed.";
                break;

            case 'remove_orphaned_postmeta':
                $wpdb->query("DELETE pm FROM $wpdb->postmeta pm LEFT JOIN $wpdb->posts wp ON wp.ID = pm.post_id WHERE wp.ID IS NULL");
                $msg = "Orphaned post metadata removed.";
                break;

            case 'remove_orphaned_term_relationships':
                $wpdb->query("DELETE tr FROM $wpdb->term_relationships tr LEFT JOIN $wpdb->posts wp ON wp.ID = tr.object_id WHERE wp.ID IS NULL");
                $msg = "Orphaned term relationships removed.";
                break;

            case 'remove_orphaned_comments':
                $wpdb->query("DELETE FROM $wpdb->comments WHERE comment_post_ID > 0 AND comment_post_ID NOT IN (SELECT ID FROM $wpdb->posts)");
                $msg = "Orphaned comments deleted.";
                break;

            case 'optimize_db':
                $tables = $wpdb->get_results("SHOW TABLES", ARRAY_N);
                foreach ($tables as $table) {
                    $wpdb->query("OPTIMIZE TABLE $table[0]");
                }
                $msg = "All database tables optimized and healthy.";
                break;
            
            case 'clear_cron':
                $wpdb->query("DELETE FROM $wpdb->options WHERE option_name = 'cron'");
                $msg = "All scheduled tasks cleared. Core tasks will regenerate automatically.";
                break;

            case 'delete_orphaned_crons':
                $crons = _get_cron_array();
                $count = 0;
                if ( is_array( $crons ) ) {
                    foreach ( $crons as $timestamp => $cronhooks ) {
                        foreach ( $cronhooks as $hook => $events ) {
                            // চেক: এই হুকটির সাথে কোনো অ্যাকশন ফাংশন যুক্ত আছে কিনা
                            if ( ! has_action( $hook ) ) {
                                foreach ( $events as $key => $event ) {
                                    // আনশিডিউল করা
                                    wp_unschedule_event( $timestamp, $hook, $event['args'] );
                                    $count++;
                                }
                            }
                        }
                    }
                }
                $msg = "Removed $count orphaned cron events.";
                break;

            case 'regenerate_permalinks':
                // পার্মালিংক স্ট্রাকচার রিফ্রেশ করা (হার্ড ফ্লাশ)
                global $wp_rewrite;
                $wp_rewrite->init();
                $wp_rewrite->flush_rules(true);
                
                $msg = "Permalinks successfully regenerated.";
                break;

            case 'reset_htaccess':
                // .htaccess ফাইলের পাথ
                $htaccess_file = ABSPATH . '.htaccess';
                
                // ডিফল্ট ওয়ার্ডপ্রেস রুলস
                $rules = "# BEGIN WordPress\n";
                $rules .= "<IfModule mod_rewrite.c>\n";
                $rules .= "RewriteEngine On\n";
                $rules .= "RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]\n";
                $rules .= "RewriteBase /\n";
                $rules .= "RewriteRule ^index\.php$ - [L]\n";
                $rules .= "RewriteCond %{REQUEST_FILENAME} !-f\n";
                $rules .= "RewriteCond %{REQUEST_FILENAME} !-d\n";
                $rules .= "RewriteRule . /index.php [L]\n";
                $rules .= "</IfModule>\n";
                $rules .= "# END WordPress\n";

                if ( is_writable( $htaccess_file ) ) {
                    // ফাইল ওভাররাইট করা
                    file_put_contents( $htaccess_file, $rules );
                    $msg = ".htaccess has been reset to default WordPress rules.";
                } else {
                    $msg = "Error: .htaccess file is not writable.";
                    wp_send_json_error($msg); // এরর রিটার্ন
                }
                break;
        }

        wp_send_json_success($msg);
    }



    /**
     * নির্দিষ্ট পোস্ট টাইপের জন্য কমেন্ট বন্ধ করার ফিল্টার
     */
    public function filter_specific_comments( $open, $post_id ) {
        $post = get_post( $post_id );
        
        // পোস্ট না পাওয়া গেলে যা আছে তাই রিটার্ন
        if ( ! $post ) return $open;

        // পোস্টের জন্য চেক
        if ( !empty($this->settings['disable_post_comments']) && $post->post_type === 'post' ) {
            return false;
        }

        // পেজের জন্য চেক
        if ( !empty($this->settings['disable_page_comments']) && $post->post_type === 'page' ) {
            return false;
        }

        return $open;
    }


}

new RS_Tweaks_Logic();