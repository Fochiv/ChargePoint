<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');
startSession();

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId < 1) {
    http_response_code(401);
    echo json_encode(['status' => 'unauthorized']);
    exit;
}

$id = $_GET['id'] ?? '';
if (!$id) { echo json_encode(['status' => 'error', 'message' => 'ID manquant']); exit; }

$db = getDB();
$stmt = $db->prepare("SELECT * FROM transactions WHERE type='deposit' AND user_id=? AND (ashtech_transaction_id = ? OR id = ?)");
$stmt->execute([$userId, $id, (int)$id]);
$tx = $stmt->fetch();

if (!$tx) { echo json_encode(['status' => 'not_found']); exit; }

if (in_array($tx['status'], ['success', 'failed', 'cancelled', 'expired'], true)) {
    echo json_encode(['status' => $tx['status']]);
    exit;
}

if ($tx['ashtech_transaction_id']) {
    $result = getTransactionStatus($tx['ashtech_transaction_id']);
    $httpCode = $result['_http_code'] ?? 0;
    $apiStatus = strtolower((string)($result['status'] ?? 'pending'));
    if ($httpCode >= 200 && $httpCode < 300) {
        $status = finalizeAshtechDeposit($db, $tx, $apiStatus);
        echo json_encode(['status' => $status]);
        exit;
    }
}

echo json_encode(['status' => $tx['status']]);
