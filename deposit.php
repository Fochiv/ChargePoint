<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_once 'includes/layout.php';
startSession();
requireLogin();
$user = getCurrentUser();
$db = getDB();

$preFillAmount = (int)($_GET['amount'] ?? 0);
$preFillPlanId = (int)($_GET['plan'] ?? 0);
$countries = getCountries();
if (empty($countries)) $countries = getDefaultCountries();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dépôt de Fonds — ChargePoint</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<?php renderAppLayout($user, 'deposit'); ?>

<div class="page-title">Dépôt de Fonds</div>
<div class="page-subtitle">Alimentez votre compte via Mobile Money pour investir.</div>

<div style="max-width:580px;">
  <div class="card-custom deposit-form">
    <div class="card-custom-header"><h5><i class="fas fa-arrow-down-to-line" style="color:var(--primary)"></i> Initier un dépôt</h5></div>
    <div class="card-custom-body">
      <form id="deposit_form" onsubmit="submitDepositForm(event)">
        <?= csrf_field() ?>
        <?php if ($preFillPlanId): ?>
        <input type="hidden" name="plan_id" value="<?= $preFillPlanId ?>">
        <?php
        $pStmt = $db->prepare("SELECT name FROM vip_plans WHERE id=?");
        $pStmt->execute([$preFillPlanId]);
        $planName = $pStmt->fetchColumn() ?: 'VIP';
        ?>
        <div class="alert alert-info" style="margin-bottom:16px;">
          <i class="fas fa-crown"></i> Vous investissez dans le plan <strong><?= e($planName) ?></strong>
        </div>
        <?php endif; ?>

        <div class="form-group">
          <label class="form-label">Montant à déposer (FCFA) *</label>
          <div class="input-with-icon">
            <i class="fas fa-coins"></i>
            <input type="number" name="amount" id="deposit_amount" class="form-control" placeholder="Ex: 5000" min="1000" required value="<?= $preFillAmount ?: '' ?>">
          </div>
          <div style="font-size:0.8rem;color:var(--text-muted);margin-top:4px;">Montant minimum : 1 000 FCFA</div>
        </div>

        <div class="form-group">
          <label class="form-label">Pays *</label>
          <select name="country_code" id="country_code" class="form-select" required onchange="updateOperators(this)">
            <option value="">Sélectionner votre pays</option>
            <?php foreach ($countries as $c): ?>
            <option value="<?= e($c['code']) ?>"
              data-operators='<?= htmlspecialchars(json_encode($c['operators']), ENT_QUOTES) ?>'
              data-currency="<?= e($c['currency'] ?? 'XOF') ?>"
              data-name="<?= e($c['name']) ?>">
              <?= e($c['name']) ?> (<?= e($c['currency'] ?? 'XOF') ?>)
            </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label">Opérateur Mobile Money *</label>
          <select name="operator" id="operator" class="form-select" required>
            <option value="">Sélectionner d'abord le pays</option>
          </select>
        </div>

        <input type="hidden" name="currency" id="currency" value="XOF">

        <div class="form-group" id="phone_group">
          <label class="form-label">Numéro de téléphone *</label>
          <div class="input-with-icon">
            <i class="fas fa-phone"></i>
            <input type="tel" name="phone" id="phone" class="form-control" placeholder="Ex: 07XXXXXXXX" required>
          </div>
          <div style="font-size:0.8rem;color:var(--text-muted);margin-top:4px;">Le numéro qui recevra la demande de paiement</div>
        </div>

        <div style="background:var(--bg);border-radius:14px;padding:16px;margin-bottom:20px;">
          <div style="font-size:0.85rem;font-weight:700;margin-bottom:8px;">Récapitulatif</div>
          <div class="info-row"><span class="info-label">Montant</span><span class="info-value" id="recap_amount">—</span></div>
          <div class="info-row"><span class="info-label">Opérateur</span><span class="info-value" id="recap_operator">—</span></div>
          <div class="info-row" style="border:none"><span class="info-label">Pays</span><span class="info-value" id="recap_country">—</span></div>
        </div>

        <button type="submit" class="btn-auth"><i class="fas fa-arrow-right"></i> Continuer vers le paiement</button>
      </form>
    </div>
  </div>
</div>

<!-- PAYMENT WAITING OVERLAY -->
<div id="payment_overlay" class="payment-overlay" style="display:none;">
  <div class="payment-modal">
    <div style="font-size:1.5rem;font-weight:800;margin-bottom:4px;">Validation du paiement</div>
    <div style="color:var(--text-muted);font-size:0.9rem;margin-bottom:24px;">Veuillez valider le paiement sur votre téléphone</div>
    <input type="hidden" id="transaction_id" value="">
    <div id="payment_spinner" class="payment-spinner"></div>
    <div class="countdown" id="countdown">08:00</div>
    <div style="margin-bottom:16px;">
      <div style="font-size:0.85rem;color:var(--text-muted);">
        Montant : <strong id="pay_amount"></strong> •
        Tel : <strong id="pay_phone"></strong> •
        Via : <strong id="pay_operator"></strong>
      </div>
    </div>
    <div class="payment-status-text status-pending" id="payment_status_text">🟡 En attente de validation...</div>
    <div id="otp_section" class="otp-section" style="display:none;margin-top:16px;">
      <div id="ussd_code_info" style="font-size:0.9rem;margin-bottom:12px;"></div>
      <form id="otp_form" onsubmit="submitOTPDirect(event)">
        <input type="hidden" id="otp_transaction_id" name="transaction_id">
        <div style="display:flex;gap:8px;">
          <input type="text" name="otp" id="otp_input" class="form-control" placeholder="Entrez le code OTP" maxlength="10" style="text-align:center;letter-spacing:4px;font-size:1.2rem;font-weight:700;">
          <button type="submit" class="btn-primary-custom" style="padding:10px 20px;white-space:nowrap;">Valider</button>
        </div>
      </form>
    </div>
    <div id="wave_link_section" style="display:none;margin-top:16px;">
      <a id="wave_open_btn" href="#" target="_blank" class="btn-wave"><i class="fas fa-mobile-screen"></i> Payer avec Wave</a>
    </div>
    <div id="retry_btn" style="display:none;margin-top:16px;">
      <button onclick="window.location.reload()" style="background:var(--danger);color:white;border:none;padding:12px 28px;border-radius:12px;font-weight:700;cursor:pointer;">Réessayer</button>
    </div>
  </div>
</div>

<?php renderBottomNav('deposit'); ?>

<script>
function updateOperators(select) {
  const val = select.value;
  const opt = select.options[select.selectedIndex];
  const operators = JSON.parse(opt.dataset.operators || '[]');
  const currency = opt.dataset.currency || 'XOF';
  const name = opt.dataset.name || '';
  const opSel = document.getElementById('operator');
  opSel.innerHTML = '<option value="">Sélectionner l\'opérateur</option>';
  operators.forEach(op => {
    const o = document.createElement('option');
    o.value = op; o.textContent = op;
    opSel.appendChild(o);
  });
  document.getElementById('currency').value = currency;
  document.getElementById('phone_group').style.display = 'block';
  document.getElementById('recap_country').textContent = name;
  // Hide phone for Wave
  opSel.addEventListener('change', function() {
    const isWave = this.value.toLowerCase().includes('wave');
    document.getElementById('phone').required = !isWave;
    document.getElementById('recap_operator').textContent = this.value;
  });
}

document.getElementById('deposit_amount')?.addEventListener('input', function() {
  const v = parseInt(this.value);
  document.getElementById('recap_amount').textContent = v ? v.toLocaleString('fr-FR') + ' FCFA' : '—';
});

function submitDepositForm(e) {
  e.preventDefault();
  const form = e.target;
  const btn = form.querySelector('[type=submit]');
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Traitement...';

  fetch('/api/initiate_payment.php', { method:'POST', body: new FormData(form) })
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      document.getElementById('transaction_id').value = data.transaction_id;
      document.getElementById('pay_amount').textContent = data.amount + ' ' + data.currency;
      document.getElementById('pay_phone').textContent = data.phone || 'N/A';
      document.getElementById('pay_operator').textContent = data.operator;

      if (data.flow === 'wave' && data.wave_url) {
        document.getElementById('wave_link_section').style.display = 'block';
        document.getElementById('wave_open_btn').href = data.wave_url;
      }
      if (data.otp_required) {
        document.getElementById('payment_spinner').style.display = 'none';
        document.querySelector('.countdown').style.display = 'none';
        document.getElementById('otp_section').style.display = 'block';
        document.getElementById('otp_transaction_id').value = data.transaction_id;
        const ussdInfo = document.getElementById('ussd_code_info');
        if (data.ussd_code) {
          ussdInfo.innerHTML = '<strong>Composez le code USSD :</strong> <code style="background:#f1f1f1;padding:2px 8px;border-radius:4px;">' + data.ussd_code + '</code><br>puis entrez le code OTP reçu.';
        } else {
          ussdInfo.innerHTML = '<strong>Un SMS avec votre code OTP a été envoyé sur votre téléphone.</strong><br>Entrez le code reçu ci-dessous.';
        }
      }
      document.getElementById('payment_overlay').style.display = 'flex';
      startPolling(data.transaction_id);
    } else {
      let alertEl = document.getElementById('form_error') || document.createElement('div');
      alertEl.id = 'form_error';
      alertEl.className = 'alert alert-danger';
      alertEl.textContent = data.message || 'Erreur lors de l\'initiation du paiement.';
      form.prepend(alertEl);
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-arrow-right"></i> Continuer vers le paiement';
    }
  })
  .catch(() => {
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-arrow-right"></i> Continuer vers le paiement';
  });
}

function startPolling(txId) {
  let seconds = 8 * 60;
  const countdownEl = document.getElementById('countdown');
  const statusEl = document.getElementById('payment_status_text');

  const countdownInterval = setInterval(() => {
    const m = Math.floor(seconds/60).toString().padStart(2,'0');
    const s = (seconds%60).toString().padStart(2,'0');
    if(countdownEl) countdownEl.textContent = m+':'+s;
    if(seconds > 0) seconds--;
  }, 1000);

  const pollInterval = setInterval(async () => {
    try {
      const r = await fetch('/api/payment_status.php?id=' + encodeURIComponent(txId));
      const d = await r.json();
      if (d.status === 'success') {
        clearInterval(pollInterval); clearInterval(countdownInterval);
        statusEl.textContent = '✅ Paiement validé !'; statusEl.className = 'payment-status-text status-success';
        document.getElementById('payment_spinner').style.display = 'none';
        setTimeout(() => window.location.href = '/dashboard.php?deposit=success', 1500);
      } else if (d.status === 'failed') {
        clearInterval(pollInterval); clearInterval(countdownInterval);
        statusEl.textContent = '❌ Paiement échoué.'; statusEl.className = 'payment-status-text status-failed';
        document.getElementById('payment_spinner').style.display = 'none';
        document.getElementById('retry_btn').style.display = 'block';
      }
    } catch(e) {}
  }, 3000);

  setTimeout(() => {
    clearInterval(pollInterval); clearInterval(countdownInterval);
    statusEl.textContent = '⏰ Délai expiré.'; statusEl.className = 'payment-status-text status-failed';
    document.getElementById('payment_spinner').style.display = 'none';
    document.getElementById('retry_btn').style.display = 'block';
  }, 8 * 60 * 1000);
}

function submitOTPDirect(e) {
  e.preventDefault();
  const form = e.target;
  fetch('/api/submit_otp.php', { method:'POST', body: new FormData(form) })
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      document.getElementById('otp_section').style.display = 'none';
      document.getElementById('payment_spinner').style.display = 'block';
      document.querySelector('.countdown').style.display = 'block';
      document.getElementById('payment_status_text').textContent = '🟡 En attente de validation...';
      document.getElementById('payment_status_text').className = 'payment-status-text status-pending';
    } else {
      alert(data.message || 'OTP invalide.');
    }
  });
}
</script>
<script src="/assets/js/main.js"></script>
</body>
</html>
