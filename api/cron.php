<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

$secretKey = 'cp_cron_secret_2026';
$providedKey = $_GET['key'] ?? '';
if ($providedKey !== $secretKey && php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Accès refusé');
}

$db = getDB();
$now = date('Y-m-d H:i:s');

$investments = $db->query("SELECT i.*, u.id as uid FROM investments i JOIN users u ON i.user_id=u.id WHERE i.status='active' AND i.days_remaining > 0")->fetchAll();

$processed = 0;
foreach ($investments as $inv) {
    $db->prepare("UPDATE users SET balance=balance+?, total_earnings=total_earnings+? WHERE id=?")
       ->execute([$inv['daily_gain'], $inv['daily_gain'], $inv['user_id']]);
    $db->prepare("INSERT INTO transactions (user_id, type, description, amount, status) VALUES (?, 'daily_gain', ?, ?, 'success')")
       ->execute([$inv['user_id'], "Gain journalier " . $inv['plan_id'], $inv['daily_gain']]);
    $newRemaining = $inv['days_remaining'] - 1;
    if ($newRemaining <= 0) {
        $db->prepare("UPDATE investments SET days_remaining=0, status='completed', completed_at=? WHERE id=?")->execute([date('Y-m-d H:i:s'), $inv['id']]);
        addNotification($inv['user_id'], "Votre plan d'investissement a atteint sa durée maximale et est maintenant complété !");
    } else {
        $db->prepare("UPDATE investments SET days_remaining=? WHERE id=?")->execute([$newRemaining, $inv['id']]);
    }
    $processed++;
}

echo "OK - $processed investissements traités le $now\n";
