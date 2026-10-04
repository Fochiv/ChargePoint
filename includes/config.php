<?php
define('ASHTECH_WEBHOOK_SECRET', trim((string)(getenv('ASHTECH_WEBHOOK_SECRET') ?: '')));
define('ASHTECH_API_KEY', trim((string)(getenv('ASHTECH_API_KEY') ?: '')));
define('ASHTECH_BASE_URL', 'https://www.ashtechpay.com');
define('DB_PATH', __DIR__ . '/../database.sqlite');
define('SITE_URL', 'https://chargepoint.zya.me');
define('SITE_NAME', 'ChargePoint');
define('WITHDRAWAL_FEE_PERCENT', 15);
define('REFERRAL_LEVEL1', 20);
define('REFERRAL_LEVEL2', 5);
define('REFERRAL_LEVEL3', 2);
define('MIN_WITHDRAWAL', 1200);
define('MAX_WITHDRAWAL', 5000000);
define('SESSION_LIFETIME', 3600 * 24);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCK_DURATION', 900);
