<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once 'includes/admin_layout.php';
startSession();
requireAdmin();
$db = getDB();

$filterType = $_GET['type'] ?? '';
$filterStatus = $_GET['status'] ?? '';
$filterPeriod = $_GET['period'] ?? '';
$search = trim($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$offset = ($page - 1) * $perPage;

$where = ["t.type IN ('deposit','withdrawal','admin_deposit','manual_deposit','admin_adjustment')"];
$params = [];
if ($filterType) { $where = ['t.type=?']; $params[] = $filterType; }
if ($filterStatus) { $where[] = 't.status=?'; $params[] = $filterStatus; }
if ($filterPeriod === '7d') { $where[] = "t.created_at >= datetime('now','-7 days')"; }
elseif ($filterPeriod === '30d') { $where[] = "t.created_at >= datetime('now','-30 days')"; }
if ($search) { $where[] = "(u.name LIKE ? OR u.email LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
$whereStr = 'WHERE ' . implode(' AND ', $where);

$countStmt = $db->prepare("SELECT COUNT(*) FROM transactions t JOIN users u ON t.user_id=u.id $whereStr");
$countStmt->execute($params);
$total = $countStmt->fetchColumn();
$totalPages = ceil($total / $perPage);

$stmt = $db->prepare("SELECT t.*, u.name as user_name, u.email as user_email FROM transactions t JOIN users u ON t.user_id=u.id $whereStr ORDER BY t.created_at DESC LIMIT ? OFFSET ?");
$stmt->execute(array_merge($params, [$perPage, $offset]));
$txList = $stmt->fetchAll();
?>
<?php renderAdminHead('Transactions'); ?>
<?php renderAdminSidebar('transactions'); ?>
<?php renderAdminTopbar('Gestion des Transactions', $total . ' transactions au total'); ?>

<div class="admin-page-content">
<!-- FILTERS -->
<div class="card-custom" style="margin-bottom:20px;">
  <div class="card-custom-body">
    <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
      <div style="flex:1;min-width:180px;">
        <label class="form-label">Recherche</label>
        <div class="input-with-icon">
          <i class="fas fa-search"></i>
          <input type="text" name="search" class="form-control" placeholder="Nom ou email..." value="<?= e($search) ?>">
        </div>
      </div>
      <div>
        <label class="form-label">Type</label>
        <select name="type" class="form-select">
          <option value="">Tous les types</option>
          <option value="deposit" <?= $filterType==='deposit'?'selected':'' ?>>Dépôt</option>
          <option value="withdrawal" <?= $filterType==='withdrawal'?'selected':'' ?>>Retrait</option>
          <option value="admin_deposit" <?= $filterType==='admin_deposit'?'selected':'' ?>>Dépôt admin</option>
          <option value="manual_deposit" <?= $filterType==='manual_deposit'?'selected':'' ?>>Dépôt manuel</option>
        </select>
      </div>
      <div>
        <label class="form-label">Statut</label>
        <select name="status" class="form-select">
          <option value="">Tous</option>
          <option value="success" <?= $filterStatus==='success'?'selected':'' ?>>Réussi</option>
          <option value="pending" <?= $filterStatus==='pending'?'selected':'' ?>>En attente</option>
          <option value="failed" <?= $filterStatus==='failed'?'selected':'' ?>>Échoué</option>
          <option value="rejected" <?= $filterStatus==='rejected'?'selected':'' ?>>Rejeté</option>
        </select>
      </div>
      <div>
        <label class="form-label">Période</label>
        <select name="period" class="form-select">
          <option value="">Tout</option>
          <option value="7d" <?= $filterPeriod==='7d'?'selected':'' ?>>7 jours</option>
          <option value="30d" <?= $filterPeriod==='30d'?'selected':'' ?>>30 jours</option>
        </select>
      </div>
      <button type="submit" class="btn-primary-custom" style="padding:10px 20px;">Filtrer</button>
      <a href="/admin/transactions.php" style="padding:10px 16px;border:2px solid var(--border);border-radius:12px;font-weight:600;color:var(--text-muted);">Réinitialiser</a>
    </form>
  </div>
</div>

<div class="card-custom">
  <div style="overflow-x:auto;">
    <table class="table-custom">
      <thead><tr><th>Date</th><th>Utilisateur</th><th>Type</th><th>Description</th><th>Méthode/Opérateur</th><th>Montant</th><th>Statut</th></tr></thead>
      <tbody>
      <?php foreach ($txList as $tx): ?>
      <tr>
        <td style="font-size:0.82rem;white-space:nowrap"><?= date('d/m/Y H:i', strtotime($tx['created_at'])) ?></td>
        <td>
          <a href="/admin/user_detail.php?id=<?= $tx['user_id'] ?>" style="color:var(--primary);font-weight:700;"><?= e($tx['user_name']) ?></a>
          <div style="font-size:0.78rem;color:var(--text-muted)"><?= e($tx['user_email']) ?></div>
        </td>
        <td><span style="background:var(--bg);padding:4px 8px;border-radius:8px;font-size:0.8rem;font-weight:600;"><?= e(getTypeLabel($tx['type'])) ?></span></td>
        <td style="font-size:0.85rem;color:var(--text-muted);max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= e($tx['description'] ?? '—') ?></td>
        <td style="font-size:0.85rem;"><?= e($tx['operator'] ?? $tx['method'] ?? '—') ?></td>
        <td style="font-weight:800;"><?= formatAmount($tx['amount']) ?></td>
        <td><?= getStatusBadge($tx['status']) ?></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if ($totalPages > 1): ?>
  <div style="padding:16px 20px;border-top:1px solid var(--border);display:flex;gap:8px;justify-content:center;flex-wrap:wrap;">
    <?php for ($p=max(1,$page-2);$p<=min($totalPages,$page+2);$p++): ?>
    <a href="?page=<?=$p?>&type=<?=urlencode($filterType)?>&status=<?=urlencode($filterStatus)?>&period=<?=urlencode($filterPeriod)?>&search=<?=urlencode($search)?>"
       style="padding:6px 12px;border-radius:8px;font-size:0.88rem;font-weight:600;<?=$p===$page?'background:var(--primary);color:white;':'background:var(--bg);'?>"><?=$p?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>
</div>
<?php renderAdminFooter(); ?>
