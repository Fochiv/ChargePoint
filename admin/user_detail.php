<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once 'includes/admin_layout.php';
startSession();
requireAdmin();
$db = getDB();

$userId = (int)($_GET['id'] ?? 0);
$stmt = $db->prepare("SELECT * FROM users WHERE id=?");
$stmt->execute([$userId]);
$user = $stmt->fetch();
if (!$user) { header('Location: /admin/users.php'); exit; }

$investments = $db->prepare("SELECT i.*, p.name as plan_name FROM investments i JOIN vip_plans p ON i.plan_id=p.id WHERE i.user_id=? ORDER BY i.started_at DESC");
$investments->execute([$userId]);
$invList = $investments->fetchAll();

$txStmt = $db->prepare("SELECT * FROM transactions WHERE user_id=? ORDER BY created_at DESC LIMIT 30");
$txStmt->execute([$userId]);
$txList = $txStmt->fetchAll();

$referrer = null;
if ($user['referred_by']) {
    $refStmt = $db->prepare("SELECT name, email FROM users WHERE id=?");
    $refStmt->execute([$user['referred_by']]);
    $referrer = $refStmt->fetch();
}

$level1Count = $db->prepare("SELECT COUNT(*) FROM users WHERE referred_by=?");
$level1Count->execute([$userId]);
$l1 = $level1Count->fetchColumn();
?>
<?php renderAdminHead('Profil: ' . $user['name']); ?>
<?php renderAdminSidebar('users'); ?>
<?php renderAdminTopbar('Profil Utilisateur', $user['name']); ?>

<div class="admin-page-content">
  <div style="margin-bottom:16px;"><a href="/admin/users.php" style="color:var(--primary);font-weight:600;"><i class="fas fa-arrow-left"></i> Retour aux utilisateurs</a></div>

  <div style="display:grid;grid-template-columns:1fr 2fr;gap:20px;" class="detail-grid">
    <div>
      <div class="card-custom" style="margin-bottom:16px;">
        <div class="card-custom-body" style="text-align:center;">
          <div class="profile-avatar" style="margin-bottom:12px;"><?= strtoupper(substr($user['name'],0,1)) ?></div>
          <div style="font-size:1.1rem;font-weight:700;"><?= e($user['name']) ?></div>
          <div style="color:var(--text-muted);font-size:0.85rem;margin-bottom:8px;"><?= e($user['email']) ?></div>
          <?= getStatusBadge($user['status']) ?>
          <div style="margin-top:16px;">
            <div style="font-size:1.4rem;font-weight:800;color:var(--primary)"><?= formatAmount($user['balance']) ?></div>
            <div style="font-size:0.8rem;color:var(--text-muted)">Solde actuel</div>
          </div>
        </div>
      </div>
      <div class="card-custom">
        <div class="card-custom-header"><h5>Informations</h5></div>
        <div class="card-custom-body">
          <div class="info-row"><span class="info-label">Téléphone</span><span class="info-value"><?= e($user['phone']) ?></span></div>
          <div class="info-row"><span class="info-label">Code parrainage</span><span class="info-value"><?= e($user['referral_code']) ?></span></div>
          <div class="info-row"><span class="info-label">Parrain</span><span class="info-value"><?= $referrer ? e($referrer['name']) : '—' ?></span></div>
          <div class="info-row"><span class="info-label">Filleuls directs</span><span class="info-value"><?= $l1 ?></span></div>
          <div class="info-row"><span class="info-label">Gains totaux</span><span class="info-value" style="color:var(--success)"><?= formatAmount($user['total_earnings']) ?></span></div>
          <div class="info-row"><span class="info-label">Gains parrainage</span><span class="info-value" style="color:var(--info)"><?= formatAmount($user['referral_earnings']) ?></span></div>
          <div class="info-row"><span class="info-label">A déposé</span><span class="info-value"><?= $user['has_deposit'] ? '✅ Oui' : '❌ Non' ?></span></div>
          <div class="info-row"><span class="info-label">Inscription</span><span class="info-value"><?= date('d/m/Y', strtotime($user['created_at'])) ?></span></div>
          <div class="info-row" style="border:none"><span class="info-label">Dernière connexion</span><span class="info-value"><?= $user['last_login'] ? date('d/m/Y H:i', strtotime($user['last_login'])) : '—' ?></span></div>
        </div>
      </div>
    </div>

    <div>
      <!-- INVESTMENTS -->
      <div class="card-custom" style="margin-bottom:16px;">
        <div class="card-custom-header"><h5><i class="fas fa-crown" style="color:var(--primary)"></i> Investissements</h5></div>
        <div style="overflow-x:auto;">
          <?php if (empty($invList)): ?>
          <div class="empty-state" style="padding:20px"><div class="empty-state-title">Aucun investissement</div></div>
          <?php else: ?>
          <table class="table-custom">
            <thead><tr><th>Plan</th><th>Montant</th><th>Gain/Jour</th><th>Jours restants</th><th>Statut</th></tr></thead>
            <tbody>
            <?php foreach ($invList as $inv): ?>
            <tr>
              <td><?= e($inv['plan_name']) ?></td>
              <td><?= formatAmount($inv['amount']) ?></td>
              <td style="color:var(--success)"><?= formatAmount($inv['daily_gain']) ?></td>
              <td><?= $inv['days_remaining'] ?> / <?= $inv['days_total'] ?></td>
              <td><?= getStatusBadge($inv['status']) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
          <?php endif; ?>
        </div>
      </div>

      <!-- TRANSACTIONS -->
      <div class="card-custom">
        <div class="card-custom-header"><h5><i class="fas fa-list" style="color:var(--primary)"></i> Transactions récentes</h5></div>
        <div style="overflow-x:auto;">
          <table class="table-custom">
            <thead><tr><th>Date</th><th>Type</th><th>Montant</th><th>Statut</th></tr></thead>
            <tbody>
            <?php foreach ($txList as $tx): ?>
            <tr>
              <td style="font-size:0.82rem;"><?= date('d/m/Y H:i', strtotime($tx['created_at'])) ?></td>
              <td><span style="font-size:0.8rem;background:var(--bg);padding:3px 8px;border-radius:6px;"><?= e(getTypeLabel($tx['type'])) ?></span></td>
              <td style="font-weight:700"><?= formatAmount($tx['amount']) ?></td>
              <td><?= getStatusBadge($tx['status']) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
<style>@media(max-width:767px){.detail-grid{grid-template-columns:1fr!important;}}</style>
<?php renderAdminFooter(); ?>
