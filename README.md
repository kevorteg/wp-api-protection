# 🛡️ WP API Protection

![Version](https://img.shields.io/badge/version-2.0.0-blue.svg?style=flat-square)
![WordPress](https://img.shields.io/badge/WordPress-6.0+-21759b.svg?style=flat-square&logo=wordpress&logoColor=white)
![License](https://img.shields.io/badge/license-GPL--3.0-green.svg?style=flat-square)

**WP API Protection** is a professional security plugin designed to protect WordPress REST API endpoints with a hybrid approach: **Hard Blocking** (Whitelist/Roles) and **Biblical Rate Limiting** (Grace Attempts).

> *Originally designed for Christian ministries, now robust enough for any organization valuing ethical security.*

---

## ✨ Features

### 🔒 Layer 1: Hard Protection
- **IP Whitelist:** Allow specific IPs to bypass all checks.
- **Role Based Access:** Administrators always have access.
- **Hard Block Mode:** (Optional) Completely lock down the API for non-whitelisted users (Private API mode).
- **Anti-Hacking:** prevents user enumeration (`/?author=1`) and hides WP version headers.

### ⏳ Layer 2: Grace Rate Limiting
- **Smart Counting:** Tracks failed attempts by IP.
- **Grace Attempt:** Warns the user before the final block.
- **Biblical/Custom Messages:** Returns HTTP 401/403 errors with reflective messages (configurable).

### 📊 Modern Dashboard
- **Visual Status:** See active rules and whitelist status at a glance.
- **Live Logs:** Monitor recent blocks directly from the admin panel.
- **Easy Config:** No coding required. Manage everything from `Settings > API Protection`.

---

## 🚀 Installation

1. Download the latest release (`wp-api-protection.zip`).
2. Upload to your WordPress via **Plugins > Add New > Upload**.
3. Activate the plugin.
4. Go to **Settings > API Protection** to configure your Whitelist and Messages.

---

## ⚙️ Configuration

| Setting | Description | Recommended |
| :--- | :--- | :--- |
| **Hard Block Mode** | Restricts API to Whitelist/Admins only. | `Files` (Public) or `True` (Private Intranet) |
| **Whitelist IPs** | List of trusted IPs (Server, Devs). | Your Office IP |
| **Rate Limit** | Max attempts before temp block. | `5` |

---

## 🤝 Contributing

This project is open source. Feel free to submit Pull Requests or open Issues on GitHub.

**License:** GPLv3  
**Authors:** Kevin Ortega & Misión Juvenil
