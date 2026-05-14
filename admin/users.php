<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once 'includes/admin_layout.php';
startSession();
requireAdmin();
$db = getDB();

$error = '';
$success = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $userId = (int)($_POST['user_id'] ?? 0);
    if (!$userId) { $error = 'Utilisateur introuvable.'; }
    else {
        switch ($action) {
            case 'suspend':
                $db->prepare("UPDATE users SET status='suspended' WHERE id=?")->execute([$userId]);
                $success = 'Compte suspendu.';
                break;
            case 'activate':
                $db->prepare("UPDATE users SET status='active' WHERE id=?")->execute([$userId]);
                $success = 'Compte activé.';
                break;
            case 'delete':
                $db->prepare("DELETE FROM transactions WHERE user_id=?")->execute([$userId]);
                $db->prepare("DELETE FROM investments WHERE user_id=?")->execute([$userId]);
                $db->prepare("DELETE FROM notifications WHERE user_id=?")->execute([$userId]);
                $db->prepare("DELETE FROM users WHERE id=?")->execute([$userId]);
                $success = 'Compte supprimé.';
                break;
            case 'admin_deposit':
                $amount = (float)($_POST['amount'] ?? 0);
                $note = trim($_POST['note'] ?? 'Dépôt administratif');
                if ($amount > 0) {
                    $db->prepare("UPDATE users SET balance=balance+?, has_deposit=1 WHERE id=?")->execute([$amount, $userId]);
                    $db->prepare("INSERT INTO transactions (user_id, type, description, amount, status, is_admin_deposit) VALUES (?, 'admin_deposit', ?, ?, 'success', 1)")
                       ->execute([$userId, $note, $amount]);
                    addNotification($userId, "Dépôt administratif de " . formatAmount($amount) . " crédité sur votre compte.");
                    $success = "Dépôt administratif de " . formatAmount($amount) . " effectué.";
                }
                break;
            case 'manual_deposit':
                $amount = (float)($_POST['amount'] ?? 0);
                $planId = (int)($_POST['plan_id'] ?? 0);
                if ($amount > 0) {
                    $db->prepare("INSERT INTO transactions (user_id, type, description, amount, status, is_admin_deposit) VALUES (?, 'manual_deposit', ?, ?, 'success', 1)")
                       ->execute([$userId, 'Dépôt manuel admin — activation VIP', $amount]);
                    if ($planId) {
                        activateInvestmentPlan($userId, $planId, $amount);
                        $success = "Dépôt manuel effectué et plan VIP activé.";
                    } else {
                        $success = "Dépôt manuel effectué.";
                    }
                    addNotification($userId, "Un dépôt manuel de " . formatAmount($amount) . " a été effectué sur votre compte.");
                }
                break;
            case 'adjust_balance':
                $amount = (float)($_POST['amount'] ?? 0);
                $type = $_POST['adjust_type'] ?? 'add';
                if ($amount > 0) {
                    $balChange = $type === 'remove' ? -$amount : $amount;
                    $db->prepare("UPDATE users SET balance=MAX(0,balance+?) WHERE id=?")->execute([$balChange, $userId]);
                    $db->prepare("INSERT INTO transactions (user_id, type, description, amount, status) VALUES (?, 'admin_adjustment', ?, ?, 'success')")
                       ->execute([$userId, ($type==='remove'?'Déduction':'Ajout').' manuel de solde', $amount]);
                    $success = "Solde ajusté de " . formatAmount($amount);
                }
                break;
        }
    }
}

$search = trim($_GET['search'] ?? '');
$filterStatus = $_GET['status'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$where = ['1=1'];
$params = [];
if ($search) { $where[] = "(name LIKE ? OR email LIKE ? OR phone LIKE ?)"; $params = array_merge($params, ["%$search%", "%$search%", "%$search%"]); }
if ($filterStatus) { $where[] = "status = ?"; $params[] = $filterStatus; }
$whereStr = implode(' AND ', $where);

$countStmt = $db->prepare("SELECT COUNT(*) FROM users WHERE $whereStr");
$countStmt->execute($params);
$total = $countStmt->fetchColumn();
$totalPages = ceil($total / $perPage);

$stmt = $db->prepare("SELECT * FROM users WHERE $whereStr ORDER BY created_at DESC LIMIT ? OFFSET ?");
$stmt->execute(array_merge($params, [$perPage, $offset]));
$users = $stmt->fetchAll();

$plans = $db->query("SELECT * FROM vip_plans WHERE is_active=1 ORDER BY amount ASC")->fetchAll();
?>
<?php renderAdminHead('Utilisateurs'); ?>
<?php renderAdminSidebar('users'); ?>
<?php renderAdminTopbar('Gestion des Utilisateurs', $total . ' utilisateurs au total'); ?>

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
          <input type="text" name="search" class="form-control" placeholder="Nom, email ou téléphone..." value="<?= e($search) ?>">
        </div>
      </div>
      <div>
        <label class="form-label">Statut</label>
        <select name="status" class="form-select">
          <option value="">Tous</option>
          <option value="active" <?= $filterStatus==='active'?'selected':'' ?>>Actifs</option>
          <option value="suspended" <?= $filterStatus==='suspended'?'selected':'' ?>>Suspendus</option>
        </select>
      </div>
      <button type="submit" class="btn-primary-custom" style="padding:10px 20px;">Filtrer</button>
    </form>
  </div>
</div>

<!-- USERS TABLE -->
<div class="card-custom">
  <div style="overflow-x:auto;">
    <table class="table-custom">
      <thead><tr><th>#</th><th>Utilisateur</th><th>Téléphone</th><th>Solde</th><th>Inscrit le</th><th>Statut</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach ($users as $u): ?>
      <tr>
        <td style="color:var(--text-muted);font-size:0.82rem;"><?= $u['id'] ?></td>
        <td>
          <div style="font-weight:700;"><?= e($u['name']) ?></div>
          <div style="font-size:0.8rem;color:var(--text-muted)"><?= e($u['email']) ?></div>
          <div style="font-size:0.78rem;color:var(--text-muted)">Code: <?= e($u['referral_code']) ?></div>
        </td>
        <td style="font-size:0.88rem;"><?= e($u['phone']) ?></td>
        <td style="font-weight:700;color:var(--primary)"><?= formatAmount($u['balance']) ?></td>
        <td style="font-size:0.82rem;"><?= date('d/m/Y', strtotime($u['created_at'])) ?></td>
        <td><?= getStatusBadge($u['status']) ?></td>
        <td>
          <div style="display:flex;gap:6px;flex-wrap:wrap;">
            <button onclick="openUserModal(<?= $u['id'] ?>, '<?= e(addslashes($u['name'])) ?>', <?= $u['balance'] ?>)" style="background:var(--primary);color:white;border:none;padding:5px 10px;border-radius:8px;font-size:0.78rem;cursor:pointer;font-weight:600;">
              <i class="fas fa-cog"></i> Gérer
            </button>
            <a href="/admin/user_detail.php?id=<?= $u['id'] ?>" style="background:var(--info);color:white;padding:5px 10px;border-radius:8px;font-size:0.78rem;font-weight:600;">
              <i class="fas fa-eye"></i>
            </a>
            <?php if ($u['status'] === 'active'): ?>
            <form method="POST" style="display:inline" onsubmit="return confirm('Suspendre ce compte ?')">
              <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
              <input type="hidden" name="action" value="suspend">
              <button type="submit" style="background:var(--warning);color:white;border:none;padding:5px 10px;border-radius:8px;font-size:0.78rem;cursor:pointer;"><i class="fas fa-ban"></i></button>
            </form>
            <?php else: ?>
            <form method="POST" style="display:inline">
              <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
              <input type="hidden" name="action" value="activate">
              <button type="submit" style="background:var(--success);color:white;border:none;padding:5px 10px;border-radius:8px;font-size:0.78rem;cursor:pointer;"><i class="fas fa-check"></i></button>
            </form>
            <?php endif; ?>
            <form method="POST" style="display:inline" onsubmit="return confirm('SUPPRIMER définitivement ce compte et toutes ses données ?')">
              <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
              <input type="hidden" name="action" value="delete">
              <button type="submit" style="background:var(--danger);color:white;border:none;padding:5px 10px;border-radius:8px;font-size:0.78rem;cursor:pointer;"><i class="fas fa-trash"></i></button>
            </form>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if ($totalPages > 1): ?>
  <div style="padding:16px 20px;border-top:1px solid var(--border);display:flex;gap:8px;flex-wrap:wrap;justify-content:center;">
    <?php for ($p = max(1, $page-2); $p <= min($totalPages, $page+2); $p++): ?>
    <a href="?page=<?= $p ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($filterStatus) ?>"
       style="padding:6px 12px;border-radius:8px;font-size:0.88rem;font-weight:600;<?= $p===$page ? 'background:var(--primary);color:white;' : 'background:var(--bg);' ?>">
      <?= $p ?>
    </a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>
</div>

<!-- USER ACTION MODAL -->
<div id="user_modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.6);z-index:9999;align-items:center;justify-content:center;padding:20px;">
  <div style="background:white;border-radius:20px;padding:32px;width:100%;max-width:500px;max-height:90vh;overflow-y:auto;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
      <h3 id="modal_user_name" style="font-size:1.1rem;font-weight:700;"></h3>
      <button onclick="closeUserModal()" style="background:none;border:none;font-size:1.3rem;cursor:pointer;color:var(--text-muted);">✕</button>
    </div>
    <input type="hidden" id="modal_user_id">

    <!-- Admin Deposit -->
    <div style="margin-bottom:20px;border:1px solid var(--border);border-radius:14px;padding:16px;">
      <div style="font-weight:700;margin-bottom:12px;color:var(--success);"><i class="fas fa-circle-plus"></i> Dépôt Administratif</div>
      <p style="font-size:0.82rem;color:var(--text-muted);margin-bottom:12px;">Permet à l'utilisateur de retirer sans dépôt préalable.</p>
      <form method="POST">
        <input type="hidden" name="user_id" id="adm_dep_uid">
        <input type="hidden" name="action" value="admin_deposit">
        <div style="display:flex;gap:8px;margin-bottom:8px;">
          <input type="number" name="amount" class="form-control" placeholder="Montant FCFA" min="1" required>
          <button type="submit" class="btn-primary-custom" style="padding:10px 16px;white-space:nowrap;background:var(--success);">Créditer</button>
        </div>
        <input type="text" name="note" class="form-control" placeholder="Note (optionnel)" value="Dépôt administratif">
      </form>
    </div>

    <!-- Manual Deposit -->
    <div style="margin-bottom:20px;border:1px solid var(--border);border-radius:14px;padding:16px;">
      <div style="font-weight:700;margin-bottom:12px;color:var(--info);"><i class="fas fa-crown"></i> Dépôt Manuel (activer VIP)</div>
      <p style="font-size:0.82rem;color:var(--text-muted);margin-bottom:12px;">Non retirable — peut activer un ou plusieurs plans VIP.</p>
      <form method="POST">
        <input type="hidden" name="user_id" id="man_dep_uid">
        <input type="hidden" name="action" value="manual_deposit">
        <div style="display:flex;gap:8px;margin-bottom:8px;">
          <input type="number" name="amount" class="form-control" placeholder="Montant FCFA" min="1" required>
          <button type="submit" class="btn-primary-custom" style="padding:10px 16px;white-space:nowrap;">Activer</button>
        </div>
        <select name="plan_id" class="form-select">
          <option value="">Sélectionner un plan VIP (optionnel)</option>
          <?php foreach ($plans as $p): ?>
          <option value="<?= $p['id'] ?>"><?= e($p['name']) ?> — <?= formatAmount($p['amount']) ?></option>
          <?php endforeach; ?>
        </select>
      </form>
    </div>

    <!-- Balance Adjustment -->
    <div style="border:1px solid var(--border);border-radius:14px;padding:16px;">
      <div style="font-weight:700;margin-bottom:12px;color:var(--warning);"><i class="fas fa-sliders"></i> Ajustement de Solde</div>
      <form method="POST">
        <input type="hidden" name="user_id" id="adj_uid">
        <input type="hidden" name="action" value="adjust_balance">
        <div style="display:flex;gap:8px;margin-bottom:8px;">
          <input type="number" name="amount" class="form-control" placeholder="Montant" min="1" required>
          <select name="adjust_type" class="form-select" style="max-width:120px;">
            <option value="add">Ajouter</option>
            <option value="remove">Retirer</option>
          </select>
          <button type="submit" class="btn-primary-custom" style="padding:10px 16px;white-space:nowrap;">OK</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function openUserModal(id, name, balance) {
  document.getElementById('modal_user_name').textContent = name + ' — Solde : ' + balance.toLocaleString('fr-FR') + ' FCFA';
  document.getElementById('modal_user_id').value = id;
  document.getElementById('adm_dep_uid').value = id;
  document.getElementById('man_dep_uid').value = id;
  document.getElementById('adj_uid').value = id;
  document.getElementById('user_modal').style.display = 'flex';
}
function closeUserModal() { document.getElementById('user_modal').style.display = 'none'; }
</script>
<?php renderAdminFooter(); ?>
