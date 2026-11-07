=== Royal Sentinel ===
Contributors: royaltechnologies
Tags: security, cloudflare, 2fa, optimization, hardening
Requires at least: 5.8
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 26.11.07
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Modular Cloudflare Security, IP Manager, and WordPress Hardening Suite.

== Description ==

Royal Sentinel is an all-in-one security and performance optimization toolkit designed to keep your WordPress site safe and lean. It combines powerful Cloudflare API integration with deep system hardening and performance tweaks.

= Key Features =
* **Cloudflare Manager:** Manage blocked IP lists, Custom WAF rules, and sync domains directly from your dashboard.
* **Two-Factor Authentication (2FA):** Secure your login with email-based verification codes.
* **Advanced Hardening:** Protect system files, hide login URLs, disable file editors, and force SSL.
* **System Tweaks:** Clean up unused media, purge post revisions, optimize database tables, and manage WP-Cron.
* **Local Bot Protection:** Track and ban malicious IPs locally before they hit your login page.

== Installation ==

1. Upload the `royal-sentinel` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Navigate to the 'Royal Sentinel' menu to configure your settings.
4. For Cloudflare features, enter your API credentials in the Settings tab.

== Frequently Asked Questions ==

= Why are my settings not saving in Hardening tab? =
If you enable 'Disable Plugin & Theme Install', the system enters a strict read-only mode to prevent unauthorized changes. To change settings, you may need to temporarily disable this through your database.

= Do I need a Cloudflare account? =
Yes, to use the Cloudflare specific features like IP List management and Custom Rules, a valid API key is required.

== Screenshots ==
1. Dashboard overview with threat statistics.
2. Cloudflare IP List manager with CSV import/export.
3. Advanced Hardening options for system security.
4. Performance tweaks and database optimization tools.

== Changelog ==

= 26.11.07 =
* Migrated update server to GitHub Releases for faster and reliable automated updates.
* Refactored update checker to load release manifests directly from GitHub.

= 26.11.02 =
* Fixed PHP Warning: "Cannot modify header information - headers already sent" in Security Headers module by verifying header status before removal.
* Added headers_sent() check and error suppression for technology masking headers (X-Powered-By, Server, X-Turbo-Charged-By).
* Added headers_sent() safety check in security.txt generation logic.

= 26.10.26 =
* Added custom plugin icon and logo across the admin menu and plugins list page.
* Minor improvements and code refinements.

= 26.10.22 =
* Ensured comprehensive Admin Whitelisting: automatically trusts and auto-whitelists administrator IPs on login and dashboard activity, purges conflicting bans, and syncs trusted IPs to Cloudflare Access Rules.
* Improved Cloudflare Smart Rate Limiting and Real-time Defense to automatically exempt logged-in users and admin-ajax.php, preventing DataTables Ajax errors and admin dashboard lockouts.
* Added Cross-Origin-Opener-Policy (COOP), Cross-Origin-Resource-Policy (CORP), and X-Permitted-Cross-Domain-Policies security headers.
* Added Server & Technology Masking to prevent version leaks (strips X-Powered-By, X-Turbo-Charged-By, and Server headers via PHP and .htaccess).
* Fixed Content-Security-Policy (CSP) escaping bug by stripping slashes before output and unslashing on save.
* Improved Strict-Transport-Security (HSTS) SSL detection behind reverse proxies and Cloudflare.
* Updated Security Headers UI with configuration toggles and live status preview for all headers.

= 26.09.21 =
* Fixed a critical rate limit issue that caused legitimate users to be permanently locked out if they refreshed the page during a temporary ban.
* Fixed 404 attack detection sliding window bug.

= 25.12.07 =
* Initial stable release.
* Added Cloudflare WAF integration.
* Added Email-based 2FA logic.
* Implemented system performance tweaks.