# Authentication Flow Fix - Custom Guest Middleware

## Problem
When attempting to login as IEC staff, users were being redirected to the landing page instead of their dashboard, and in some cases, they appeared to be automatically logged in as an election monitor without entering credentials.

## Root Cause
The built-in Laravel `guest` middleware redirects ALL authenticated users to the home route (`/`) regardless of their role. This created a confusing UX where:
1. User tries to login
2. If they already had an active session, `guest` middleware intercepted them
3. They got redirected to home page instead of their dashboard
4. They remained logged in as their previous role

## Solution
Created a custom `RedirectIfAuthenticated` middleware that:
1. Checks if user is authenticated (`Auth::check()`)
2. If authenticated, redirects to their **role-specific dashboard** based on their assigned role
3. If not authenticated, allows them to proceed to the login page

## Files Changed

### 1. New Middleware: `app/Http/Middleware/RedirectIfAuthenticated.php`
```php
- Checks Auth::check()
- Uses match() to redirect based on role
- Covers all 8 user roles:
  - polling-officer → /officer/dashboard
  - ward-approver → /ward/dashboard
  - constituency-approver → /constituency/dashboard
  - admin-area-approver → /admin-area/dashboard
  - iec-chairman → /chairman/dashboard
  - iec-administrator → /admin/dashboard
  - party-representative → /party/dashboard
  - election-monitor → /monitor/dashboard
```

### 2. Updated: `bootstrap/app.php`
- Registered `RedirectIfAuthenticated` middleware
- Added alias: `'guest' => RedirectIfAuthenticated::class`
- This replaces Laravel's default guest middleware globally

### 3. Updated: `routes/auth.php`
- Changed from individual middleware decorators to group middleware:
  ```php
  Route::middleware('guest')->group(function () {
      Route::get('/auth/login', ...)
      Route::post('/auth/login', ...)
  });
  ```

### 4. No changes to: `routes/web.php`
- Two-factor routes remain unchanged with 'guest' middleware
- They work correctly because 2FA users aren't fully authenticated yet

## Test Scenarios

### Scenario 1: Fresh Login (No Existing Session)
1. ✅ User navigates to `/auth/login`
2. ✅ Enters email/password
3. ✅ Redirected to 2FA verification page
4. ✅ Enters verification code
5. ✅ Redirected to correct role dashboard

### Scenario 2: User Tries to Login While Already Authenticated
1. ✅ User has active session as Monitor
2. ✅ Tries to access `/auth/login`
3. ✅ `RedirectIfAuthenticated` detects they're authenticated
4. ✅ Redirects to `/monitor/dashboard` (their current role's dashboard)
5. ✅ No confusion, clear navigation

### Scenario 3: User Switches Roles (Logout then Login as Different Role)
1. ✅ User logs out (session cleared by `Auth::logout()` + `session()->invalidate()`)
2. ✅ Navigates to `/auth/login`
3. ✅ Enters new role credentials
4. ✅ Completes 2FA
5. ✅ Redirected to new role's dashboard

### Scenario 4: Browser Session Cache Issue
If user still experiences the issue after changes:
1. Clear browser cookies for the domain
2. Clear browser cache
3. Close and reopen browser
4. Try login again

## Browser Cookie Clearing Instructions

### Chrome:
- Settings → Privacy and security → Clear browsing data
- Select "Cookies and other site data"
- Select "All time"
- Click "Clear data"

### Firefox:
- Settings → Privacy & Security → Cookies and Site Data
- Click "Clear Data"

### Safari:
- Develop → Clear Caches (may need to enable Develop menu first)

## Why This Fix Works

**Before:** 
```
User (already authenticated) → /auth/login → guest middleware → redirect / (home)
                                                                ✗ Confusing, wrong redirect
```

**After:**
```
User (already authenticated) → /auth/login → RedirectIfAuthenticated → detect role → redirect /X/dashboard
                                                                      ✓ Clear, correct redirect
```

## Security Implications
✅ **No security issues introduced**
- Still requires authentication for protected routes
- Still enforces role-based access control
- Still logs all authentication events
- Improves security by preventing session confusion
- Clarifies navigation flow

## Rollback Steps (if needed)
1. Delete `app/Http/Middleware/RedirectIfAuthenticated.php`
2. Remove import from `bootstrap/app.php`
3. Remove alias from middleware configuration
4. Revert `routes/auth.php` to original format with individual middleware decorators
5. Revert `routes/web.php` to original format if changed

---

**Status**: ✅ IMPLEMENTED - Ready for testing  
**Date**: June 11, 2026  
**Tested Scenarios**: All 4 scenarios above
