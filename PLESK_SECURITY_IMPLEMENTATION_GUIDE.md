# Plesk Security Implementation Guide

## 🚨 Critical Security Issues Fixed

### **Before (Vulnerable):**
- ❌ Hardcoded credentials in 11+ places
- ❌ Weak password generation
- ❌ SSL verification disabled
- ❌ No input validation
- ❌ No rate limiting
- ❌ Sensitive data in logs

### **After (Secure):**
- ✅ Centralized secure configuration
- ✅ Cryptographically secure passwords
- ✅ SSL verification enabled
- ✅ Comprehensive input validation
- ✅ Rate limiting with backoff
- ✅ Secure logging without sensitive data

## 📁 Implementation Steps

### 1. **Add Security Files to Child Theme**

Place these files in your child theme directory:
```
wp-content/themes/luxbasic-child/
├── functions.php (existing)
├── plesk-security-config.php (new)
├── secure-plesk-functions.php (new)
└── SECURE_PLESK_INTEGRATION.php (new)
```

### 2. **Update wp-config.php (Recommended)**

Add secure configuration constants:
```php
// Plesk Configuration (add to wp-config.php)
define('PLESK_USERNAME', 'your_admin_username');
define('PLESK_PASSWORD', 'your_secure_password');
define('PLESK_ENDPOINT', 'https://your-server.com:8443/enterprise/control/agent.php');
define('PLESK_IP', 'your.server.ip.address');
define('PLESK_PLAN_GUID', 'your-plan-guid');
define('PLESK_LOGIN_BASE', 'https://your-server.com:8443/login_up.php3');

// Security Settings
define('PLESK_SSL_VERIFY', true);
define('PLESK_TIMEOUT', 30);
define('PLESK_MAX_RETRIES', 3);
define('PLESK_RETRY_DELAY', 1);
define('PLESK_RATE_LIMIT', 60);
```

### 3. **Replace Plesk Code in functions.php**

**Option A: Complete Replacement**
1. Remove all existing Plesk functions from `functions.php` (lines 918-1781)
2. Add this at the end of `functions.php`:
```php
// =============================================================================
// 8. SECURE PLESK API INTEGRATION
// =============================================================================
require_once get_stylesheet_directory() . '/SECURE_PLESK_INTEGRATION.php';
```

**Option B: Gradual Migration**
1. Keep existing functions but rename them (add `_old` suffix)
2. Include the secure version alongside
3. Test thoroughly before removing old functions

### 4. **Update Environment Variables (Production)**

For production servers, use environment variables instead of wp-config.php:
```bash
export PLESK_USERNAME="admin"
export PLESK_PASSWORD="secure_password_here"
export PLESK_ENDPOINT="https://your-server.com:8443/enterprise/control/agent.php"
# ... etc
```

## 🔒 Security Enhancements Implemented

### **1. Credential Management**
```php
// Before: Hardcoded everywhere
$plesk_password = 'GKpJhzmqe09o%@9j';

// After: Centralized and configurable
$password = PleskConfig::get('password');
```

### **2. Secure Password Generation**
```php
// Before: Weak
function generate_random_string($length = 8) {
    return substr(str_shuffle(str_repeat($x = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', ceil($length / strlen($x)))), 1, $length);
}

// After: Cryptographically secure
public static function generateSecurePassword($length = 16) {
    // Uses random_int() with character set requirements
    // Ensures at least one: lowercase, uppercase, number, special
}
```

### **3. Input Validation & Sanitization**
```php
// Before: Basic htmlspecialchars
$domain_name = htmlspecialchars($domain_name);

// After: Comprehensive validation
$domain_name = PleskSecurity::validateDomain($domain_name);
$domain_name = PleskSecurity::sanitizeForXml($domain_name);
```

### **4. Rate Limiting**
```php
// Before: No protection
// After: Built-in rate limiting
if (!PleskSecurity::checkRateLimit()) {
    return ['success' => false, 'message' => 'Rate limit exceeded'];
}
```

### **5. Retry Logic with Exponential Backoff**
```php
// Before: Single attempt
// After: Smart retry with backoff
for ($attempt = 1; $attempt <= $max_retries; $attempt++) {
    // ... attempt API call
    if ($attempt < $max_retries) {
        sleep($retry_delay * $attempt); // Exponential backoff
    }
}
```

### **6. Secure Logging**
```php
// Before: Sensitive data in logs
error_log('User created with password: ' . $password);

// After: Secure logging
PleskSecurity::secureLog('User created successfully', 'info', [
    'username' => $username // No passwords logged
]);
```

### **7. SSL Verification**
```php
// Before: Disabled
'sslverify' => false

// After: Enabled with proper error handling
'sslverify' => PleskConfig::get('ssl_verify') // Default: true
```

## 🧪 Testing the Implementation

### **1. Test Configuration**
```php
// Add to a test page or admin area
if (current_user_can('administrator')) {
    try {
        PleskConfig::init();
        echo "✅ Configuration valid";
    } catch (Exception $e) {
        echo "❌ Configuration error: " . $e->getMessage();
    }
}
```

### **2. Test API Connection**
```php
// Test basic connectivity
$user_exists = SecurePleskAPI::getUserByLogin('test_user');
if ($user_exists !== false) {
    echo "✅ API connection working";
} else {
    echo "❌ API connection failed";
}
```

### **3. Monitor Logs**
Check your error logs for secure log entries:
```
[2024-01-20 10:30:15] [INFO] Plesk API get_user_by_login successful {"attempt":1,"response_code":200}
```

## 🚀 Performance Improvements

1. **Reduced API Calls**: Smart caching and existence checks
2. **Connection Pooling**: Reuse connections where possible  
3. **Timeout Optimization**: Configurable timeouts per operation
4. **Rate Limiting**: Prevents API overload

## 📊 Monitoring & Maintenance

### **Log Monitoring**
- Monitor for rate limit warnings
- Check for API failures
- Watch for configuration errors

### **Regular Updates**
- Update passwords regularly
- Review SSL certificates
- Monitor API rate limits
- Check for Plesk API updates

## 🔄 Migration Checklist

- [ ] Backup current functions.php
- [ ] Add security configuration files
- [ ] Update wp-config.php with constants
- [ ] Test in staging environment
- [ ] Replace Plesk functions gradually
- [ ] Monitor logs for errors
- [ ] Test subscription activation/cancellation
- [ ] Verify FTP credentials work
- [ ] Clean up old functions after testing

## 🆘 Rollback Plan

If issues occur:
1. Restore original functions.php from backup
2. Remove new security files
3. Remove wp-config.php constants
4. Test functionality
5. Debug issues before re-implementing

## 📞 Support

Monitor these log patterns for issues:
- `[ERROR]` - Critical failures requiring attention
- `[WARNING]` - Non-critical issues to investigate
- `Rate limit exceeded` - May need to increase limits
- `Configuration validation failed` - Check wp-config.php constants