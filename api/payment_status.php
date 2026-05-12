<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');
startSession();

$id = $_GET['id'] ?? '';
if (!$id) { echo json_encode(['status' => 'error', 'message' => 'ID manquant']); exit; }

$db = getDB();
$stmt = $db->prepare("SELECT * FROM transactions WHERE ashtech_transaction_id = ? OR id = ?");
$stmt->execute([$id, (int)$id]);
$tx = $stmt->fetch();

if (!$tx) { echo json_encode(['status' => 'not_found']); exit; }

if (in_array($tx['status'], ['success', 'failed', 'expired'])) {
    echo json_encode(['status' => $tx['status']]);
    exit;
}

// Poll Ashtech API
if ($tx['ashtech_transaction_id']) {
    $result = getTransactionStatus($tx['ashtech_transaction_id']);
    $apiStatus = $result['status'] ?? 'pending';
    
    if ($apiStatus === 'success') {
        if ($tx['status'] !== 'success') {
            $db->prepare("UPDATE transactions SET status='success', updated_at=datetime('now') WHERE id=?")->execute([$tx['id']]);
            $db->prepare("UPDATE users SET balance=balance+?, has_deposit=1 WHERE id=?")->execute([$tx['amount'], $tx['user_id']]);
            if ($tx['plan_id']) activateInvestmentPlan($tx['user_id'], $tx['plan_id'], $tx['amount']);
            processReferralCommissions($tx['user_id'], $tx['amount']);
            addNotification($tx['user_id'], "Dépôt de " . formatAmount($tx['amount']) . " validé !");
        }
        echo json_encode(['status' => 'success']);
    } elseif ($apiStatus === 'failed') {
        $db->prepare("UPDATE transactions SET status='failed', updated_at=datetime('now') WHERE id=?")->execute([$tx['id']]);
        echo json_encode(['status' => 'failed']);
    } else {
        echo json_encode(['status' => 'pending']);
    }
} else {
    echo json_encode(['status' => $tx['status']]);
}
