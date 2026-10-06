<?php
require_once __DIR__ . '/config.php';

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $config = getDatabaseConfig();
        if ($config['driver'] === 'mysql') {
            if (!in_array('mysql', PDO::getAvailableDrivers(), true)) {
                throw new RuntimeException('Le pilote PDO MySQL doit être activé sur cet hébergement.');
            }
            if ($config['dsn'] !== '') {
                $dsn = $config['dsn'];
            } else {
                foreach (['host', 'database', 'username'] as $required) {
                    if ($config[$required] === '') {
                        throw new RuntimeException('Configuration MySQL incomplète. Renseignez host, database et username.');
                    }
                }
                $dsn = sprintf(
                    'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                    $config['host'],
                    $config['port'],
                    $config['database']
                );
            }
            $pdo = new PDO($dsn, $config['username'], $config['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            $pdo->exec('SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci');
            initMySQLDatabase($pdo);
        } else {
            if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
                throw new RuntimeException('Le pilote PDO SQLite doit être activé pour le mode local.');
            }
            $pdo = new PDO('sqlite:' . $config['path']);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $pdo->exec('PRAGMA journal_mode=WAL');
            $pdo->exec('PRAGMA foreign_keys=ON');
            initDB($pdo);
        }
    }
    return $pdo;
}

function getDatabaseConfig(): array {
    static $config = null;
    if ($config !== null) return $config;

    $localFile = __DIR__ . '/database.local.php';
    $local = is_file($localFile) ? require $localFile : [];
    if (!is_array($local)) {
        throw new RuntimeException('includes/database.local.php doit retourner un tableau de configuration.');
    }

    $value = static function (array $environmentNames, string $localName, $default = '') use ($local) {
        foreach ($environmentNames as $name) {
            $environmentValue = getenv($name);
            if ($environmentValue !== false && $environmentValue !== '') return $environmentValue;
        }
        return $local[$localName] ?? $default;
    };

    $dsn = trim((string)$value(['DB_DSN'], 'dsn'));
    $host = trim((string)$value(['DB_HOST', 'MYSQL_HOST'], 'host'));
    $database = trim((string)$value(['DB_NAME', 'DB_DATABASE', 'MYSQL_DB'], 'database'));
    $username = trim((string)$value(['DB_USER', 'DB_USERNAME', 'MYSQL_USER'], 'username'));
    $password = (string)$value(['DB_PASSWORD', 'DB_PASS', 'MYSQL_PASS'], 'password');
    $driver = strtolower(trim((string)$value(['DB_DRIVER'], 'driver')));
    if ($driver === '') {
        $driver = (str_starts_with(strtolower($dsn), 'mysql:') || $host !== '' || $database !== '')
            ? 'mysql'
            : 'sqlite';
    }
    if (!in_array($driver, ['sqlite', 'mysql'], true)) {
        throw new RuntimeException('DB_DRIVER doit être sqlite ou mysql.');
    }

    $port = (int)$value(['DB_PORT', 'MYSQL_PORT'], 'port', 3306);
    $path = trim((string)$value(['DB_PATH'], 'path', DB_PATH));
    if (
        str_starts_with(strtolower($dsn), 'sqlite:')
        && getenv('DB_PATH') === false
        && !isset($local['path'])
    ) {
        $path = substr($dsn, 7);
    }
    $config = [
        'driver' => $driver,
        'path' => $path,
        'dsn' => $dsn,
        'host' => $host,
        'port' => $port > 0 ? $port : 3306,
        'database' => $database,
        'username' => $username,
        'password' => $password,
    ];
    return $config;
}

function initMySQLDatabase(PDO $pdo): void {
    try {
        $pdo->query('SHOW COLUMNS FROM transactions')->fetchAll();
    } catch (PDOException $error) {
        throw new RuntimeException(
            'La base MySQL est accessible, mais sa table transactions est absente. Importez le schéma MySQL dans une base vide.',
            0,
            $error
        );
    }

    ensureAshtechTransactionColumns($pdo);
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ashtech_webhook_events (
            event_key VARCHAR(80) NOT NULL PRIMARY KEY,
            received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

function ensureAshtechTransactionColumns(PDO $pdo): void {
    $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'mysql') {
        $rows = $pdo->query('SHOW COLUMNS FROM transactions')->fetchAll();
        $existing = array_fill_keys(array_column($rows, 'Field'), true);
        $definitions = [
            'reference' => 'VARCHAR(100) DEFAULT NULL',
            'ashtech_transaction_id' => 'VARCHAR(100) DEFAULT NULL',
            'method' => 'VARCHAR(100) DEFAULT NULL',
            'phone' => 'VARCHAR(50) DEFAULT NULL',
            'country_code' => 'VARCHAR(10) DEFAULT NULL',
            'operator' => 'VARCHAR(100) DEFAULT NULL',
            'plan_id' => 'INT DEFAULT NULL',
            'updated_at' => 'DATETIME DEFAULT NULL',
        ];
    } else {
        $rows = $pdo->query('PRAGMA table_info(transactions)')->fetchAll();
        $existing = array_fill_keys(array_column($rows, 'name'), true);
        $definitions = [
            'reference' => 'TEXT DEFAULT NULL',
            'ashtech_transaction_id' => 'TEXT DEFAULT NULL',
            'method' => 'TEXT DEFAULT NULL',
            'phone' => 'TEXT DEFAULT NULL',
            'country_code' => 'TEXT DEFAULT NULL',
            'operator' => 'TEXT DEFAULT NULL',
            'plan_id' => 'INTEGER DEFAULT NULL',
            'updated_at' => 'TEXT DEFAULT NULL',
        ];
    }

    foreach ($definitions as $column => $definition) {
        if (!isset($existing[$column])) {
            $pdo->exec("ALTER TABLE transactions ADD COLUMN `$column` $definition");
        }
    }
}

function insertAshtechWebhookEvent(PDO $pdo, string $eventKey): bool {
    $sql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql'
        ? 'INSERT IGNORE INTO ashtech_webhook_events (event_key) VALUES (?)'
        : 'INSERT OR IGNORE INTO ashtech_webhook_events (event_key) VALUES (?)';
    $statement = $pdo->prepare($sql);
    $statement->execute([$eventKey]);
    return $statement->rowCount() === 1;
}

function initDB(PDO $pdo): void {

    // --------------------------------------------------------
    // TABLES
    // --------------------------------------------------------
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id                    INTEGER PRIMARY KEY AUTOINCREMENT,
            name                  TEXT    NOT NULL,
            email                 TEXT    UNIQUE NOT NULL,
            phone                 TEXT    UNIQUE NOT NULL,
            password              TEXT    NOT NULL,
            referral_code         TEXT    UNIQUE NOT NULL,
            referred_by           INTEGER DEFAULT NULL,
            balance               REAL    DEFAULT 0,
            total_earnings        REAL    DEFAULT 0,
            referral_earnings     REAL    DEFAULT 0,
            has_deposit           INTEGER DEFAULT 0,
            wallet_name           TEXT    DEFAULT NULL,
            wallet_country        TEXT    DEFAULT NULL,
            wallet_method         TEXT    DEFAULT NULL,
            wallet_phone          TEXT    DEFAULT NULL,
            wallet_updated_at     TEXT    DEFAULT NULL,
            status                TEXT    DEFAULT 'active',
            failed_login_attempts INTEGER DEFAULT 0,
            locked_until          TEXT    DEFAULT NULL,
            last_login            TEXT    DEFAULT NULL,
            created_at            TEXT    DEFAULT (datetime('now')),
            FOREIGN KEY (referred_by) REFERENCES users(id) ON DELETE SET NULL
        );

        CREATE TABLE IF NOT EXISTS vip_plans (
            id            INTEGER PRIMARY KEY AUTOINCREMENT,
            name          TEXT    NOT NULL,
            amount        REAL    NOT NULL,
            daily_gain    REAL    NOT NULL,
            total_gain    REAL    NOT NULL,
            duration_days INTEGER NOT NULL DEFAULT 125,
            is_active     INTEGER DEFAULT 1
        );

        CREATE TABLE IF NOT EXISTS investments (
            id             INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id        INTEGER NOT NULL,
            plan_id        INTEGER NOT NULL,
            amount         REAL    NOT NULL,
            daily_gain     REAL    NOT NULL,
            days_total     INTEGER NOT NULL,
            days_remaining INTEGER NOT NULL,
            status         TEXT    DEFAULT 'active',
            started_at     TEXT    DEFAULT (datetime('now')),
            completed_at   TEXT    DEFAULT NULL,
            FOREIGN KEY (user_id) REFERENCES users(id)    ON DELETE CASCADE,
            FOREIGN KEY (plan_id) REFERENCES vip_plans(id)
        );

        CREATE TABLE IF NOT EXISTS transactions (
            id                     INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id                INTEGER NOT NULL,
            type                   TEXT    NOT NULL,
            description            TEXT    DEFAULT NULL,
            amount                 REAL    NOT NULL,
            status                 TEXT    DEFAULT 'pending',
            reference              TEXT    DEFAULT NULL,
            ashtech_transaction_id TEXT    DEFAULT NULL,
            method                 TEXT    DEFAULT NULL,
            phone                  TEXT    DEFAULT NULL,
            country_code           TEXT    DEFAULT NULL,
            operator               TEXT    DEFAULT NULL,
            plan_id                INTEGER DEFAULT NULL,
            reject_reason          TEXT    DEFAULT NULL,
            is_admin_deposit       INTEGER DEFAULT 0,
            created_at             TEXT    DEFAULT (datetime('now')),
            updated_at             TEXT    DEFAULT (datetime('now')),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS ashtech_webhook_events (
            event_key  TEXT PRIMARY KEY,
            received_at TEXT NOT NULL DEFAULT (datetime('now'))
        );

        CREATE TABLE IF NOT EXISTS notifications (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id    INTEGER NOT NULL,
            message    TEXT    NOT NULL,
            is_read    INTEGER DEFAULT 0,
            created_at TEXT    DEFAULT (datetime('now')),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS settings (
            key   TEXT PRIMARY KEY,
            value TEXT NOT NULL
        );

        CREATE TABLE IF NOT EXISTS admin_users (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            username   TEXT UNIQUE NOT NULL,
            password   TEXT        NOT NULL,
            created_at TEXT        DEFAULT (datetime('now'))
        );
    ");

    ensureAshtechTransactionColumns($pdo);

    // --------------------------------------------------------
    // INDEX
    // --------------------------------------------------------
    $pdo->exec("
        CREATE INDEX IF NOT EXISTS idx_users_email         ON users(email);
        CREATE INDEX IF NOT EXISTS idx_users_phone         ON users(phone);
        CREATE INDEX IF NOT EXISTS idx_users_referral_code ON users(referral_code);
        CREATE INDEX IF NOT EXISTS idx_users_referred_by   ON users(referred_by);
        CREATE INDEX IF NOT EXISTS idx_users_status        ON users(status);

        CREATE INDEX IF NOT EXISTS idx_investments_user_id ON investments(user_id);
        CREATE INDEX IF NOT EXISTS idx_investments_status  ON investments(status);

        CREATE INDEX IF NOT EXISTS idx_tx_user_id   ON transactions(user_id);
        CREATE INDEX IF NOT EXISTS idx_tx_type      ON transactions(type);
        CREATE INDEX IF NOT EXISTS idx_tx_status    ON transactions(status);
        CREATE INDEX IF NOT EXISTS idx_tx_reference ON transactions(reference);
        CREATE INDEX IF NOT EXISTS idx_tx_ashtech   ON transactions(ashtech_transaction_id);
        CREATE INDEX IF NOT EXISTS idx_tx_created   ON transactions(created_at);

        CREATE INDEX IF NOT EXISTS idx_notif_user_id ON notifications(user_id);
        CREATE INDEX IF NOT EXISTS idx_notif_is_read ON notifications(is_read);
    ");

    // --------------------------------------------------------
    // VUES
    // --------------------------------------------------------
    $pdo->exec("
        CREATE VIEW IF NOT EXISTS v_user_summary AS
        SELECT
            u.id,
            u.name,
            u.email,
            u.phone,
            u.balance,
            u.total_earnings,
            u.referral_earnings,
            u.has_deposit,
            u.status,
            u.created_at,
            (SELECT COUNT(*) FROM investments  WHERE user_id = u.id AND status = 'active')   AS active_plans,
            (SELECT COUNT(*) FROM users        WHERE referred_by = u.id)                      AS direct_referrals,
            (SELECT COALESCE(SUM(amount),0) FROM transactions WHERE user_id=u.id AND type='deposit'    AND status='success') AS total_deposited,
            (SELECT COALESCE(SUM(amount),0) FROM transactions WHERE user_id=u.id AND type='withdrawal' AND status='success') AS total_withdrawn
        FROM users u;

        CREATE VIEW IF NOT EXISTS v_platform_stats AS
        SELECT
            (SELECT COUNT(*)                 FROM users)                                                                        AS total_users,
            (SELECT COUNT(*)                 FROM users        WHERE has_deposit = 1)                                           AS users_with_deposit,
            (SELECT COUNT(*)                 FROM investments  WHERE status = 'active')                                         AS active_investments,
            (SELECT COALESCE(SUM(amount),0)  FROM transactions WHERE type='deposit'    AND status='success')                    AS total_deposited,
            (SELECT COALESCE(SUM(amount),0)  FROM transactions WHERE type='withdrawal' AND status='success')                    AS total_withdrawn,
            (SELECT COALESCE(SUM(amount),0)  FROM transactions WHERE type='daily_gain')                                         AS total_gains_distributed,
            (SELECT COUNT(*)                 FROM transactions WHERE type='withdrawal' AND status='pending')                     AS pending_withdrawals,
            (SELECT COALESCE(SUM(amount),0)  FROM transactions WHERE type='withdrawal' AND status='pending')                    AS pending_withdrawal_amount,
            (SELECT COUNT(*)                 FROM users        WHERE date(created_at)=date('now'))                              AS new_users_today,
            (SELECT COALESCE(SUM(amount),0)  FROM transactions WHERE type='deposit' AND status='success' AND date(created_at)=date('now')) AS deposits_today;

        CREATE VIEW IF NOT EXISTS v_pending_withdrawals AS
        SELECT
            t.id,
            t.created_at,
            t.amount,
            t.method,
            t.phone        AS withdraw_phone,
            u.name         AS user_name,
            u.email        AS user_email,
            u.phone        AS user_phone,
            u.wallet_country
        FROM transactions t
        JOIN users u ON t.user_id = u.id
        WHERE t.type = 'withdrawal' AND t.status = 'pending'
        ORDER BY t.created_at ASC;

        CREATE VIEW IF NOT EXISTS v_active_investments AS
        SELECT
            i.id,
            u.name         AS user_name,
            u.email        AS user_email,
            p.name         AS plan_name,
            i.amount,
            i.daily_gain,
            i.days_total,
            i.days_remaining,
            (i.days_total - i.days_remaining) AS days_elapsed,
            ROUND(CAST(i.days_total - i.days_remaining AS REAL) / i.days_total * 100, 1) AS progress_pct,
            i.started_at
        FROM investments i
        JOIN users     u ON i.user_id = u.id
        JOIN vip_plans p ON i.plan_id = p.id
        WHERE i.status = 'active'
        ORDER BY i.started_at DESC;
    ");

    // --------------------------------------------------------
    // DONNÉES PAR DÉFAUT
    // --------------------------------------------------------
    $planCount = $pdo->query("SELECT COUNT(*) FROM vip_plans")->fetchColumn();
    if ($planCount == 0) {
        $plans = [
            ['VIP 1',  3000,   315,    39375,   125],
            ['VIP 2',  7000,   770,    96250,   125],
            ['VIP 3',  15000,  1800,   225000,  125],
            ['VIP 4',  25000,  3125,   390625,  125],
            ['VIP 5',  45000,  5850,   731250,  125],
            ['VIP 6',  70000,  9450,   1181250, 125],
            ['VIP 7',  115000, 16100,  2012500, 125],
            ['VIP 8',  170000, 24650,  3081250, 125],
            ['VIP 9',  250000, 48750,  6093750, 125],
            ['VIP 10', 400000, 78000,  9750000, 125],
        ];
        $stmt = $pdo->prepare("INSERT INTO vip_plans (name, amount, daily_gain, total_gain, duration_days) VALUES (?,?,?,?,?)");
        foreach ($plans as $p) $stmt->execute($p);
    }

    $adminCount = $pdo->query("SELECT COUNT(*) FROM admin_users")->fetchColumn();
    if ($adminCount == 0) {
        $pdo->prepare("INSERT INTO admin_users (username, password) VALUES (?,?)")
            ->execute(['Ben10', password_hash('1214161820@Ben', PASSWORD_DEFAULT)]);
    }

    $settingsCount = $pdo->query("SELECT COUNT(*) FROM settings")->fetchColumn();
    if ($settingsCount == 0) {
        $defaults = [
            ['referral_level1',  '20'],
            ['referral_level2',  '5'],
            ['referral_level3',  '2'],
            ['min_withdrawal',   '1200'],
            ['max_withdrawal',   '5000000'],
            ['withdrawal_fee',   '15'],
            ['maintenance_mode', '0'],
        ];
        $stmt = $pdo->prepare("INSERT INTO settings (`key`, value) VALUES (?,?)");
        foreach ($defaults as $s) $stmt->execute($s);
    }
}

function getSetting(string $key, string $default = ''): string {
    $stmt = getDB()->prepare("SELECT value FROM settings WHERE `key` = ?");
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    return $row ? $row['value'] : $default;
}
