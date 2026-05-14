<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once 'includes/admin_layout.php';
startSession();
requireAdmin();
$db = getDB();

$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $db->prepare("INSERT INTO settings (key, value) VALUES (?,?) ON CONFLICT(key) DO UPDATE SET value=excluded.value");
    $simpleFields = ['referral_level1','referral_level2','referral_level3','min_deposit','max_deposit','min_withdrawal','max_withdrawal','withdrawal_fee'];
    foreach ($simpleFields as $field) {
        if (isset($_POST[$field])) {
            $stmt->execute([$field, trim($_POST[$field])]);
        }
    }
    $stmt->execute(['maintenance_mode', isset($_POST['maintenance_mode']) ? '1' : '0']);
    $activeCountries = isset($_POST['active_countries']) && is_array($_POST['active_countries'])
        ? json_encode(array_values($_POST['active_countries']))
        : json_encode([]);
    $stmt->execute(['active_countries', $activeCountries]);
    $success = 'Paramètres enregistrés avec succès.';
}

$settings = [];
$rows = $db->query("SELECT key, value FROM settings")->fetchAll();
foreach ($rows as $r) $settings[$r['key']] = $r['value'];
?>
<?php renderAdminHead('Paramètres'); ?>
<?php renderAdminSidebar('settings'); ?>
<?php renderAdminTopbar('Paramètres de la Plateforme', 'Configurez les règles et limites de ChargePoint'); ?>

<div class="admin-page-content">
<?php if ($success): ?><div class="alert alert-success alert-auto"><?= e($success) ?></div><?php endif; ?>

<form method="POST">
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;" class="settings-grid">

    <!-- REFERRAL -->
    <div class="card-custom">
      <div class="card-custom-header"><h5><i class="fas fa-users" style="color:var(--primary)"></i> Commissions de Parrainage</h5></div>
      <div class="card-custom-body">
        <div class="form-group">
          <label class="form-label">Commission Niveau 1 (%)</label>
          <div class="input-with-icon">
            <i class="fas fa-percent"></i>
            <input type="number" name="referral_level1" class="form-control" value="<?= e($settings['referral_level1'] ?? '20') ?>" min="0" max="100" step="0.1" required>
          </div>
          <div style="font-size:0.8rem;color:var(--text-muted);margin-top:4px;">Sur chaque dépôt des filleuls directs</div>
        </div>
        <div class="form-group">
          <label class="form-label">Commission Niveau 2 (%)</label>
          <div class="input-with-icon">
            <i class="fas fa-percent"></i>
            <input type="number" name="referral_level2" class="form-control" value="<?= e($settings['referral_level2'] ?? '5') ?>" min="0" max="100" step="0.1" required>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Commission Niveau 3 (%)</label>
          <div class="input-with-icon">
            <i class="fas fa-percent"></i>
            <input type="number" name="referral_level3" class="form-control" value="<?= e($settings['referral_level3'] ?? '2') ?>" min="0" max="100" step="0.1" required>
          </div>
        </div>
      </div>
    </div>

    <!-- DEPOSIT LIMITS -->
    <div class="card-custom">
      <div class="card-custom-header"><h5><i class="fas fa-arrow-down-to-line" style="color:var(--success)"></i> Limites de Dépôt</h5></div>
      <div class="card-custom-body">
        <div class="form-group">
          <label class="form-label">Montant minimum de dépôt (FCFA)</label>
          <div class="input-with-icon">
            <i class="fas fa-arrow-down"></i>
            <input type="number" name="min_deposit" class="form-control" value="<?= e($settings['min_deposit'] ?? '1000') ?>" min="0" required>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Montant maximum de dépôt (FCFA)</label>
          <div class="input-with-icon">
            <i class="fas fa-arrow-up"></i>
            <input type="number" name="max_deposit" class="form-control" value="<?= e($settings['max_deposit'] ?? '10000000') ?>" min="0" required>
          </div>
        </div>
      </div>
    </div>

    <!-- WITHDRAWAL -->
    <div class="card-custom">
      <div class="card-custom-header"><h5><i class="fas fa-money-bill-transfer" style="color:var(--primary)"></i> Limites de Retrait</h5></div>
      <div class="card-custom-body">
        <div class="form-group">
          <label class="form-label">Montant minimum de retrait (FCFA)</label>
          <div class="input-with-icon">
            <i class="fas fa-arrow-down"></i>
            <input type="number" name="min_withdrawal" class="form-control" value="<?= e($settings['min_withdrawal'] ?? '1000') ?>" min="0" required>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Montant maximum de retrait (FCFA)</label>
          <div class="input-with-icon">
            <i class="fas fa-arrow-up"></i>
            <input type="number" name="max_withdrawal" class="form-control" value="<?= e($settings['max_withdrawal'] ?? '5000000') ?>" min="0" required>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Frais de retrait (%)</label>
          <div class="input-with-icon">
            <i class="fas fa-percent"></i>
            <input type="number" name="withdrawal_fee" class="form-control" value="<?= e($settings['withdrawal_fee'] ?? '11') ?>" min="0" max="100" step="0.1" required>
          </div>
          <div style="font-size:0.8rem;color:var(--text-muted);margin-top:4px;">Déduit automatiquement du montant de retrait</div>
        </div>
      </div>
    </div>

    <!-- MAINTENANCE -->
    <div class="card-custom">
      <div class="card-custom-header"><h5><i class="fas fa-tools" style="color:var(--warning)"></i> Mode Maintenance</h5></div>
      <div class="card-custom-body">
        <div style="display:flex;align-items:center;justify-content:space-between;padding:16px;background:<?= ($settings['maintenance_mode'] ?? '0') === '1' ? 'rgba(245,158,11,0.1)' : 'var(--bg)' ?>;border-radius:12px;margin-bottom:16px;">
          <div>
            <div style="font-weight:700;">Mode Maintenance</div>
            <div style="font-size:0.85rem;color:var(--text-muted);">Désactive l'accès au site pour les utilisateurs</div>
          </div>
          <label style="position:relative;display:inline-block;width:52px;height:28px;">
            <input type="checkbox" name="maintenance_mode" value="1" <?= ($settings['maintenance_mode'] ?? '0') === '1' ? 'checked' : '' ?> style="opacity:0;width:0;height:0;">
            <span onclick="this.previousElementSibling.click()" style="position:absolute;cursor:pointer;inset:0;background:<?= ($settings['maintenance_mode'] ?? '0') === '1' ? 'var(--warning)' : '#ccc' ?>;border-radius:28px;transition:0.3s;">
              <span style="position:absolute;height:20px;width:20px;left:4px;bottom:4px;background:white;border-radius:50%;transition:0.3s;transform:<?= ($settings['maintenance_mode'] ?? '0') === '1' ? 'translateX(24px)' : 'translateX(0)' ?>"></span>
            </span>
          </label>
        </div>
        <?php if (($settings['maintenance_mode'] ?? '0') === '1'): ?>
        <div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> Le site est actuellement en maintenance. Les utilisateurs ne peuvent pas se connecter.</div>
        <?php endif; ?>
      </div>
    </div>

    <!-- COUNTRIES -->
    <div class="card-custom" style="grid-column:1/-1;">
      <div class="card-custom-header"><h5><i class="fas fa-globe" style="color:var(--info)"></i> Pays et méthodes actifs</h5></div>
      <div class="card-custom-body">
        <div style="font-size:0.85rem;color:var(--text-muted);margin-bottom:16px;">Cochez les pays que vous souhaitez activer sur la plateforme de dépôt.</div>
        <?php
        $activeList = json_decode($settings['active_countries'] ?? '[]', true) ?: [];
        $allCountries = [
            ['code'=>'BJ','name'=>'Bénin'],['code'=>'BF','name'=>'Burkina Faso'],
            ['code'=>'CM','name'=>'Cameroun'],['code'=>'CF','name'=>'Centrafrique'],
            ['code'=>'CG','name'=>'Congo'],['code'=>'CI','name'=>"Côte d'Ivoire"],
            ['code'=>'GA','name'=>'Gabon'],['code'=>'GN','name'=>'Guinée Conakry'],
            ['code'=>'GQ','name'=>'Guinée équatoriale'],['code'=>'GW','name'=>'Guinée-Bissau'],
            ['code'=>'ML','name'=>'Mali'],['code'=>'NE','name'=>'Niger'],
            ['code'=>'CD','name'=>'RD Congo'],['code'=>'SN','name'=>'Sénégal'],
            ['code'=>'TD','name'=>'Tchad'],['code'=>'TG','name'=>'Togo'],
        ];
        $allActive = empty($activeList);
        ?>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:10px;">
          <?php foreach ($allCountries as $c): ?>
          <?php $checked = $allActive || in_array($c['code'], $activeList); ?>
          <label style="display:flex;align-items:center;gap:8px;padding:10px 12px;border:1px solid var(--border);border-radius:10px;cursor:pointer;background:<?= $checked ? 'rgba(16,185,129,0.05)' : 'var(--bg)' ?>;">
            <input type="checkbox" name="active_countries[]" value="<?= $c['code'] ?>" <?= $checked ? 'checked' : '' ?> style="accent-color:var(--primary);width:16px;height:16px;">
            <span style="font-size:0.88rem;font-weight:600;"><?= e($c['name']) ?></span>
          </label>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- INFO -->
    <div class="card-custom">
      <div class="card-custom-header"><h5><i class="fas fa-info-circle" style="color:var(--info)"></i> Informations Système</h5></div>
      <div class="card-custom-body">
        <div class="info-row"><span class="info-label">Version PHP</span><span class="info-value"><?= phpversion() ?></span></div>
        <div class="info-row"><span class="info-label">Base de données</span><span class="info-value">SQLite 3</span></div>
        <div class="info-row"><span class="info-label">URL du site</span><span class="info-value"><?= e(SITE_URL) ?></span></div>
        <div class="info-row"><span class="info-label">Tâche CRON</span><span class="info-value"><code style="font-size:0.8rem;">/api/cron.php?key=cp_cron_secret_2026</code></span></div>
        <div class="info-row" style="border:none"><span class="info-label">Webhook URL</span><span class="info-value"><code style="font-size:0.8rem;">/webhook.php</code></span></div>
      </div>
    </div>
  </div>

  <div style="margin-top:20px;text-align:right;">
    <button type="submit" class="btn-primary-custom" style="padding:14px 40px;font-size:1rem;">
      <i class="fas fa-save"></i> Enregistrer les paramètres
    </button>
  </div>
</form>
</div>
<style>@media(max-width:767px){.settings-grid{grid-template-columns:1fr!important;}}</style>
<?php renderAdminFooter(); ?>
