# Fix Support User Permissions

## Problem
The support user can see everything like an admin, which means they likely have incorrect permissions assigned.

## Solution

### Option 1: Via User Management Page (Recommended)
1. Log in as admin
2. Go to `/dashboard/users`
3. Find the support user
4. Click "Edit"
5. Make sure the role is set to `support`
6. Save

### Option 2: Via Database/Artisan

Run this command to fix the support user:

```bash
cd elmcorner-sys-backend
php artisan db:seed --class=SupportUserSeeder --force
```

Then run this to ensure permissions are correct:

```bash
php artisan db:seed --class=RoleSeeder --force
```

### Option 3: Direct Database Query

If you have database access, run:

```sql
-- Find support user
SELECT id, email, role FROM users WHERE role = 'support' OR email LIKE '%support%';

-- Remove all roles from support user (replace USER_ID with actual ID)
DELETE FROM model_has_roles WHERE model_id = USER_ID;

-- Assign support role (replace USER_ID and ROLE_ID)
INSERT INTO model_has_roles (role_id, model_type, model_id) 
VALUES (ROLE_ID, 'App\\Models\\User', USER_ID);

-- Verify support role has correct permissions
SELECT p.name 
FROM permissions p
JOIN role_has_permissions rhp ON p.id = rhp.permission_id
JOIN roles r ON rhp.role_id = r.id
WHERE r.name = 'support';
```

Expected support permissions:
- view_students
- view_teachers
- view_timetables
- view_courses
- view_packages
- view_trials
- view_duties
- view_reports
- send_whatsapp

## After Fixing

1. **Clear permission cache:**
   ```bash
   php artisan permission:cache-reset
   ```

2. **Have the support user log out and log back in** - This is important because permissions are cached in the JWT token and frontend state.

3. **Check the debug panel** - When logged in as support, you should see a debug panel at the bottom right showing:
   - Role: support
   - Permissions Count: 9 (not all permissions)
   - Should NOT have: manage_users, manage_roles, manage_billing, etc.

## Verification

After the fix, the support user should:
- ✅ See: Students, Families, Leads, Teachers, Courses, Timetables, Classes, Trial Classes, Packages, Duties, Reports, Activity
- ❌ NOT see: Users, Roles, Billing, Financials, Salaries, Settings (unless they have view_settings)
