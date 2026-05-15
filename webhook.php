<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

header('Content-Type: application/json');
http_response_code(200);
echo json_encode(['received' => true]);

$payload = file_get_contents('php://input');
$data = json_decode($payload, true);
if (!$data) exit;

$event = $data['event'] ?? '';
$transactionId = $data['transaction_id'] ?? '';
$reference = $data['reference'] ?? '';
$status = $data['status'] ?? '';
$amount = (float)($data['amount'] ?? 0);

if (!$transactionId && !$reference) exit;

$db = getDB();

$stmt = $db->prepare("SELECT * FROM transactions WHERE ashtech_transaction_id = ? OR reference = ?");
$stmt->execute([$transactionId, $reference]);
$tx = $stmt->fetch();
if (!$tx) exit;

if ($tx['status'] !== 'pending') exit;

if ($event === 'payment.completed' || $status === 'completed') {
    $db->prepare("UPDATE transactions SET status='success', updated_at=? WHERE id=?")->execute([date('Y-m-d H:i:s'), $tx['id']]);
    $db->prepare("UPDATE users SET balance=balance+?, has_deposit=1 WHERE id=?")->execute([$tx['amount'], $tx['user_id']]);
    if ($tx['plan_id']) {
        activateInvestmentPlan($tx['user_id'], $tx['plan_id'], $tx['amount']);
    }
    processReferralCommissions($tx['user_id'], $tx['amount']);
    addNotification($tx['user_id'], "Dépôt de " . formatAmount($tx['amount']) . " validé avec succès !");
} elseif ($event === 'payment.failed' || $status === 'failed') {
    $db->prepare("UPDATE transactions SET status='failed', updated_at=? WHERE id=?")->execute([date('Y-m-d H:i:s'), $tx['id']]);
    $db->prepare("UPDATE users SET balance=balance+? WHERE id=?")->execute([$tx['amount'], $tx['user_id']]);
    addNotification($tx['user_id'], "Votre paiement de " . formatAmount($tx['amount']) . " a échoué.");
}
exit;
