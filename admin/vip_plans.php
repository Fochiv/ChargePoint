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
    if ($action === 'update') {
        $planId = (int)($_POST['plan_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $amount = (float)($_POST['amount'] ?? 0);
        $dailyGain = (float)($_POST['daily_gain'] ?? 0);
        $totalGain = (float)($_POST['total_gain'] ?? 0);
        $duration = (int)($_POST['duration_days'] ?? 125);
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        if ($amount > 0 && $dailyGain > 0) {
            $db->prepare("UPDATE vip_plans SET name=?, amount=?, daily_gain=?, total_gain=?, duration_days=?, is_active=? WHERE id=?")
               ->execute([$name, $amount, $dailyGain, $totalGain, $duration, $isActive, $planId]);
            $success = 'Plan mis à jour avec succès.';
        }
    } elseif ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        $amount = (float)($_POST['amount'] ?? 0);
        $dailyGain = (float)($_POST['daily_gain'] ?? 0);
        $totalGain = $dailyGain * 125;
        $duration = (int)($_POST['duration_days'] ?? 125);
        if ($name && $amount > 0 && $dailyGain > 0) {
            $db->prepare("INSERT INTO vip_plans (name, amount, daily_gain, total_gain, duration_days) VALUES (?,?,?,?,?)")
               ->execute([$name, $amount, $dailyGain, $totalGain, $duration]);
            $success = 'Nouveau plan créé.';
        }
    } elseif ($action === 'toggle') {
        $planId = (int)($_POST['plan_id'] ?? 0);
        $db->prepare("UPDATE vip_plans SET is_active = 1 - is_active WHERE id=?")->execute([$planId]);
        $success = 'Statut du plan mis à jour.';
    }
}

$plans = $db->query("SELECT p.*, (SELECT COUNT(*) FROM investments WHERE plan_id=p.id AND status='active') as active_count FROM vip_plans p ORDER BY p.amount ASC")->fetchAll();
?>
<?php renderAdminHead('Plans VIP'); ?>
<?php renderAdminSidebar('vip_plans'); ?>
<?php renderAdminTopbar('Gestion des Plans VIP', 'Créez et modifiez les plans d\'investissement'); ?>

<div class="admin-page-content">
<?php if ($success): ?><div class="alert alert-success alert-auto"><?= e($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger alert-auto"><?= e($error) ?></div><?php endif; ?>

<!-- CREATE NEW PLAN -->
<div class="card-custom" style="margin-bottom:24px;">
  <div class="card-custom-header"><h5><i class="fas fa-circle-plus" style="color:var(--success)"></i> Créer un Nouveau Plan</h5></div>
  <div class="card-custom-body">
    <form method="POST" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:12px;align-items:flex-end;">
      <input type="hidden" name="action" value="create">
      <div><label class="form-label">Nom</label><input type="text" name="name" class="form-control" placeholder="VIP 11" required></div>
      <div><label class="form-label">Montant (FCFA)</label><input type="number" name="amount" class="form-control" placeholder="500000" required></div>
      <div><label class="form-label">Gain/Jour (FCFA)</label><input type="number" name="daily_gain" class="form-control" placeholder="100000" required></div>
      <div><label class="form-label">Durée (jours)</label><input type="number" name="duration_days" class="form-control" value="125" required></div>
      <div style="grid-column:span 1;"><button type="submit" class="btn-primary-custom" style="width:100%;padding:12px;"><i class="fas fa-plus"></i> Créer</button></div>
    </form>
  </div>
</div>

<!-- PLANS TABLE -->
<div class="card-custom">
  <div style="overflow-x:auto;">
    <table class="table-custom">
      <thead><tr><th>Plan</th><th>Montant</th><th>Gain/Jour</th><th>Gain Total</th><th>Durée</th><th>ROI</th><th>Actifs</th><th>Statut</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach ($plans as $plan): ?>
      <tr>
        <td style="font-weight:700;color:var(--primary)"><?= e($plan['name']) ?></td>
        <td style="font-weight:700"><?= formatAmount($plan['amount']) ?></td>
        <td style="color:var(--success);font-weight:700"><?= formatAmount($plan['daily_gain']) ?></td>
        <td><?= formatAmount($plan['total_gain']) ?></td>
        <td><?= $plan['duration_days'] ?> jours</td>
        <td style="color:var(--info);"><?= round(($plan['total_gain']/$plan['amount']-1)*100) ?>%</td>
        <td><span style="background:rgba(255,107,0,0.1);color:var(--primary);padding:3px 10px;border-radius:10px;font-weight:700;"><?= $plan['active_count'] ?></span></td>
        <td><?= $plan['is_active'] ? '<span class="badge bg-success">Actif</span>' : '<span class="badge bg-secondary">Inactif</span>' ?></td>
        <td>
          <div style="display:flex;gap:6px;">
            <button onclick="openEditPlan(<?= htmlspecialchars(json_encode($plan), ENT_QUOTES) ?>)" style="background:var(--info);color:white;border:none;padding:5px 10px;border-radius:8px;font-size:0.82rem;cursor:pointer;font-weight:600;"><i class="fas fa-pen-to-square"></i></button>
            <form method="POST" style="display:inline">
              <input type="hidden" name="action" value="toggle">
              <input type="hidden" name="plan_id" value="<?= $plan['id'] ?>">
              <button type="submit" style="background:<?= $plan['is_active'] ? 'var(--warning)' : 'var(--success)' ?>;color:white;border:none;padding:5px 10px;border-radius:8px;font-size:0.82rem;cursor:pointer;">
                <?= $plan['is_active'] ? '<i class="fas fa-pause"></i>' : '<i class="fas fa-play"></i>' ?>
              </button>
            </form>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
</div>

<!-- EDIT MODAL -->
<div id="edit_modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.6);z-index:9999;align-items:center;justify-content:center;padding:20px;">
  <div style="background:white;border-radius:20px;padding:32px;max-width:500px;width:100%;">
    <div style="display:flex;justify-content:space-between;margin-bottom:20px;">
      <h3 style="font-weight:700;"><i class="fas fa-crown" style="color:var(--primary)"></i> Modifier le Plan</h3>
      <button onclick="document.getElementById('edit_modal').style.display='none'" style="background:none;border:none;font-size:1.3rem;cursor:pointer;">✕</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="plan_id" id="edit_plan_id">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <div class="form-group"><label class="form-label">Nom</label><input type="text" name="name" id="edit_name" class="form-control" required></div>
        <div class="form-group"><label class="form-label">Montant (FCFA)</label><input type="number" name="amount" id="edit_amount" class="form-control" required></div>
        <div class="form-group"><label class="form-label">Gain/Jour (FCFA)</label><input type="number" name="daily_gain" id="edit_daily_gain" class="form-control" required></div>
        <div class="form-group"><label class="form-label">Gain Total (FCFA)</label><input type="number" name="total_gain" id="edit_total_gain" class="form-control" required></div>
        <div class="form-group"><label class="form-label">Durée (jours)</label><input type="number" name="duration_days" id="edit_duration" class="form-control" required></div>
        <div class="form-group" style="display:flex;align-items:center;gap:8px;padding-top:20px;">
          <input type="checkbox" name="is_active" id="edit_is_active" style="accent-color:var(--primary);">
          <label for="edit_is_active" style="font-weight:600;">Plan actif</label>
        </div>
      </div>
      <button type="submit" class="btn-primary-custom" style="width:100%;margin-top:8px;">Enregistrer</button>
    </form>
  </div>
</div>

<script>
function openEditPlan(plan) {
  document.getElementById('edit_plan_id').value = plan.id;
  document.getElementById('edit_name').value = plan.name;
  document.getElementById('edit_amount').value = plan.amount;
  document.getElementById('edit_daily_gain').value = plan.daily_gain;
  document.getElementById('edit_total_gain').value = plan.total_gain;
  document.getElementById('edit_duration').value = plan.duration_days;
  document.getElementById('edit_is_active').checked = plan.is_active == 1;
  document.getElementById('edit_modal').style.display = 'flex';
}
</script>
<?php renderAdminFooter(); ?>
