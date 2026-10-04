<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');
startSession();
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée.']);
    exit;
}
if (!verify_csrf()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Session expirée. Rechargez la page et réessayez.']);
    exit;
}

$localTxId = (int)($_POST['transaction_id'] ?? 0);
$otp = trim($_POST['otp'] ?? '');

if (!$localTxId || !$otp) { echo json_encode(['success' => false, 'message' => 'Données manquantes.']); exit; }

$db = getDB();
$tx = $db->prepare("SELECT * FROM transactions WHERE id=? AND user_id=?");
$tx->execute([$localTxId, $_SESSION['user_id']]);
$txData = $tx->fetch();
if (!$txData) { echo json_encode(['success' => false, 'message' => 'Transaction introuvable.']); exit; }
if ($txData['status'] !== 'pending' || $txData['ashtech_transaction_id']) {
    echo json_encode(['success' => false, 'message' => 'Cette demande OTP n’est plus en attente de validation.']);
    exit;
}

$reference = trim((string)($txData['reference'] ?? ''));
if ($reference === '') {
    echo json_encode(['success' => false, 'message' => 'La référence de validation AshTech Pay est manquante.']);
    exit;
}

$currency = '';
foreach (getCountries() as $country) {
    if (($country['code'] ?? '') === $txData['country_code']
        && in_array($txData['operator'], array_map('strval', $country['operators'] ?? []), true)) {
        $currency = (string)$country['currency'];
        break;
    }
}
if ($currency === '') {
    echo json_encode(['success' => false, 'message' => 'Le catalogue AshTech Pay est indisponible. Réessayez plus tard.']);
    exit;
}

$params = [
    'amount' => $txData['amount'],
    'currency' => $currency,
    'phone' => $txData['phone'],
    'operator' => $txData['operator'],
    'country_code' => $txData['country_code'],
    'otp' => $otp,
    'reference' => $reference,
    'notify_url' => SITE_URL . '/webhook.php',
];

$result = initiatePayment($params);
$httpCode = $result['_http_code'] ?? 0;
$requestError = $result['_error'] ?? '';
unset($result['_http_code']);
unset($result['_error']);

if ($httpCode === 202) {
    $newTxId = trim((string)($result['transaction_id'] ?? ''));
    $db->prepare("UPDATE transactions SET ashtech_transaction_id=?, status='pending', updated_at=? WHERE id=? AND user_id=? AND status='pending'")
       ->execute([$newTxId !== '' ? $newTxId : null, date('Y-m-d H:i:s'), $localTxId, $_SESSION['user_id']]);
    echo json_encode(['success' => true, 'transaction_id' => $newTxId ?: $localTxId, 'amount' => $txData['amount'], 'phone' => $txData['phone'], 'operator' => $txData['operator']]);
} elseif ($httpCode === 400 && ($result['error'] ?? '') === 'otp_required') {
    echo json_encode([
        'success' => true,
        'otp_required' => true,
        'transaction_id' => $localTxId,
        'ussd_code' => $result['ussd_code'] ?? null,
        'message' => $result['message'] ?? '',
    ]);
} elseif ($httpCode === 0 || $httpCode === 409 || $httpCode >= 500 || $requestError !== '') {
    echo json_encode([
        'success' => false,
        'uncertain' => true,
        'message' => 'La réponse du fournisseur est incertaine. Ne renvoyez pas le code OTP avant d’avoir vérifié le statut du paiement.',
    ]);
} else {
    echo json_encode(['success' => false, 'message' => $result['message'] ?? 'OTP invalide ou expiré. Réessayez.']);
}
