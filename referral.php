<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
startSession();
requireLogin();
$user = getCurrentUser();
$db = getDB();

$referralLink = SITE_URL . '/register.php?ref=' . $user['referral_code'];

// Level 1 referrals
$lvl1 = $db->prepare("SELECT id, name, email, created_at, has_deposit FROM users WHERE referred_by = ?");
$lvl1->execute([$user['id']]);
$level1Users = $lvl1->fetchAll();

// Level 2
$level2Users = [];
foreach ($level1Users as $u) {
    $stmt = $db->prepare("SELECT id, name, email, created_at, has_deposit FROM users WHERE referred_by = ?");
    $stmt->execute([$u['id']]);
    $sub = $stmt->fetchAll();
    foreach ($sub as $s) $level2Users[] = $s;
}

// Level 3
$level3Users = [];
foreach ($level2Users as $u) {
    $stmt = $db->prepare("SELECT id, name, email, created_at FROM users WHERE referred_by = ?");
    $stmt->execute([$u['id']]);
    $sub = $stmt->fetchAll();
    foreach ($sub as $s) $level3Users[] = $s;
}

$totalReferralEarnings = $user['referral_earnings'];
$l1Rate = getSetting('referral_level1', '20');
$l2Rate = getSetting('referral_level2', '5');
$l3Rate = getSetting('referral_level3', '2');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Programme de Parrainage — ChargePoint</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<?php renderAppLayout($user, 'referral'); ?>

<div class="page-title">Programme de <span style="color:var(--primary)">Parrainage</span></div>
<div class="page-subtitle">Invitez vos proches et gagnez des commissions sur 3 niveaux.</div>

<!-- REFERRAL LINK -->
<div class="card-custom" style="margin-bottom:24px;">
  <div class="card-custom-header"><h5><i class="fas fa-link" style="color:var(--primary)"></i> Votre Lien de Parrainage</h5></div>
  <div class="card-custom-body">
    <div class="referral-link-box" style="margin-bottom:12px;">
      <i class="fas fa-link" style="color:var(--primary)"></i>
      <input type="text" id="referral_link" value="<?= e($referralLink) ?>" readonly>
      <button data-copy="<?= e($referralLink) ?>" class="btn-primary-custom" style="padding:8px 16px;white-space:nowrap;font-size:0.85rem;">
        <i class="fas fa-copy"></i> Copier
      </button>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap;">
      <button onclick="shareReferral()" class="btn-primary-custom" style="padding:10px 20px;font-size:0.88rem;"><i class="fas fa-share-nodes"></i> Partager</button>
      <a href="https://wa.me/?text=Rejoignez%20ChargePoint%20et%20investissez%20d%C3%A8s%20aujourd%27hui%20!%20<?= urlencode($referralLink) ?>" target="_blank" style="background:#25d366;color:white;padding:10px 20px;border-radius:12px;font-weight:600;font-size:0.88rem;display:flex;align-items:center;gap:6px;">
        <i class="fab fa-whatsapp"></i> WhatsApp
      </a>
      <a href="https://t.me/share/url?url=<?= urlencode($referralLink) ?>&text=Investissez+sur+ChargePoint" target="_blank" style="background:#229ed9;color:white;padding:10px 20px;border-radius:12px;font-weight:600;font-size:0.88rem;display:flex;align-items:center;gap:6px;">
        <i class="fab fa-telegram"></i> Telegram
      </a>
    </div>
  </div>
</div>

<!-- STATS -->
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px;" class="ref-stats">
  <div class="stat-card">
    <div class="stat-card-icon icon-orange"><i class="fas fa-users"></i></div>
    <div class="stat-card-value"><?= count($level1Users) ?></div>
    <div class="stat-card-label">Filleuls directs (N1)</div>
  </div>
  <div class="stat-card">
    <div class="stat-card-icon icon-blue"><i class="fas fa-user-group"></i></div>
    <div class="stat-card-value"><?= count($level2Users) ?></div>
    <div class="stat-card-label">Filleuls N2</div>
  </div>
  <div class="stat-card">
    <div class="stat-card-icon icon-purple"><i class="fas fa-sitemap"></i></div>
    <div class="stat-card-value"><?= count($level3Users) ?></div>
    <div class="stat-card-label">Filleuls N3</div>
  </div>
  <div class="stat-card">
    <div class="stat-card-icon icon-green"><i class="fas fa-coins"></i></div>
    <div class="stat-card-value"><?= formatAmount($totalReferralEarnings) ?></div>
    <div class="stat-card-label">Gains de parrainage</div>
  </div>
</div>

<!-- COMMISSION TABLE -->
<div class="card-custom" style="margin-bottom:24px;">
  <div class="card-custom-header"><h5><i class="fas fa-percent" style="color:var(--primary)"></i> Structure des Commissions</h5></div>
  <div style="overflow-x:auto;">
    <table class="table-custom">
      <thead><tr><th>Niveau</th><th>Commission</th><th>Description</th><th>Filleuls</th></tr></thead>
      <tbody>
        <tr>
          <td><span style="background:rgba(255,107,0,0.1);color:var(--primary);padding:4px 12px;border-radius:20px;font-weight:700;">Niveau 1</span></td>
          <td style="font-size:1.2rem;font-weight:800;color:var(--primary)"><?= $l1Rate ?>%</td>
          <td style="color:var(--text-muted)">Sur chaque dépôt de vos filleuls directs</td>
          <td><strong><?= count($level1Users) ?></strong> filleuls</td>
        </tr>
        <tr>
          <td><span style="background:rgba(59,130,246,0.1);color:var(--info);padding:4px 12px;border-radius:20px;font-weight:700;">Niveau 2</span></td>
          <td style="font-size:1.2rem;font-weight:800;color:var(--info)"><?= $l2Rate ?>%</td>
          <td style="color:var(--text-muted)">Sur chaque dépôt des filleuls de vos filleuls</td>
          <td><strong><?= count($level2Users) ?></strong> filleuls</td>
        </tr>
        <tr>
          <td><span style="background:rgba(139,92,246,0.1);color:#8b5cf6;padding:4px 12px;border-radius:20px;font-weight:700;">Niveau 3</span></td>
          <td style="font-size:1.2rem;font-weight:800;color:#8b5cf6"><?= $l3Rate ?>%</td>
          <td style="color:var(--text-muted)">Sur chaque dépôt au 3ème niveau</td>
          <td><strong><?= count($level3Users) ?></strong> filleuls</td>
        </tr>
      </tbody>
    </table>
  </div>
</div>

<!-- REFERRAL TREE -->
<div class="card-custom">
  <div class="card-custom-header"><h5><i class="fas fa-sitemap" style="color:var(--primary)"></i> Arbre de Parrainage</h5></div>
  <div class="card-custom-body referral-tree">
    <!-- You -->
    <div style="text-align:center;margin-bottom:24px;">
      <div style="display:inline-flex;flex-direction:column;align-items:center;">
        <div style="width:56px;height:56px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--primary-dark));color:white;font-weight:800;font-size:1.3rem;display:flex;align-items:center;justify-content:center;border:4px solid white;box-shadow:0 4px 15px rgba(255,107,0,0.3);">
          <?= strtoupper(substr($user['name'],0,1)) ?>
        </div>
        <div style="font-weight:700;margin-top:8px;"><?= e(explode(' ',$user['name'])[0]) ?> (Vous)</div>
        <div style="background:var(--primary);color:white;font-size:0.75rem;padding:2px 10px;border-radius:10px;margin-top:4px;">Parrain principal</div>
      </div>
    </div>

    <?php if (!empty($level1Users)): ?>
    <div class="tree-level">
      <div class="tree-level-title" style="text-align:center;margin-bottom:16px;">Niveau 1 — <?= $l1Rate ?>% commission (<?= count($level1Users) ?> filleuls)</div>
      <div style="display:flex;flex-wrap:wrap;gap:10px;justify-content:center;">
        <?php foreach ($level1Users as $u): ?>
        <div class="tree-node">
          <div class="avatar"><?= strtoupper(substr($u['name'],0,1)) ?></div>
          <div>
            <div style="font-weight:600;font-size:0.88rem;"><?= e(explode(' ',$u['name'])[0]) ?></div>
            <div style="font-size:0.75rem;color:var(--text-muted)"><?= $u['has_deposit'] ? '<span style="color:var(--success)">✅ A déposé</span>' : '<span style="color:var(--text-muted)">Pas encore déposé</span>' ?></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php else: ?>
    <div class="empty-state">
      <div class="empty-state-icon">👥</div>
      <div class="empty-state-title">Aucun filleul pour l'instant</div>
      <p style="font-size:0.85rem;margin-top:8px;">Partagez votre lien pour inviter vos proches</p>
    </div>
    <?php endif; ?>

    <?php if (!empty($level2Users)): ?>
    <div class="tree-level" style="margin-top:20px;">
      <div class="tree-level-title" style="text-align:center;margin-bottom:16px;">Niveau 2 — <?= $l2Rate ?>% commission (<?= count($level2Users) ?> filleuls)</div>
      <div style="display:flex;flex-wrap:wrap;gap:10px;justify-content:center;">
        <?php foreach (array_slice($level2Users, 0, 8) as $u): ?>
        <div class="tree-node" style="background:#f0f4ff;">
          <div class="avatar" style="background:linear-gradient(135deg,var(--info),#1e40af)"><?= strtoupper(substr($u['name'],0,1)) ?></div>
          <div>
            <div style="font-weight:600;font-size:0.88rem;"><?= e(explode(' ',$u['name'])[0]) ?></div>
            <div style="font-size:0.75rem;color:var(--info)">Niveau 2</div>
          </div>
        </div>
        <?php endforeach; ?>
        <?php if (count($level2Users) > 8): ?>
        <div class="tree-node"><span style="color:var(--text-muted)">+<?= count($level2Users)-8 ?> autres</span></div>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($level3Users)): ?>
    <div class="tree-level" style="margin-top:20px;">
      <div class="tree-level-title" style="text-align:center;margin-bottom:16px;">Niveau 3 — <?= $l3Rate ?>% commission (<?= count($level3Users) ?> filleuls)</div>
      <div style="display:flex;flex-wrap:wrap;gap:10px;justify-content:center;">
        <?php foreach (array_slice($level3Users, 0, 6) as $u): ?>
        <div class="tree-node" style="background:#f5f0ff;">
          <div class="avatar" style="background:linear-gradient(135deg,#8b5cf6,#6d28d9)"><?= strtoupper(substr($u['name'],0,1)) ?></div>
          <div>
            <div style="font-weight:600;font-size:0.88rem;"><?= e(explode(' ',$u['name'])[0]) ?></div>
            <div style="font-size:0.75rem;color:#8b5cf6">Niveau 3</div>
          </div>
        </div>
        <?php endforeach; ?>
        <?php if (count($level3Users) > 6): ?>
        <div class="tree-node"><span style="color:var(--text-muted)">+<?= count($level3Users)-6 ?> autres</span></div>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php renderBottomNav('referral'); ?>
<style>
@media(max-width:767px){.ref-stats{grid-template-columns:repeat(2,1fr)!important;}}
</style>
<script src="/assets/js/main.js"></script>
</body>
</html>
