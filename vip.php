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

$plans = $db->query("SELECT * FROM vip_plans WHERE is_active=1 ORDER BY amount ASC")->fetchAll();

$activeInv = $db->prepare("SELECT i.*, p.name as plan_name FROM investments i JOIN vip_plans p ON i.plan_id=p.id WHERE i.user_id=? AND i.status='active'");
$activeInv->execute([$user['id']]);
$activeInvestments = $activeInv->fetchAll();
$activePlanIds = array_column($activeInvestments, 'plan_id');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/jpeg" href="/assets/logo.jpg">
<title>Plans VIP — ChargePoint</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>

<?php renderAppLayout($user, 'vip'); ?>

<div class="page-title">Plans d'Investissement <span style="color:var(--primary)">VIP</span></div>
<div class="page-subtitle">Choisissez votre plan et commencez à générer des revenus journaliers dès aujourd'hui.</div>

<?php if (!empty($activeInvestments)): ?>
<div class="card-custom" style="margin-bottom:24px;">
  <div class="card-custom-header"><h5><i class="fas fa-crown" style="color:var(--primary)"></i> Vos Plans Actifs</h5></div>
  <div style="overflow-x:auto;">
    <table class="table-custom">
      <thead><tr><th>Plan</th><th>Montant investi</th><th>Gain/Jour</th><th>Jours restants</th><th>Progression</th><th>Statut</th></tr></thead>
      <tbody>
      <?php foreach ($activeInvestments as $inv): ?>
      <tr>
        <td><strong><?= e($inv['plan_name']) ?></strong></td>
        <td><?= formatAmount($inv['amount']) ?></td>
        <td style="color:var(--success);font-weight:700"><?= formatAmount($inv['daily_gain']) ?></td>
        <td><strong><?= $inv['days_remaining'] ?></strong> / <?= $inv['days_total'] ?> jours</td>
        <td style="min-width:120px">
          <div style="background:var(--bg);border-radius:8px;height:8px;overflow:hidden;">
            <div style="height:100%;background:linear-gradient(90deg,var(--primary),var(--primary-light));width:<?= round(($inv['days_total']-$inv['days_remaining'])/$inv['days_total']*100) ?>%"></div>
          </div>
          <div style="font-size:0.75rem;color:var(--text-muted);margin-top:3px;"><?= round(($inv['days_total']-$inv['days_remaining'])/$inv['days_total']*100) ?>% complété</div>
        </td>
        <td><?= getStatusBadge($inv['status']) ?></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:20px;">
  <?php foreach ($plans as $i => $plan): ?>
  <?php $isActive = in_array($plan['id'], $activePlanIds); ?>
  <div class="vip-card <?= $i===3?'popular':'' ?>">
    <?php if ($i===3): ?><span class="vip-badge">🔥 Populaire</span><?php endif; ?>
    <?php if ($isActive): ?><span class="vip-badge" style="background:var(--success);">✅ Actif</span><?php endif; ?>
    <div class="vip-name"><?= e($plan['name']) ?></div>
    <div class="vip-amount"><?= formatAmount($plan['amount']) ?></div>
    <div class="vip-gain"><i class="fas fa-arrow-trend-up"></i> +<?= formatAmount($plan['daily_gain']) ?> / jour</div>
    <div class="vip-details">
      <div class="vip-detail-row"><span><i class="fas fa-coins" style="color:var(--primary)"></i> Gain total</span><span style="color:var(--success);font-weight:700"><?= formatAmount($plan['total_gain']) ?></span></div>
      <div class="vip-detail-row"><span><i class="fas fa-calendar" style="color:var(--primary)"></i> Durée</span><span><?= $plan['duration_days'] ?> jours</span></div>
      <div class="vip-detail-row"><span><i class="fas fa-percent" style="color:var(--primary)"></i> ROI</span><span style="color:var(--info)"><?= round(($plan['total_gain']/$plan['amount']-1)*100) ?>%</span></div>
    </div>
    <?php if ($isActive): ?>
    <div style="margin-top:16px;text-align:center;background:rgba(16,185,129,0.1);color:var(--success);padding:10px;border-radius:10px;font-weight:700;font-size:0.9rem;">
      <i class="fas fa-circle-check"></i> Plan en cours
    </div>
    <?php else: ?>
    <a href="/deposit.php?plan=<?= $plan['id'] ?>&amount=<?= $plan['amount'] ?>" style="display:block;text-align:center;margin-top:16px;background:linear-gradient(135deg,var(--primary),var(--primary-dark));color:white;padding:12px;border-radius:10px;font-weight:700;font-size:0.9rem;transition:all 0.2s;">
      <i class="fas fa-rocket"></i> Investir maintenant
    </a>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
</div>

<?php renderBottomNav('vip'); ?>
