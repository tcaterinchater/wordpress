<?php
/**
 * SECURE Plesk Integration Replacement
 * 
 * Replace the existing Plesk functions in functions.php with these secure versions
 */

// Include the secure Plesk API classes
require_once get_stylesheet_directory() . '/plesk-security-config.php';
require_once get_stylesheet_directory() . '/secure-plesk-functions.php';

/**
 * SECURE: Main subscription status handler
 */
add_action('woocommerce_subscription_status_updated', 'secure_handle_plesk_subscription_status_change', 10, 3);
function secure_handle_plesk_subscription_status_change($subscription, $new_status, $old_status) {
    if ($new_status === 'active' && $old_status !== 'active') {
        secure_create_plesk_account_on_subscription($subscription, $new_status, $old_status);
    } elseif ($new_status === 'cancelled' && $old_status !== 'cancelled') {
        secure_remove_plesk_account_on_subscription_cancel($subscription);
    }
}

/**
 * SECURE: Create Plesk account on subscription activation
 */
function secure_create_plesk_account_on_subscription($subscription, $new_status, $old_status) {
    if ($new_status !== 'active' || $old_status === 'active') {
        return;
    }
    
    try {
        foreach ($subscription->get_items() as $item_id => $item) {
            $domain_name = $item->get_meta('Domain Name');
            
            if (!$domain_name) {
                continue;
            }
            
            // Validate domain
            $domain_name = PleskSecurity::validateDomain($domain_name);
            if (!$domain_name) {
                PleskSecurity::secureLog('Invalid domain name in subscription', 'warning', [
                    'subscription_id' => $subscription->get_id(),
                    'item_id' => $item_id
                ]);
                continue;
            }
            
            $email = $subscription->get_billing_email();
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                PleskSecurity::secureLog('Invalid email in subscription', 'warning', [
                    'subscription_id' => $subscription->get_id()
                ]);
                continue;
            }
            
            // Generate secure credentials
            $ftp_username = secure_sanitize_domain_name($domain_name);
            $ftp_password = secure_generate_random_string(16);
            $plesk_login_url = PleskConfig::get('login_url_base') . '?login_name=' . urlencode($ftp_username);
            
            if (!$ftp_username) {
                PleskSecurity::secureLog('Failed to generate FTP username', 'error', [
                    'domain' => $domain_name
                ]);
                continue;
            }
            
            // Handle user creation/update
            $user_result = secure_handle_plesk_user($email, $ftp_username, $ftp_password);
            
            if (!$user_result['success']) {
                PleskSecurity::secureLog('Failed to handle Plesk user', 'error', [
                    'message' => $user_result['message'],
                    'domain' => $domain_name
                ]);
                continue;
            }
            
            $user_id = $user_result['user_id'];
            
            // Check if webspace exists
            $webspace_exists = SecurePleskAPI::webspaceExists($domain_name);
            
            if ($webspace_exists) {
                PleskSecurity::secureLog('Webspace already exists, updating credentials', 'info', [
                    'domain' => $domain_name
                ]);
                
                // Update FTP credentials for existing webspace
                $update_result = SecurePleskAPI::updateWebspaceFtpCredentials($domain_name, $ftp_username, $ftp_password);
                
                if ($update_result['success']) {
                    PleskSecurity::secureLog('Successfully updated FTP credentials', 'info', [
                        'domain' => $domain_name
                    ]);
                } else {
                    PleskSecurity::secureLog('Failed to update FTP credentials', 'error', [
                        'domain' => $domain_name,
                        'message' => $update_result['message']
                    ]);
                }
                
                // Update subscription meta with existing data
                secure_update_subscription_meta_with_existing_data($subscription, $domain_name, $ftp_username, $ftp_password, $plesk_login_url);
                continue;
            }
            
            // Create new webspace
            $webspace_result = SecurePleskAPI::createWebspace($domain_name, $user_id, $ftp_username, $ftp_password);
            
            if ($webspace_result['success']) {
                $meta_data = [
                    'plesk_domain_name' => $domain_name,
                    'plesk_ftp_username' => $ftp_username,
                    'plesk_ftp_password' => $ftp_password,
                    'plesk_login_url' => $plesk_login_url,
                    'plesk_subscription_guid' => $webspace_result['guid'],
                    'plesk_subscription_id' => $webspace_result['id']
                ];
                
                foreach ($meta_data as $key => $new_value) {
                    $subscription->update_meta_data($key, $new_value);
                }
                
                $subscription->save();
                
                PleskSecurity::secureLog('Successfully created webspace', 'info', [
                    'domain' => $domain_name,
                    'webspace_id' => $webspace_result['id']
                ]);
            } else {
                PleskSecurity::secureLog('Failed to create webspace', 'error', [
                    'domain' => $domain_name,
                    'message' => $webspace_result['message']
                ]);
            }
        }
    } catch (Exception $e) {
        PleskSecurity::secureLog('Exception in Plesk account creation', 'error', [
            'exception' => $e->getMessage(),
            'subscription_id' => $subscription->get_id()
        ]);
    }
}

/**
 * SECURE: Handle Plesk user creation/update
 */
function secure_handle_plesk_user($email, $username, $password) {
    try {
        // Check if user exists
        $existing_user_id = SecurePleskAPI::getUserByLogin($username);
        
        if ($existing_user_id) {
            PleskSecurity::secureLog('Existing user found, updating password', 'info', [
                'username' => $username,
                'user_id' => $existing_user_id
            ]);
            
            // Update existing user's password
            $update_result = SecurePleskAPI::updateUserPassword($existing_user_id, $password);
            
            if ($update_result) {
                return [
                    'success' => true,
                    'user_id' => $existing_user_id,
                    'message' => 'Existing user found and password updated',
                    'action' => 'updated'
                ];
            } else {
                return [
                    'success' => true,
                    'user_id' => $existing_user_id,
                    'message' => 'Existing user found (password update failed)',
                    'action' => 'found'
                ];
            }
        }
        
        // Create new user
        $new_user_id = SecurePleskAPI::createUserAccount($email, $username, $password);
        
        if ($new_user_id) {
            PleskSecurity::secureLog('New user created successfully', 'info', [
                'username' => $username,
                'user_id' => $new_user_id
            ]);
            
            return [
                'success' => true,
                'user_id' => $new_user_id,
                'message' => 'New user created successfully',
                'action' => 'created'
            ];
        } else {
            return [
                'success' => false,
                'user_id' => null,
                'message' => 'Failed to create new user',
                'action' => 'failed'
            ];
        }
        
    } catch (Exception $e) {
        PleskSecurity::secureLog('Exception in user handling', 'error', [
            'exception' => $e->getMessage(),
            'username' => $username
        ]);
        
        return [
            'success' => false,
            'user_id' => null,
            'message' => 'Exception occurred during user handling',
            'action' => 'error'
        ];
    }
}

/**
 * SECURE: Update subscription meta with existing data
 */
function secure_update_subscription_meta_with_existing_data($subscription, $domain_name, $ftp_username, $ftp_password, $plesk_login_url) {
    try {
        // Get existing webspace details securely
        $webspace_details = secure_get_plesk_webspace_details($domain_name);
        
        if ($webspace_details) {
            $meta_data = [
                'plesk_domain_name' => $domain_name,
                'plesk_ftp_username' => $ftp_username,
                'plesk_ftp_password' => $ftp_password,
                'plesk_login_url' => $plesk_login_url,
                'plesk_subscription_guid' => $webspace_details['guid'],
                'plesk_subscription_id' => $webspace_details['id']
            ];
            
            foreach ($meta_data as $key => $new_value) {
                $subscription->update_meta_data($key, $new_value);
            }
            
            $subscription->save();
            
            PleskSecurity::secureLog('Updated subscription meta with existing webspace data', 'info', [
                'domain' => $domain_name,
                'subscription_id' => $subscription->get_id()
            ]);
        }
    } catch (Exception $e) {
        PleskSecurity::secureLog('Exception updating subscription meta', 'error', [
            'exception' => $e->getMessage(),
            'domain' => $domain_name
        ]);
    }
}

/**
 * SECURE: Get webspace details
 */
function secure_get_plesk_webspace_details($domain_name) {
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
    
    $result = SecurePleskAPI::makeSecureApiRequest($xml, 'get_webspace_details');
    
    if (!$result['success']) {
        return false;
    }
    
    $xml_response = SecurePleskAPI::parseXmlResponse($result['body'], 'webspace/get/result');
    if (!$xml_response) {
        return false;
    }
    
    if (isset($xml_response->webspace->get->result->id)) {
        return [
            'id' => (string)$xml_response->webspace->get->result->id,
            'guid' => isset($xml_response->webspace->get->result->guid) ? 
                     (string)$xml_response->webspace->get->result->guid : ''
        ];
    }
    
    return false;
}

/**
 * SECURE: Remove Plesk account on subscription cancellation
 */
function secure_remove_plesk_account_on_subscription_cancel($subscription) {
    try {
        $plesk_subscription_id = $subscription->get_meta('plesk_subscription_id');
        
        if (!$plesk_subscription_id) {
            PleskSecurity::secureLog('No Plesk subscription ID found for cancellation', 'warning', [
                'subscription_id' => $subscription->get_id()
            ]);
            return;
        }
        
        $delete_result = SecurePleskAPI::deleteWebspace($plesk_subscription_id);
        
        if ($delete_result) {
            // Clean up subscription meta data
            $subscription->delete_meta_data('plesk_subscription_id');
            $subscription->delete_meta_data('plesk_subscription_guid');
            $subscription->delete_meta_data('plesk_domain_name');
            $subscription->delete_meta_data('plesk_ftp_username');
            $subscription->delete_meta_data('plesk_ftp_password');
            $subscription->delete_meta_data('plesk_login_url');
            $subscription->save();
            
            PleskSecurity::secureLog('Successfully deleted Plesk webspace', 'info', [
                'webspace_id' => $plesk_subscription_id,
                'subscription_id' => $subscription->get_id()
            ]);
        } else {
            PleskSecurity::secureLog('Failed to delete Plesk webspace', 'error', [
                'webspace_id' => $plesk_subscription_id,
                'subscription_id' => $subscription->get_id()
            ]);
        }
        
    } catch (Exception $e) {
        PleskSecurity::secureLog('Exception during Plesk account removal', 'error', [
            'exception' => $e->getMessage(),
            'subscription_id' => $subscription->get_id()
        ]);
    }
}

/**
 * Configuration validation on plugin activation
 */
function validate_plesk_configuration() {
    try {
        PleskConfig::init();
        PleskSecurity::secureLog('Plesk configuration validated successfully', 'info');
        return true;
    } catch (Exception $e) {
        PleskSecurity::secureLog('Plesk configuration validation failed', 'error', [
            'exception' => $e->getMessage()
        ]);
        
        // Show admin notice
        add_action('admin_notices', function() use ($e) {
            echo '<div class="notice notice-error"><p>';
            echo '<strong>Plesk Integration Error:</strong> ' . esc_html($e->getMessage());
            echo '</p></div>';
        });
        
        return false;
    }
}

// Validate configuration on admin init
add_action('admin_init', 'validate_plesk_configuration');

/**
 * Add security headers for Plesk API requests
 */
add_filter('http_request_args', function($args, $url) {
    if (strpos($url, PleskConfig::get('endpoint')) !== false) {
        $args['headers']['X-Requested-With'] = 'WordPress-Plesk-Integration';
        $args['headers']['Cache-Control'] = 'no-cache, no-store, must-revalidate';
    }
    return $args;
}, 10, 2);