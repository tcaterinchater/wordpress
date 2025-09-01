<?php
/**
 * Secure Plesk Configuration
 * 
 * This file should be placed outside the web root and included securely
 * Consider using environment variables or encrypted configuration
 */

// Plesk Configuration Class
class PleskConfig {
    private static $config = null;
    
    public static function init() {
        if (self::$config === null) {
            self::$config = [
                // Move these to environment variables in production
                'username' => defined('PLESK_USERNAME') ? PLESK_USERNAME : 'admin',
                'password' => defined('PLESK_PASSWORD') ? PLESK_PASSWORD : 'GKpJhzmqe09o%@9j',
                'endpoint' => defined('PLESK_ENDPOINT') ? PLESK_ENDPOINT : 'https://3.82.33.92:8443/enterprise/control/agent.php',
                'ip_address' => defined('PLESK_IP') ? PLESK_IP : '10.0.0.82',
                'plan_guid' => defined('PLESK_PLAN_GUID') ? PLESK_PLAN_GUID : '53187fa7-1f5e-211c-b41e-24bed5ed2088',
                'login_url_base' => defined('PLESK_LOGIN_BASE') ? PLESK_LOGIN_BASE : 'https://3.82.33.92:8443/login_up.php3',
                
                // Security settings
                'ssl_verify' => defined('PLESK_SSL_VERIFY') ? PLESK_SSL_VERIFY : true,
                'timeout' => defined('PLESK_TIMEOUT') ? PLESK_TIMEOUT : 30,
                'max_retries' => defined('PLESK_MAX_RETRIES') ? PLESK_MAX_RETRIES : 3,
                'retry_delay' => defined('PLESK_RETRY_DELAY') ? PLESK_RETRY_DELAY : 1,
                
                // Rate limiting
                'rate_limit_per_minute' => defined('PLESK_RATE_LIMIT') ? PLESK_RATE_LIMIT : 60,
            ];
            
            // Validate configuration
            self::validateConfig();
        }
        
        return self::$config;
    }
    
    public static function get($key) {
        $config = self::init();
        return isset($config[$key]) ? $config[$key] : null;
    }
    
    private static function validateConfig() {
        $required = ['username', 'password', 'endpoint', 'ip_address'];
        
        foreach ($required as $key) {
            if (empty(self::$config[$key])) {
                throw new Exception("Plesk configuration missing: {$key}");
            }
        }
        
        // Validate endpoint URL
        if (!filter_var(self::$config['endpoint'], FILTER_VALIDATE_URL)) {
            throw new Exception("Invalid Plesk endpoint URL");
        }
        
        // Validate IP address
        if (!filter_var(self::$config['ip_address'], FILTER_VALIDATE_IP)) {
            throw new Exception("Invalid Plesk IP address");
        }
    }
}

// Security utilities
class PleskSecurity {
    private static $api_calls = [];
    
    /**
     * Generate cryptographically secure password
     */
    public static function generateSecurePassword($length = 16) {
        if ($length < 8) {
            $length = 8;
        }
        
        // Character sets for strong passwords
        $lowercase = 'abcdefghijklmnopqrstuvwxyz';
        $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $numbers = '0123456789';
        $special = '!@#$%^&*()_+-=[]{}|;:,.<>?';
        
        // Ensure at least one character from each set
        $password = '';
        $password .= $lowercase[random_int(0, strlen($lowercase) - 1)];
        $password .= $uppercase[random_int(0, strlen($uppercase) - 1)];
        $password .= $numbers[random_int(0, strlen($numbers) - 1)];
        $password .= $special[random_int(0, strlen($special) - 1)];
        
        // Fill remaining length
        $all_chars = $lowercase . $uppercase . $numbers . $special;
        for ($i = 4; $i < $length; $i++) {
            $password .= $all_chars[random_int(0, strlen($all_chars) - 1)];
        }
        
        // Shuffle the password
        return str_shuffle($password);
    }
    
    /**
     * Sanitize input for XML
     */
    public static function sanitizeForXml($input) {
        if (is_array($input)) {
            return array_map([self::class, 'sanitizeForXml'], $input);
        }
        
        // Remove or escape potentially dangerous characters
        $input = trim($input);
        $input = htmlspecialchars($input, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        
        // Additional validation for specific contexts
        return $input;
    }
    
    /**
     * Validate domain name
     */
    public static function validateDomain($domain) {
        // Remove protocol and www
        $domain = preg_replace('/^https?:\/\//i', '', $domain);
        $domain = preg_replace('/^www\./i', '', $domain);
        
        // Validate domain format
        if (!preg_match('/^[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?(\.[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?)*$/', $domain)) {
            return false;
        }
        
        return $domain;
    }
    
    /**
     * Rate limiting check
     */
    public static function checkRateLimit($identifier = 'global') {
        $current_time = time();
        $window = 60; // 1 minute window
        $limit = PleskConfig::get('rate_limit_per_minute');
        
        // Clean old entries
        if (isset(self::$api_calls[$identifier])) {
            self::$api_calls[$identifier] = array_filter(
                self::$api_calls[$identifier],
                function($timestamp) use ($current_time, $window) {
                    return ($current_time - $timestamp) < $window;
                }
            );
        } else {
            self::$api_calls[$identifier] = [];
        }
        
        // Check if limit exceeded
        if (count(self::$api_calls[$identifier]) >= $limit) {
            return false;
        }
        
        // Record this call
        self::$api_calls[$identifier][] = $current_time;
        return true;
    }
    
    /**
     * Secure logging without sensitive data
     */
    public static function secureLog($message, $level = 'info', $context = []) {
        // Remove sensitive data from context
        $safe_context = array_filter($context, function($key) {
            $sensitive_keys = ['password', 'passwd', 'token', 'key', 'secret'];
            return !in_array(strtolower($key), $sensitive_keys);
        }, ARRAY_FILTER_USE_KEY);
        
        // Log with timestamp and sanitized context
        $log_entry = sprintf(
            '[%s] [%s] %s %s',
            date('Y-m-d H:i:s'),
            strtoupper($level),
            $message,
            !empty($safe_context) ? json_encode($safe_context) : ''
        );
        
        error_log($log_entry);
    }
}