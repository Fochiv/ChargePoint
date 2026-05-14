<?php
function renderAdminHead(string $title = 'Admin'): void { ?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($title) ?> — ChargePoint Admin</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="/assets/css/style.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body>
<?php }

function renderAdminSidebar(string $activePage = ''): void {
    $pending = getDB()->query("SELECT COUNT(*) FROM transactions WHERE type='withdrawal' AND status='pending'")->fetchColumn();
    $failed = getDB()->query("SELECT COUNT(*) FROM transactions WHERE type='deposit' AND status='failed' AND date(created_at)=date('now')")->fetchColumn();
?>
<div style="display:flex;">
<aside class="admin-sidebar">
  <div class="admin-sidebar-logo" style="display:flex;align-items:center;gap:10px;">
    <img src="/assets/logo.jpg" alt="Logo" style="width:34px;height:34px;border-radius:8px;object-fit:cover;flex-shrink:0;">
    <div><div style="font-weight:800;"><span style="color:var(--primary);">Charge</span><span style="color:rgba(255,255,255,0.6);">Point</span></div><div style="font-size:0.7rem;color:rgba(255,255,255,0.4);margin-top:1px;">Administration</div></div>
  </div>
  <nav class="admin-sidebar-nav">
    <a href="/admin/dashboard.php" class="admin-nav-item <?= $activePage==='dashboard'?'active':'' ?>"><i class="fas fa-th-large" style="width:20px"></i> Tableau de Bord</a>
    <a href="/admin/users.php" class="admin-nav-item <?= $activePage==='users'?'active':'' ?>"><i class="fas fa-users" style="width:20px"></i> Utilisateurs</a>
    <a href="/admin/withdrawals.php" class="admin-nav-item <?= $activePage==='withdrawals'?'active':'' ?>">
      <i class="fas fa-money-bill-transfer" style="width:20px"></i> Retraits
      <?php if ($pending > 0): ?><span style="background:var(--primary);color:white;font-size:0.7rem;padding:2px 7px;border-radius:10px;margin-left:auto;"><?= $pending ?></span><?php endif; ?>
    </a>
    <a href="/admin/transactions.php" class="admin-nav-item <?= $activePage==='transactions'?'active':'' ?>"><i class="fas fa-list" style="width:20px"></i> Transactions</a>
    <a href="/admin/vip_plans.php" class="admin-nav-item <?= $activePage==='vip_plans'?'active':'' ?>"><i class="fas fa-crown" style="width:20px"></i> Plans VIP</a>
    <a href="/admin/settings.php" class="admin-nav-item <?= $activePage==='settings'?'active':'' ?>"><i class="fas fa-cog" style="width:20px"></i> Paramètres</a>
    <div style="padding:16px 24px;margin-top:auto;">
      <div style="border-top:1px solid rgba(255,255,255,0.1);padding-top:16px;">
        <a href="/admin/logout.php" class="admin-nav-item" style="color:rgba(255,100,100,0.8);padding:10px 0;">
          <i class="fas fa-sign-out-alt" style="width:20px"></i> Déconnexion
        </a>
      </div>
    </div>
  </nav>
</aside>
<div class="admin-content">
<?php }

function renderAdminTopbar(string $title, string $subtitle = ''): void { ?>
<div class="admin-topbar">
  <div>
    <div style="font-size:1.1rem;font-weight:700;"><?= e($title) ?></div>
    <?php if ($subtitle): ?><div style="font-size:0.82rem;color:var(--text-muted);"><?= e($subtitle) ?></div><?php endif; ?>
  </div>
  <div style="display:flex;align-items:center;gap:16px;">
    <a href="/" target="_blank" style="font-size:0.85rem;color:var(--primary);font-weight:600;"><i class="fas fa-external-link-alt"></i> Voir le site</a>
    <div style="display:flex;align-items:center;gap:8px;background:var(--bg);padding:8px 14px;border-radius:10px;">
      <div style="width:30px;height:30px;background:var(--primary);border-radius:50%;color:white;display:flex;align-items:center;justify-content:center;font-size:0.9rem;font-weight:700;">A</div>
      <span style="font-size:0.9rem;font-weight:600;"><?= e($_SESSION['admin_username'] ?? 'Admin') ?></span>
    </div>
  </div>
</div>
<?php }

function renderAdminFooter(): void { ?>
</div></div>
<script src="/assets/js/main.js"></script>
</body></html>
<?php }
