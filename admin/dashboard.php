<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once 'includes/admin_layout.php';
startSession();
requireAdmin();

$db = getDB();

$totalUsers = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalDeposited = $db->query("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE type='deposit' AND status='success'")->fetchColumn();
$totalWithdrawn = $db->query("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE type='withdrawal' AND status='success'")->fetchColumn();
$totalGains = $db->query("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE type='daily_gain'")->fetchColumn();
$pendingWithdrawals = $db->query("SELECT COUNT(*) FROM transactions WHERE type='withdrawal' AND status='pending'")->fetchColumn();
$pendingAmount = $db->query("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE type='withdrawal' AND status='pending'")->fetchColumn();
$today = date('Y-m-d');
$s = $db->prepare("SELECT COUNT(*) FROM transactions WHERE type='deposit' AND status='failed' AND DATE(created_at)=?"); $s->execute([$today]); $failedToday = $s->fetchColumn();
$activeInvestments = $db->query("SELECT COUNT(*) FROM investments WHERE status='active'")->fetchColumn();
$s = $db->prepare("SELECT COUNT(*) FROM users WHERE DATE(created_at)=?"); $s->execute([$today]); $newUsersToday = $s->fetchColumn();

// Last 7 days deposits
$last7Days = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $label = date('d/m', strtotime("-$i days"));
    $amount = $db->prepare("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE type='deposit' AND status='success' AND date(created_at)=?")->execute([$date]) ?
        ($db->prepare("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE type='deposit' AND status='success' AND date(created_at)=?") && ($s=$db->prepare("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE type='deposit' AND status='success' AND date(created_at)=?")) && $s->execute([$date]) ? $s->fetchColumn() : 0) : 0;
    $s2 = $db->prepare("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE type='deposit' AND status='success' AND date(created_at)=?");
    $s2->execute([$date]);
    $last7Days[] = ['label' => $label, 'amount' => (float)$s2->fetchColumn()];
}

$recentTx = $db->query("SELECT t.*, u.name as user_name FROM transactions t JOIN users u ON t.user_id=u.id ORDER BY t.created_at DESC LIMIT 10")->fetchAll();
$pendingWd = $db->query("SELECT t.*, u.name as user_name, u.phone as user_phone FROM transactions t JOIN users u ON t.user_id=u.id WHERE t.type='withdrawal' AND t.status='pending' ORDER BY t.created_at ASC LIMIT 5")->fetchAll();
?>
<?php renderAdminHead('Tableau de Bord'); ?>
<?php renderAdminSidebar('dashboard'); ?>
<?php renderAdminTopbar('Tableau de Bord', 'Vue globale de la plateforme'); ?>

<div class="admin-page-content">
  <!-- STAT CARDS -->
  <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:28px;" class="admin-stats">
    <div class="stat-card">
      <div class="stat-card-icon icon-orange"><i class="fas fa-users"></i></div>
      <div class="stat-card-value"><?= number_format($totalUsers) ?></div>
      <div class="stat-card-label">Total investisseurs</div>
      <div style="font-size:0.8rem;color:var(--success);margin-top:4px;">+<?= $newUsersToday ?> aujourd'hui</div>
    </div>
    <div class="stat-card">
      <div class="stat-card-icon icon-green"><i class="fas fa-arrow-down-to-line"></i></div>
      <div class="stat-card-value" style="font-size:1.1rem;"><?= formatAmount($totalDeposited) ?></div>
      <div class="stat-card-label">Total déposé</div>
    </div>
    <div class="stat-card">
      <div class="stat-card-icon icon-blue"><i class="fas fa-arrow-up-from-bracket"></i></div>
      <div class="stat-card-value" style="font-size:1.1rem;"><?= formatAmount($totalWithdrawn) ?></div>
      <div class="stat-card-label">Total retiré</div>
    </div>
    <div class="stat-card">
      <div class="stat-card-icon icon-purple"><i class="fas fa-chart-line"></i></div>
      <div class="stat-card-value" style="font-size:1.1rem;"><?= formatAmount($totalGains) ?></div>
      <div class="stat-card-label">Gains distribués</div>
    </div>
  </div>

  <!-- ALERTS -->
  <div class="admin-alerts-grid">
    <div style="background:rgba(245,158,11,0.1);border:1px solid rgba(245,158,11,0.3);border-radius:16px;padding:20px;">
      <div style="display:flex;align-items:center;gap:12px;margin-bottom:8px;">
        <i class="fas fa-clock" style="color:var(--warning);font-size:1.4rem;"></i>
        <div style="font-size:1.5rem;font-weight:800;color:var(--warning);"><?= $pendingWithdrawals ?></div>
      </div>
      <div style="font-weight:600;margin-bottom:4px;">Retraits en attente</div>
      <div style="font-size:0.85rem;color:var(--text-muted);"><?= formatAmount($pendingAmount) ?> à traiter</div>
      <a href="/admin/withdrawals.php" style="display:inline-block;margin-top:10px;font-size:0.85rem;color:var(--warning);font-weight:600;">Traiter →</a>
    </div>
    <div style="background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.3);border-radius:16px;padding:20px;">
      <div style="display:flex;align-items:center;gap:12px;margin-bottom:8px;">
        <i class="fas fa-xmark-circle" style="color:var(--danger);font-size:1.4rem;"></i>
        <div style="font-size:1.5rem;font-weight:800;color:var(--danger);"><?= $failedToday ?></div>
      </div>
      <div style="font-weight:600;margin-bottom:4px;">Paiements échoués</div>
      <div style="font-size:0.85rem;color:var(--text-muted);">Aujourd'hui</div>
    </div>
    <div style="background:rgba(16,185,129,0.1);border:1px solid rgba(16,185,129,0.3);border-radius:16px;padding:20px;">
      <div style="display:flex;align-items:center;gap:12px;margin-bottom:8px;">
        <i class="fas fa-crown" style="color:var(--success);font-size:1.4rem;"></i>
        <div style="font-size:1.5rem;font-weight:800;color:var(--success);"><?= $activeInvestments ?></div>
      </div>
      <div style="font-weight:600;margin-bottom:4px;">Investissements actifs</div>
      <div style="font-size:0.85rem;color:var(--text-muted);">Plans VIP en cours</div>
    </div>
  </div>

  <div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;margin-bottom:28px;" class="chart-grid">
    <!-- CHART -->
    <div class="card-custom">
      <div class="card-custom-header"><h5><i class="fas fa-chart-bar" style="color:var(--primary)"></i> Dépôts — 7 derniers jours</h5></div>
      <div class="card-custom-body">
        <canvas id="depositsChart" height="80"></canvas>
      </div>
    </div>
    <!-- QUICK STATS -->
    <div class="card-custom">
      <div class="card-custom-header"><h5><i class="fas fa-bolt" style="color:var(--primary)"></i> Activité récente</h5></div>
      <div class="card-custom-body">
        <?php
        $sd = $db->prepare("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE type='deposit' AND status='success' AND DATE(created_at)=?"); $sd->execute([$today]); $todayDeposit = $sd->fetchColumn();
        $sw = $db->prepare("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE type='withdrawal' AND (status='success' OR status='pending') AND DATE(created_at)=?"); $sw->execute([$today]); $todayWithdrawal = $sw->fetchColumn();
        ?>
        <div class="info-row"><span class="info-label">Dépôts aujourd'hui</span><span class="info-value" style="color:var(--success)"><?= formatAmount($todayDeposit) ?></span></div>
        <div class="info-row"><span class="info-label">Retraits demandés</span><span class="info-value" style="color:var(--warning)"><?= formatAmount($todayWithdrawal) ?></span></div>
        <div class="info-row"><span class="info-label">Nouveaux membres</span><span class="info-value"><?= $newUsersToday ?></span></div>
        <div class="info-row" style="border:none"><span class="info-label">Plans actifs</span><span class="info-value"><?= $activeInvestments ?></span></div>
        <a href="/admin/users.php" class="btn-primary-custom" style="width:100%;justify-content:center;margin-top:12px;font-size:0.88rem;padding:10px;">Gérer les utilisateurs</a>
      </div>
    </div>
  </div>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;" class="recent-grid">
    <!-- RECENT TRANSACTIONS -->
    <div class="card-custom">
      <div class="card-custom-header">
        <h5><i class="fas fa-list" style="color:var(--primary)"></i> Transactions récentes</h5>
        <a href="/admin/transactions.php" style="font-size:0.85rem;color:var(--primary);">Tout voir</a>
      </div>
      <div style="overflow-x:auto;">
        <table class="table-custom">
          <thead><tr><th>Utilisateur</th><th>Type</th><th>Montant</th><th>Statut</th></tr></thead>
          <tbody>
          <?php foreach ($recentTx as $tx): ?>
          <tr>
            <td style="font-size:0.85rem;"><?= e($tx['user_name']) ?></td>
            <td><span style="font-size:0.8rem;background:var(--bg);padding:3px 8px;border-radius:6px;"><?= e(getTypeLabel($tx['type'])) ?></span></td>
            <td style="font-weight:700;font-size:0.88rem;"><?= formatAmount($tx['amount']) ?></td>
            <td><?= getStatusBadge($tx['status']) ?></td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- PENDING WITHDRAWALS -->
    <div class="card-custom">
      <div class="card-custom-header">
        <h5><i class="fas fa-money-bill-transfer" style="color:var(--warning)"></i> Retraits à traiter</h5>
        <a href="/admin/withdrawals.php" style="font-size:0.85rem;color:var(--primary);">Tout voir</a>
      </div>
      <?php if (empty($pendingWd)): ?>
      <div class="empty-state"><div class="empty-state-icon">✅</div><div class="empty-state-title">Aucun retrait en attente</div></div>
      <?php else: ?>
      <div class="card-custom-body" style="padding:0;">
        <?php foreach ($pendingWd as $wd): ?>
        <div style="padding:14px 20px;border-bottom:1px solid var(--border);">
          <div style="display:flex;justify-content:space-between;align-items:flex-start;">
            <div>
              <div style="font-weight:700;font-size:0.9rem;"><?= e($wd['user_name']) ?></div>
              <div style="font-size:0.8rem;color:var(--text-muted)"><?= e($wd['method'] ?? '') ?> — <?= e($wd['phone'] ?? '') ?></div>
            </div>
            <div style="text-align:right;">
              <div style="font-weight:800;color:var(--danger)"><?= formatAmount($wd['amount']) ?></div>
              <a href="/admin/withdrawals.php" style="font-size:0.78rem;color:var(--primary);">Traiter →</a>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<style>
@media(max-width:1199px){.admin-stats{grid-template-columns:repeat(2,1fr)!important;}}
@media(max-width:991px){.admin-stats{grid-template-columns:repeat(2,1fr)!important;}.chart-grid,.recent-grid{grid-template-columns:1fr!important;}}
@media(max-width:575px){.admin-stats{grid-template-columns:1fr!important;}}
.admin-alerts-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:28px;}
@media(max-width:991px){.admin-alerts-grid{grid-template-columns:1fr!important;}}
@media(max-width:767px){.admin-alerts-grid{grid-template-columns:1fr!important;}}
</style>
<script>
const ctx = document.getElementById('depositsChart')?.getContext('2d');
if (ctx) {
  new Chart(ctx, {
    type: 'bar',
    data: {
      labels: <?= json_encode(array_column($last7Days, 'label')) ?>,
      datasets: [{
        label: 'Dépôts (FCFA)',
        data: <?= json_encode(array_column($last7Days, 'amount')) ?>,
        backgroundColor: 'rgba(255,107,0,0.7)',
        borderColor: '#FF6B00',
        borderWidth: 2,
        borderRadius: 8,
      }]
    },
    options: {
      responsive: true,
      plugins: { legend: { display: false } },
      scales: {
        y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' }, ticks: { callback: v => v.toLocaleString('fr-FR') } },
        x: { grid: { display: false } }
      }
    }
  });
}
</script>
<?php renderAdminFooter(); ?>
