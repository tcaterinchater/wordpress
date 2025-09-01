# Plesk Integration Logic - How It Handles Existing Users & Sites

## Current Improved Logic Flow

### When Subscription Becomes Active:

```
1. GET DOMAIN from subscription items
2. GENERATE FTP credentials (username, password)
3. CHECK USER EXISTS?
   ├─ YES → Get existing user ID + Update user password
   └─ NO → Create new user + Get new user ID
4. CHECK WEBSPACE/SITE EXISTS for this domain?
   ├─ YES → Update FTP credentials + Update subscription meta + SKIP creation
   └─ NO → Create new webspace with user ID
5. SAVE subscription meta data
```

## Key Points:

### ✅ **User Handling (Fixed)**
- **If user exists**: Gets existing user ID and updates their password
- **If user doesn't exist**: Creates new user
- **Result**: Always have a valid user ID to proceed

### ✅ **Site/Webspace Handling (Fixed)**  
- **If webspace exists**: 
  - Updates FTP credentials for existing webspace
  - Updates subscription meta with existing data
  - **SKIPS** creating duplicate webspace
- **If webspace doesn't exist**: Creates new webspace

### ✅ **Login Credentials (Fixed)**
- **Existing users**: Password gets updated to new generated password
- **Existing webspaces**: FTP credentials get updated to match new credentials
- **New everything**: Creates with fresh credentials

## What Happens in Each Scenario:

### Scenario 1: Completely New (User + Domain)
```
✅ Create new user
✅ Create new webspace  
✅ Save meta data
```

### Scenario 2: Existing User, New Domain
```
✅ Get existing user ID + Update password
✅ Create new webspace (domain doesn't exist)
✅ Save meta data
```

### Scenario 3: Existing User, Existing Domain
```
✅ Get existing user ID + Update password
✅ Update FTP credentials for existing webspace
✅ Update meta data (no new creation)
❌ SKIP webspace creation (prevents duplicates)
```

### Scenario 4: New User, Existing Domain (Edge case)
```
✅ Create new user
✅ Update existing webspace owner to new user
✅ Update FTP credentials
✅ Update meta data
```

## The Answer to Your Question:

**YES**, the logic is now properly updated! Here's what happens:

### If User Exists:
1. ✅ Gets existing user ID (no duplicate user creation)
2. ✅ Updates user password to new generated password
3. ✅ Checks if webspace exists for domain
4. ✅ If webspace exists: Updates FTP credentials + Updates meta (NO duplicate site creation)
5. ✅ If webspace doesn't exist: Creates new webspace with existing user ID

### Key Improvement:
The new `update_plesk_webspace_ftp_credentials()` function ensures that even when a webspace already exists, the FTP login credentials are updated to match the current subscription, so users can still log in with the new credentials.

## Functions Added/Improved:

1. **`handle_plesk_user()`** - Smart user management
2. **`get_plesk_user_by_login()`** - Check user existence  
3. **`update_plesk_user_password()`** - Update existing user passwords
4. **`check_plesk_webspace_exists()`** - Check webspace existence
5. **`update_plesk_webspace_ftp_credentials()`** - Update FTP for existing webspaces ⭐ **NEW**
6. **`create_plesk_webspace()`** - Create new webspace only when needed

## Result:
- ❌ **No duplicate users**
- ❌ **No duplicate webspaces/sites** 
- ✅ **Updated login credentials**
- ✅ **Proper error handling**
- ✅ **Complete subscription meta data**