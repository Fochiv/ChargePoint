<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');
startSession();
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['success' => false]); exit; }

$localTxId = (int)($_POST['transaction_id'] ?? 0);
$otp = trim($_POST['otp'] ?? '');

if (!$localTxId || !$otp) { echo json_encode(['success' => false, 'message' => 'Données manquantes.']); exit; }

$db = getDB();
$tx = $db->prepare("SELECT * FROM transactions WHERE id=? AND user_id=?");
$tx->execute([$localTxId, $_SESSION['user_id']]);
$txData = $tx->fetch();
if (!$txData) { echo json_encode(['success' => false, 'message' => 'Transaction introuvable.']); exit; }

$reference = 'CP-' . $_SESSION['user_id'] . '-' . time() . '-otp';
$params = [
    'amount' => $txData['amount'],
    'currency' => 'XOF',
    'phone' => $txData['phone'],
    'operator' => $txData['operator'],
    'country_code' => $txData['country_code'],
    'otp' => $otp,
    'reference' => $reference,
    'notify_url' => SITE_URL . '/webhook.php',
];

$result = initiatePayment($params);
$httpCode = $result['_http_code'] ?? 0;
unset($result['_http_code']);

if ($httpCode === 202) {
    $newTxId = $result['transaction_id'] ?? '';
    $db->prepare("UPDATE transactions SET ashtech_transaction_id=?, status='pending', reference=? WHERE id=?")->execute([$newTxId, $reference, $localTxId]);
    echo json_encode(['success' => true, 'transaction_id' => $newTxId ?: $localTxId, 'amount' => $txData['amount'], 'phone' => $txData['phone'], 'operator' => $txData['operator']]);
} else {
    echo json_encode(['success' => false, 'message' => $result['message'] ?? 'OTP invalide ou expiré. Réessayez.']);
}
