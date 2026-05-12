<?php
require_once __DIR__ . '/db.php';

function startSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params(['lifetime' => SESSION_LIFETIME, 'httponly' => true, 'samesite' => 'Lax']);
        session_start();
    }
}

function isLoggedIn(): bool {
    startSession();
    return isset($_SESSION['user_id']);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: /login.php');
        exit;
    }
}

function getCurrentUser(): ?array {
    if (!isLoggedIn()) return null;
    $stmt = getDB()->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch() ?: null;
}

function isAdminLoggedIn(): bool {
    startSession();
    return isset($_SESSION['admin_id']);
}

function requireAdmin(): void {
    if (!isAdminLoggedIn()) {
        header('Location: /admin/login.php');
        exit;
    }
}

function generateReferralCode(): string {
    do {
        $code = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));
        $exists = getDB()->prepare("SELECT id FROM users WHERE referral_code = ?");
        $exists->execute([$code]);
    } while ($exists->fetch());
    return $code;
}

function loginUser(string $identifier, string $password): array {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE email = ? OR phone = ?");
    $stmt->execute([$identifier, $identifier]);
    $user = $stmt->fetch();

    if (!$user) return ['success' => false, 'message' => 'Identifiants incorrects.'];
    if ($user['status'] === 'suspended') return ['success' => false, 'message' => 'Compte suspendu. Contactez le support.'];

    if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
        $remaining = ceil((strtotime($user['locked_until']) - time()) / 60);
        return ['success' => false, 'message' => "Compte temporairement bloqué. Réessayez dans {$remaining} minutes."];
    }

    if (!password_verify($password, $user['password'])) {
        $attempts = $user['failed_login_attempts'] + 1;
        if ($attempts >= MAX_LOGIN_ATTEMPTS) {
            $lockedUntil = date('Y-m-d H:i:s', time() + LOCK_DURATION);
            $db->prepare("UPDATE users SET failed_login_attempts = ?, locked_until = ? WHERE id = ?")
               ->execute([$attempts, $lockedUntil, $user['id']]);
            return ['success' => false, 'message' => 'Trop de tentatives. Compte bloqué 15 minutes.'];
        }
        $db->prepare("UPDATE users SET failed_login_attempts = ? WHERE id = ?")
           ->execute([$attempts, $user['id']]);
        return ['success' => false, 'message' => 'Identifiants incorrects.'];
    }

    $db->prepare("UPDATE users SET failed_login_attempts = 0, locked_until = NULL, last_login = datetime('now') WHERE id = ?")
       ->execute([$user['id']]);

    startSession();
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    return ['success' => true];
}

function addNotification(int $userId, string $message): void {
    getDB()->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)")
           ->execute([$userId, $message]);
}
