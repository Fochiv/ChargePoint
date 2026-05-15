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
    $txId   = (int)($_POST['tx_id'] ?? 0);
    $tx = $db->prepare("SELECT * FROM transactions WHERE id=? AND type='deposit'")->execute([$txId]) ?
         $db->prepare("SELECT * FROM transactions WHERE id=? AND type='deposit'") : null;
    $stmt = $db->prepare("SELECT * FROM transactions WHERE id=? AND type='deposit'");
    $stmt->execute([$txId]);
    $tx = $stmt->fetch();
    if (!$tx) {
        $error = 'Transaction introuvable.';
    } else {
        if ($action === 'validate' && $tx['status'] !== 'success') {
            $db->prepare("UPDATE transactions SET status='success', updated_at=? WHERE id=?")->execute([date('Y-m-d H:i:s'), $txId]);
            $db->prepare("UPDATE users SET balance=balance+?, has_deposit=1 WHERE id=?")->execute([$tx['amount'], $tx['user_id']]);
            processReferralCommissions($tx['user_id'], $tx['amount']);
            if ($tx['plan_id']) {
                activateInvestmentPlan($tx['user_id'], $tx['plan_id'], $tx['amount']);
            }
            addNotification($tx['user_id'], "Votre dépôt de " . formatAmount($tx['amount']) . " a été validé. Votre solde a été crédité.");
            $success = "Dépôt de " . formatAmount($tx['amount']) . " validé et crédité.";
        } elseif ($action === 'reject' && $tx['status'] === 'pending') {
            $reason = trim($_POST['reason'] ?? 'Dépôt rejeté par l\'administrateur.');
            $db->prepare("UPDATE transactions SET status='rejected', reject_reason=?, updated_at=? WHERE id=?")->execute([$reason, date('Y-m-d H:i:s'), $txId]);
            addNotification($tx['user_id'], "Votre dépôt de " . formatAmount($tx['amount']) . " a été rejeté. Motif: $reason.");
            $success = "Dépôt rejeté.";
        }
    }
}

$filterStatus = $_GET['status'] ?? '';
$search = trim($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$where = ["t.type='deposit'"];
$params = [];
if ($filterStatus !== '') { $where[] = "t.status=?"; $params[] = $filterStatus; }
if ($search) { $where[] = "(u.name LIKE ? OR u.email LIKE ? OR t.reference LIKE ? OR t.ashtech_transaction_id LIKE ?)"; $params = array_merge($params, ["%$search%", "%$search%", "%$search%", "%$search%"]); }
$whereStr = 'WHERE ' . implode(' AND ', $where);

$countStmt = $db->prepare("SELECT COUNT(*) FROM transactions t JOIN users u ON t.user_id=u.id $whereStr");
$countStmt->execute($params);
$total = $countStmt->fetchColumn();
$totalPages = ceil($total / $perPage);

$stmt = $db->prepare("SELECT t.*, u.name as user_name, u.email as user_email FROM transactions t JOIN users u ON t.user_id=u.id $whereStr ORDER BY t.created_at DESC LIMIT ? OFFSET ?");
$stmt->execute(array_merge($params, [$perPage, $offset]));
$deposits = $stmt->fetchAll();

$pendingCount = $db->query("SELECT COUNT(*) FROM transactions WHERE type='deposit' AND status='pending'")->fetchColumn();
?>
<?php renderAdminHead('Dépôts'); ?>
<?php renderAdminSidebar('deposits'); ?>
<?php renderAdminTopbar('Gestion des Dépôts', $pendingCount . ' dépôt(s) en attente'); ?>

<div class="admin-page-content">
<?php if ($error): ?><div class="alert alert-danger alert-auto"><?= e($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success alert-auto"><?= e($success) ?></div><?php endif; ?>

<!-- FILTERS -->
<div class="card-custom" style="margin-bottom:20px;">
  <div class="card-custom-body">
    <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
      <div style="flex:1;min-width:200px;">
        <label class="form-label">Recherche</label>
        <div class="input-with-icon">
          <i class="fas fa-search"></i>
          <input type="text" name="search" class="form-control" placeholder="Nom, email, référence, transaction..." value="<?= e($search) ?>">
        </div>
      </div>
      <div>
        <label class="form-label">Statut</label>
        <select name="status" class="form-select">
          <option value="pending" <?= $filterStatus==='pending'?'selected':'' ?>>En attente (<?= $pendingCount ?>)</option>
          <option value="success" <?= $filterStatus==='success'?'selected':'' ?>>Validés</option>
          <option value="rejected" <?= $filterStatus==='rejected'?'selected':'' ?>>Rejetés</option>
          <option value="failed" <?= $filterStatus==='failed'?'selected':'' ?>>Échoués</option>
          <option value="" <?= $filterStatus===''?'selected':'' ?>>Tous</option>
        </select>
      </div>
      <button type="submit" class="btn-primary-custom" style="padding:10px 20px;"><i class="fas fa-search"></i> Filtrer</button>
      <a href="/admin/deposits.php" style="padding:10px 16px;border:2px solid var(--border);border-radius:12px;font-weight:600;color:var(--text-muted);">Réinitialiser</a>
    </form>
  </div>
</div>

<div class="card-custom">
  <div style="overflow-x:auto;">
    <?php if (empty($deposits)): ?>
    <div class="empty-state"><div class="empty-state-icon"><i class="fas fa-arrow-down-to-line" style="color:var(--success);font-size:2rem;"></i></div><div class="empty-state-title">Aucun dépôt dans cette catégorie</div></div>
    <?php else: ?>
    <table class="table-custom">
      <thead>
        <tr>
          <th>ID</th>
          <th>Utilisateur</th>
          <th>Montant</th>
          <th>Méthode / Opérateur</th>
          <th>N° Transaction</th>
          <th>Date</th>
          <th>Statut</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($deposits as $dep): ?>
      <tr>
        <td style="font-family:monospace;font-weight:700;color:var(--text-muted);">#<?= $dep['id'] ?></td>
        <td>
          <a href="/admin/user_detail.php?id=<?= $dep['user_id'] ?>" style="color:var(--primary);font-weight:700;"><?= e($dep['user_name']) ?></a>
          <div style="font-size:0.78rem;color:var(--text-muted)"><?= e($dep['user_email']) ?></div>
        </td>
        <td style="font-weight:800;color:var(--success);"><?= formatAmount($dep['amount']) ?></td>
        <td style="font-size:0.88rem;">
          <div style="font-weight:600;"><?= e($dep['operator'] ?? $dep['method'] ?? '—') ?></div>
          <?php if ($dep['country_code']): ?><div style="font-size:0.78rem;color:var(--text-muted)"><?= e($dep['country_code']) ?></div><?php endif; ?>
        </td>
        <td>
          <?php $txNum = $dep['ashtech_transaction_id'] ?: $dep['reference']; ?>
          <div style="display:flex;align-items:center;gap:6px;">
            <span style="font-family:monospace;font-size:0.78rem;max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= e($txNum ?? '—') ?></span>
            <?php if ($txNum): ?><button data-copy="<?= e($txNum) ?>" style="background:var(--bg);border:1px solid var(--border);border-radius:6px;padding:2px 7px;font-size:0.72rem;cursor:pointer;font-weight:600;flex-shrink:0;">Copier</button><?php endif; ?>
          </div>
        </td>
        <td style="font-size:0.82rem;white-space:nowrap"><?= date('d/m/Y H:i', strtotime($dep['created_at'])) ?></td>
        <td><?= getStatusBadge($dep['status']) ?></td>
        <td>
          <?php if ($dep['status'] === 'pending'): ?>
          <div style="display:flex;gap:6px;flex-wrap:wrap;">
            <form method="POST" onsubmit="return confirm('Valider ce dépôt et créditer le compte ?')">
              <input type="hidden" name="tx_id" value="<?= $dep['id'] ?>">
              <input type="hidden" name="action" value="validate">
              <button type="submit" style="background:var(--success);color:white;border:none;padding:6px 12px;border-radius:8px;font-size:0.82rem;cursor:pointer;font-weight:600;white-space:nowrap;">
                <i class="fas fa-circle-check"></i> Valider
              </button>
            </form>
            <button onclick="openRejectModal(<?= $dep['id'] ?>)" style="background:var(--danger);color:white;border:none;padding:6px 12px;border-radius:8px;font-size:0.82rem;cursor:pointer;font-weight:600;white-space:nowrap;">
              <i class="fas fa-xmark"></i> Rejeter
            </button>
          </div>
          <?php elseif ($dep['status'] === 'success'): ?>
          <span style="color:var(--success);font-size:0.82rem;font-weight:600;"><i class="fas fa-circle-check"></i> Validé</span>
          <?php elseif ($dep['status'] === 'rejected' && $dep['reject_reason']): ?>
          <span style="font-size:0.78rem;color:var(--text-muted);">Motif: <?= e($dep['reject_reason']) ?></span>
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
  <div style="padding:16px 20px;border-top:1px solid var(--border);display:flex;gap:8px;justify-content:center;flex-wrap:wrap;">
    <?php for ($p=max(1,$page-2);$p<=min($totalPages,$page+2);$p++): ?>
    <a href="?page=<?=$p?>&status=<?=urlencode($filterStatus)?>&search=<?=urlencode($search)?>"
       style="padding:6px 14px;border-radius:8px;font-size:0.88rem;font-weight:600;<?=$p===$page?'background:var(--primary);color:white;':'background:var(--bg);color:var(--text);border:1px solid var(--border);'?>"><?=$p?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>
</div>

<!-- REJECT MODAL -->
<div id="reject_modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.6);z-index:9999;align-items:center;justify-content:center;padding:20px;">
  <div style="background:white;border-radius:20px;padding:32px;max-width:420px;width:100%;">
    <h3 style="margin-bottom:16px;color:var(--danger);"><i class="fas fa-circle-xmark"></i> Rejeter le dépôt</h3>
    <form method="POST">
      <input type="hidden" name="tx_id" id="reject_tx_id">
      <input type="hidden" name="action" value="reject">
      <div class="form-group">
        <label class="form-label">Motif du rejet</label>
        <textarea name="reason" class="form-control" rows="3" placeholder="Expliquez pourquoi ce dépôt est rejeté...">Paiement non reçu ou informations incorrectes.</textarea>
      </div>
      <div style="display:flex;gap:10px;margin-top:16px;">
        <button type="submit" class="btn-primary-custom" style="background:var(--danger);"><i class="fas fa-xmark"></i> Confirmer le rejet</button>
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
