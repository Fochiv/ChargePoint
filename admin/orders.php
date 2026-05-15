<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once 'includes/admin_layout.php';
startSession();
requireAdmin();
$db = getDB();

$search = trim($_GET['search'] ?? '');
$filterStatus = $_GET['status'] ?? 'active';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$offset = ($page - 1) * $perPage;

$where = ['1=1'];
$params = [];
if ($filterStatus) { $where[] = "i.status=?"; $params[] = $filterStatus; }
if ($search) { $where[] = "(u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)"; $params = array_merge($params, ["%$search%", "%$search%", "%$search%"]); }
$whereStr = 'WHERE ' . implode(' AND ', $where);

$countStmt = $db->prepare("SELECT COUNT(*) FROM investments i JOIN users u ON i.user_id=u.id JOIN vip_plans p ON i.plan_id=p.id $whereStr");
$countStmt->execute($params);
$total = $countStmt->fetchColumn();
$totalPages = ceil($total / $perPage);

$stmt = $db->prepare("SELECT i.*, u.name as user_name, u.email as user_email, u.phone as user_phone, p.name as plan_name FROM investments i JOIN users u ON i.user_id=u.id JOIN vip_plans p ON i.plan_id=p.id $whereStr ORDER BY i.started_at DESC LIMIT ? OFFSET ?");
$stmt->execute(array_merge($params, [$perPage, $offset]));
$orders = $stmt->fetchAll();

$activeCount = $db->query("SELECT COUNT(*) FROM investments WHERE status='active'")->fetchColumn();
$totalGains = $db->query("SELECT COALESCE(SUM(daily_gain),0) FROM investments WHERE status='active'")->fetchColumn();
?>
<?php renderAdminHead('Commandes'); ?>
<?php renderAdminSidebar('orders'); ?>
<?php renderAdminTopbar('Commandes VIP', $activeCount . ' plan(s) actif(s) — Gains/jour : ' . formatAmount((float)$totalGains)); ?>

<div class="admin-page-content">

<!-- STATS -->
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:24px;" class="admin-stats">
  <div class="stat-card">
    <div class="stat-card-icon icon-orange"><i class="fas fa-crown"></i></div>
    <div class="stat-card-value"><?= $activeCount ?></div>
    <div class="stat-card-label">Plans actifs</div>
  </div>
  <div class="stat-card">
    <div class="stat-card-icon icon-green"><i class="fas fa-coins"></i></div>
    <div class="stat-card-value" style="font-size:1rem;"><?= formatAmount((float)$totalGains) ?></div>
    <div class="stat-card-label">Gains journaliers totaux</div>
  </div>
  <div class="stat-card">
    <div class="stat-card-icon icon-blue"><i class="fas fa-users"></i></div>
    <div class="stat-card-value"><?= $db->query("SELECT COUNT(DISTINCT user_id) FROM investments WHERE status='active'")->fetchColumn() ?></div>
    <div class="stat-card-label">Investisseurs actifs</div>
  </div>
</div>

<!-- FILTERS -->
<div class="card-custom" style="margin-bottom:20px;">
  <div class="card-custom-body">
    <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
      <div style="flex:1;min-width:200px;">
        <label class="form-label">Recherche</label>
        <div class="input-with-icon">
          <i class="fas fa-search"></i>
          <input type="text" name="search" class="form-control" placeholder="Nom, email, téléphone..." value="<?= e($search) ?>">
        </div>
      </div>
      <div>
        <label class="form-label">Statut</label>
        <select name="status" class="form-select">
          <option value="active" <?= $filterStatus==='active'?'selected':'' ?>>Actifs</option>
          <option value="completed" <?= $filterStatus==='completed'?'selected':'' ?>>Terminés</option>
          <option value="" <?= $filterStatus===''?'selected':'' ?>>Tous</option>
        </select>
      </div>
      <button type="submit" class="btn-primary-custom" style="padding:10px 20px;"><i class="fas fa-search"></i> Filtrer</button>
      <a href="/admin/orders.php" style="padding:10px 16px;border:2px solid var(--border);border-radius:12px;font-weight:600;color:var(--text-muted);">Réinitialiser</a>
    </form>
  </div>
</div>

<div class="card-custom">
  <div style="overflow-x:auto;">
    <?php if (empty($orders)): ?>
    <div class="empty-state"><div class="empty-state-icon"><i class="fas fa-crown" style="color:var(--primary);font-size:2rem;"></i></div><div class="empty-state-title">Aucune commande trouvée</div></div>
    <?php else: ?>
    <table class="table-custom">
      <thead>
        <tr>
          <th>ID</th>
          <th>Utilisateur</th>
          <th>Plan</th>
          <th>Montant investi</th>
          <th>Gain/Jour</th>
          <th>Jours restants</th>
          <th>Démarré le</th>
          <th>Statut</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($orders as $order): ?>
      <?php $progress = $order['days_total'] > 0 ? round(($order['days_total'] - $order['days_remaining']) / $order['days_total'] * 100) : 0; ?>
      <tr>
        <td style="font-family:monospace;font-weight:700;color:var(--text-muted);">#<?= $order['id'] ?></td>
        <td>
          <a href="/admin/user_detail.php?id=<?= $order['user_id'] ?>" style="color:var(--primary);font-weight:700;"><?= e($order['user_name']) ?></a>
          <div style="font-size:0.78rem;color:var(--text-muted)"><?= e($order['user_phone']) ?></div>
        </td>
        <td>
          <span style="background:rgba(255,107,0,0.1);color:var(--primary);padding:4px 10px;border-radius:8px;font-size:0.85rem;font-weight:700;"><?= e($order['plan_name']) ?></span>
        </td>
        <td style="font-weight:800;"><?= formatAmount($order['amount']) ?></td>
        <td style="font-weight:700;color:var(--success);"><?= formatAmount($order['daily_gain']) ?></td>
        <td>
          <div style="margin-bottom:4px;font-weight:600;"><?= $order['days_remaining'] ?> / <?= $order['days_total'] ?> jours</div>
          <div style="background:var(--bg);border-radius:6px;height:5px;overflow:hidden;width:80px;">
            <div style="height:100%;background:linear-gradient(90deg,var(--primary),var(--primary-light));width:<?= $progress ?>%;"></div>
          </div>
        </td>
        <td style="font-size:0.82rem;white-space:nowrap"><?= date('d/m/Y', strtotime($order['started_at'])) ?></td>
        <td><?= getStatusBadge($order['status']) ?></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
  <?php if ($totalPages > 1): ?>
  <div style="padding:16px 20px;border-top:1px solid var(--border);display:flex;gap:8px;justify-content:center;flex-wrap:wrap;">
    <?php for ($p=max(1,$page-2);$p<=min($totalPages,$page+2);$p++): ?>
    <a href="?page=<?=$p?>&status=<?=urlencode($filterStatus)?>&search=<?=urlencode($search)?>"
       style="padding:6px 14px;border-radius:8px;font-size:0.88rem;font-weight:600;<?=$p===$page?'background:var(--primary);color:white;':'background:var(--bg);color:var(--text);border:1px solid var(--border);'?>"><?=$p?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>
</div>
<?php renderAdminFooter(); ?>
