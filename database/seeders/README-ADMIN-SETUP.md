# Admin Account Setup Instructions

## Overview
This directory contains seeders for creating admin accounts for the BodyF1rst platform.

## Security Notice
⚠️ **IMPORTANT**: The seeders contain placeholders for passwords. You must update them with actual passwords before running.

## Accounts Created

### 1. System Admin Account
- **Table**: `admins`
- **Email**: charlesblanchard85@gmail.com
- **Name**: Charles Blanchard
- **Role**: System Administrator

### 2. Coaches Dashboard Admin
- **Table**: `admins` AND `coaches`
- **Email**: Charley@bodyf1rst.com
- **Name**: Charley BodyF1rst
- **Role**: admin / lead_trainer
- **Purpose**: Full access to Coaches Dashboard

### 3. Mobile App User
- **Table**: `users`
- **Email**: Charley@bodyf1rst.com
- **Name**: Charley BodyF1rst
- **Role**: client
- **Purpose**: Testing mobile app

## How to Run Seeders

### Option 1: Run All Seeders
```bash
php artisan db:seed
```

### Option 2: Run Specific Seeders
```bash
php artisan db:seed --class=AdminSeeder
php artisan db:seed --class=CoachAdminSeeder
php artisan db:seed --class=CharleyUserSeeder
```

### Option 3: Run on Production (SSH into EC2)
```bash
ssh your-ec2-server
cd /path/to/bodyf1rst-backend
php artisan db:seed --class=AdminSeeder --force
```

## Manual Database Creation

If you cannot run seeders, you can:
1. Use the local script: `CREATE_ADMIN_ACCOUNTS_LOCAL.sh` (not committed to Git)
2. Run SQL commands directly on the database
3. Use Laravel Tinker:
   ```bash
   php artisan tinker
   >>> App\Models\Admin::create(['email' => 'admin@example.com', 'password' => 'YourPassword', ...]);
   ```

## Password Security

- Passwords are automatically hashed using bcrypt when using the `password` attribute
- Never commit actual passwords to version control
- Use environment variables or secure vaults for production passwords
- The `CREATE_ADMIN_ACCOUNTS_LOCAL.sh` script (gitignored) contains actual passwords for local use only

## Testing

After creating accounts, test login at:
- **Mobile App**: http://localhost:4200/login or https://your-app-url.com/login
- **Coaches Dashboard**: http://localhost:4201/login or https://coaches.bodyf1rst.com/login
- **Admin Panel**: https://api.bodyf1rst.com/admin/login

Test credentials are documented in `LOGIN-REGISTRATION-TEST-PLAN.md` in the workspace root.
