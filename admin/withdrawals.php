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
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $txId = (int)($_POST['tx_id'] ?? 0);
    $stmt = $db->prepare("SELECT * FROM transactions WHERE id=? AND type='withdrawal'");
    $stmt->execute([$txId]);
    $tx = $stmt->fetch();
    if (!$tx) { $error = 'Transaction introuvable.'; }
    else {
        if ($action === 'validate') {
            $db->prepare("UPDATE transactions SET status='success', updated_at=? WHERE id=?")->execute([date('Y-m-d H:i:s'), $txId]);
            addNotification($tx['user_id'], "Votre retrait de " . formatAmount($tx['amount']) . " a été validé et traité.");
            $success = "Retrait de " . formatAmount($tx['amount']) . " validé.";
        } elseif ($action === 'reject') {
            $reason = trim($_POST['reason'] ?? 'Retrait rejeté par l\'administrateur.');
            $db->prepare("UPDATE users SET balance=balance+? WHERE id=?")->execute([$tx['amount'], $tx['user_id']]);
            $db->prepare("UPDATE transactions SET status='rejected', reject_reason=?, updated_at=? WHERE id=?")->execute([$reason, date('Y-m-d H:i:s'), $txId]);
            addNotification($tx['user_id'], "Votre retrait de " . formatAmount($tx['amount']) . " a été rejeté. Motif: $reason. Votre solde a été remboursé.");
            $success = "Retrait rejeté. Le solde a été remboursé.";
        }
    }
}

$filterStatus = $_GET['status'] ?? 'pending';
$search = trim($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$where = ["t.type='withdrawal'"];
$params = [];
if ($filterStatus) { $where[] = "t.status=?"; $params[] = $filterStatus; }
if ($search) { $where[] = "(u.name LIKE ? OR u.phone LIKE ? OR t.phone LIKE ?)"; $params = array_merge($params, ["%$search%", "%$search%", "%$search%"]); }
$whereStr = 'WHERE ' . implode(' AND ', $where);

$countStmt = $db->prepare("SELECT COUNT(*) FROM transactions t JOIN users u ON t.user_id=u.id $whereStr");
$countStmt->execute($params);
$total = $countStmt->fetchColumn();
$totalPages = ceil($total / $perPage);

$stmt = $db->prepare("SELECT t.*, u.name as user_name, u.phone as user_phone, u.wallet_country as country FROM transactions t JOIN users u ON t.user_id=u.id $whereStr ORDER BY t.created_at ASC LIMIT ? OFFSET ?");
$stmt->execute(array_merge($params, [$perPage, $offset]));
$withdrawals = $stmt->fetchAll();

$pendingCount = $db->query("SELECT COUNT(*) FROM transactions WHERE type='withdrawal' AND status='pending'")->fetchColumn();
?>
<?php renderAdminHead('Retraits'); ?>
<?php renderAdminSidebar('withdrawals'); ?>
<?php renderAdminTopbar('Gestion des Retraits', $pendingCount . ' retraits en attente de traitement'); ?>

<div class="admin-page-content">
<?php if ($error): ?><div class="alert alert-danger alert-auto"><?= e($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success alert-auto"><?= e($success) ?></div><?php endif; ?>

<!-- FILTERS -->
<div class="card-custom" style="margin-bottom:20px;">
  <div class="card-custom-body">
    <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
      <div style="flex:1;min-width:180px;">
        <label class="form-label">Recherche</label>
        <div class="input-with-icon">
          <i class="fas fa-search"></i>
          <input type="text" name="search" class="form-control" placeholder="Nom, téléphone..." value="<?= e($search) ?>">
        </div>
      </div>
      <div>
        <label class="form-label">Statut</label>
        <select name="status" class="form-select">
          <option value="pending" <?= $filterStatus==='pending'?'selected':'' ?>>En attente (<?= $pendingCount ?>)</option>
          <option value="success" <?= $filterStatus==='success'?'selected':'' ?>>Traités</option>
          <option value="rejected" <?= $filterStatus==='rejected'?'selected':'' ?>>Rejetés</option>
          <option value="" <?= $filterStatus===''?'selected':'' ?>>Tous</option>
        </select>
      </div>
      <button type="submit" class="btn-primary-custom" style="padding:10px 20px;">Filtrer</button>
    </form>
  </div>
</div>

<!-- TABLE -->
<div class="card-custom">
  <div style="overflow-x:auto;">
    <?php if (empty($withdrawals)): ?>
    <div class="empty-state"><div class="empty-state-icon">✅</div><div class="empty-state-title">Aucun retrait dans cette catégorie</div></div>
    <?php else: ?>
    <table class="table-custom">
      <thead><tr><th>ID</th><th>Utilisateur</th><th>Pays</th><th>Opérateur</th><th>Montant</th><th>Numéro de retrait</th><th>Date</th><th>Statut</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach ($withdrawals as $wd): ?>
      <tr>
        <td style="font-family:monospace;font-weight:700;color:var(--text-muted);">#<?= $wd['id'] ?></td>
        <td>
          <a href="/admin/user_detail.php?id=<?= $wd['user_id'] ?>" style="color:var(--primary);font-weight:700;"><?= e($wd['user_name']) ?></a>
          <div style="font-size:0.78rem;color:var(--text-muted)"><?= e($wd['user_phone']) ?></div>
        </td>
        <td style="font-size:0.88rem;"><?= e($wd['country'] ?? '—') ?></td>
        <td style="font-size:0.88rem;font-weight:600;"><?= e($wd['operator'] ?? $wd['method'] ?? '—') ?></td>
        <td style="font-weight:800;color:var(--danger)"><?= formatAmount($wd['amount']) ?></td>
        <td>
          <div style="display:flex;align-items:center;gap:6px;">
            <span style="font-family:monospace;font-weight:700;font-size:0.88rem;"><?= e($wd['phone'] ?? '—') ?></span>
            <?php if ($wd['phone']): ?><button data-copy="<?= e($wd['phone']) ?>" style="background:var(--bg);border:1px solid var(--border);border-radius:6px;padding:3px 8px;font-size:0.72rem;cursor:pointer;font-weight:600;flex-shrink:0;">Copier</button><?php endif; ?>
          </div>
        </td>
        <td style="font-size:0.82rem;white-space:nowrap"><?= date('d/m/Y H:i', strtotime($wd['created_at'])) ?></td>
        <td><?= getStatusBadge($wd['status']) ?></td>
        <td>
          <?php if ($wd['status'] === 'pending'): ?>
          <div style="display:flex;gap:6px;flex-wrap:wrap;">
            <form method="POST" onsubmit="return confirm('Confirmer la validation de ce retrait ?')">
              <input type="hidden" name="tx_id" value="<?= $wd['id'] ?>">
              <input type="hidden" name="action" value="validate">
              <button type="submit" style="background:var(--success);color:white;border:none;padding:6px 12px;border-radius:8px;font-size:0.82rem;cursor:pointer;font-weight:600;">
                <i class="fas fa-check"></i> Valider
              </button>
            </form>
            <button onclick="openRejectModal(<?= $wd['id'] ?>)" style="background:var(--danger);color:white;border:none;padding:6px 12px;border-radius:8px;font-size:0.82rem;cursor:pointer;font-weight:600;">
              <i class="fas fa-xmark"></i> Rejeter
            </button>
          </div>
          <?php elseif ($wd['status'] === 'rejected' && $wd['reject_reason']): ?>
          <span style="font-size:0.8rem;color:var(--text-muted);">Motif: <?= e($wd['reject_reason']) ?></span>
          <?php else: ?>
          <span style="color:var(--text-muted);font-size:0.82rem;">—</span>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
  <?php if ($totalPages > 1): ?>
  <div style="padding:16px 20px;border-top:1px solid var(--border);display:flex;gap:8px;justify-content:center;">
    <?php for ($p=max(1,$page-2);$p<=min($totalPages,$page+2);$p++): ?>
    <a href="?page=<?=$p?>&status=<?=urlencode($filterStatus)?>&search=<?=urlencode($search)?>"
       style="padding:6px 12px;border-radius:8px;font-size:0.88rem;font-weight:600;<?=$p===$page?'background:var(--primary);color:white;':'background:var(--bg);'?>"><?=$p?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>
</div>

<!-- REJECT MODAL -->
<div id="reject_modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.6);z-index:9999;align-items:center;justify-content:center;padding:20px;">
  <div style="background:white;border-radius:20px;padding:32px;max-width:420px;width:100%;">
    <h3 style="margin-bottom:16px;color:var(--danger);"><i class="fas fa-circle-xmark"></i> Rejeter le retrait</h3>
    <form method="POST">
      <input type="hidden" name="tx_id" id="reject_tx_id">
      <input type="hidden" name="action" value="reject">
      <div class="form-group">
        <label class="form-label">Motif du rejet</label>
        <textarea name="reason" class="form-control" rows="3" placeholder="Expliquez pourquoi ce retrait est rejeté...">Documents insuffisants ou information incorrecte.</textarea>
      </div>
      <div style="display:flex;gap:10px;margin-top:16px;">
        <button type="submit" class="btn-primary-custom" style="background:var(--danger);">Confirmer le rejet</button>
        <button type="button" onclick="document.getElementById('reject_modal').style.display='none'" style="background:var(--bg);color:var(--text);border:1px solid var(--border);padding:12px 20px;border-radius:12px;font-weight:600;cursor:pointer;">Annuler</button>
      </div>
    </form>
  </div>
</div>
<script>
function openRejectModal(txId) {
  document.getElementById('reject_tx_id').value = txId;
  document.getElementById('reject_modal').style.display = 'flex';
}
</script>
<?php renderAdminFooter(); ?>
