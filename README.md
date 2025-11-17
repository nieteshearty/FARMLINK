# 🌾 FARMLINK

An agricultural marketplace connecting farmers and buyers with super-admin oversight. FARMLINK delivers a three-role experience (Super Admin, Farmer, Buyer), combining e-commerce, real-time messaging, delivery planning, and inventory management in one PHP/MySQL application.

---
## 🧭 Table of Contents

1. [System Overview](#-system-overview)
2. [User Roles](#-user-roles)
3. [Tech Stack](#-tech-stack)
4. [Feature Highlights](#-feature-highlights)
5. [Getting Started](#-getting-started)
6. [Deployment (Wasmer & Production Tips)](#-deployment-wasmer--production-tips)
7. [Project Structure](#-project-structure)
8. [Demo Accounts](#-demo-accounts)
9. [Troubleshooting](#-troubleshooting)
10. [Contributing](#-contributing)
11. [License](#-license)
12. [Quick Facts](#-quick-facts)

---
## 🎯 System Overview

FARMLINK connects agricultural producers with buyers under the supervision of a super admin. The platform delivers:

- Real-time chat between farmers and buyers
- Seasonal product recommendations and a Shopee-inspired shopping cart
- Location-aware delivery scheduling with customizable zones
- Powerful super admin dashboards for analytics, monitoring, and settings

---
## 👥 User Roles

### 🔱 Super Admin
- Full visibility and control over the platform
- Analytics, monitoring, and global settings management
- Advanced user management with activity logs and security tooling

### 👨‍🌾 Farmer
- Product CRUD with photos, pricing, and stock control
- Delivery zone planning with dynamic fees and scheduling
- Order fulfillment workflow (reserve, confirm, deliver)
- Business profile, farm details, certifications, and mapping

### 🛒 Buyer
- Modern cart, wishlist, and voucher system
- Multi-tier location search with Philippine focus
- Delivery visibility with farmer schedules and pricing
- Order history, reordering, and direct messaging to farmers

---
## 🧰 Tech Stack

| Layer       | Technologies                                                                |
|-------------|-----------------------------------------------------------------------------|
| Backend     | PHP 8+, MySQL 8+, PDO, secure session management                            |
| Frontend    | HTML5, CSS3 (Grid/Flexbox), Vanilla JS, Font Awesome 6, Leaflet.js          |
| Services    | OpenStreetMap, Nominatim (geocoding), Chart.js                              |
| Tooling     | XAMPP / Apache, phpMyAdmin, Composer-ready structure                        |

---
## ✨ Feature Highlights

- 🛒 **Shopee-style Cart:** bulk actions, vouchers, free shipping logic
- 🌱 **Climate-aware Suggestions:** seasonal product curation for the Philippines
- 🗺️ **Interactive Delivery Maps:** OpenStreetMap + Leaflet with custom markers
- 📦 **Inventory Mastery:** reservations, low-stock alerts, historical logs
- 💬 **Live Messaging:** buyer-farmer conversations with rich notifications
- 🛡️ **Security Everywhere:** hardened sessions, CSP, prepared statements, role gating
- 📱 **Responsive & Mobile-friendly:** optimized for phones and tablets

---
## 🚀 Getting Started

### Prerequisites
- PHP 8.0+ with PDO, PDO_MySQL, GD, OpenSSL
- MySQL 8.0+ (or MariaDB 10.4+)
- Apache/Nginx with `mod_rewrite`
- XAMPP/WAMP/MAMP recommended for local setup

### 1. Clone the Repo
```bash
# Windows (XAMPP)
C:\xampp\htdocs\FARMLINK

# Linux / macOS
/var/www/html/FARMLINK
```

### 2. Database Setup
```sql
CREATE DATABASE farmlink CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```
Import `farmlink.sql` via phpMyAdmin or CLI:
```bash
mysql -u root -p farmlink < farmlink.sql
```

### 3. Configure Credentials
FARMLINK reads environment variables first:
- `DATABASE_URL` (e.g. `mysql://user:pass@host:port/dbname`)
- or `MYSQLHOST`, `MYSQLPORT`, `MYSQLDATABASE`, `MYSQLUSER`, `MYSQLPASSWORD`

Without env vars, the defaults in `api/config.php` apply (update as needed).

### 4. Permissions
```bash
chmod 755 uploads/
chmod 755 uploads/profiles/
chmod 755 uploads/products/
```

### 5. Run Locally
Visit `http://localhost/FARMLINK/` (or root) – FARMLINK redirects to the appropriate dashboard after login.

---
## ☁️ Deployment (Wasmer & Production Tips)

- Set env vars (`DATABASE_URL` or `MYSQL*`, optional `FORCE_HTTPS=true`).
- FARMLINK uses a `BASE_URL` constant – all CSS/JS/uploads now resolve against it.
- Ensure `/uploads/profiles` and `/uploads/products` exist and are writable.
- On Wasmer, Wasmer’s public URL becomes the `BASE_URL`, preventing `/FARMLINK/` 404s.

### Production Checklist
- [x] Remove dev/test assets
- [x] Harden sessions & security headers
- [x] Normalize upload paths (`uploads/...`)
- [ ] Enable HTTPS (`FORCE_HTTPS=true` + SSL cert)
- [ ] Update DB credentials (non-root user)
- [ ] Configure backups, monitoring, and logging

---
## 🗃️ Project Structure
```
FARMLINK/
├── api/                # PHP API endpoints
│   ├── config.php      # DB + BASE_URL detection
│   ├── messages/       # Chat APIs
│   └── ...
├── pages/              # Role-based UI (superadmin, farmer, buyer, auth, common)
├── includes/           # Helpers (SessionManager, ImageHelper, InventoryHelper...)
├── assets/             # css/, js/, img/
├── uploads/            # profiles/, products/
├── farmlink.sql        # schema
└── index.php           # entry point
```

---
## 🔐 Demo Accounts

| Role        | Email                     | Password    | Notes                          |
|-------------|---------------------------|-------------|--------------------------------|
| Super Admin | superadmin@farmlink.com   | password123 | Full control                   |
| Farmer      | farmer1@farmlink.app      | password123 | Product & delivery management  |
| Buyer       | buyer1@farmlink.app       | password123 | Shopping cart & messaging      |

> Only Super Admin, Farmer, Buyer roles exist (legacy “admin” removed).

---
## 🛠️ Troubleshooting

### Common Issues
- **DB connection:** verify env vars or `api/config.php`
- **Permissions:** ensure uploads directories are writable
- **Sessions:** clear cookies, check PHP session path
- **Map errors:** confirm internet access and Nominatim availability

### Useful Logs
- PHP / Apache error logs
- MySQL error log

---
## 🤝 Contributing

1. Follow the 3-role architecture
2. Add migrations/schema changes to `farmlink.sql`
3. Route APIs through `api/`
4. Reuse helpers under `includes/`
5. Document significant features or configuration changes

We welcome PRs that maintain FARMLINK’s clean, production-ready style.

---
## 📄 License

Released under the **MIT License**.

---
## 📊 Quick Facts

- ✅ Three-role architecture (super admin, farmer, buyer)
- ✅ Legacy `/FARMLINK/` paths replaced with `BASE_URL`
- ✅ Real-time messaging, advanced inventory, delivery mapping
- ✅ Mobile-first UI with agricultural theme
- ✅ Hardened security (sessions, headers, input validation)

---
**FARMLINK — Connecting Agriculture, Empowering Communities.** 🌾🇵🇭
