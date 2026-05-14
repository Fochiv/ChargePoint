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

$db->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?")->execute([$user['id']]);
$notifs = $db->prepare("SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 50");
$notifs->execute([$user['id']]);
$notifications = $notifs->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Notifications — ChargePoint</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<?php renderAppLayout($user, ''); ?>

<div class="page-title">Notifications</div>
<div class="page-subtitle">Vos dernières alertes et informations.</div>

<div class="card-custom">
  <?php if (empty($notifications)): ?>
  <div class="empty-state"><div class="empty-state-icon">🔔</div><div class="empty-state-title">Aucune notification</div></div>
  <?php else: ?>
  <?php foreach ($notifications as $n): ?>
  <div style="padding:16px 20px;border-bottom:1px solid var(--border);display:flex;gap:14px;align-items:flex-start;">
    <div style="width:40px;height:40px;background:rgba(255,107,0,0.1);border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.1rem;flex-shrink:0;">🔔</div>
    <div style="flex:1;">
      <div style="font-size:0.9rem;"><?= e($n['message']) ?></div>
      <div style="font-size:0.78rem;color:var(--text-muted);margin-top:4px;"><?= date('d/m/Y H:i', strtotime($n['created_at'])) ?></div>
    </div>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>
</div>

<?php renderBottomNav(''); ?>
<script src="/assets/js/main.js"></script>
</body>
</html>
