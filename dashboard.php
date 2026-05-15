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

$activeInvestments = $db->prepare("SELECT i.*, p.name as plan_name FROM investments i JOIN vip_plans p ON i.plan_id = p.id WHERE i.user_id = ? AND i.status = 'active' ORDER BY i.started_at DESC");
$activeInvestments->execute([$user['id']]);
$investments = $activeInvestments->fetchAll();

$recentTx = $db->prepare("SELECT * FROM transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
$recentTx->execute([$user['id']]);
$transactions = $recentTx->fetchAll();

$todayGains = $db->prepare("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE user_id = ? AND type='daily_gain' AND DATE(created_at)=?");
$todayGains->execute([$user['id'], date('Y-m-d')]);
$todayGainsTotal = $todayGains->fetchColumn();

$refCount = $db->prepare("SELECT COUNT(*) FROM users WHERE referred_by = ?");
$refCount->execute([$user['id']]);
$referralCount = $refCount->fetchColumn();

$welcome = isset($_GET['welcome']);
$depositSuccess = isset($_GET['deposit']) && $_GET['deposit'] === 'success';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Tableau de Bord — ChargePoint</title>
<link rel="icon" type="image/jpeg" href="/assets/logo.jpg">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>

<?php renderAppLayout($user, 'dashboard'); ?>

<?php if ($welcome): ?>
<div class="alert alert-success alert-auto" style="margin-bottom:16px;">🎉 Bienvenue sur ChargePoint ! Votre compte est créé. Investissez dès maintenant.</div>
<?php endif; ?>
<?php if ($depositSuccess): ?>
<div class="alert alert-success alert-auto" style="margin-bottom:16px;">✅ Dépôt validé avec succès ! Votre solde a été mis à jour.</div>
<?php endif; ?>

<!-- BANNER SLIDER -->
<div class="banner-slider">
  <div class="banner-slide active">
    <img src="/assets/chargepoint1.jpg" alt="ChargePoint">
    <div class="banner-caption"><div class="banner-caption-title"><i class="fas fa-bolt"></i> Investissez, Gagnez, Retirez</div><div class="banner-caption-sub">Vos gains journaliers créditent automatiquement</div></div>
  </div>
  <div class="banner-slide">
    <img src="/assets/chargepoint2.jpg" alt="ChargePoint">
    <div class="banner-caption"><div class="banner-caption-title"><i class="fas fa-crown"></i> Plans VIP disponibles</div><div class="banner-caption-sub">Dès 3 000 FCFA — Gains sur 125 jours</div></div>
  </div>
  <div class="banner-slide">
    <img src="/assets/chargepoint3.jpg" alt="ChargePoint">
    <div class="banner-caption"><div class="banner-caption-title"><i class="fas fa-users"></i> Programme de Parrainage</div><div class="banner-caption-sub">Gagnez jusqu'à 27% sur les dépôts de vos filleuls</div></div>
  </div>
  <div class="banner-slide">
    <img src="/assets/chargepoint4.jpg" alt="ChargePoint">
    <div class="banner-caption"><div class="banner-caption-title"><i class="fas fa-shield-alt"></i> Plateforme Sécurisée</div><div class="banner-caption-sub">100% fiable et transparent</div></div>
  </div>
  <div class="banner-slide">
    <img src="/assets/chargepoint5.jpg" alt="ChargePoint">
    <div class="banner-caption"><div class="banner-caption-title"><i class="fas fa-mobile-alt"></i> Mobile Money</div><div class="banner-caption-sub">Retraits rapides via Orange, MTN, Wave</div></div>
  </div>
  <div class="banner-dots">
    <span class="banner-dot active"></span>
    <span class="banner-dot"></span>
    <span class="banner-dot"></span>
    <span class="banner-dot"></span>
    <span class="banner-dot"></span>
  </div>
</div>

<!-- STAT CARDS -->
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px;" class="stats-grid">
  <div class="stat-card">
    <div class="stat-card-icon icon-orange"><i class="fas fa-wallet"></i></div>
    <div class="stat-card-value"><?= formatAmount($user['balance']) ?></div>
    <div class="stat-card-label">Solde du compte</div>
  </div>
  <div class="stat-card">
    <div class="stat-card-icon icon-green"><i class="fas fa-chart-line"></i></div>
    <div class="stat-card-value"><?= formatAmount($todayGainsTotal) ?></div>
    <div class="stat-card-label">Gains du jour</div>
  </div>
  <div class="stat-card">
    <div class="stat-card-icon icon-blue"><i class="fas fa-coins"></i></div>
    <div class="stat-card-value"><?= formatAmount($user['total_earnings']) ?></div>
    <div class="stat-card-label">Gains totaux</div>
  </div>
  <div class="stat-card">
    <div class="stat-card-icon icon-purple"><i class="fas fa-users"></i></div>
    <div class="stat-card-value"><?= $referralCount ?></div>
    <div class="stat-card-label">Parrainages</div>
  </div>
</div>

<!-- MAIN DEPOSIT BUTTON -->
<div style="margin-bottom:24px;">
  <a href="/deposit.php" class="btn-primary-custom" style="width:100%;justify-content:center;font-size:1.05rem;padding:16px;">
    <i class="fas fa-circle-plus"></i> Faire un Dépôt
  </a>
</div>

<!-- QUICK ACTIONS -->
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:24px;" class="quick-grid">
  <a href="/deposit.php" class="quick-action">
    <div class="quick-action-icon"><i class="fas fa-download"></i></div>
    <div class="quick-action-label">Dépôt</div>
  </a>
  <a href="/withdraw.php" class="quick-action">
    <div class="quick-action-icon"><i class="fas fa-arrow-up-from-bracket"></i></div>
    <div class="quick-action-label">Retrait</div>
  </a>
  <a href="/vip.php" class="quick-action">
    <div class="quick-action-icon"><i class="fas fa-crown"></i></div>
    <div class="quick-action-label">Investir</div>
  </a>
  <a href="/referral.php" class="quick-action">
    <div class="quick-action-icon"><i class="fas fa-users"></i></div>
    <div class="quick-action-label">Parrainage</div>
  </a>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px;" class="two-col">

  <!-- ACTIVE INVESTMENTS -->
  <div class="card-custom">
    <div class="card-custom-header">
      <h5><i class="fas fa-crown" style="color:var(--primary)"></i> Plans Actifs</h5>
      <a href="/vip.php" style="font-size:0.85rem;color:var(--primary)">Voir tous</a>
    </div>
    <div class="card-custom-body" style="padding:0;">
      <?php if (empty($investments)): ?>
      <div class="empty-state">
        <div class="empty-state-icon"><i class="fas fa-crown" style="color:var(--primary);font-size:2rem;"></i></div>
        <div class="empty-state-title">Aucun plan actif</div>
        <p style="font-size:0.85rem;margin-top:8px;">Investissez dans un plan VIP</p>
        <a href="/vip.php" class="btn-primary-custom" style="margin-top:12px;font-size:0.85rem;padding:10px 20px;">Choisir un plan</a>
      </div>
      <?php else: ?>
      <?php foreach ($investments as $inv): ?>
      <div style="padding:16px 20px;border-bottom:1px solid var(--border);">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
          <span style="font-weight:700;color:var(--primary)"><?= e($inv['plan_name']) ?></span>
          <?= getStatusBadge($inv['status']) ?>
        </div>
        <div style="display:flex;justify-content:space-between;font-size:0.85rem;color:var(--text-muted);margin-bottom:8px;">
          <span>Gain/jour: <strong style="color:var(--success)"><?= formatAmount($inv['daily_gain']) ?></strong></span>
          <span>Reste: <strong><?= $inv['days_remaining'] ?> jours</strong></span>
        </div>
        <div style="background:var(--bg);border-radius:8px;height:6px;overflow:hidden;">
          <div style="height:100%;background:linear-gradient(90deg,var(--primary),var(--primary-light));width:<?= round(($inv['days_total']-$inv['days_remaining'])/$inv['days_total']*100) ?>%;transition:width 0.5s;"></div>
        </div>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <!-- ACCOUNT INFO -->
  <div class="card-custom">
    <div class="card-custom-header"><h5><i class="fas fa-info-circle" style="color:var(--primary)"></i> Mon Compte</h5></div>
    <div class="card-custom-body">
      <div class="info-row">
        <span class="info-label">Statut</span>
        <span class="info-value"><?= getStatusBadge($user['status']) ?></span>
      </div>
      <div class="info-row">
        <span class="info-label">Date d'inscription</span>
        <span class="info-value"><?= date('d/m/Y', strtotime($user['created_at'])) ?></span>
      </div>
      <div class="info-row">
        <span class="info-label">Dernière connexion</span>
        <span class="info-value"><?= $user['last_login'] ? date('d/m/Y H:i', strtotime($user['last_login'])) : 'Première connexion' ?></span>
      </div>
      <div class="info-row">
        <span class="info-label">Gains parrainage</span>
        <span class="info-value" style="color:var(--success)"><?= formatAmount($user['referral_earnings']) ?></span>
      </div>
      <div class="info-row">
        <span class="info-label">Dépôt effectué</span>
        <span class="info-value"><?= $user['has_deposit'] ? '<span style="color:var(--success)">✅ Oui</span>' : '<span style="color:var(--danger)">❌ Non</span>' ?></span>
      </div>
    </div>
  </div>
</div>

<!-- RECENT TRANSACTIONS -->
<div class="card-custom">
  <div class="card-custom-header">
    <h5><i class="fas fa-history" style="color:var(--primary)"></i> Transactions récentes</h5>
    <a href="/transactions.php" style="font-size:0.85rem;color:var(--primary)">Voir tout</a>
  </div>
  <div style="overflow-x:auto;">
    <?php if (empty($transactions)): ?>
    <div class="empty-state"><div class="empty-state-icon"><i class="fas fa-receipt" style="color:var(--text-muted);font-size:2rem;"></i></div><div class="empty-state-title">Aucune transaction</div></div>
    <?php else: ?>
    <table class="table-custom">
      <thead><tr><th>Date</th><th>Type</th><th>Description</th><th>Montant</th><th>Statut</th></tr></thead>
      <tbody>
      <?php foreach ($transactions as $tx): ?>
      <tr>
        <td style="white-space:nowrap"><?= date('d/m/Y H:i', strtotime($tx['created_at'])) ?></td>
        <td><?= e(getTypeLabel($tx['type'])) ?></td>
        <td><?= e($tx['description'] ?? '—') ?></td>
        <td style="font-weight:700;color:<?= in_array($tx['type'],['deposit','daily_gain','referral_commission','admin_deposit','manual_deposit','admin_adjustment']) ? 'var(--success)' : 'var(--danger)' ?>"><?= in_array($tx['type'],['withdrawal']) ? '-' : '+' ?><?= formatAmount($tx['amount']) ?></td>
        <td><?= getStatusBadge($tx['status']) ?></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>

<style>
@media(max-width:767px){
  .stats-grid{grid-template-columns:repeat(2,1fr)!important;}
  .two-col{grid-template-columns:1fr!important;}
  .quick-grid{grid-template-columns:repeat(4,1fr)!important;}
}
@media(max-width:380px){
  .stats-grid{grid-template-columns:1fr 1fr!important;}
}
</style>
<?php renderBottomNav('dashboard'); ?>
