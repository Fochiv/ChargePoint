<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

header('Content-Type: application/json');

function ashtechWebhookResponse(int $statusCode, array $body): never {
    http_response_code($statusCode);
    echo json_encode($body);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    ashtechWebhookResponse(405, ['received' => false]);
}

$rawBody = file_get_contents('php://input');
$timestamp = $_SERVER['HTTP_X_ASHTECH_TIMESTAMP'] ?? '';
$signature = $_SERVER['HTTP_X_ASHTECH_SIGNATURE'] ?? '';

if (ASHTECH_WEBHOOK_SECRET === '') {
    ashtechWebhookResponse(503, ['received' => false, 'error' => 'webhook_not_configured']);
}
if (!verifyAshtechWebhookSignature($rawBody, $timestamp, $signature)) {
    ashtechWebhookResponse(401, ['received' => false, 'error' => 'invalid_signature']);
}

$data = json_decode($rawBody, true);
if (!is_array($data)) {
    ashtechWebhookResponse(400, ['received' => false, 'error' => 'invalid_payload']);
}

$event = strtolower((string)($data['event'] ?? ''));
$transactionId = trim((string)($data['transaction_id'] ?? ''));
$reference = trim((string)($data['reference'] ?? ''));
$deliveryId = trim((string)($_SERVER['HTTP_X_ASHTECH_EVENT_ID'] ?? ''));
$reportedStatus = strtolower((string)($data['status'] ?? ''));

if ($transactionId === '' && $reference === '') {
    ashtechWebhookResponse(400, ['received' => false, 'error' => 'missing_transaction_reference']);
}

$db = getDB();
$stmt = $db->prepare(
    "SELECT * FROM transactions
     WHERE type='deposit'
       AND ((? <> '' AND ashtech_transaction_id = ?) OR (? <> '' AND reference = ?))
     LIMIT 1"
);
$stmt->execute([$transactionId, $transactionId, $reference, $reference]);
$transaction = $stmt->fetch();
if (!$transaction) {
    ashtechWebhookResponse(404, ['received' => false, 'error' => 'transaction_not_found']);
}

$providerTransactionId = $transactionId !== ''
    ? $transactionId
    : trim((string)($transaction['ashtech_transaction_id'] ?? ''));
if ($providerTransactionId === '') {
    ashtechWebhookResponse(503, ['received' => false, 'error' => 'transaction_not_verifiable']);
}

// A signed notification is a prompt to re-check the status, not proof of payment.
$verification = getTransactionStatus($providerTransactionId);
$verificationCode = (int)($verification['_http_code'] ?? 0);
$verifiedStatus = strtolower((string)($verification['status'] ?? ''));
if ($verificationCode < 200 || $verificationCode >= 300) {
    ashtechWebhookResponse(503, ['received' => false, 'error' => 'status_verification_unavailable']);
}
if (!in_array($verifiedStatus, ['success', 'completed', 'failed', 'cancelled', 'canceled', 'expired'], true)) {
    ashtechWebhookResponse(503, ['received' => false, 'error' => 'transaction_not_final']);
}

$eventName = $event !== '' ? $event : $reportedStatus;
$eventKey = $deliveryId !== ''
    ? 'delivery:' . hash('sha256', $deliveryId)
    : hash('sha256', $providerTransactionId . '|' . $eventName . '|' . $verifiedStatus);

try {
    $db->beginTransaction();
    $insertEvent = $db->prepare("INSERT OR IGNORE INTO ashtech_webhook_events (event_key) VALUES (?)");
    $insertEvent->execute([$eventKey]);

    if ($insertEvent->rowCount() === 0) {
        $db->commit();
        ashtechWebhookResponse(200, ['received' => true, 'duplicate' => true]);
    }

    $currentStmt = $db->prepare("SELECT * FROM transactions WHERE id=? AND type='deposit'");
    $currentStmt->execute([$transaction['id']]);
    $currentTransaction = $currentStmt->fetch();
    if (!$currentTransaction) {
        $db->rollBack();
        ashtechWebhookResponse(404, ['received' => false, 'error' => 'transaction_not_found']);
    }

    if (empty($currentTransaction['ashtech_transaction_id'])) {
        $db->prepare("UPDATE transactions SET ashtech_transaction_id=? WHERE id=?")
           ->execute([$providerTransactionId, $currentTransaction['id']]);
        $currentTransaction['ashtech_transaction_id'] = $providerTransactionId;
    }

    finalizeAshtechDeposit($db, $currentTransaction, $verifiedStatus);
    $db->commit();
    ashtechWebhookResponse(200, ['received' => true]);
} catch (Throwable $error) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    ashtechWebhookResponse(503, ['received' => false, 'error' => 'processing_failed']);
}