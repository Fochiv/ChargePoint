<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');
startSession();
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

$user = getCurrentUser();
$db = getDB();

$amount = (float)($_POST['amount'] ?? 0);
$countryCode = trim($_POST['country_code'] ?? '');
$operator = trim($_POST['operator'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$currency = trim($_POST['currency'] ?? 'XOF');
$planId = (int)($_POST['plan_id'] ?? 0);

if (!$amount || $amount < 1000) { echo json_encode(['success' => false, 'message' => 'Montant minimum : 1 000 FCFA']); exit; }
if (!$countryCode) { echo json_encode(['success' => false, 'message' => 'Veuillez sélectionner un pays']); exit; }
if (!$operator) { echo json_encode(['success' => false, 'message' => 'Veuillez sélectionner un opérateur']); exit; }
$isWave = stripos($operator, 'wave') !== false;
if (!$isWave && !$phone) { echo json_encode(['success' => false, 'message' => 'Numéro de téléphone requis']); exit; }

$reference = 'CP-' . $user['id'] . '-' . time();
$notifyUrl = SITE_URL . '/webhook.php';

// Insert pending transaction
$db->prepare("INSERT INTO transactions (user_id, type, description, amount, status, reference, method, phone, country_code, operator, plan_id) VALUES (?,?,?,?,'pending',?,?,?,?,?,?)")
   ->execute([$user['id'], 'deposit', "Dépôt via $operator", $amount, $reference, $operator, $phone, $countryCode, $operator, $planId ?: null]);
$localTxId = $db->lastInsertId();

$params = [
    'amount' => $amount,
    'currency' => $currency,
    'phone' => $phone ?: '0000000000',
    'operator' => $operator,
    'country_code' => $countryCode,
    'reference' => $reference,
    'notify_url' => $notifyUrl,
];

$result = initiatePayment($params);
$httpCode = $result['_http_code'] ?? 0;
unset($result['_http_code']);

if ($httpCode === 202) {
    $txId = $result['transaction_id'] ?? '';
    $db->prepare("UPDATE transactions SET ashtech_transaction_id=? WHERE id=?")->execute([$txId, $localTxId]);
    $response = ['success' => true, 'transaction_id' => $txId ?: $localTxId, 'amount' => $amount, 'currency' => $currency, 'phone' => $phone, 'operator' => $operator];
    if (isset($result['flow']) && $result['flow'] === 'wave') {
        $response['flow'] = 'wave';
        $response['wave_url'] = $result['wave_url'] ?? '';
    }
    echo json_encode($response);
} elseif ($httpCode === 400 && ($result['error'] ?? '') === 'otp_required') {
    echo json_encode([
        'success' => true,
        'otp_required' => true,
        'transaction_id' => $localTxId,
        'ussd_code' => $result['ussd_code'] ?? null,
        'message' => $result['message'] ?? '',
        'amount' => $amount,
        'currency' => $currency,
        'phone' => $phone,
        'operator' => $operator,
        'country_code' => $countryCode,
    ]);
} else {
    $db->prepare("UPDATE transactions SET status='failed' WHERE id=?")->execute([$localTxId]);
    echo json_encode(['success' => false, 'message' => $result['message'] ?? 'Erreur lors de l\'initiation du paiement. Vérifiez vos informations.']);
}
