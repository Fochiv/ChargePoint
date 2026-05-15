-- ============================================================
-- ChargePoint — Schema SQLite complet
-- Compatible avec : DB Browser for SQLite, DBeaver, sqlite3 CLI
-- Usage : sqlite3 database.sqlite < schema.sql
-- ============================================================

PRAGMA journal_mode = WAL;
PRAGMA foreign_keys = ON;

-- ============================================================
-- TABLE : users
-- ============================================================
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
    has_deposit           INTEGER DEFAULT 0,   -- 0 = jamais déposé, 1 = a déposé
    wallet_name           TEXT    DEFAULT NULL,
    wallet_country        TEXT    DEFAULT NULL,
    wallet_method         TEXT    DEFAULT NULL,
    wallet_phone          TEXT    DEFAULT NULL,
    wallet_updated_at     TEXT    DEFAULT NULL,
    status                TEXT    DEFAULT 'active', -- 'active' | 'suspended'
    failed_login_attempts INTEGER DEFAULT 0,
    locked_until          TEXT    DEFAULT NULL,
    last_login            TEXT    DEFAULT NULL,
    created_at            TEXT    DEFAULT (datetime('now')),
    FOREIGN KEY (referred_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_users_email        ON users(email);
CREATE INDEX IF NOT EXISTS idx_users_phone        ON users(phone);
CREATE INDEX IF NOT EXISTS idx_users_referral_code ON users(referral_code);
CREATE INDEX IF NOT EXISTS idx_users_referred_by  ON users(referred_by);
CREATE INDEX IF NOT EXISTS idx_users_status       ON users(status);

-- ============================================================
-- TABLE : vip_plans
-- ============================================================
CREATE TABLE IF NOT EXISTS vip_plans (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    name          TEXT    NOT NULL,
    amount        REAL    NOT NULL,  -- montant a investir (FCFA)
    daily_gain    REAL    NOT NULL,  -- gain journalier (FCFA)
    total_gain    REAL    NOT NULL,  -- gain total sur la duree (FCFA)
    duration_days INTEGER NOT NULL DEFAULT 125,
    is_active     INTEGER DEFAULT 1  -- 1 = actif, 0 = inactif
);

-- ============================================================
-- TABLE : investments
-- ============================================================
CREATE TABLE IF NOT EXISTS investments (
    id             INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id        INTEGER NOT NULL,
    plan_id        INTEGER NOT NULL,
    amount         REAL    NOT NULL,
    daily_gain     REAL    NOT NULL,
    days_total     INTEGER NOT NULL,
    days_remaining INTEGER NOT NULL,
    status         TEXT    DEFAULT 'active',    -- 'active' | 'completed'
    started_at     TEXT    DEFAULT (datetime('now')),
    completed_at   TEXT    DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id)    ON DELETE CASCADE,
    FOREIGN KEY (plan_id) REFERENCES vip_plans(id)
);

CREATE INDEX IF NOT EXISTS idx_investments_user_id ON investments(user_id);
CREATE INDEX IF NOT EXISTS idx_investments_status  ON investments(status);

-- ============================================================
-- TABLE : transactions
-- ============================================================
CREATE TABLE IF NOT EXISTS transactions (
    id                    INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id               INTEGER NOT NULL,
    type                  TEXT    NOT NULL,
    -- type possibles :
    --   'deposit'              dépôt via Mobile Money
    --   'withdrawal'           demande de retrait
    --   'daily_gain'           gain journalier du cron
    --   'referral_commission'  commission de parrainage
    --   'admin_deposit'        dépôt admin (retirable)
    --   'manual_deposit'       dépôt manuel (VIP uniquement)
    --   'admin_adjustment'     ajustement manuel de solde
    description           TEXT    DEFAULT NULL,
    amount                REAL    NOT NULL,
    status                TEXT    DEFAULT 'pending',
    -- status possibles :
    --   'pending'   en attente
    --   'success'   validé
    --   'failed'    échoué
    --   'rejected'  rejeté par admin
    --   'expired'   délai expiré
    reference             TEXT    DEFAULT NULL,  -- référence interne CP-{userId}-{timestamp}
    ashtech_transaction_id TEXT   DEFAULT NULL,  -- ID retourné par Ashtechpay
    method                TEXT    DEFAULT NULL,  -- ex: 'Orange Money', 'MTN Mobile Money'
    phone                 TEXT    DEFAULT NULL,  -- numéro utilisé pour le paiement
    country_code          TEXT    DEFAULT NULL,  -- ex: 'CM', 'CI', 'SN'
    operator              TEXT    DEFAULT NULL,  -- opérateur Mobile Money
    plan_id               INTEGER DEFAULT NULL,  -- plan VIP cible si dépôt pour activation
    reject_reason         TEXT    DEFAULT NULL,  -- motif de rejet (retraits)
    is_admin_deposit      INTEGER DEFAULT 0,     -- 1 = dépôt effectué par admin
    created_at            TEXT    DEFAULT (datetime('now')),
    updated_at            TEXT    DEFAULT (datetime('now')),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_tx_user_id   ON transactions(user_id);
CREATE INDEX IF NOT EXISTS idx_tx_type      ON transactions(type);
CREATE INDEX IF NOT EXISTS idx_tx_status    ON transactions(status);
CREATE INDEX IF NOT EXISTS idx_tx_reference ON transactions(reference);
CREATE INDEX IF NOT EXISTS idx_tx_ashtech   ON transactions(ashtech_transaction_id);
CREATE INDEX IF NOT EXISTS idx_tx_created   ON transactions(created_at);

-- ============================================================
-- TABLE : notifications
-- ============================================================
CREATE TABLE IF NOT EXISTS notifications (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id    INTEGER NOT NULL,
    message    TEXT    NOT NULL,
    is_read    INTEGER DEFAULT 0,  -- 0 = non lue, 1 = lue
    created_at TEXT    DEFAULT (datetime('now')),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_notif_user_id ON notifications(user_id);
CREATE INDEX IF NOT EXISTS idx_notif_is_read ON notifications(is_read);

-- ============================================================
-- TABLE : settings
-- ============================================================
CREATE TABLE IF NOT EXISTS settings (
    key   TEXT PRIMARY KEY,
    value TEXT NOT NULL
);

-- ============================================================
-- TABLE : admin_users
-- ============================================================
CREATE TABLE IF NOT EXISTS admin_users (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    username   TEXT UNIQUE NOT NULL,
    password   TEXT        NOT NULL,  -- bcrypt hash
    created_at TEXT        DEFAULT (datetime('now'))
);

-- ============================================================
-- DONNÉES INITIALES : Plans VIP
-- ============================================================
INSERT OR IGNORE INTO vip_plans (id, name, amount, daily_gain, total_gain, duration_days, is_active) VALUES
    (1,  'VIP 1',  3000,   315,    39375,    125, 1),
    (2,  'VIP 2',  7000,   770,    96250,    125, 1),
    (3,  'VIP 3',  15000,  1800,   225000,   125, 1),
    (4,  'VIP 4',  25000,  3125,   390625,   125, 1),
    (5,  'VIP 5',  45000,  5850,   731250,   125, 1),
    (6,  'VIP 6',  70000,  9450,   1181250,  125, 1),
    (7,  'VIP 7',  115000, 16100,  2012500,  125, 1),
    (8,  'VIP 8',  170000, 24650,  3081250,  125, 1),
    (9,  'VIP 9',  250000, 48750,  6093750,  125, 1),
    (10, 'VIP 10', 400000, 78000,  9750000,  125, 1);

-- ============================================================
-- DONNÉES INITIALES : Paramètres plateforme
-- ============================================================
INSERT OR IGNORE INTO settings (key, value) VALUES
    ('referral_level1',  '20'),
    ('referral_level2',  '5'),
    ('referral_level3',  '2'),
    ('min_withdrawal',   '1200'),
    ('max_withdrawal',   '5000000'),
    ('withdrawal_fee',   '15'),
    ('maintenance_mode', '0');

-- ============================================================
-- DONNÉES INITIALES : Administrateur
-- Identifiants : Ben10 / 1214161820@Ben
-- Hash bcrypt généré avec password_hash('1214161820@Ben', PASSWORD_DEFAULT)
-- ============================================================
INSERT OR IGNORE INTO admin_users (username, password) VALUES
    ('Ben10', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');
-- Note : ce hash est un placeholder. Le vrai hash est généré au premier demarrage
-- par PHP dans includes/db.php. Pour reinitialiser le mot de passe depuis SQL,
-- utilisez un hash bcrypt valide genere avec PHP :
--   php -r "echo password_hash('1214161820@Ben', PASSWORD_DEFAULT);"

-- ============================================================
-- VUES UTILES (pour consultation dans DB Browser, etc.)
-- ============================================================

-- Vue : résumé par utilisateur
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
    (SELECT COUNT(*) FROM investments  WHERE user_id = u.id AND status = 'active')    AS active_plans,
    (SELECT COUNT(*) FROM users        WHERE referred_by = u.id)                       AS direct_referrals,
    (SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE user_id = u.id AND type = 'deposit'    AND status = 'success') AS total_deposited,
    (SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE user_id = u.id AND type = 'withdrawal' AND status = 'success') AS total_withdrawn
FROM users u;

-- Vue : statistiques globales plateforme
CREATE VIEW IF NOT EXISTS v_platform_stats AS
SELECT
    (SELECT COUNT(*)                                          FROM users)                                                                       AS total_users,
    (SELECT COUNT(*)                                          FROM users        WHERE has_deposit = 1)                                           AS users_with_deposit,
    (SELECT COUNT(*)                                          FROM investments  WHERE status = 'active')                                         AS active_investments,
    (SELECT COALESCE(SUM(amount), 0)                          FROM transactions WHERE type = 'deposit'    AND status = 'success')                AS total_deposited,
    (SELECT COALESCE(SUM(amount), 0)                          FROM transactions WHERE type = 'withdrawal' AND status = 'success')                AS total_withdrawn,
    (SELECT COALESCE(SUM(amount), 0)                          FROM transactions WHERE type = 'daily_gain')                                       AS total_gains_distributed,
    (SELECT COUNT(*)                                          FROM transactions WHERE type = 'withdrawal' AND status = 'pending')                AS pending_withdrawals,
    (SELECT COALESCE(SUM(amount), 0)                          FROM transactions WHERE type = 'withdrawal' AND status = 'pending')                AS pending_withdrawal_amount,
    (SELECT COUNT(*)                                          FROM users        WHERE date(created_at) = date('now'))                            AS new_users_today,
    (SELECT COALESCE(SUM(amount), 0)                          FROM transactions WHERE type = 'deposit'   AND status = 'success' AND date(created_at) = date('now')) AS deposits_today;

-- Vue : retraits en attente avec infos utilisateur
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
WHERE t.type = 'withdrawal'
  AND t.status = 'pending'
ORDER BY t.created_at ASC;

-- Vue : investissements actifs avec progression
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
    ROUND((CAST(i.days_total - i.days_remaining AS REAL) / i.days_total) * 100, 1) AS progress_pct,
    i.started_at
FROM investments i
JOIN users     u ON i.user_id  = u.id
JOIN vip_plans p ON i.plan_id  = p.id
WHERE i.status = 'active'
ORDER BY i.started_at DESC;

-- ============================================================
-- FIN DU SCHEMA
-- ============================================================
