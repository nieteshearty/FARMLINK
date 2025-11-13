# 🌾 FARMLINK - Agricultural Marketplace Platform

A comprehensive **3-role web application** that connects farmers and buyers through a secure agricultural marketplace with super admin oversight. Built with PHP, MySQL, and featuring real-time communication, interactive maps, modern e-commerce features, and comprehensive inventory management.

## 🎯 System Overview

FARMLINK is a **streamlined agricultural marketplace** that facilitates direct connections between agricultural producers and buyers, with super admin oversight. The system features a clean 3-role hierarchy, real-time messaging, location-based delivery mapping, modern shopping cart experience, and advanced inventory tracking with seasonal product recommendations.

## 👥 User Roles & Capabilities

### 🔱 **Super Admin** (Highest Authority)
- **Complete System Control:** Access to all platform features and data
- **Advanced Analytics:** Comprehensive system performance metrics and business intelligence
- **System Monitoring:** Real-time user activity tracking and system health monitoring
- **Global Settings:** Platform-wide configuration and feature management
- **User Management:** Create, modify, and manage all user accounts across all roles
- **Security Oversight:** Activity logging, security monitoring, and access control

### 👨‍🌾 **Farmer** (Product Suppliers)
- **Product Management:** Full CRUD operations for agricultural products
- **Inventory Control:** Real-time stock tracking with low-stock alerts
- **Order Processing:** Receive, process, and fulfill buyer orders with delivery scheduling
- **Delivery Zone Management:** Configure multiple delivery areas with custom pricing and schedules
- **Delivery Scheduling:** Set specific delivery dates and time slots for orders
- **Sales Analytics:** Track revenue, popular products, and customer insights
- **Communication:** Direct messaging with buyers for order coordination
- **Location Services:** Delivery address mapping and route optimization with buyer location viewing
- **Profile Management:** Farm details, certifications, business information, delivery preferences, and precise location setting with interactive maps

### 🛒 **Buyer** (Product Consumers)
- **Modern Shopping Experience:** Shopee-inspired cart with select all, vouchers, and bulk actions
- **Product Discovery:** Advanced search, seasonal recommendations, and recently viewed items
- **Interactive Maps:** OpenStreetMap integration for precise delivery location selection
- **Delivery Information:** Real-time visibility of farmer delivery zones, schedules, and pricing in cart
- **Order Tracking:** Complete order lifecycle visibility with delivery scheduling and status updates
- **Smart Features:** Wishlist functionality, quick reorder, and voucher system
- **Location Services:** Multi-tier location search with Philippine location prioritization
- **Communication:** Direct messaging with farmers for product inquiries
- **Profile Management:** Company details, contact information (phone number), delivery preferences, and order history

## 🛠️ Technology Stack

### **Backend Technologies**
- **PHP 8.0+** - Server-side scripting and business logic
- **MySQL 8.0+** - Relational database with advanced features
- **PDO** - Secure database abstraction layer with prepared statements
- **Session Management** - Secure user authentication and role-based access control

### **Frontend Technologies**
- **HTML5** - Semantic markup and modern web standards
- **CSS3** - Responsive design with CSS Grid and Flexbox
- **Vanilla JavaScript** - Client-side interactivity and API communication
- **Font Awesome 6.0** - Comprehensive icon library
- **Leaflet.js** - Interactive maps for location services

### **External Services**
- **OpenStreetMap** - Free mapping service with global coverage (replaced Google Maps)
- **Nominatim API** - Geocoding and reverse geocoding services with Philippine focus
- **Leaflet.js** - Open-source mapping library with custom markers and popups
- **Chart.js** - Interactive charts for analytics and reporting

### **Development Tools**
- **XAMPP** - Local development environment
- **Apache** - Web server
- **phpMyAdmin** - Database administration interface

## 🚀 Installation & Setup

### **Prerequisites**
- **PHP 8.0+** with extensions: PDO, PDO_MySQL, GD, OpenSSL
- **MySQL 8.0+** or MariaDB 10.4+
- **Apache/Nginx** web server with mod_rewrite enabled
- **XAMPP/WAMP/MAMP** (recommended for local development)

### **Step 1: Project Setup**
1. Clone or download the project to your web server directory:
   ```bash
   # For XAMPP users
   C:\xampp\htdocs\FARMLINK\
   
   # For Linux/Mac
   /var/www/html/FARMLINK/
   ```

### **Step 2: Database Configuration**
1. **Create Database:**
   ```sql
   CREATE DATABASE farmlink CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

2. **Import Database Schema:**
   ```bash
   # Via phpMyAdmin: Import farmlink.sql
   # Or via command line:
   mysql -u root -p farmlink < farmlink.sql
   ```

3. **Configure Database Connection**
   - The app prefers environment variables (suitable for Wasmer/containers). If not present, it falls back to local defaults.
   - Supported env vars:
     - `DATABASE_URL` (e.g., `mysql://user:pass@host:port/dbname`)
     - `MYSQLHOST`, `MYSQLPORT`, `MYSQLDATABASE`, `MYSQLUSER`, `MYSQLPASSWORD`
   - For local dev without env vars, set credentials in Apache or a local `.env` equivalent, or ensure your local MySQL matches the defaults in `api/config.php`.

### **Step 3: File Permissions**
Set appropriate permissions for upload directories:
```bash
chmod 755 uploads/
chmod 755 uploads/profiles/
chmod 755 uploads/products/
```

### **Step 4: Access the Application**
Navigate to your base URL. Examples:
- Local XAMPP: `http://localhost/FARMLINK/`
- Wasmer: the provided public URL, e.g., `https://<your-app>.wasmer.app/`

The system will automatically redirect to the login page and then to the appropriate dashboard based on user role.

### **Step 5 (Optional): Wasmer Deployment**
- Ensure the following environment variables are set in Wasmer:
  - `DATABASE_URL` or the `MYSQL*` variables listed above
  - Optional: `FORCE_HTTPS=true`
- The application uses a PHP constant `BASE_URL` to prefix all web asset and route links. On Wasmer, this resolves correctly to avoid 404s.
- Upload directories must exist at `/uploads/profiles` and `/uploads/products` in the deployed environment.

## 🔐 Demo Accounts

The system comes with pre-configured demo accounts for testing all role functionalities:

### **Super Admin Account**
- **Email:** `superadmin@farmlink.com`
- **Password:** `password123`
- **Access:** Complete system control, analytics, monitoring, settings

### **Farmer Account**
- **Email:** `farmer1@farmlink.app`
- **Password:** `password123`
- **Access:** Product management, order processing, inventory control, delivery mapping

### **Buyer Account**
- **Email:** `buyer1@farmlink.app`
- **Password:** `password123`
- **Access:** Modern shopping cart, product browsing, location selection, order tracking

> **Note:** Admin role has been removed from the system. Only Super Admin, Farmer, and Buyer roles are supported.

## 🏗️ System Architecture

### **Directory Structure**
```
FARMLINK/
├── 🔐 api/                    # Backend API endpoints
│   ├── config.php            # Database connection & CORS
│   ├── auth.php             # Authentication system
│   ├── users.php            # User management API
│   ├── products.php         # Product operations API
│   ├── orders.php           # Order processing API
│   ├── cart.php             # Shopping cart API
│   ├── messages/            # Real-time messaging
│   ├── notifications/       # User notifications
│   └── reviews/             # Rating & review system
├── 📁 pages/                 # Role-based page structure
│   ├── superadmin/          # Super admin controls & analytics
│   ├── farmer/              # Farmer management interface
│   ├── buyer/               # Modern buyer shopping interface
│   ├── auth/                # Authentication pages
│   └── common/              # Shared components
├── 🛠️ includes/              # Helper classes & utilities
│   ├── session.php          # Session management
│   ├── DatabaseHelper.php   # Database operations
│   ├── ImageHelper.php      # File upload handling
│   ├── InventoryHelper.php  # Stock management
│   └── CropManager.php      # Agricultural data
├── 🎨 assets/               # Static resources
│   ├── css/                 # Stylesheets
│   ├── js/                  # JavaScript files
│   └── img/                 # Images & icons
├── 📤 uploads/              # User-generated content
│   ├── profiles/            # Profile pictures
│   └── products/            # Product images
├── 📊 farmlink.sql          # Complete database schema
├── 🎨 style.css             # Main stylesheet
└── 🏠 index.php             # Application entry point
```

### **Authentication Flow**
1. **Entry Point:** `index.php` checks login status
2. **Session Management:** `SessionManager` class handles role-based authentication
3. **Role Routing:** Automatic redirection to appropriate dashboard
4. **Access Control:** `requireRole()` method protects sensitive pages

### **Database Schema**
The system uses a comprehensive MySQL database with 15+ interconnected tables:

#### **Core Tables:**
- **`users`** - User accounts with role hierarchy and profile data
- **`products`** - Agricultural products with inventory and pricing
- **`orders`** - Purchase orders with delivery tracking
- **`order_items`** - Individual items within orders
- **`cart`** - Shopping cart functionality

#### **Enhanced Features:**
- **`messages`** - Real-time farmer-buyer communication
- **`conversations`** - Chat thread management
- **`reviews`** - Product and farmer rating system
- **`notifications`** - User alert system
- **`inventory_logs`** - Stock movement tracking
- **`payment_transactions`** - Payment processing records
- **`user_addresses`** - Multiple delivery addresses
- **`activity_log`** - System activity monitoring

## ✨ Key Features

### **🛒 Modern E-commerce Experience**
- **Shopee-inspired Cart:** Select all functionality, bulk actions, and modern card layout
- **Voucher System:** FRESH10 (10% off), FARM20 (₱20 off), NEWBUYER (15% off)
- **Smart Shipping:** Free shipping over ₱500 with real-time calculation
- **Wishlist & Quick Reorder:** Save favorites and reorder from previous purchases
- **Seasonal Products:** Climate-based recommendations for Philippines agriculture

### **🗺️ Advanced Location Services**
- **Interactive Maps:** Leaflet.js integration with OpenStreetMap (Google Maps replacement)
- **Delivery Mapping:** Farmers view buyer locations with custom markers and popups
- **Multi-tier Search:** Predefined locations + Nominatim API with Philippine prioritization
- **Address Selection:** Click-to-select delivery locations with coordinate saving
- **Location Intelligence:** Automatic address lookup and coordinate conversion
- **Real-time Geocoding:** Live location detection with accurate municipality recognition
- **Philippine Focus:** Specialized handling for Philippine locations (Naval, Cabucgayan, Culaba, Biliran)
- **Centralized Location System:** Unified location recognition across farmer profiles and buyer dashboards
- **Visual Confirmation:** Map-based location setting with instant address display

### **🚚 Comprehensive Delivery System**
- **Delivery Zone Management:** Farmers configure multiple delivery areas with coverage maps
- **Flexible Scheduling:** Day-of-week and time slot configuration per delivery zone
- **Dynamic Pricing:** Zone-based delivery fees with minimum order requirements
- **Smart Cart Integration:** Real-time delivery information display for buyers
- **Schedule Visibility:** Buyers see farmer's upcoming delivery dates and time slots
- **Order Delivery Scheduling:** Farmers set specific delivery dates for individual orders
- **Delivery Status Tracking:** Complete order lifecycle with delivery status updates

### **💬 Real-time Communication**
- **Direct Messaging:** Farmers and buyers can communicate directly
- **Order Notifications:** Real-time updates on order status changes
- **System Alerts:** Important notifications for users across all roles
- **Activity Logging:** Comprehensive tracking of user actions

### **📦 Advanced Inventory Management**
- **Real-time Stock Tracking:** Automatic inventory updates with each order
- **Low Stock Alerts:** Notifications when products reach minimum thresholds
- **Inventory Logs:** Complete history of stock movements and adjustments
- **Stock Reservations:** Temporary holds during checkout process

### **🔐 Security Features**
- **Password Security:** bcrypt hashing with salt for secure password storage
- **SQL Injection Prevention:** Prepared statements for all database operations
- **Role-based Access Control:** Hierarchical permission system
- **Session Security:** Secure session management with timeout handling
- **Input Validation:** Both client-side and server-side validation

### **📱 Responsive Design & UX**
- **Mobile-first Approach:** Optimized for iPhone 12 and all mobile devices
- **Hamburger Menu:** Collapsible navigation with smooth animations
- **Touch-friendly Interface:** Large buttons and intuitive navigation
- **Modern UI:** Agricultural-themed design with smooth transitions
- **Cross-browser Compatibility:** Works on all modern browsers

## 🚀 Recent Improvements & Updates

### **🗑️ System Cleanup (2024)**
- **Admin Role Removal:** Streamlined to 3-role system (Super Admin, Farmer, Buyer)
- **Development File Cleanup:** Removed debug, analysis, and testing files (CSS analysis tools, test scripts, init utilities)
- **IDE Configuration Cleanup:** Removed .idea folder and development-specific configurations
- **Security Enhancement:** Eliminated debug files and development endpoints
- **Performance Optimization:** Cleaner codebase with reduced file system overhead and production-ready structure

### **🛒 E-commerce Enhancements**
- **Modern Shopping Cart:** Shopee-inspired design with select all and bulk actions
- **Voucher System:** Integrated discount codes with real-time validation
- **Wishlist Feature:** Save and manage favorite products with localStorage
- **Quick Reorder:** One-click reordering from previous purchases
- **Seasonal Recommendations:** Climate-based product suggestions for Philippines

### **🗺️ Location System Overhaul & Fixes (October 2024)**
- **Google Maps Replacement:** Migrated to free OpenStreetMap with Leaflet.js
- **Enhanced Search:** Multi-tier location search with Philippine prioritization
- **Delivery Mapping:** Interactive maps for farmers to view buyer locations
- **Improved UX:** Better error handling and user feedback for location services
- **Real-time Geocoding:** Live location detection using Nominatim API for accurate address lookup
- **Farmer Location Management:** Precise coordinate-based location setting with visual map confirmation
- **Municipality Recognition:** Accurate detection of Philippine municipalities (Naval, Cabucgayan, Culaba, etc.)
- **Centralized Location System:** Unified location recognition across all map interactions
- **Debug Information Removal:** Clean UI without technical debug data display

### **🚚 Advanced Delivery Management System**
- **Delivery Zones:** Farmers can set up multiple delivery areas with specific coverage
- **Delivery Scheduling:** Time-based delivery slots with day-of-week configuration
- **Zone-based Pricing:** Different delivery fees and minimum orders per area
- **Delivery Instructions:** Special notes and requirements for each delivery zone
- **Buyer Visibility:** Complete delivery information displayed in shopping cart
- **Schedule Display:** Buyers see upcoming delivery dates and time slots
- **Order Scheduling:** Farmers can set specific delivery dates and times for orders
- **Delivery Tracking:** Enhanced order management with delivery status updates

### **👤 Profile & User Experience Enhancements (October 2024)**
- **Enhanced Profile Pictures:** Professional styling with white borders, shadows, and online status indicators
- **Buyer Phone Number Integration:** Added phone number field to buyer basic information with database integration
- **Navigation Profile Display:** Improved profile picture rendering in navigation with cache-busting
- **Profile Picture Path Handling:** Robust path resolution for different image storage formats
- **User Communication:** Enhanced contact information for better farmer-buyer coordination

### **📱 Mobile & UX Improvements**
- **iPhone 12 Optimization:** Specific fixes for mobile navigation and layout
- **Hamburger Menu:** Smooth sidebar toggle functionality
- **Responsive Design:** Enhanced mobile experience across all pages
- **Profile Picture Handling:** Robust path normalization for consistent image display
- **Interactive Map Experience:** Touch-friendly map controls with precise location selection

### **🔐 Security & Performance**
- **Path Security:** Fixed all file inclusion vulnerabilities
- **Database Optimization:** Improved queries and removed unused references
- **Session Management:** Enhanced authentication with proper role hierarchy
- **Error Handling:** Comprehensive error logging and user feedback
- **Cache Management:** Implemented cache-busting for CSS and JavaScript files
- **Input Validation:** Enhanced phone number and location data validation

### **☁️ Wasmer Migration (2025)**
- Replaced hardcoded `/FARMLINK/` paths with dynamic `BASE_URL` across PHP, HTML, and JavaScript.
- Normalized upload storage to relative paths (e.g., `uploads/products/...`, `uploads/profiles/...`) and render via `BASE_URL`.
- Updated asset links (CSS/JS/images) and internal navigation to be BASE_URL-aware.
- Fixed SQL queries to match Wasmer DB schema where columns differed.

#### BASE_URL Conventions
- Always build web URLs like:
  - `<?= BASE_URL ?>/assets/css/...`
  - `<?= BASE_URL ?>/assets/js/...`
  - `<?= BASE_URL ?>/uploads/products/filename.jpg`
- JavaScript fetch calls should prefix `BASE_URL` in embedded PHP or use a server-provided base.

#### Troubleshooting on Wasmer
- 404s for CSS/JS/Images usually mean a hardcoded `/FARMLINK/` remains. Replace with `<?= BASE_URL ?>`.
- Image paths saved in DB should be relative (e.g., `uploads/products/...`). Rendering will prepend `BASE_URL`.
- Ensure upload directories exist and are writable.

## 🔧 Development & Customization

### **Adding New Features**
1. **Database Changes:** Update schema in `farmlink.sql`
2. **API Endpoints:** Create new endpoints in `api/` directory
3. **Frontend Pages:** Add pages in appropriate role directory under `pages/`
4. **Helper Classes:** Extend functionality in `includes/` directory
5. **Testing:** Thoroughly test with all user roles

### **Code Organization**
- **MVC Pattern:** Separation of concerns with clear data flow
- **Role-based Structure:** Pages organized by user roles for easy maintenance
- **Reusable Components:** Helper classes for common functionality
- **Consistent Styling:** Centralized CSS with role-specific overrides

### **API Development**
- **RESTful Design:** Consistent API patterns across all endpoints
- **Error Handling:** Standardized error responses with proper HTTP codes
- **Authentication:** Token-based authentication for API access
- **Documentation:** Self-documenting code with clear parameter definitions

## 🐛 Troubleshooting

### **Common Issues**

#### **Database Connection Problems**
```php
// Check api/config.php for correct credentials
define('DB_HOST', 'localhost');
define('DB_NAME', 'farmlink');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');
```

#### **Permission Issues**
```bash
# Set correct permissions for upload directories
chmod 755 uploads/
chmod 755 uploads/profiles/
chmod 755 uploads/products/
```

#### **Session Problems**
- Clear browser cookies and cache
- Check PHP session configuration
- Verify session directory permissions

#### **Map/Location Issues**
- Check internet connection for OpenStreetMap access
- Verify Nominatim API availability
- Test with different location searches

### **Error Logs**
- **PHP Errors:** Check Apache/PHP error logs
- **Database Errors:** Monitor MySQL error logs
### **Production Checklist**
- [x] Remove development/test files (✅ Completed via system cleanup)
- [x] Eliminate admin role security risks (✅ Admin functionality removed)
- [x] Implement HTTPS security measures (✅ Security framework added)
- [x] Add security headers and CSP (✅ Comprehensive security headers)
- [x] Secure session configuration (✅ HTTPS-ready session management)
- [ ] Enable HTTPS in production (`FORCE_HTTPS = true`)
- [ ] Update database credentials for production (use dedicated user, not root)
- [ ] Configure SSL certificate (Let's Encrypt recommended)
- [ ] Set proper file permissions (755 for directories, 644 for files)
- [ ] Set up regular database backups
- [ ] Configure error logging and monitoring
- [ ] Optimize database indexes
- [ ] Set up security monitoring and alerts

### **Security Features**
- **HTTPS Enforcement:** Automatic redirect to HTTPS in production
- **Secure Sessions:** HttpOnly, Secure cookies with configurable timeout
- **Security Headers:** XSS protection, CSRF prevention, content type validation
- **Content Security Policy:** Restricts resource loading for XSS prevention
- **Input Validation:** Prepared statements and server-side validation
- **File Upload Security:** Restricted file types and upload directory protection
- **Role-based Access:** Hierarchical permission system with proper authorization

## 📄 License

This project is open source and available under the **MIT License**.

## 🤝 Support & Contributing

### **Getting Help**
- Check this documentation for common issues
- Review the code comments for implementation details
- Test with the provided demo accounts
- Check the database schema for data relationships

### **Contributing**
- Follow the existing 3-role system structure (Super Admin, Farmer, Buyer)
- Test thoroughly with all user roles before submitting changes
- Document any new features or API endpoints
- Maintain the clean, production-ready codebase
- Use the modern e-commerce patterns established in the shopping cart

---

## 📊 System Statistics

- **🏗️ Architecture:** Clean 3-role system (Super Admin, Farmer, Buyer)
- **🗑️ Cleanup:** 30+ unnecessary files removed for production readiness
- **🛒 E-commerce:** Modern Shopee-inspired shopping experience
- **🚚 Delivery System:** Comprehensive zone-based delivery management with scheduling
- **🗺️ Maps:** Free OpenStreetMap integration with real-time geocoding (no API keys required)
- **📱 Mobile:** Optimized for iPhone 12 and all mobile devices with touch-friendly controls
- **🔐 Security:** Enhanced with admin role removal, file cleanup, and input validation
- **👤 Profiles:** Professional profile management with enhanced contact information
- **🌍 Location Services:** Accurate Philippine municipality recognition and mapping

---

**FARMLINK** - Connecting Agriculture, Empowering Communities 🌾

*A streamlined, secure, and modern agricultural marketplace built for the Philippines* 🇵🇭
#   F A R M L I N K  
 