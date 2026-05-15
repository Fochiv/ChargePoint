<?php
require_once __DIR__ . '/config.php';

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO('sqlite:' . DB_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA journal_mode=WAL');
        $pdo->exec('PRAGMA foreign_keys=ON');
        initDB($pdo);
    }
    return $pdo;
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
        $stmt = $pdo->prepare("INSERT INTO settings (key, value) VALUES (?,?)");
        foreach ($defaults as $s) $stmt->execute($s);
    }
}

function getSetting(string $key, string $default = ''): string {
    $stmt = getDB()->prepare("SELECT value FROM settings WHERE key = ?");
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    return $row ? $row['value'] : $default;
}
