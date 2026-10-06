# ChargePoint — Plateforme d'Investissement

## Description
Full-stack investment platform built with PHP 8.2, Bootstrap/custom CSS, vanilla JS, and SQLite (PDO).

## Stack
- **Backend**: PHP 8.2 (built-in server on port 5000)
- **Database**: PDO SQLite by default in Replit; PDO MySQL is also supported for classic PHP hosting
- **Frontend**: Custom CSS (orange #FF6B00 theme), vanilla JS, Font Awesome icons
- **Payment**: AshTech Pay Direct API at `https://www.ashtechpay.com`; configure `ASHTECH_API_KEY` and `ASHTECH_WEBHOOK_SECRET` in Replit Secrets.

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
- `/api/cron.php (configured key required)` — Daily gains cron
- `/webhook.php` — Ashtechpay payment webhook

## Database Configuration
- Replit defaults to SQLite and keeps using `database.sqlite`.
- For a MySQL host, either set `DB_DRIVER=mysql`, `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, and `DB_PASSWORD`, or copy `includes/database.local.example.php` to the ignored `includes/database.local.php` and fill it privately.
- `SITE_URL` can be set to the public HTTPS URL of the hosted site.
- Existing MySQL databases are preserved. Runtime setup only adds missing AshTech transaction columns and the webhook-deduplication table; it does not import or replace the database.
- Import `schema_mysql.sql` only when creating a new, empty MySQL database.
- `ASHTECH_API_KEY` is required. `ASHTECH_WEBHOOK_SECRET` is recommended; without it, signed webhooks are rejected and payment completion relies on server-side status polling.

## Admin Access
- Do not store administrator credentials in project documentation.

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
