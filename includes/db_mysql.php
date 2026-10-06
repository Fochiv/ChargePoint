<?php
// Compatibility entry point for older pages that included db_mysql.php.
// Connection settings now come from database.local.php or environment variables.
require_once __DIR__ . '/db.php';
