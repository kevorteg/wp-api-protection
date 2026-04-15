# REST API Protection

![Version](https://img.shields.io/badge/version-3.0.0-blue.svg?style=flat-square)
![WordPress](https://img.shields.io/badge/WordPress-6.0+-21759b.svg?style=flat-square&logo=wordpress&logoColor=white)
![License](https://img.shields.io/badge/license-GPLv2--or--later-green.svg?style=flat-square)

**REST API Protection** is a professional, multi-layered cybersecurity suite designed specifically to defend WordPress REST API endpoints against scraping, automated exploitation, injection attacks, and unauthorized access.

---

## Architecture and Features

### Layer 1: Firewall and Access Control
- **Hard Block Status:** (Optional) Deny all REST API traffic by default except for authenticated Administrators and Whitelisted IP addresses.
- **IP Blacklisting:** Permanently ban known malicious actors. Blacklist rules execute with priority zero before any other logic.
- **IP Whitelisting:** Bypass all security rules and rate limits for trusted endpoints (e.g., origin servers, development teams, integrations).
- **Geo-Blocking:** Deny traffic originating from configurable ISO 3166-1 alpha-2 country codes. Lookups are locally cached to maximize performance.
- **Namespace Blocking:** Hide specific REST namespaces or routes (e.g., `/wp/v2/users` or `/wc/v3`) from public discovery, mitigating data leakage and user enumeration.
- **Proxy-Aware Resolution:** Ensure accurate threat detection when running behind Cloudflare, Nginx proxies, or load balancers, defeating X-Forwarded-For spoofing.

### Layer 2: Behavioral Defense
- **Rate Limiting:** Granular, sliding-window rate tracking. Automatically temporarily ban IP addresses that exceed request thresholds.
- **Security Headers:** Automatically injects strict HTTP response headers into all REST communications (`X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, `X-XSS-Protection`, etc.).
- **Troll Mode:** (Optional) Replaces standard JSON 403 blocks with obfuscated terminal-like browser responses and CLI decoys to frustrate automated scanners and waste threat actor resources.

### Layer 3: Auditing and Monitoring
- **Intrusion Dashboards:** Visual metrics on blocked interactions, rate limit violations, and security events.
- **Detailed Forensic Logs:** Track IP, Request Type (Block, Rate, Geo, NS), Request URL, and User-Agent.
- **Data Export:** Secure, nonce-protected CSV export for external Security Information and Event Management (SIEM) ingestion.

---

## Installation

### Option 1: Composer (Recommended)
```bash
composer require kevorteg/wp-api-protection
```

### Option 2: Manual
1. Download the latest release (`wp-api-protection.zip`).
2. Upload the uncompressed directory to `/wp-content/plugins/wp-api-protection/`.
3. Activate the plugin through the WordPress Administration interface.
4. Navigate to **API Protection** in the main sidebar to configure firewall policies.

---

## Operations Guide

| Component | Default | Configuration Context |
| :--- | :--- | :--- |
| **Hard Block Mode** | Enabled | Disable if Public REST access is required for unauthenticated operations. |
| **Security Headers** | Enabled | Recommended to leave enabled for baseline security. |
| **Rate Limiter** | 30 requests / 60s | Adjust based on normal web application consumption. |
| **Block Duration** | 3600 seconds | Penalty duration for rate limit violations. |
| **Alert Threshold** | 20 triggers / 5 min | Threshold for alerting the site administrator via email. |

---

## Contributing

This project is released open source under the GPLv2 (or later) license. Security patches, pull requests, and vulnerability disclosures are welcome via GitHub.

**Authors:** Kevin Ortega