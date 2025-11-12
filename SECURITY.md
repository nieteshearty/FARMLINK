# 🔐 FARMLINK Security Guide

This document outlines the security measures implemented in FARMLINK and provides guidance for secure deployment.

## 🛡️ Security Features Implemented

### **1. HTTPS Enforcement**
- Automatic HTTPS redirection in production
- Secure cookie configuration
- HSTS (HTTP Strict Transport Security) headers
- SSL/TLS encryption for all communications

### **2. Session Security**
- Secure session configuration with HttpOnly cookies
- Session timeout management (configurable)
- Session ID regeneration for security
- Secure cookie settings for HTTPS

### **3. Security Headers**
- **X-Content-Type-Options:** Prevents MIME type sniffing
- **X-Frame-Options:** Prevents clickjacking attacks
- **X-XSS-Protection:** Enables browser XSS filtering
- **Content Security Policy (CSP):** Restricts resource loading
- **Referrer-Policy:** Controls referrer information
- **HSTS:** Forces HTTPS connections

### **4. Input Validation & Sanitization**
- Prepared statements for all database queries
- Input validation on both client and server side
- File upload restrictions and validation
- SQL injection prevention

### **5. Access Control**
- Role-based access control (Super Admin, Farmer, Buyer)
- Protected directories and sensitive files
- Authentication required for all protected pages
- Proper authorization checks

## 🚀 Production Deployment Security

### **Step 1: Enable HTTPS**

#### **Option A: Using Let's Encrypt (Free SSL)**
```bash
# Install Certbot
sudo apt install certbot python3-certbot-apache

# Generate SSL certificate
sudo certbot --apache -d yourdomain.com -d www.yourdomain.com

# Auto-renewal
sudo crontab -e
# Add: 0 12 * * * /usr/bin/certbot renew --quiet
```

#### **Option B: Using Cloudflare (Free)**
1. Sign up for Cloudflare
2. Add your domain
3. Update nameservers
4. Enable "Always Use HTTPS" in SSL/TLS settings
5. Set SSL mode to "Full (strict)"

#### **Option C: Commercial SSL Certificate**
1. Purchase SSL certificate from provider
2. Generate CSR (Certificate Signing Request)
3. Install certificate on server
4. Configure Apache/Nginx for HTTPS

### **Step 2: Update Configuration**

**Edit `api/config.php`:**
```php
// Enable HTTPS enforcement
define('FORCE_HTTPS', true);
define('SECURE_COOKIES', true);
```

**Edit `.htaccess` - Uncomment HTTPS redirect:**
```apache
# Uncomment these lines:
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [R=301,L]

# Uncomment HSTS header:
Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains; preload"
```

### **Step 3: Database Security**

**Create dedicated database user:**
```sql
-- Don't use root in production
CREATE USER 'farmlink_user'@'localhost' IDENTIFIED BY 'strong_password_here';
GRANT SELECT, INSERT, UPDATE, DELETE ON farmlink.* TO 'farmlink_user'@'localhost';
FLUSH PRIVILEGES;
```

**Update database configuration:**
```php
define('DB_USER', 'farmlink_user');  // Not root
define('DB_PASS', 'your_strong_password');
```

### **Step 4: File Permissions**
```bash
# Set proper permissions
chmod 755 /var/www/html/FARMLINK/
chmod 644 /var/www/html/FARMLINK/*.php
chmod 755 /var/www/html/FARMLINK/uploads/
chmod 644 /var/www/html/FARMLINK/uploads/*
chmod 600 /var/www/html/FARMLINK/api/config.php
```

### **Step 5: Server Configuration**

#### **Apache Security**
```apache
# In httpd.conf or virtual host
ServerTokens Prod
ServerSignature Off
TraceEnable Off

# Hide PHP version
expose_php = Off
```

#### **PHP Security Settings**
```ini
# In php.ini
display_errors = Off
log_errors = On
error_log = /var/log/php_errors.log
session.cookie_httponly = 1
session.cookie_secure = 1
session.use_only_cookies = 1
allow_url_fopen = Off
allow_url_include = Off
```

## 🔒 Security Checklist

### **Pre-Deployment**
- [ ] Enable HTTPS enforcement (`FORCE_HTTPS = true`)
- [ ] Enable secure cookies (`SECURE_COOKIES = true`)
- [ ] Update database credentials (not root)
- [ ] Set strong session timeout
- [ ] Configure error logging
- [ ] Test all security headers
- [ ] Validate file upload restrictions
- [ ] Test role-based access control

### **Post-Deployment**
- [ ] Verify HTTPS is working
- [ ] Test SSL certificate validity
- [ ] Check security headers with online tools
- [ ] Verify database connection security
- [ ] Test file upload security
- [ ] Monitor error logs
- [ ] Set up automated backups
- [ ] Configure monitoring alerts

## 🛠️ Security Testing Tools

### **Online Security Scanners**
- **SSL Labs:** https://www.ssllabs.com/ssltest/
- **Security Headers:** https://securityheaders.com/
- **Mozilla Observatory:** https://observatory.mozilla.org/

### **Manual Testing**
```bash
# Test HTTPS redirect
curl -I http://yourdomain.com

# Check security headers
curl -I https://yourdomain.com

# Test file upload restrictions
# Try uploading .php files to uploads directory
```

## 🚨 Security Monitoring

### **Log Monitoring**
Monitor these logs for security issues:
- Apache/Nginx access logs
- Apache/Nginx error logs
- PHP error logs
- Database logs
- Application activity logs

### **Key Security Indicators**
- Unusual login attempts
- File upload attempts with suspicious extensions
- SQL injection attempts
- XSS attempts
- Excessive API requests (potential DDoS)

## 📞 Security Incident Response

### **If Security Breach Detected:**
1. **Immediate Actions:**
   - Take site offline if necessary
   - Change all passwords
   - Revoke active sessions
   - Check for malicious files

2. **Investigation:**
   - Review access logs
   - Check database for unauthorized changes
   - Scan for malware
   - Identify attack vector

3. **Recovery:**
   - Restore from clean backup if needed
   - Patch security vulnerabilities
   - Update security measures
   - Monitor for continued attacks

## 🔄 Regular Security Maintenance

### **Weekly**
- Review access logs
- Check for failed login attempts
- Monitor file uploads
- Verify SSL certificate status

### **Monthly**
- Update PHP and server software
- Review user accounts and permissions
- Check for security updates
- Test backup restoration

### **Quarterly**
- Security audit and penetration testing
- Review and update security policies
- Update SSL certificates if needed
- Security awareness training

---

## 📚 Additional Resources

- [OWASP Top 10](https://owasp.org/www-project-top-ten/)
- [PHP Security Guidelines](https://www.php.net/manual/en/security.php)
- [Apache Security Tips](https://httpd.apache.org/docs/2.4/misc/security_tips.html)
- [MySQL Security Guidelines](https://dev.mysql.com/doc/refman/8.0/en/security-guidelines.html)

---

**Remember:** Security is an ongoing process, not a one-time setup. Regular monitoring and updates are essential for maintaining a secure application.

# Force HTTPS (uncomment for production)
# RewriteCond %{HTTPS} off
# RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [R=301,L]

# Security Headers
<IfModule mod_headers.c>
    # Prevent MIME type sniffing
    Header always set X-Content-Type-Options nosniff
    
    # Prevent clickjacking
    Header always set X-Frame-Options DENY
    
    # XSS Protection
    Header always set X-XSS-Protection "1; mode=block"
    
    # Referrer Policy
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
    
    # HSTS (only when using HTTPS)
    # Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains; preload"
    
    # Remove server information
    Header unset Server
    Header unset X-Powered-By
</IfModule>

# Hide sensitive files
<FilesMatch "\.(sql|log|bak|config|env)$">
    Require all denied
</FilesMatch>

# Hide .htaccess itself
<Files ".htaccess">
    Require all denied
</Files>

# Hide git files
<FilesMatch "^\.git">
    Require all denied
</FilesMatch>

# Disable directory browsing
Options -Indexes

# Prevent access to includes directory
<Directory "includes/">
    Require all denied
</Directory>

# Prevent access to config directory
<Directory "config/">
    Require all denied
</Directory>

# File upload security
<Directory "uploads/">
    # Prevent PHP execution in uploads
    php_flag engine off
    AddType text/plain .php .php3 .phtml .pht
    
    # Only allow specific file types
    <FilesMatch "\.(jpg|jpeg|png|gif|webp|pdf|doc|docx)$">
        Require all granted
    </FilesMatch>
    
    <FilesMatch "^(?!.*\.(jpg|jpeg|png|gif|webp|pdf|doc|docx)$).*$">
        Require all denied
    </FilesMatch>
</Directory>

# Cache control for static assets
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType image/jpg "access plus 1 month"
    ExpiresByType image/jpeg "access plus 1 month"
    ExpiresByType image/gif "access plus 1 month"
    ExpiresByType image/png "access plus 1 month"
    ExpiresByType text/css "access plus 1 month"
    ExpiresByType application/pdf "access plus 1 month"
    ExpiresByType text/javascript "access plus 1 month"
    ExpiresByType application/javascript "access plus 1 month"
</IfModule>

# Compress files for better performance
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/plain
    AddOutputFilterByType DEFLATE text/html
    AddOutputFilterByType DEFLATE text/xml
    AddOutputFilterByType DEFLATE text/css
    AddOutputFilterByType DEFLATE application/xml
    AddOutputFilterByType DEFLATE application/xhtml+xml
    AddOutputFilterByType DEFLATE application/rss+xml
    AddOutputFilterByType DEFLATE application/javascript
    AddOutputFilterByType DEFLATE application/x-javascript
</IfModule>

# Rate limiting (if mod_evasive is available)
<IfModule mod_evasive24.c>
    DOSHashTableSize    2048
    DOSPageCount        10
    DOSSiteCount        50
    DOSPageInterval     1
    DOSSiteInterval     1
    DOSBlockingPeriod   600
</IfModule>
