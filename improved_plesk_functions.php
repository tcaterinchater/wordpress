<?php

/**
 * Improved Plesk account creation function that handles existing users
 */
function create_plesk_account_on_subscription($subscription, $new_status, $old_status) {
    if ($new_status === 'active' && $old_status !== 'active') {
        foreach ( $subscription->get_items() as $item_id => $item ) {
            $domain_name = $item->get_meta('Domain Name');

            if ($domain_name) {
                $email = $subscription->get_billing_email();
                
                // Generate FTP credentials
                $ftp_username = sanitize_domain_name($domain_name);
                $ftp_password = generate_random_string(12);
                $plesk_login_url = 'https://3.82.33.92:8443/login_up.php3?login_name=' . $ftp_username;
                $ip_id = '10.0.0.82';

                // Check if user already exists and handle accordingly
                $user_result = handle_plesk_user($email, $ftp_username, $ftp_password);
                
                if (!$user_result['success']) {
                    error_log('Failed to handle Plesk user: ' . $user_result['message']);
                    continue; // Skip to next item
                }

                $user_id = $user_result['user_id'];
                
                // Check if webspace already exists
                $webspace_exists = check_plesk_webspace_exists($domain_name);
                
                if ($webspace_exists) {
                    error_log("Webspace for domain {$domain_name} already exists. Skipping creation.");
                    // Optionally update subscription meta with existing data
                    update_subscription_meta_with_existing_data($subscription, $domain_name, $ftp_username, $ftp_password, $plesk_login_url);
                    continue;
                }

                // Create webspace if it doesn't exist
                $webspace_result = create_plesk_webspace($domain_name, $ip_id, $user_id, $ftp_username, $ftp_password);
                
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
                    error_log("Successfully created webspace for domain: {$domain_name}");
                } else {
                    error_log('Failed to create webspace: ' . $webspace_result['message']);
                }
            }
        }
    }
}

/**
 * Handle Plesk user - create if doesn't exist, or get existing user ID
 */
function handle_plesk_user($email, $username, $password) {
    // First check if user exists
    $existing_user_id = get_plesk_user_by_login($username);
    
    if ($existing_user_id) {
        error_log("User {$username} already exists with ID: {$existing_user_id}");
        
        // Optionally update the existing user's password
        $update_result = update_plesk_user_password($existing_user_id, $password);
        
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
    
    // User doesn't exist, create new one
    $new_user_id = create_plesk_user_account($email, $username, $password);
    
    if ($new_user_id) {
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
}

/**
 * Check if a Plesk user exists by login name
 */
function get_plesk_user_by_login($login) {
    $plesk_username = 'admin';
    $plesk_password = 'GKpJhzmqe09o%@9j';
    $plesk_endpoint = 'https://3.82.33.92:8443/enterprise/control/agent.php';
    
    $xml = '<?xml version="1.0"?>
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

    $response = wp_remote_post($plesk_endpoint, array(
        'headers' => array(
            'Content-Type' => 'text/xml',
            'HTTP_AUTH_LOGIN' => $plesk_username,
            'HTTP_AUTH_PASSWD' => $plesk_password,
        ),
        'body' => $xml,
        'timeout' => 60,
        'sslverify' => false,
    ));

    if (is_wp_error($response)) {
        error_log('Plesk API request failed: ' . $response->get_error_message());
        return false;
    }

    $response_body = wp_remote_retrieve_body($response);
    $xml_response = simplexml_load_string($response_body);
    
    // Check if user exists and extract ID
    if (isset($xml_response->customer->get->result->id)) {
        return (string)$xml_response->customer->get->result->id;
    }
    
    return false;
}

/**
 * Update existing Plesk user password
 */
function update_plesk_user_password($user_id, $new_password) {
    $plesk_username = 'admin';
    $plesk_password = 'GKpJhzmqe09o%@9j';
    $plesk_endpoint = 'https://3.82.33.92:8443/enterprise/control/agent.php';
    
    $xml = '<?xml version="1.0"?>
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

    $response = wp_remote_post($plesk_endpoint, array(
        'headers' => array(
            'Content-Type' => 'text/xml',
            'HTTP_AUTH_LOGIN' => $plesk_username,
            'HTTP_AUTH_PASSWD' => $plesk_password,
        ),
        'body' => $xml,
        'timeout' => 60,
        'sslverify' => false,
    ));

    if (is_wp_error($response)) {
        error_log('Plesk password update failed: ' . $response->get_error_message());
        return false;
    }

    $response_body = wp_remote_retrieve_body($response);
    $xml_response = simplexml_load_string($response_body);
    
    // Check if update was successful
    return isset($xml_response->customer->set->result->status) && 
           (string)$xml_response->customer->set->result->status === 'ok';
}

/**
 * Check if a webspace (domain) already exists in Plesk
 */
function check_plesk_webspace_exists($domain_name) {
    $plesk_username = 'admin';
    $plesk_password = 'GKpJhzmqe09o%@9j';
    $plesk_endpoint = 'https://3.82.33.92:8443/enterprise/control/agent.php';
    
    $xml = '<?xml version="1.0"?>
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

    $response = wp_remote_post($plesk_endpoint, array(
        'headers' => array(
            'Content-Type' => 'text/xml',
            'HTTP_AUTH_LOGIN' => $plesk_username,
            'HTTP_AUTH_PASSWD' => $plesk_password,
        ),
        'body' => $xml,
        'timeout' => 60,
        'sslverify' => false,
    ));

    if (is_wp_error($response)) {
        error_log('Plesk webspace check failed: ' . $response->get_error_message());
        return false;
    }

    $response_body = wp_remote_retrieve_body($response);
    $xml_response = simplexml_load_string($response_body);
    
    // Check if webspace exists
    return isset($xml_response->webspace->get->result->id);
}

/**
 * Create Plesk webspace (separated from user creation)
 */
function create_plesk_webspace($domain_name, $ip_id, $owner_id, $ftp_username, $ftp_password) {
    $plesk_username = 'admin';
    $plesk_password = 'GKpJhzmqe09o%@9j';
    $plesk_endpoint = 'https://3.82.33.92:8443/enterprise/control/agent.php';

    $xml = '<?xml version="1.0"?>
    <packet version="1.6.3.0">
        <webspace>
            <add>
                <gen_setup>
                    <name>'.$domain_name.'</name>
                    <ip_address>'.$ip_id.'</ip_address>
                    <owner-id>'.$owner_id.'</owner-id>
                    <htype>vrt_hst</htype>
                </gen_setup>
                <hosting>
                    <vrt_hst>
                        <property>
                            <name>ftp_login</name>
                            <value>'.$ftp_username.'</value>
                        </property>
                        <property>
                            <name>ftp_password</name>
                            <value>'.$ftp_password.'</value>
                        </property>
                        <ip_address>'.$ip_id.'</ip_address>
                    </vrt_hst>
                </hosting>
                <plan-guid>53187fa7-1f5e-211c-b41e-24bed5ed2088</plan-guid>
            </add>
        </webspace>
    </packet>';

    $response = wp_remote_post($plesk_endpoint, array(
        'headers' => array(
            'Content-Type' => 'text/xml',
            'HTTP_AUTH_LOGIN' => $plesk_username,
            'HTTP_AUTH_PASSWD' => $plesk_password,
        ),
        'body' => $xml,
        'timeout' => 60,
        'sslverify' => false,
    ));

    if (is_wp_error($response)) {
        return [
            'success' => false,
            'message' => 'Plesk API request failed: ' . $response->get_error_message()
        ];
    }

    $response_body = wp_remote_retrieve_body($response);
    $xml_response = simplexml_load_string($response_body);
    
    if (isset($xml_response->webspace->add->result->status) && 
        (string)$xml_response->webspace->add->result->status === 'ok') {
        
        return [
            'success' => true,
            'id' => (string)$xml_response->webspace->add->result->id,
            'guid' => (string)$xml_response->webspace->add->result->guid,
            'message' => 'Webspace created successfully'
        ];
    } else {
        $error_message = isset($xml_response->webspace->add->result->errtext) ? 
            (string)$xml_response->webspace->add->result->errtext : 'Unknown error';
        
        return [
            'success' => false,
            'message' => 'Webspace creation failed: ' . $error_message
        ];
    }
}

/**
 * Update subscription meta data with existing Plesk data
 */
function update_subscription_meta_with_existing_data($subscription, $domain_name, $ftp_username, $ftp_password, $plesk_login_url) {
    // Get existing webspace details
    $webspace_details = get_plesk_webspace_details($domain_name);
    
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
        error_log("Updated subscription meta with existing webspace data for: {$domain_name}");
    }
}

/**
 * Get details of an existing webspace
 */
function get_plesk_webspace_details($domain_name) {
    $plesk_username = 'admin';
    $plesk_password = 'GKpJhzmqe09o%@9j';
    $plesk_endpoint = 'https://3.82.33.92:8443/enterprise/control/agent.php';
    
    $xml = '<?xml version="1.0"?>
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

    $response = wp_remote_post($plesk_endpoint, array(
        'headers' => array(
            'Content-Type' => 'text/xml',
            'HTTP_AUTH_LOGIN' => $plesk_username,
            'HTTP_AUTH_PASSWD' => $plesk_password,
        ),
        'body' => $xml,
        'timeout' => 60,
        'sslverify' => false,
    ));

    if (is_wp_error($response)) {
        return false;
    }

    $response_body = wp_remote_retrieve_body($response);
    $xml_response = simplexml_load_string($response_body);
    
    if (isset($xml_response->webspace->get->result->id)) {
        return [
            'id' => (string)$xml_response->webspace->get->result->id,
            'guid' => (string)$xml_response->webspace->get->result->guid
        ];
    }
    
    return false;
}

/**
 * Improved Plesk user account creation with better error handling
 */
function create_plesk_user_account($email, $username, $password) {
    $plesk_username = 'admin';
    $plesk_password = 'GKpJhzmqe09o%@9j';
    $plesk_endpoint = 'https://3.82.33.92:8443/enterprise/control/agent.php';
    
    $xml = '<?xml version="1.0"?>
    <packet version="1.6.3.0">
        <customer>
            <add>
                <gen_info>
                    <pname>' . htmlspecialchars($username) . '</pname>
                    <login>' . htmlspecialchars($username) . '</login>
                    <passwd>' . htmlspecialchars($password) . '</passwd>
                    <email>' . htmlspecialchars($email) . '</email>
                </gen_info>
            </add>
        </customer>
    </packet>';

    $response = wp_remote_post($plesk_endpoint, array(
        'headers' => array(
            'Content-Type' => 'text/xml',
            'HTTP_AUTH_LOGIN' => $plesk_username,
            'HTTP_AUTH_PASSWD' => $plesk_password,
        ),
        'body' => $xml,
        'timeout' => 60,
        'sslverify' => false,
    ));

    if (is_wp_error($response)) {
        error_log('Plesk API request failed: ' . $response->get_error_message());
        return false;
    }

    $response_body = wp_remote_retrieve_body($response);
    $xml_response = simplexml_load_string($response_body);
    
    // Check for successful creation
    if (isset($xml_response->customer->add->result->status) && 
        (string)$xml_response->customer->add->result->status === 'ok') {
        
        return (string)$xml_response->customer->add->result->id;
    } else {
        $error_message = isset($xml_response->customer->add->result->errtext) ? 
            (string)$xml_response->customer->add->result->errtext : 'Unknown error';
        error_log('Plesk user creation failed: ' . $error_message);
        return false;
    }
}

/**
 * Helper function to sanitize domain name for username
 */
function sanitize_domain_name($domain) {
    // Remove protocol if present
    $domain = preg_replace('/^https?:\/\//', '', $domain);
    // Remove www. if present
    $domain = preg_replace('/^www\./', '', $domain);
    // Replace dots and other special characters with underscores
    $domain = preg_replace('/[^a-zA-Z0-9]/', '_', $domain);
    // Ensure it starts with a letter
    if (!preg_match('/^[a-zA-Z]/', $domain)) {
        $domain = 'user_' . $domain;
    }
    // Limit length to reasonable size
    return substr($domain, 0, 20);
}

/**
 * Generate a random string for passwords
 */
function generate_random_string($length = 12) {
    $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
    $charactersLength = strlen($characters);
    $randomString = '';
    
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    
    return $randomString;
}

?>