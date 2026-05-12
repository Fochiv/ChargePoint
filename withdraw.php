<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
startSession();
requireLogin();
$user = getCurrentUser();
$db = getDB();

$minWithdrawal = (float)getSetting('min_withdrawal', '1000');
$maxWithdrawal = (float)getSetting('max_withdrawal', '5000000');
$withdrawalFee = (float)getSetting('withdrawal_fee', '11');

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) { $error = 'Token invalide.'; }
    elseif (isset($_POST['update_wallet'])) {
        $wName = trim($_POST['wallet_name'] ?? '');
        $wCountry = trim($_POST['wallet_country'] ?? '');
        $wMethod = trim($_POST['wallet_method'] ?? '');
        $wPhone = trim($_POST['wallet_phone'] ?? '');
        if (!$wName || !$wCountry || !$wMethod || !$wPhone) { $error = 'Tous les champs du portefeuille sont requis.'; }
        else {
            $db->prepare("UPDATE users SET wallet_name=?, wallet_country=?, wallet_method=?, wallet_phone=?, wallet_updated_at=datetime('now') WHERE id=?")
               ->execute([$wName, $wCountry, $wMethod, $wPhone, $user['id']]);
            $success = 'Portefeuille mis à jour avec succès.';
            $user = getCurrentUser();
        }
    } elseif (isset($_POST['submit_withdrawal'])) {
        if (!$user['has_deposit']) {
            $error = 'Vous devez effectuer au moins un dépôt avant de pouvoir retirer.';
        } else {
            $amount = (float)($_POST['amount'] ?? 0);
            $method = trim($_POST['method'] ?? '');
            $phone = trim($_POST['withdraw_phone'] ?? '');
            if (!$amount || !$method || !$phone) { $error = 'Veuillez remplir tous les champs.'; }
            elseif ($amount < $minWithdrawal) { $error = "Montant minimum de retrait : " . formatAmount($minWithdrawal); }
            elseif ($amount > $maxWithdrawal) { $error = "Montant maximum de retrait : " . formatAmount($maxWithdrawal); }
            elseif ($amount > $user['balance']) { $error = 'Solde insuffisant.'; }
            else {
                $fee = round($amount * $withdrawalFee / 100);
                $netAmount = $amount - $fee;
                $db->prepare("UPDATE users SET balance = balance - ? WHERE id = ?")->execute([$amount, $user['id']]);
                $db->prepare("INSERT INTO transactions (user_id, type, description, amount, status, method, phone) VALUES (?, 'withdrawal', ?, ?, 'pending', ?, ?)")
                   ->execute([$user['id'], "Retrait via $method", $amount, $method, $phone]);
                addNotification($user['id'], "Votre demande de retrait de " . formatAmount($amount) . " est en cours de traitement.");
                $success = "Demande de retrait de " . formatAmount($amount) . " soumise avec succès. Elle sera traitée prochainement.";
                $user = getCurrentUser();
            }
        }
    }
}

$pendingWithdrawals = $db->prepare("SELECT * FROM transactions WHERE user_id=? AND type='withdrawal' AND status='pending' ORDER BY created_at DESC LIMIT 5");
$pendingWithdrawals->execute([$user['id']]);
$pendingList = $pendingWithdrawals->fetchAll();
$countries = getDefaultCountries();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Retrait de Fonds — ChargePoint</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<?php renderAppLayout($user, 'withdraw'); ?>

<div class="page-title">Retrait de Fonds</div>
<div class="page-subtitle">Retirez vos gains via Mobile Money.</div>

<?php if ($error): ?><div class="alert alert-danger alert-auto"><?= e($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success alert-auto"><?= e($success) ?></div><?php endif; ?>

<?php if (!$user['has_deposit']): ?>
<div class="alert alert-warning">
  <i class="fas fa-exclamation-triangle"></i> <strong>Dépôt requis :</strong> Vous devez effectuer au moins un dépôt pour pouvoir retirer des fonds. <a href="/deposit.php" style="color:var(--primary);font-weight:700;">Faire un dépôt →</a>
</div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;" class="withdraw-grid">

  <!-- WITHDRAW FORM -->
  <div class="card-custom">
    <div class="card-custom-header"><h5><i class="fas fa-arrow-up-from-bracket" style="color:var(--primary)"></i> Demande de Retrait</h5></div>
    <div class="card-custom-body">
      <!-- Wallet Balance Card -->
      <div class="wallet-card" style="margin-bottom:20px;">
        <div class="wallet-label">Solde disponible</div>
        <div class="wallet-balance"><?= formatAmount($user['balance']) ?></div>
        <div style="margin-top:8px;font-size:0.85rem;opacity:0.7;">Après frais (<?= $withdrawalFee ?>%) : net reçu</div>
      </div>

      <div style="background:var(--bg);border-radius:12px;padding:14px;margin-bottom:20px;font-size:0.85rem;">
        <div style="display:flex;justify-content:space-between;margin-bottom:6px;"><span>Retrait minimum</span><span style="font-weight:700;"><?= formatAmount($minWithdrawal) ?></span></div>
        <div style="display:flex;justify-content:space-between;margin-bottom:6px;"><span>Retrait maximum</span><span style="font-weight:700;"><?= formatAmount($maxWithdrawal) ?></span></div>
        <div style="display:flex;justify-content:space-between;margin-bottom:6px;"><span>Frais de retrait</span><span style="font-weight:700;color:var(--danger)"><?= $withdrawalFee ?>%</span></div>
        <div style="display:flex;justify-content:space-between;"><span>Délai de traitement</span><span style="font-weight:700;">00h – 15h</span></div>
      </div>

      <form method="POST" <?= !$user['has_deposit'] ? 'style="opacity:0.5;pointer-events:none"' : '' ?>>
        <?= csrf_field() ?>
        <div class="form-group">
          <label class="form-label">Montant à retirer (FCFA) *</label>
          <div class="input-with-icon">
            <i class="fas fa-coins"></i>
            <input type="number" name="amount" id="wd_amount" class="form-control" placeholder="Ex: 10000" min="<?= $minWithdrawal ?>" max="<?= min($maxWithdrawal, $user['balance']) ?>" required>
          </div>
          <div id="net_amount" style="font-size:0.8rem;color:var(--success);margin-top:4px;"></div>
        </div>
        <div class="form-group">
          <label class="form-label">Méthode de retrait *</label>
          <select name="method" id="wd_method" class="form-select" required>
            <option value="">Sélectionner la méthode</option>
            <?php
            $ops = [];
            foreach ($countries as $c) foreach ($c['operators'] as $op) $ops[] = $op;
            $ops = array_unique($ops);
            sort($ops);
            foreach ($ops as $op): ?>
            <option value="<?= e($op) ?>"><?= e($op) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Numéro de téléphone de réception *</label>
          <div class="input-with-icon">
            <i class="fas fa-phone"></i>
            <input type="text" name="withdraw_phone" class="form-control" placeholder="Numéro qui recevra les fonds"
              value="<?= e($user['wallet_phone'] ?? '') ?>" required>
          </div>
        </div>
        <button type="submit" name="submit_withdrawal" class="btn-auth" <?= !$user['has_deposit'] ? 'disabled' : '' ?>>
          <i class="fas fa-paper-plane"></i> Confirmer le retrait
        </button>
      </form>
    </div>
  </div>

  <!-- RIGHT COLUMN -->
  <div style="display:flex;flex-direction:column;gap:20px;">
    <!-- WALLET SECTION -->
    <div class="card-custom" id="wallet">
      <div class="card-custom-header"><h5><i class="fas fa-wallet" style="color:var(--primary)"></i> Mon Portefeuille</h5></div>
      <div class="card-custom-body">
        <?php if ($user['wallet_name']): ?>
        <div style="margin-bottom:16px;">
          <div class="info-row"><span class="info-label">Nom</span><span class="info-value"><?= e($user['wallet_name']) ?></span></div>
          <div class="info-row"><span class="info-label">Pays</span><span class="info-value"><?= e($user['wallet_country'] ?? '—') ?></span></div>
          <div class="info-row"><span class="info-label">Méthode</span><span class="info-value"><?= e($user['wallet_method'] ?? '—') ?></span></div>
          <div class="info-row"><span class="info-label">Numéro</span><span class="info-value"><?= e($user['wallet_phone'] ?? '—') ?></span></div>
          <div class="info-row" style="border:none"><span class="info-label">Dernière modif</span><span class="info-value"><?= $user['wallet_updated_at'] ? date('d/m/Y', strtotime($user['wallet_updated_at'])) : '—' ?></span></div>
        </div>
        <?php else: ?>
        <div class="alert alert-warning">Aucun portefeuille configuré.</div>
        <?php endif; ?>
        <form method="POST">
          <?= csrf_field() ?>
          <div class="form-group">
            <label class="form-label">Nom du portefeuille</label>
            <input type="text" name="wallet_name" class="form-control" placeholder="Ex: Mon Orange Money" value="<?= e($user['wallet_name'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Pays</label>
            <select name="wallet_country" class="form-select">
              <option value="">Sélectionner</option>
              <?php foreach ($countries as $c): ?>
              <option value="<?= e($c['name']) ?>" <?= ($user['wallet_country'] ?? '') === $c['name'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Méthode de paiement</label>
            <select name="wallet_method" class="form-select">
              <option value="">Sélectionner</option>
              <?php foreach ($ops as $op): ?>
              <option value="<?= e($op) ?>" <?= ($user['wallet_method'] ?? '') === $op ? 'selected' : '' ?>><?= e($op) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Numéro de téléphone</label>
            <input type="text" name="wallet_phone" class="form-control" placeholder="Votre numéro" value="<?= e($user['wallet_phone'] ?? '') ?>">
          </div>
          <button type="submit" name="update_wallet" class="btn-auth">Sauvegarder le portefeuille</button>
        </form>
      </div>
    </div>

    <!-- PENDING WITHDRAWALS -->
    <?php if (!empty($pendingList)): ?>
    <div class="card-custom">
      <div class="card-custom-header"><h5><i class="fas fa-clock" style="color:var(--warning)"></i> Retraits en attente</h5></div>
      <div class="card-custom-body" style="padding:0;">
        <?php foreach ($pendingList as $p): ?>
        <div style="padding:14px 20px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;">
          <div>
            <div style="font-weight:600;"><?= formatAmount($p['amount']) ?></div>
            <div style="font-size:0.8rem;color:var(--text-muted);"><?= e($p['method'] ?? '') ?> — <?= date('d/m/Y', strtotime($p['created_at'])) ?></div>
          </div>
          <?= getStatusBadge($p['status']) ?>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php renderBottomNav('withdraw'); ?>
<style>
@media(max-width:767px){.withdraw-grid{grid-template-columns:1fr!important;}}
</style>
<script>
document.getElementById('wd_amount')?.addEventListener('input', function() {
  const v = parseFloat(this.value);
  const fee = <?= $withdrawalFee ?>;
  const el = document.getElementById('net_amount');
  if (v && !isNaN(v)) {
    const net = v - Math.round(v * fee / 100);
    el.textContent = 'Montant net reçu : ' + net.toLocaleString('fr-FR') + ' FCFA (après ' + fee + '% de frais)';
  } else { el.textContent = ''; }
});
</script>
<script src="/assets/js/main.js"></script>
</body>
</html>
