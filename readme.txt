=== WP API Protection ===
Contributors: kevorteg
Donate link: https://example.com/
Tags: rest api, security, api firewall, rate limit, geo block
Requires at least: 5.8
Tested up to: 6.4
Stable tag: 3.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Professional multi-layer security suite for the WordPress REST API. Firewall, rate limiting, geo-blocking, IP blacklist/whitelist, namespace blocking, and security headers.

== Description ==

**WP API Protection** is a comprehensive, lightweight security plugin designed specifically to lock down and monitor your WordPress REST API. 

As the REST API is increasingly targeted by automated scanners, scrapers, and malicious bots, this plugin acts as a dedicated application firewall to ensure that your endpoints are protected.

### Key Features:

*   **Hard Block Mode:** Lock down the REST API entirely. Only allow authenticated Administrators and IP addresses in your Whitelist to access the API.
*   **Rate Limiting:** Protect against brute-force attacks and scrapers by limiting the number of API requests an IP can make within a configurable time window.
*   **Geo-Blocking:** Block entire countries from accessing your API using real-time IP-to-Country resolution (cached locally for performance).
*   **Namespace Blocking:** Hide specific REST namespaces or routes (e.g., `/wp/v2/users`) from public access to prevent user enumeration and data leakage.
*   **IP Whitelist & Blacklist:** Explicitly allow trusted IPs or permanently ban known malicious actors.
*   **Security Headers:** Automatically injects secure HTTP headers into all REST API responses (`X-Content-Type-Options`, `X-Frame-Options: SAMEORIGIN`, `X-XSS-Protection`, etc.).
*   **Intrusion Log Dashboard:** Monitor all blocked requests, including the IP, country, HTTP method, Requested URL, User-Agent, and the exact reason for the block.
*   **Proxy-Aware IP Resolution:** Accurately detects the real IP of visitors behind Cloudflare, Nginx proxies, or load balancers.

### Third-Party Service Disclosure
To provide the Geo-Blocking functionality, this plugin securely communicates with the free `ip-api.com` service to resolve an IP address to a country code. This lookup is only performed when an IP address first accesses the API, and the result is cached locally in your database for 24 hours to ensure privacy and maximum performance.

== Installation ==

1. Upload the `wp-api-protection` directory to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Navigate to **API Protection** in your admin sidebar to configure your firewall settings and view intrusion logs.

== Frequently Asked Questions ==

= Does this plugin block normal WordPress site visitors? =
No. WP API Protection acts strictly on the WordPress REST API endpoints (urls starting with `/wp-json/`). Your regular website traffic, pages, and posts are completely unaffected.

= How does the Hard Block mode work? =
When enabled, anyone making a request to the REST API who is not logged in as an Administrator, or whose IP is not explicitly whitelisted, will receive a `403 Forbidden` response.

= A service I use (like Zapier) stopped working after I activated this plugin! =
If you rely on third-party services that need to talk to your site via the REST API, you must add their IP addresses to the **IP Whitelist** in the plugin settings, or disable "Hard Block Mode" and rely on the Rate Limiter instead.

== Screenshots ==

1. The modern API Security settings dashboard.
2. Detailed Intrusion Logs showing exactly who is probing your API.

== Changelog ==

= 3.0.0 =
* Complete architecture rewrite for professional-grade security.
* Added IP Blacklist functionality.
* Added Namespace blocking (hide specific endpoints).
* Added real-time Geo-blocking with 24-hour localized caching.
* Implemented secure proxy-aware IP resolution (Cloudflare/Load Balancer support).
* Massive UI redesign: replaced emojis with a clean, professional dark sidebar layout.
* Hardened internal data handling to strict WP.org security standards.

= 2.0.0 =
* Initial public release with Core Rate Limiting and Whitelisting.
