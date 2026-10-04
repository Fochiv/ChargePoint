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
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}
if (!verify_csrf()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Session expirée. Rechargez la page et réessayez.']);
    exit;
}

$user = getCurrentUser();
$db = getDB();

$amount = (float)($_POST['amount'] ?? 0);
$countryCode = trim($_POST['country_code'] ?? '');
$operator = trim($_POST['operator'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$planId = (int)($_POST['plan_id'] ?? 0);

if (!$amount || $amount < 1000) { echo json_encode(['success' => false, 'message' => 'Montant minimum : 1 000 FCFA']); exit; }
if (!$countryCode) { echo json_encode(['success' => false, 'message' => 'Veuillez sélectionner un pays']); exit; }
if (!$operator) { echo json_encode(['success' => false, 'message' => 'Veuillez sélectionner un opérateur']); exit; }
if (!$phone || !preg_match('/^[+\d\s().-]+$/u', $phone) || !preg_match('/\d{6,}/', $phone)) {
    echo json_encode(['success' => false, 'message' => 'Un numéro de téléphone valide est requis.']);
    exit;
}

$country = null;
foreach (getCountries() as $availableCountry) {
    if (($availableCountry['code'] ?? '') === $countryCode) {
        $country = $availableCountry;
        break;
    }
}
if (!$country) {
    http_response_code(503);
    echo json_encode(['success' => false, 'message' => 'Le catalogue AshTech Pay est indisponible. Réessayez plus tard.']);
    exit;
}

$availableOperators = array_map('strval', $country['operators'] ?? []);
if (!in_array($operator, $availableOperators, true)) {
    echo json_encode(['success' => false, 'message' => 'Cet opérateur n’est plus disponible pour le pays sélectionné.']);
    exit;
}
$currency = (string)$country['currency'];

$reference = 'CP-' . $user['id'] . '-' . bin2hex(random_bytes(12));
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
$requestError = $result['_error'] ?? '';
unset($result['_http_code']);
unset($result['_error']);

if ($httpCode === 202) {
    $txId = trim((string)($result['transaction_id'] ?? ''));
    $apiReference = trim((string)($result['reference'] ?? $reference));
    $db->prepare("UPDATE transactions SET ashtech_transaction_id=?, reference=? WHERE id=?")
       ->execute([$txId !== '' ? $txId : null, $apiReference, $localTxId]);
    $response = ['success' => true, 'transaction_id' => $txId ?: $localTxId, 'amount' => $amount, 'currency' => $currency, 'phone' => $phone, 'operator' => $operator];
    if (isset($result['flow']) && $result['flow'] === 'wave') {
        $response['flow'] = 'wave';
        $response['wave_url'] = $result['wave_url'] ?? '';
    }
    echo json_encode($response);
} elseif ($httpCode === 400 && ($result['error'] ?? '') === 'otp_required') {
    $apiReference = trim((string)($result['reference'] ?? ''));
    if ($apiReference === '') {
        $db->prepare("UPDATE transactions SET status='failed' WHERE id=?")->execute([$localTxId]);
        echo json_encode(['success' => false, 'message' => 'AshTech Pay n’a pas fourni la référence nécessaire pour valider le code OTP.']);
        exit;
    }
    $db->prepare("UPDATE transactions SET reference=? WHERE id=?")->execute([$apiReference, $localTxId]);
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
    $message = $result['message'] ?? '';
    if ($httpCode === 0 || $httpCode === 409 || $httpCode >= 500 || $requestError !== '') {
        echo json_encode([
            'success' => true,
            'transaction_id' => $localTxId,
            'amount' => $amount,
            'currency' => $currency,
            'phone' => $phone,
            'operator' => $operator,
            'message' => 'La réponse du fournisseur est incertaine. Ne relancez pas le paiement avant d’avoir vérifié son statut.',
        ]);
    } else {
        $db->prepare("UPDATE transactions SET status='failed', updated_at=? WHERE id=?")
           ->execute([date('Y-m-d H:i:s'), $localTxId]);
        echo json_encode(['success' => false, 'message' => $message ?: 'AshTech Pay a refusé cette demande. Vérifiez les informations ou les paramètres du compte marchand.']);
    }
}
