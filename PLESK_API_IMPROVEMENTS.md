# Plesk API WordPress Integration - Improvements

## Overview
The improved Plesk API integration now handles existing users and webspaces gracefully, preventing errors and duplicate account creation.

## Key Improvements

### 1. User Existence Check
- **New Function**: `get_plesk_user_by_login($login)`
- Checks if a user already exists in Plesk before attempting creation
- Returns the existing user ID if found, false otherwise

### 2. Smart User Handling
- **New Function**: `handle_plesk_user($email, $username, $password)`
- Handles both new and existing users intelligently:
  - If user exists: Returns existing user ID and optionally updates password
  - If user doesn't exist: Creates new user and returns ID
  - Provides detailed response with success status and action taken

### 3. Webspace Existence Check
- **New Function**: `check_plesk_webspace_exists($domain_name)`
- Prevents duplicate webspace creation
- Checks if a domain already has a webspace in Plesk

### 4. Separated Webspace Creation
- **New Function**: `create_plesk_webspace()`
- Separated webspace creation logic for better modularity
- Improved error handling and response parsing
- Returns structured response with success status and details

### 5. Enhanced Error Handling
- Better XML response parsing
- Structured return values with success/failure status
- Detailed error logging
- Graceful handling of API failures

### 6. Security Improvements
- Added `htmlspecialchars()` to XML data to prevent injection
- Better input sanitization

## Usage Flow

### Original Flow
1. Create user account (fails if exists)
2. Create webspace
3. Update subscription meta

### Improved Flow
1. **Check if user exists**
   - If exists: Get user ID, optionally update password
   - If not exists: Create new user
2. **Check if webspace exists**
   - If exists: Skip creation, update meta with existing data
   - If not exists: Create new webspace
3. **Update subscription meta** with appropriate data

## Function Breakdown

### `create_plesk_account_on_subscription()` - Main Function
- **Improved**: Now uses `handle_plesk_user()` instead of direct creation
- **Added**: Webspace existence check before creation
- **Enhanced**: Better error handling and logging

### `handle_plesk_user()` - Smart User Management
- **Purpose**: Central function for user management
- **Returns**: Structured array with success status, user ID, message, and action taken
- **Actions**: 'created', 'updated', 'found', or 'failed'

### `get_plesk_user_by_login()` - User Existence Check
- **Purpose**: Check if user exists without creating
- **Method**: Uses Plesk customer GET API with login filter
- **Returns**: User ID if exists, false otherwise

### `update_plesk_user_password()` - Password Update
- **Purpose**: Update existing user's password
- **Method**: Uses Plesk customer SET API
- **Returns**: Boolean success status

### `check_plesk_webspace_exists()` - Webspace Check
- **Purpose**: Verify if domain already has webspace
- **Method**: Uses Plesk webspace GET API
- **Returns**: Boolean existence status

### `create_plesk_webspace()` - Webspace Creation
- **Purpose**: Create new webspace (separated from user creation)
- **Returns**: Structured array with success status and webspace details

## Error Scenarios Handled

1. **User Already Exists**: 
   - Gets existing user ID
   - Optionally updates password
   - Continues with webspace creation/check

2. **Webspace Already Exists**:
   - Skips webspace creation
   - Updates subscription meta with existing data
   - Logs the skip action

3. **API Failures**:
   - Comprehensive error logging
   - Graceful failure handling
   - Continues processing other items in subscription

4. **Invalid Responses**:
   - Validates XML response structure
   - Extracts error messages from Plesk responses
   - Provides meaningful error reporting

## Benefits

1. **No Duplicate Accounts**: Prevents creation of duplicate users/webspaces
2. **Robust Error Handling**: Gracefully handles various failure scenarios
3. **Better Logging**: Detailed logs for debugging and monitoring
4. **Modular Design**: Separated concerns for easier maintenance
5. **Password Updates**: Can update passwords for existing users
6. **Data Consistency**: Ensures subscription meta is updated correctly

## Configuration Notes

- Plesk credentials and endpoint are still hardcoded (consider moving to config)
- Plan GUID is hardcoded (consider making configurable)
- IP address is hardcoded (consider making dynamic)

## Next Steps for Production

1. Move credentials to secure configuration
2. Add retry logic for failed API calls
3. Implement rate limiting for API requests
4. Add comprehensive logging with different log levels
5. Consider caching user/webspace existence checks