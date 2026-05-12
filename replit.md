# ChargePoint — Plateforme d'Investissement

## Description
Full-stack investment platform built with PHP 8.2, Bootstrap/custom CSS, vanilla JS, and SQLite (PDO).

## Stack
- **Backend**: PHP 8.2 (built-in server on port 5000)
- **Database**: SQLite 3 via PDO (`database.sqlite`)
- **Frontend**: Custom CSS (orange #FF6B00 theme), vanilla JS, Font Awesome icons
- **Payment**: Ashtechpay API (`ak_83adbb920ef3efd424561f70d6b76e7bf0ed91cce302973a`)

## Running the App
```
php -S 0.0.0.0:5000 router.php
```

## Key URLs
- `/` — Landing page
- `/register.php` — User registration (supports ?ref=CODE)
- `/login.php` — User login
- `/dashboard.php` — User dashboard
- `/vip.php` — VIP investment plans
- `/deposit.php` — Deposit via Mobile Money
- `/withdraw.php` — Withdrawal request
- `/transactions.php` — Transaction history
- `/referral.php` — Referral tree & commissions
- `/profile.php` — User profile & wallet settings
- `/admin/login.php` — Admin panel login
- `/admin/dashboard.php` — Admin dashboard
- `/admin/users.php` — User management
- `/admin/withdrawals.php` — Withdrawal processing
- `/admin/vip_plans.php` — VIP plan management
- `/admin/settings.php` — Platform settings
- `/api/cron.php?key=cp_cron_secret_2026` — Daily gains cron
- `/webhook.php` — Ashtechpay payment webhook

## Admin Credentials
- **Username**: Ben10
- **Password**: 1214161820@Ben

## Business Rules
- 10 VIP plans (3,000 → 400,000 FCFA), 125-day duration, daily gains auto-credited
- Referral commissions: Level 1 = 20%, Level 2 = 5%, Level 3 = 2%
- Withdrawal fee: 11%
- Min withdrawal: 1,000 FCFA | Max: 5,000,000 FCFA
- Admin can make "admin_deposit" (withdrawable) or "manual_deposit" (VIP activation only)
- Daily cron must be triggered via URL or cron job

## User Preferences
- Language: French (FR)
- Theme: Orange (#FF6B00) on white/dark
- No emojis in code comments
