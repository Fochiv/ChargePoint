<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
startSession();
requireLogin();
$user = getCurrentUser();
$db = getDB();

$perPage = (int)($_GET['per_page'] ?? 10);
if (!in_array($perPage, [10, 25, 50])) $perPage = 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

$filterType = $_GET['type'] ?? '';
$filterStatus = $_GET['status'] ?? '';
$filterPeriod = $_GET['period'] ?? '';

$where = ['user_id = ?'];
$params = [$user['id']];
if ($filterType) { $where[] = 'type = ?'; $params[] = $filterType; }
if ($filterStatus) { $where[] = 'status = ?'; $params[] = $filterStatus; }
if ($filterPeriod === '7d') { $where[] = "created_at >= datetime('now', '-7 days')"; }
elseif ($filterPeriod === '30d') { $where[] = "created_at >= datetime('now', '-30 days')"; }
elseif ($filterPeriod === '3m') { $where[] = "created_at >= datetime('now', '-3 months')"; }

$whereStr = 'WHERE ' . implode(' AND ', $where);
$total = $db->prepare("SELECT COUNT(*) FROM transactions $whereStr")->execute($params) ? $db->prepare("SELECT COUNT(*) FROM transactions $whereStr")->execute($params) : 0;
$countStmt = $db->prepare("SELECT COUNT(*) FROM transactions $whereStr");
$countStmt->execute($params);
$total = $countStmt->fetchColumn();

$stmt = $db->prepare("SELECT * FROM transactions $whereStr ORDER BY created_at DESC LIMIT ? OFFSET ?");
$stmt->execute(array_merge($params, [$perPage, $offset]));
$txList = $stmt->fetchAll();
$totalPages = ceil($total / $perPage);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Transactions — ChargePoint</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<?php renderAppLayout($user, 'transactions'); ?>

<div class="page-title">Historique des Transactions</div>
<div class="page-subtitle">Consultez l'ensemble de vos opérations financières.</div>

<!-- FILTERS -->
<div class="card-custom" style="margin-bottom:20px;">
  <div class="card-custom-body">
    <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
      <div>
        <label class="form-label">Type</label>
        <select name="type" class="form-select" style="min-width:160px;">
          <option value="">Tous les types</option>
          <option value="deposit" <?= $filterType==='deposit'?'selected':'' ?>>Dépôt</option>
          <option value="withdrawal" <?= $filterType==='withdrawal'?'selected':'' ?>>Retrait</option>
          <option value="daily_gain" <?= $filterType==='daily_gain'?'selected':'' ?>>Gain journalier</option>
          <option value="referral_commission" <?= $filterType==='referral_commission'?'selected':'' ?>>Parrainage</option>
          <option value="admin_deposit" <?= $filterType==='admin_deposit'?'selected':'' ?>>Dépôt administratif</option>
        </select>
      </div>
      <div>
        <label class="form-label">Statut</label>
        <select name="status" class="form-select" style="min-width:140px;">
          <option value="">Tous</option>
          <option value="success" <?= $filterStatus==='success'?'selected':'' ?>>Réussi</option>
          <option value="pending" <?= $filterStatus==='pending'?'selected':'' ?>>En attente</option>
          <option value="failed" <?= $filterStatus==='failed'?'selected':'' ?>>Échoué</option>
          <option value="rejected" <?= $filterStatus==='rejected'?'selected':'' ?>>Rejeté</option>
        </select>
      </div>
      <div>
        <label class="form-label">Période</label>
        <select name="period" class="form-select" style="min-width:140px;">
          <option value="">Tout</option>
          <option value="7d" <?= $filterPeriod==='7d'?'selected':'' ?>>7 derniers jours</option>
          <option value="30d" <?= $filterPeriod==='30d'?'selected':'' ?>>30 derniers jours</option>
          <option value="3m" <?= $filterPeriod==='3m'?'selected':'' ?>>3 derniers mois</option>
        </select>
      </div>
      <div>
        <label class="form-label">Par page</label>
        <select name="per_page" class="form-select">
          <option value="10" <?= $perPage===10?'selected':'' ?>>10</option>
          <option value="25" <?= $perPage===25?'selected':'' ?>>25</option>
          <option value="50" <?= $perPage===50?'selected':'' ?>>50</option>
        </select>
      </div>
      <button type="submit" class="btn-primary-custom" style="padding:10px 20px;">Filtrer</button>
      <a href="/transactions.php" style="padding:10px 20px;border:2px solid var(--border);border-radius:12px;color:var(--text-muted);font-weight:600;">Réinitialiser</a>
    </form>
  </div>
</div>

<div class="card-custom">
  <div style="overflow-x:auto;">
    <?php if (empty($txList)): ?>
    <div class="empty-state"><div class="empty-state-icon">📋</div><div class="empty-state-title">Aucune transaction trouvée</div></div>
    <?php else: ?>
    <table class="table-custom">
      <thead><tr><th>Date</th><th>Type</th><th>Description</th><th>Montant</th><th>Statut</th></tr></thead>
      <tbody>
      <?php foreach ($txList as $tx): ?>
      <?php $isCredit = in_array($tx['type'], ['deposit','daily_gain','referral_commission','admin_deposit','manual_deposit','admin_adjustment']); ?>
      <tr>
        <td style="white-space:nowrap;font-size:0.85rem;"><?= date('d/m/Y H:i', strtotime($tx['created_at'])) ?></td>
        <td><span style="background:var(--bg);padding:4px 10px;border-radius:8px;font-size:0.82rem;font-weight:600;"><?= e(getTypeLabel($tx['type'])) ?></span></td>
        <td style="font-size:0.88rem;color:var(--text-muted)"><?= e($tx['description'] ?? '—') ?></td>
        <td style="font-weight:700;color:<?= $isCredit ? 'var(--success)' : 'var(--danger)' ?>;white-space:nowrap">
          <?= $isCredit ? '+' : '-' ?><?= formatAmount($tx['amount']) ?>
        </td>
        <td><?= getStatusBadge($tx['status']) ?></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>

  <?php if ($totalPages > 1): ?>
  <div style="padding:16px 20px;border-top:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
    <div style="font-size:0.85rem;color:var(--text-muted)">Affichage <?= $offset+1 ?>–<?= min($offset+$perPage, $total) ?> sur <?= $total ?> transactions</div>
    <div style="display:flex;gap:8px;">
      <?php for ($p = max(1, $page-2); $p <= min($totalPages, $page+2); $p++): ?>
      <a href="?page=<?= $p ?>&type=<?= urlencode($filterType) ?>&status=<?= urlencode($filterStatus) ?>&period=<?= urlencode($filterPeriod) ?>&per_page=<?= $perPage ?>"
         style="padding:6px 12px;border-radius:8px;font-size:0.88rem;font-weight:600;<?= $p===$page ? 'background:var(--primary);color:white;' : 'background:var(--bg);color:var(--text);' ?>">
        <?= $p ?>
      </a>
      <?php endfor; ?>
    </div>
  </div>
  <?php endif; ?>
</div>

<?php renderBottomNav('transactions'); ?>
<script src="/assets/js/main.js"></script>
</body>
</html>
