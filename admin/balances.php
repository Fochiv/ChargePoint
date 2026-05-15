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
    $userId    = (int)($_POST['user_id'] ?? 0);
    $action    = $_POST['balance_action'] ?? '';
    $amount    = (float)($_POST['amount'] ?? 0);
    $note      = trim($_POST['note'] ?? 'Ajustement administrateur');

    if (!$userId || $amount <= 0) {
        $error = 'Utilisateur et montant requis.';
    } else {
        $targetUser = $db->prepare("SELECT * FROM users WHERE id=?");
        $targetUser->execute([$userId]);
        $targetUser = $targetUser->fetch();
        if (!$targetUser) {
            $error = 'Utilisateur introuvable.';
        } else {
            if ($action === 'add') {
                $db->prepare("UPDATE users SET balance=balance+? WHERE id=?")->execute([$amount, $userId]);
                $db->prepare("INSERT INTO transactions (user_id, type, description, amount, status, is_admin_deposit) VALUES (?, 'admin_deposit', ?, ?, 'success', 1)")
                   ->execute([$userId, $note, $amount]);
                addNotification($userId, "Un ajustement de solde de " . formatAmount($amount) . " a été effectué sur votre compte.");
                $success = "+" . formatAmount($amount) . " ajouté au compte de " . e($targetUser['name']) . ".";
            } elseif ($action === 'subtract') {
                if ($targetUser['balance'] < $amount) {
                    $error = "Solde insuffisant. Solde actuel : " . formatAmount($targetUser['balance']);
                } else {
                    $db->prepare("UPDATE users SET balance=balance-? WHERE id=?")->execute([$amount, $userId]);
                    $db->prepare("INSERT INTO transactions (user_id, type, description, amount, status) VALUES (?, 'admin_adjustment', ?, ?, 'success')")
                       ->execute([$userId, $note, $amount]);
                    addNotification($userId, "Un ajustement de solde de -" . formatAmount($amount) . " a été effectué sur votre compte.");
                    $success = "-" . formatAmount($amount) . " déduit du compte de " . e($targetUser['name']) . ".";
                }
            }
        }
    }
}

$search = trim($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$offset = ($page - 1) * $perPage;

$where = ['1=1'];
$params = [];
if ($search) { $where[] = "(u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)"; $params = array_merge($params, ["%$search%", "%$search%", "%$search%"]); }
$whereStr = 'WHERE ' . implode(' AND ', $where);

$countStmt = $db->prepare("SELECT COUNT(*) FROM users u $whereStr");
$countStmt->execute($params);
$total = $countStmt->fetchColumn();
$totalPages = ceil($total / $perPage);

$stmt = $db->prepare("SELECT u.*, (SELECT COALESCE(SUM(amount),0) FROM transactions WHERE user_id=u.id AND type='deposit' AND status='success') as total_deposited, (SELECT COALESCE(SUM(amount),0) FROM transactions WHERE user_id=u.id AND type='withdrawal' AND status='success') as total_withdrawn FROM users u $whereStr ORDER BY u.balance DESC LIMIT ? OFFSET ?");
$stmt->execute(array_merge($params, [$perPage, $offset]));
$users = $stmt->fetchAll();

$totalBalance = $db->query("SELECT COALESCE(SUM(balance),0) FROM users")->fetchColumn();
?>
<?php renderAdminHead('Gestion des Soldes'); ?>
<?php renderAdminSidebar('balances'); ?>
<?php renderAdminTopbar('Gestion des Soldes', 'Solde total plateforme : ' . formatAmount((float)$totalBalance)); ?>

<div class="admin-page-content">
<?php if ($error): ?><div class="alert alert-danger alert-auto"><?= e($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success alert-auto"><?= e($success) ?></div><?php endif; ?>

<!-- ADJUSTMENT FORM -->
<div class="card-custom" style="margin-bottom:24px;">
  <div class="card-custom-header"><h5><i class="fas fa-sliders" style="color:var(--primary)"></i> Ajuster le solde d'un utilisateur</h5></div>
  <div class="card-custom-body">
    <form method="POST" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
      <div style="flex:2;min-width:220px;">
        <label class="form-label">Utilisateur (ID ou recherche)</label>
        <div class="input-with-icon">
          <i class="fas fa-user"></i>
          <input type="number" name="user_id" id="adj_user_id" class="form-control" placeholder="ID utilisateur" required min="1">
        </div>
        <div style="font-size:0.8rem;color:var(--text-muted);margin-top:4px;">Cliquez sur un utilisateur dans le tableau pour le sélectionner</div>
      </div>
      <div style="flex:1;min-width:140px;">
        <label class="form-label">Montant (FCFA)</label>
        <input type="number" name="amount" class="form-control" placeholder="Ex: 5000" required min="1">
      </div>
      <div>
        <label class="form-label">Action</label>
        <select name="balance_action" class="form-select">
          <option value="add">Ajouter (+)</option>
          <option value="subtract">Déduire (−)</option>
        </select>
      </div>
      <div style="flex:2;min-width:200px;">
        <label class="form-label">Note / Motif</label>
        <input type="text" name="note" class="form-control" value="Ajustement administrateur" placeholder="Raison de l'ajustement">
      </div>
      <button type="submit" class="btn-primary-custom" style="padding:12px 24px;"><i class="fas fa-sliders"></i> Appliquer</button>
    </form>
  </div>
</div>

<!-- SEARCH -->
<div class="card-custom" style="margin-bottom:20px;">
  <div class="card-custom-body">
    <form method="GET" style="display:flex;gap:12px;align-items:flex-end;">
      <div style="flex:1;">
        <label class="form-label">Rechercher un utilisateur</label>
        <div class="input-with-icon">
          <i class="fas fa-search"></i>
          <input type="text" name="search" class="form-control" placeholder="Nom, email, téléphone..." value="<?= e($search) ?>">
        </div>
      </div>
      <button type="submit" class="btn-primary-custom" style="padding:10px 20px;"><i class="fas fa-search"></i> Rechercher</button>
      <?php if ($search): ?><a href="/admin/balances.php" style="padding:10px 16px;border:2px solid var(--border);border-radius:12px;font-weight:600;color:var(--text-muted);">Réinitialiser</a><?php endif; ?>
    </form>
  </div>
</div>

<div class="card-custom">
  <div style="overflow-x:auto;">
    <table class="table-custom">
      <thead>
        <tr>
          <th>ID</th>
          <th>Utilisateur</th>
          <th>Téléphone</th>
          <th>Solde actuel</th>
          <th>Total déposé</th>
          <th>Total retiré</th>
          <th>Gains totaux</th>
          <th>Statut</th>
          <th>Action rapide</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($users as $u): ?>
      <tr style="cursor:pointer;" onclick="document.getElementById('adj_user_id').value='<?= $u['id'] ?>';document.getElementById('adj_user_id').focus();window.scrollTo(0,0);">
        <td style="font-family:monospace;color:var(--text-muted);">#<?= $u['id'] ?></td>
        <td>
          <a href="/admin/user_detail.php?id=<?= $u['id'] ?>" style="color:var(--primary);font-weight:700;" onclick="event.stopPropagation();"><?= e($u['name']) ?></a>
          <div style="font-size:0.78rem;color:var(--text-muted)"><?= e($u['email']) ?></div>
        </td>
        <td style="font-size:0.85rem;"><?= e($u['phone']) ?></td>
        <td style="font-weight:800;font-size:1rem;color:<?= $u['balance'] > 0 ? 'var(--success)' : 'var(--text-muted)' ?>"><?= formatAmount($u['balance']) ?></td>
        <td style="font-size:0.88rem;"><?= formatAmount((float)$u['total_deposited']) ?></td>
        <td style="font-size:0.88rem;"><?= formatAmount((float)$u['total_withdrawn']) ?></td>
        <td style="font-size:0.88rem;color:var(--success);"><?= formatAmount($u['total_earnings']) ?></td>
        <td><?= getStatusBadge($u['status']) ?></td>
        <td onclick="event.stopPropagation();">
          <button onclick="selectUser(<?= $u['id'] ?>,'<?= e(addslashes($u['name'])) ?>')" style="background:var(--primary);color:white;border:none;padding:6px 12px;border-radius:8px;font-size:0.82rem;cursor:pointer;font-weight:600;">
            <i class="fas fa-pen-to-square"></i> Sélectionner
          </button>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if ($totalPages > 1): ?>
  <div style="padding:16px 20px;border-top:1px solid var(--border);display:flex;gap:8px;justify-content:center;flex-wrap:wrap;">
    <?php for ($p=max(1,$page-2);$p<=min($totalPages,$page+2);$p++): ?>
    <a href="?page=<?=$p?>&search=<?=urlencode($search)?>"
       style="padding:6px 14px;border-radius:8px;font-size:0.88rem;font-weight:600;<?=$p===$page?'background:var(--primary);color:white;':'background:var(--bg);color:var(--text);border:1px solid var(--border);'?>"><?=$p?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>
</div>
<script>
function selectUser(id, name) {
  document.getElementById('adj_user_id').value = id;
  window.scrollTo({top: 0, behavior: 'smooth'});
  document.getElementById('adj_user_id').focus();
  document.getElementById('adj_user_id').style.border = '2px solid var(--primary)';
  setTimeout(function(){ document.getElementById('adj_user_id').style.border = ''; }, 2000);
}
</script>
<?php renderAdminFooter(); ?>
