# 🔐 HTTPS Setup Guide for FARMLINK

## 🏠 Local Development (XAMPP)

### **Step 1: Enable SSL in XAMPP**

1. **Open XAMPP Control Panel**
2. **Click "Config" next to Apache**
3. **Select "httpd.conf"**
4. **Uncomment these lines:**
   ```apache
   LoadModule rewrite_module modules/mod_rewrite.so
   LoadModule ssl_module modules/mod_ssl.so
   Include conf/extra/httpd-ssl.conf
   ```

5. **Save and restart Apache**

### **Step 2: Create Self-Signed Certificate**

**Open Command Prompt as Administrator and run:**
```bash
cd C:\xampp\apache\bin

# Generate private key
openssl genrsa -out farmlink.key 2048

# Generate certificate signing request
openssl req -new -key farmlink.key -out farmlink.csr

# When prompted, enter:
# Country Name: PH
# State: Your State
# City: Your City  
# Organization: FARMLINK
# Organizational Unit: IT Department
# Common Name: localhost (IMPORTANT!)
# Email: your-email@domain.com
# Challenge password: (leave empty)
# Optional company name: (leave empty)

# Generate self-signed certificate
openssl x509 -req -days 365 -in farmlink.csr -signkey farmlink.key -out farmlink.crt

# Move certificates to SSL directory
move farmlink.key C:\xampp\apache\conf\ssl.key\
move farmlink.crt C:\xampp\apache\conf\ssl.crt\
```

### **Step 3: Configure SSL Virtual Host**

**Edit `C:\xampp\apache\conf\extra\httpd-ssl.conf`:**

Add this virtual host configuration:
```apache
<VirtualHost localhost:443>
    DocumentRoot "C:/xampp/htdocs/FARMLINK"
    ServerName localhost
    ServerAlias localhost
    
    SSLEngine on
    SSLCertificateFile "conf/ssl.crt/farmlink.crt"
    SSLCertificateKeyFile "conf/ssl.key/farmlink.key"
    
    <Directory "C:/xampp/htdocs/FARMLINK">
        AllowOverride All
        Require all granted
    </Directory>
    
    # Security headers
    Header always set Strict-Transport-Security "max-age=63072000; includeSubDomains; preload"
    Header always set X-Content-Type-Options nosniff
    Header always set X-Frame-Options DENY
    Header always set X-XSS-Protection "1; mode=block"
</VirtualHost>
```

### **Step 4: Update FARMLINK Configuration**

**For local HTTPS testing, edit `api/config.php`:**
```php
// Enable for local HTTPS testing
define('FORCE_HTTPS', false);  // Keep false for local testing
define('SECURE_COOKIES', true); // Enable for HTTPS
```

### **Step 5: Test HTTPS**

1. **Restart Apache in XAMPP**
2. **Visit:** `https://localhost/FARMLINK/`
3. **Accept the security warning** (self-signed certificate)
4. **Verify HTTPS is working**

---

## 🌐 Production Deployment

### **Option 1: Let's Encrypt (Free SSL) - Recommended**

**For Ubuntu/Debian servers:**
```bash
# Install Certbot
sudo apt update
sudo apt install certbot python3-certbot-apache

# Get SSL certificate
sudo certbot --apache -d yourdomain.com -d www.yourdomain.com

# Test auto-renewal
sudo certbot renew --dry-run

# Set up auto-renewal
echo "0 12 * * * /usr/bin/certbot renew --quiet" | sudo crontab -
```

**For CentOS/RHEL:**
```bash
# Install EPEL and Certbot
sudo yum install epel-release
sudo yum install certbot python3-certbot-apache

# Get SSL certificate
sudo certbot --apache -d yourdomain.com -d www.yourdomain.com
```

### **Option 2: Cloudflare SSL (Free)**

1. **Sign up for Cloudflare**
2. **Add your domain to Cloudflare**
3. **Update your domain's nameservers**
4. **In Cloudflare dashboard:**
   - Go to SSL/TLS → Overview
   - Set SSL mode to "Full (strict)"
   - Enable "Always Use HTTPS"
   - Enable "HSTS"

### **Option 3: Commercial SSL Certificate**

1. **Purchase SSL from provider** (GoDaddy, Namecheap, etc.)
2. **Generate CSR on your server:**
   ```bash
   openssl req -new -newkey rsa:2048 -nodes -keyout yourdomain.key -out yourdomain.csr
   ```
3. **Submit CSR to SSL provider**
4. **Download and install certificate**
5. **Configure Apache/Nginx**

---

## ⚙️ Production Configuration

### **Step 1: Enable HTTPS in FARMLINK**

**Edit `api/config.php`:**
```php
// Production settings
define('FORCE_HTTPS', true);
define('SECURE_COOKIES', true);
define('SESSION_TIMEOUT', 1800); // 30 minutes
```

### **Step 2: Enable HTTPS Redirect**

**Edit `.htaccess` - Uncomment these lines:**
```apache
# Force HTTPS
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [R=301,L]

# Enable HSTS
Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains; preload"
```

### **Step 3: Update Database Configuration**

**Create production database user:**
```sql
CREATE USER 'farmlink_prod'@'localhost' IDENTIFIED BY 'strong_random_password';
GRANT SELECT, INSERT, UPDATE, DELETE ON farmlink.* TO 'farmlink_prod'@'localhost';
FLUSH PRIVILEGES;
```

**Update config:**
```php
define('DB_USER', 'farmlink_prod');
define('DB_PASS', 'your_strong_password');
```

---

## 🧪 Testing Your HTTPS Setup

### **1. SSL Certificate Test**
Visit: https://www.ssllabs.com/ssltest/
Enter your domain and check for A+ rating

### **2. Security Headers Test**
Visit: https://securityheaders.com/
Enter your domain and check security score

### **3. Manual Testing**
```bash
# Test HTTPS redirect
curl -I http://yourdomain.com

# Should return 301 redirect to https://

# Test HTTPS response
curl -I https://yourdomain.com

# Should return 200 OK with security headers
```

### **4. Browser Testing**
- Check for green padlock icon
- Verify certificate details
- Test all major pages
- Verify login/logout functionality
- Test file uploads
- Check maps functionality

---

## 🔧 Troubleshooting

### **Common Issues:**

**1. "SSL_ERROR_SELF_SIGNED_CERT" in browser**
- Normal for self-signed certificates
- Click "Advanced" → "Accept Risk and Continue"

**2. "Mixed Content" warnings**
- Ensure all resources use HTTPS URLs
- Check for http:// links in HTML/CSS/JS

**3. Session issues after enabling HTTPS**
- Clear browser cookies
- Verify SECURE_COOKIES setting

**4. Maps not working with HTTPS**
- OpenStreetMap works with HTTPS by default
- No API key changes needed

### **Performance Optimization:**
```apache
# Enable HTTP/2 (if supported)
LoadModule http2_module modules/mod_http2.so
Protocols h2 http/1.1

# Enable compression
LoadModule deflate_module modules/mod_deflate.so
```

---

## 📋 Security Checklist

### **Before Going Live:**
- [ ] SSL certificate installed and valid
- [ ] HTTPS redirect working
- [ ] Security headers configured
- [ ] Database user not using root
- [ ] File permissions set correctly
- [ ] Error reporting disabled
- [ ] Debug mode disabled
- [ ] Backup system configured
- [ ] Monitoring set up

### **After Going Live:**
- [ ] Test all functionality over HTTPS
- [ ] Verify SSL certificate auto-renewal
- [ ] Monitor security headers
- [ ] Check for mixed content warnings
- [ ] Test on multiple browsers/devices
- [ ] Monitor server logs
- [ ] Set up security monitoring

---

**🎯 Result:** Your FARMLINK application will be secure with HTTPS encryption, protecting user data and building trust with your agricultural marketplace users!
