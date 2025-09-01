<?php
/**
 * Security-Hardened Plesk API Integration
 * 
 * This replaces the existing Plesk functions with secure versions
 * Include this after including plesk-security-config.php
 */

require_once 'plesk-security-config.php';

class SecurePleskAPI {
    
    /**
     * Make secure API request with retry logic
     */
    private static function makeSecureApiRequest($xml, $operation = 'api_call') {
        // Rate limiting check
        if (!PleskSecurity::checkRateLimit()) {
            PleskSecurity::secureLog('Rate limit exceeded for Plesk API', 'warning');
            return [
                'success' => false,
                'message' => 'Rate limit exceeded. Please try again later.'
            ];
        }
        
        $config = PleskConfig::init();
        $max_retries = $config['max_retries'];
        $retry_delay = $config['retry_delay'];
        
        for ($attempt = 1; $attempt <= $max_retries; $attempt++) {
            $response = wp_remote_post($config['endpoint'], [
                'headers' => [
                    'Content-Type' => 'text/xml; charset=utf-8',
                    'HTTP_AUTH_LOGIN' => $config['username'],
                    'HTTP_AUTH_PASSWD' => $config['password'],
                    'User-Agent' => 'WordPress-Plesk-Integration/1.0'
                ],
                'body' => $xml,
                'timeout' => $config['timeout'],
                'sslverify' => $config['ssl_verify'],
                'httpversion' => '1.1'
            ]);
            
            if (!is_wp_error($response)) {
                $response_code = wp_remote_retrieve_response_code($response);
                
                if ($response_code === 200) {
                    PleskSecurity::secureLog("Plesk API {$operation} successful", 'info', [
                        'attempt' => $attempt,
                        'response_code' => $response_code
                    ]);
                    
                    return [
                        'success' => true,
                        'body' => wp_remote_retrieve_body($response),
                        'attempt' => $attempt
                    ];
                }
                
                PleskSecurity::secureLog("Plesk API {$operation} failed with HTTP {$response_code}", 'warning', [
                    'attempt' => $attempt,
                    'response_code' => $response_code
                ]);
            } else {
                PleskSecurity::secureLog("Plesk API {$operation} connection failed", 'error', [
                    'attempt' => $attempt,
                    'error' => $response->get_error_message()
                ]);
            }
            
            // Wait before retry (except on last attempt)
            if ($attempt < $max_retries) {
                sleep($retry_delay * $attempt); // Exponential backoff
            }
        }
        
        return [
            'success' => false,
            'message' => "API request failed after {$max_retries} attempts"
        ];
    }
    
    /**
     * Parse XML response securely
     */
    private static function parseXmlResponse($xml_string, $expected_path) {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xml_string);
        
        if ($xml === false) {
            $errors = libxml_get_errors();
            PleskSecurity::secureLog('XML parsing failed', 'error', [
                'errors' => array_map(function($e) { return $e->message; }, $errors)
            ]);
            return false;
        }
        
        return $xml;
    }
    
    /**
     * Check if Plesk user exists by login
     */
    public static function getUserByLogin($login) {
        $login = PleskSecurity::sanitizeForXml($login);
        
        $xml = '<?xml version="1.0" encoding="UTF-8"?>
        <packet version="1.6.3.0">
            <customer>
                <get>
                    <filter>
                        <login>' . $login . '</login>
                    </filter>
                    <dataset>
                        <gen_info/>
                    </dataset>
                </get>
            </customer>
        </packet>';
        
        $result = self::makeSecureApiRequest($xml, 'get_user_by_login');
        
        if (!$result['success']) {
            return false;
        }
        
        $xml_response = self::parseXmlResponse($result['body'], 'customer/get/result');
        if (!$xml_response) {
            return false;
        }
        
        if (isset($xml_response->customer->get->result->id)) {
            return (string)$xml_response->customer->get->result->id;
        }
        
        return false;
    }
    
    /**
     * Create Plesk user account securely
     */
    public static function createUserAccount($email, $username, $password) {
        // Validate inputs
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        
        if (strlen($username) < 3 || strlen($username) > 20) {
            return false;
        }
        
        if (strlen($password) < 8) {
            return false;
        }
        
        // Sanitize inputs
        $email = PleskSecurity::sanitizeForXml($email);
        $username = PleskSecurity::sanitizeForXml($username);
        $password = PleskSecurity::sanitizeForXml($password);
        
        $xml = '<?xml version="1.0" encoding="UTF-8"?>
        <packet version="1.6.3.0">
            <customer>
                <add>
                    <gen_info>
                        <pname>' . $username . '</pname>
                        <login>' . $username . '</login>
                        <passwd>' . $password . '</passwd>
                        <email>' . $email . '</email>
                    </gen_info>
                </add>
            </customer>
        </packet>';
        
        $result = self::makeSecureApiRequest($xml, 'create_user');
        
        if (!$result['success']) {
            return false;
        }
        
        $xml_response = self::parseXmlResponse($result['body'], 'customer/add/result');
        if (!$xml_response) {
            return false;
        }
        
        if (isset($xml_response->customer->add->result->status) && 
            (string)$xml_response->customer->add->result->status === 'ok') {
            
            return (string)$xml_response->customer->add->result->id;
        }
        
        // Log error without exposing sensitive details
        if (isset($xml_response->customer->add->result->errtext)) {
            PleskSecurity::secureLog('Plesk user creation failed', 'error', [
                'username' => $username,
                'email' => $email
            ]);
        }
        
        return false;
    }
    
    /**
     * Update user password securely
     */
    public static function updateUserPassword($user_id, $new_password) {
        if (strlen($new_password) < 8) {
            return false;
        }
        
        $user_id = PleskSecurity::sanitizeForXml($user_id);
        $new_password = PleskSecurity::sanitizeForXml($new_password);
        
        $xml = '<?xml version="1.0" encoding="UTF-8"?>
        <packet version="1.6.3.0">
            <customer>
                <set>
                    <filter>
                        <id>' . $user_id . '</id>
                    </filter>
                    <values>
                        <gen_info>
                            <passwd>' . $new_password . '</passwd>
                        </gen_info>
                    </values>
                </set>
            </customer>
        </packet>';
        
        $result = self::makeSecureApiRequest($xml, 'update_user_password');
        
        if (!$result['success']) {
            return false;
        }
        
        $xml_response = self::parseXmlResponse($result['body'], 'customer/set/result');
        if (!$xml_response) {
            return false;
        }
        
        return isset($xml_response->customer->set->result->status) && 
               (string)$xml_response->customer->set->result->status === 'ok';
    }
    
    /**
     * Check if webspace exists
     */
    public static function webspaceExists($domain_name) {
        $domain_name = PleskSecurity::validateDomain($domain_name);
        if (!$domain_name) {
            return false;
        }
        
        $domain_name = PleskSecurity::sanitizeForXml($domain_name);
        
        $xml = '<?xml version="1.0" encoding="UTF-8"?>
        <packet version="1.6.3.0">
            <webspace>
                <get>
                    <filter>
                        <name>' . $domain_name . '</name>
                    </filter>
                    <dataset>
                        <gen_info/>
                    </dataset>
                </get>
            </webspace>
        </packet>';
        
        $result = self::makeSecureApiRequest($xml, 'check_webspace');
        
        if (!$result['success']) {
            return false;
        }
        
        $xml_response = self::parseXmlResponse($result['body'], 'webspace/get/result');
        if (!$xml_response) {
            return false;
        }
        
        return isset($xml_response->webspace->get->result->id);
    }
    
    /**
     * Create webspace securely
     */
    public static function createWebspace($domain_name, $owner_id, $ftp_username, $ftp_password) {
        // Validate inputs
        $domain_name = PleskSecurity::validateDomain($domain_name);
        if (!$domain_name) {
            return [
                'success' => false,
                'message' => 'Invalid domain name'
            ];
        }
        
        if (strlen($ftp_username) < 3 || strlen($ftp_password) < 8) {
            return [
                'success' => false,
                'message' => 'Invalid FTP credentials'
            ];
        }
        
        // Sanitize inputs
        $domain_name = PleskSecurity::sanitizeForXml($domain_name);
        $owner_id = PleskSecurity::sanitizeForXml($owner_id);
        $ftp_username = PleskSecurity::sanitizeForXml($ftp_username);
        $ftp_password = PleskSecurity::sanitizeForXml($ftp_password);
        $ip_address = PleskSecurity::sanitizeForXml(PleskConfig::get('ip_address'));
        $plan_guid = PleskSecurity::sanitizeForXml(PleskConfig::get('plan_guid'));
        
        $xml = '<?xml version="1.0" encoding="UTF-8"?>
        <packet version="1.6.3.0">
            <webspace>
                <add>
                    <gen_setup>
                        <name>' . $domain_name . '</name>
                        <ip_address>' . $ip_address . '</ip_address>
                        <owner-id>' . $owner_id . '</owner-id>
                        <htype>vrt_hst</htype>
                    </gen_setup>
                    <hosting>
                        <vrt_hst>
                            <property>
                                <name>ftp_login</name>
                                <value>' . $ftp_username . '</value>
                            </property>
                            <property>
                                <name>ftp_password</name>
                                <value>' . $ftp_password . '</value>
                            </property>
                            <ip_address>' . $ip_address . '</ip_address>
                        </vrt_hst>
                    </hosting>
                    <plan-guid>' . $plan_guid . '</plan-guid>
                </add>
            </webspace>
        </packet>';
        
        $result = self::makeSecureApiRequest($xml, 'create_webspace');
        
        if (!$result['success']) {
            return [
                'success' => false,
                'message' => $result['message']
            ];
        }
        
        $xml_response = self::parseXmlResponse($result['body'], 'webspace/add/result');
        if (!$xml_response) {
            return [
                'success' => false,
                'message' => 'Failed to parse response'
            ];
        }
        
        if (isset($xml_response->webspace->add->result->status) && 
            (string)$xml_response->webspace->add->result->status === 'ok') {
            
            return [
                'success' => true,
                'id' => (string)$xml_response->webspace->add->result->id,
                'guid' => (string)$xml_response->webspace->add->result->guid,
                'message' => 'Webspace created successfully'
            ];
        }
        
        $error_message = isset($xml_response->webspace->add->result->errtext) ? 
            'Webspace creation failed' : 'Unknown error';
        
        PleskSecurity::secureLog('Webspace creation failed', 'error', [
            'domain' => $domain_name,
            'owner_id' => $owner_id
        ]);
        
        return [
            'success' => false,
            'message' => $error_message
        ];
    }
    
    /**
     * Update webspace FTP credentials
     */
    public static function updateWebspaceFtpCredentials($domain_name, $ftp_username, $ftp_password) {
        $domain_name = PleskSecurity::validateDomain($domain_name);
        if (!$domain_name) {
            return [
                'success' => false,
                'message' => 'Invalid domain name'
            ];
        }
        
        if (strlen($ftp_username) < 3 || strlen($ftp_password) < 8) {
            return [
                'success' => false,
                'message' => 'Invalid FTP credentials'
            ];
        }
        
        $domain_name = PleskSecurity::sanitizeForXml($domain_name);
        $ftp_username = PleskSecurity::sanitizeForXml($ftp_username);
        $ftp_password = PleskSecurity::sanitizeForXml($ftp_password);
        
        $xml = '<?xml version="1.0" encoding="UTF-8"?>
        <packet version="1.6.3.0">
            <webspace>
                <set>
                    <filter>
                        <name>' . $domain_name . '</name>
                    </filter>
                    <values>
                        <hosting>
                            <vrt_hst>
                                <property>
                                    <name>ftp_login</name>
                                    <value>' . $ftp_username . '</value>
                                </property>
                                <property>
                                    <name>ftp_password</name>
                                    <value>' . $ftp_password . '</value>
                                </property>
                            </vrt_hst>
                        </hosting>
                    </values>
                </set>
            </webspace>
        </packet>';
        
        $result = self::makeSecureApiRequest($xml, 'update_webspace_ftp');
        
        if (!$result['success']) {
            return [
                'success' => false,
                'message' => $result['message']
            ];
        }
        
        $xml_response = self::parseXmlResponse($result['body'], 'webspace/set/result');
        if (!$xml_response) {
            return [
                'success' => false,
                'message' => 'Failed to parse response'
            ];
        }
        
        if (isset($xml_response->webspace->set->result->status) && 
            (string)$xml_response->webspace->set->result->status === 'ok') {
            
            return [
                'success' => true,
                'message' => 'FTP credentials updated successfully'
            ];
        }
        
        return [
            'success' => false,
            'message' => 'Failed to update FTP credentials'
        ];
    }
    
    /**
     * Delete webspace securely
     */
    public static function deleteWebspace($webspace_id) {
        $webspace_id = PleskSecurity::sanitizeForXml($webspace_id);
        
        $xml = '<?xml version="1.0" encoding="UTF-8"?>
        <packet version="1.6.3.0">
            <webspace>
                <del>
                    <filter>
                        <id>' . $webspace_id . '</id>
                    </filter>
                </del>
            </webspace>
        </packet>';
        
        $result = self::makeSecureApiRequest($xml, 'delete_webspace');
        
        if (!$result['success']) {
            return false;
        }
        
        $xml_response = self::parseXmlResponse($result['body'], 'webspace/del/result');
        if (!$xml_response) {
            return false;
        }
        
        return isset($xml_response->webspace->del->result->status) && 
               (string)$xml_response->webspace->del->result->status === 'ok';
    }
}

// Secure utility functions
function secure_generate_random_string($length = 16) {
    return PleskSecurity::generateSecurePassword($length);
}

function secure_sanitize_domain_name($domain_name) {
    $domain_name = PleskSecurity::validateDomain($domain_name);
    if (!$domain_name) {
        return false;
    }
    
    // Create safe username from domain
    $username = preg_replace('/\.[a-zA-Z]{2,}$/', '', $domain_name);
    $username = preg_replace('/[^a-zA-Z0-9]/', '_', $username);
    $username = substr($username, 0, 20);
    
    // Ensure it starts with a letter
    if (!preg_match('/^[a-zA-Z]/', $username)) {
        $username = 'user_' . $username;
    }
    
    return substr($username, 0, 20);
}