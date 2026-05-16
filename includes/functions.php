<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';

function formatAmount(float $amount, string $currency = 'FCFA'): string {
    return number_format($amount, 0, ',', ' ') . ' ' . $currency;
}

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
}

function verify_csrf(): bool {
    return isset($_POST['csrf_token']) && hash_equals(csrf_token(), $_POST['csrf_token']);
}

function e(?string $str): string {
    return htmlspecialchars((string)($str ?? ''), ENT_QUOTES, 'UTF-8');
}

function flash(string $key, string $msg = null): ?string {
    if ($msg !== null) {
        $_SESSION['flash'][$key] = $msg;
        return null;
    }
    $val = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $val;
}

function redirect(string $url): void {
    header("Location: $url");
    exit;
}

function ashtechRequest(string $endpoint, array $data = [], string $method = 'GET'): array {
    $url = ASHTECH_BASE_URL . $endpoint;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . ASHTECH_API_KEY,
        'Content-Type: application/json',
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $decoded = json_decode($response, true) ?? [];
    $decoded['_http_code'] = $httpCode;
    return $decoded;
}

function initiatePayment(array $params): array {
    return ashtechRequest('/v1/collect', $params, 'POST');
}

function getTransactionStatus(string $transactionId): array {
    return ashtechRequest('/v1/transaction/' . $transactionId);
}

function getCountries(): array {
    static $cache = null;
    if ($cache !== null) return $cache;
    $cacheFile = sys_get_temp_dir() . '/ashtech_countries.json';
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 3600) {
        $cache = json_decode(file_get_contents($cacheFile), true) ?? [];
        return $cache;
    }
    $result = ashtechRequest('/v1/countries');
    if (is_array($result) && isset($result[0]['code'])) {
        $valid = array_values(array_filter($result, fn($c) => !empty($c['code']) && !empty($c['name'])));
        file_put_contents($cacheFile, json_encode($valid));
        $cache = $valid;
    } else {
        $cache = getDefaultCountries();
    }
    return $cache;
}

function getDefaultCountries(): array {
    return [
        ['code'=>'BJ','name'=>'Bénin','currency'=>'XOF','operators'=>['Moov Money','MTN Mobile Money']],
        ['code'=>'BF','name'=>'Burkina Faso','currency'=>'XOF','operators'=>['Moov Money','Orange Money']],
        ['code'=>'CM','name'=>'Cameroun','currency'=>'XAF','operators'=>['MTN Mobile Money','Orange Money']],
        ['code'=>'CF','name'=>'Centrafrique','currency'=>'XAF','operators'=>['Orange Money']],
        ['code'=>'CG','name'=>'Congo','currency'=>'XAF','operators'=>['Airtel Money','MTN Mobile Money']],
        ['code'=>'CI','name'=>"Côte d'Ivoire",'currency'=>'XOF','operators'=>['Moov Money','MTN Mobile Money','Orange Money','Wave']],
        ['code'=>'GA','name'=>'Gabon','currency'=>'XAF','operators'=>['Airtel Money','Moov Money']],
        ['code'=>'GN','name'=>'Guinée Conakry','currency'=>'GNF','operators'=>['MTN Mobile Money','Orange Money']],
        ['code'=>'GQ','name'=>'Guinée équatoriale','currency'=>'XAF','operators'=>['Orange Money']],
        ['code'=>'GW','name'=>'Guinée-Bissau','currency'=>'XOF','operators'=>['Orange Money']],
        ['code'=>'ML','name'=>'Mali','currency'=>'XOF','operators'=>['Moov Money','Orange Money']],
        ['code'=>'NE','name'=>'Niger','currency'=>'XOF','operators'=>['Airtel Money']],
        ['code'=>'CD','name'=>'RD Congo','currency'=>'CDF','operators'=>['Afrimoney','Airtel Money','Orange Money','Vodacom M-Pesa']],
        ['code'=>'SN','name'=>'Sénégal','currency'=>'XOF','operators'=>['Free Money','Orange Money','Wave']],
        ['code'=>'TD','name'=>'Tchad','currency'=>'XAF','operators'=>['Airtel Money','Moov Money']],
        ['code'=>'TG','name'=>'Togo','currency'=>'XOF','operators'=>['Flooz (Moov)','T-Money']],
    ];
}

function processReferralCommissions(int $userId, float $amount): void {
    $db = getDB();
    $stmt = $db->prepare("SELECT referred_by FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if (!$user || !$user['referred_by']) return;

    $levels = [
        1 => ['id' => $user['referred_by'], 'rate' => (float)getSetting('referral_level1', '20')],
    ];

    $stmt2 = $db->prepare("SELECT referred_by FROM users WHERE id = ?");
    $stmt2->execute([$levels[1]['id']]);
    $lvl1 = $stmt2->fetch();
    if ($lvl1 && $lvl1['referred_by']) {
        $levels[2] = ['id' => $lvl1['referred_by'], 'rate' => (float)getSetting('referral_level2', '5')];
        $stmt3 = $db->prepare("SELECT referred_by FROM users WHERE id = ?");
        $stmt3->execute([$lvl1['referred_by']]);
        $lvl2 = $stmt3->fetch();
        if ($lvl2 && $lvl2['referred_by']) {
            $levels[3] = ['id' => $lvl2['referred_by'], 'rate' => (float)getSetting('referral_level3', '2')];
        }
    }

    foreach ($levels as $level => $info) {
        $commission = round($amount * $info['rate'] / 100);
        if ($commission <= 0) continue;
        $db->prepare("UPDATE users SET balance = balance + ?, referral_earnings = referral_earnings + ?, total_earnings = total_earnings + ? WHERE id = ?")
           ->execute([$commission, $commission, $commission, $info['id']]);
        $db->prepare("INSERT INTO transactions (user_id, type, description, amount, status) VALUES (?, 'referral_commission', ?, ?, 'success')")
           ->execute([$info['id'], "Commission parrainage niveau {$level}", $commission]);
        addNotification($info['id'], "Vous avez reçu une commission de parrainage de " . formatAmount($commission) . " (Niveau {$level}).");
    }
}

function activateInvestmentPlan(int $userId, int $planId, float $amount): void {
    $db = getDB();
    $plan = $db->prepare("SELECT * FROM vip_plans WHERE id = ?");
    $plan->execute([$planId]);
    $plan = $plan->fetch();
    if (!$plan) return;
    $db->prepare("INSERT INTO investments (user_id, plan_id, amount, daily_gain, days_total, days_remaining, status) VALUES (?, ?, ?, ?, ?, ?, 'active')")
       ->execute([$userId, $planId, $amount, $plan['daily_gain'], $plan['duration_days'], $plan['duration_days']]);
    addNotification($userId, "Votre plan {$plan['name']} a été activé ! Gains journaliers : " . formatAmount($plan['daily_gain']));
}

function processUserDailyGains(int $userId): array {
    $db = getDB();

    $stmt = $db->prepare("SELECT i.*, p.name as plan_name FROM investments i JOIN vip_plans p ON i.plan_id=p.id WHERE i.user_id=? AND i.status='active' AND i.days_remaining>0");
    $stmt->execute([$userId]);
    $investments = $stmt->fetchAll();

    if (empty($investments)) {
        return ['processed' => 0, 'total' => 0, 'already_done' => false, 'no_vip' => true, 'next_available_at' => null];
    }

    $alreadyDone = false;
    $processed = 0;
    $totalGain = 0.0;
    $now = date('Y-m-d H:i:s');
    $nextAvailableAt = null;

    foreach ($investments as $inv) {
        $check = $db->prepare("SELECT id, created_at FROM transactions WHERE user_id=? AND type='daily_gain' AND created_at >= datetime('now','-24 hours') AND description LIKE ? ORDER BY created_at DESC LIMIT 1");
        $check->execute([$userId, '%' . $inv['plan_name'] . '%']);
        $lastGain = $check->fetch();
        if ($lastGain) {
            $alreadyDone = true;
            $nextTs = strtotime($lastGain['created_at']) + 86400;
            if ($nextAvailableAt === null || $nextTs > $nextAvailableAt) {
                $nextAvailableAt = $nextTs;
            }
            continue;
        }

        $db->prepare("UPDATE users SET balance=balance+?, total_earnings=total_earnings+? WHERE id=?")
           ->execute([$inv['daily_gain'], $inv['daily_gain'], $userId]);
        $db->prepare("INSERT INTO transactions (user_id, type, description, amount, status) VALUES (?, 'daily_gain', ?, ?, 'success')")
           ->execute([$userId, "Gain journalier " . $inv['plan_name'], $inv['daily_gain']]);

        $newRemaining = $inv['days_remaining'] - 1;
        if ($newRemaining <= 0) {
            $db->prepare("UPDATE investments SET days_remaining=0, status='completed', completed_at=? WHERE id=?")
               ->execute([$now, $inv['id']]);
            addNotification($userId, "Votre plan {$inv['plan_name']} est maintenant terminé. Merci d'avoir investi !");
        } else {
            $db->prepare("UPDATE investments SET days_remaining=? WHERE id=?")->execute([$newRemaining, $inv['id']]);
        }

        $totalGain += $inv['daily_gain'];
        $processed++;
    }

    if ($processed > 0 && $nextAvailableAt === null) {
        $nextAvailableAt = time() + 86400;
    }

    return ['processed' => $processed, 'total' => $totalGain, 'already_done' => ($alreadyDone && $processed === 0), 'no_vip' => false, 'next_available_at' => $nextAvailableAt];
}

function countUnreadNotifications(int $userId): int {
    $stmt = getDB()->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}

function getStatusBadge(string $status): string {
    $map = [
        'success' => ['bg-success', 'Réussi'],
        'completed' => ['bg-success', 'Réussi'],
        'pending' => ['bg-warning text-dark', 'En attente'],
        'failed' => ['bg-danger', 'Échoué'],
        'rejected' => ['bg-danger', 'Rejeté'],
        'expired' => ['bg-secondary', 'Expiré'],
        'processing' => ['bg-info', 'En cours'],
        'active' => ['bg-success', 'Actif'],
        'suspended' => ['bg-danger', 'Suspendu'],
    ];
    [$cls, $label] = $map[$status] ?? ['bg-secondary', ucfirst($status)];
    return "<span class=\"badge {$cls}\">{$label}</span>";
}

function getTypeLabel(string $type): string {
    $map = [
        'deposit' => 'Dépôt',
        'withdrawal' => 'Retrait',
        'daily_gain' => 'Gain journalier',
        'referral_commission' => 'Commission parrainage',
        'admin_deposit' => 'Dépôt administratif',
        'manual_deposit' => 'Dépôt manuel',
        'admin_adjustment' => 'Ajustement admin',
    ];
    return $map[$type] ?? ucfirst($type);
}
