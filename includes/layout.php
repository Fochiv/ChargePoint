<?php
function renderHead(string $title = 'ChargePoint'): void { ?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($title) ?> — ChargePoint</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<?php } ?>

<?php
function renderAppLayout(array $user, string $activePage = ''): void {
    $notifCount = countUnreadNotifications($user['id']); ?>
<div class="app-layout">
  <!-- Sidebar (desktop) -->
  <aside class="sidebar">
    <div class="sidebar-logo">
      <img src="/assets/logo.jpg" alt="Logo" style="width:38px;height:38px;border-radius:10px;object-fit:cover;flex-shrink:0;">
      <span><span style="color:var(--primary);font-weight:800;">Charge</span><span style="color:#9ca3af;font-weight:800;">Point</span></span>
    </div>
    <nav class="sidebar-nav">
      <a href="/dashboard.php" class="sidebar-item <?= $activePage==='dashboard'?'active':'' ?>">
        <span class="sidebar-icon"><i class="fas fa-th-large"></i></span> Tableau de Bord
      </a>
      <a href="/vip.php" class="sidebar-item <?= $activePage==='vip'?'active':'' ?>">
        <span class="sidebar-icon"><i class="fas fa-crown"></i></span> Plans VIP
      </a>
      <a href="/deposit.php" class="sidebar-item <?= $activePage==='deposit'?'active':'' ?>">
        <span class="sidebar-icon"><i class="fas fa-arrow-down-to-line"></i></span> Dépôt
      </a>
      <a href="/withdraw.php" class="sidebar-item <?= $activePage==='withdraw'?'active':'' ?>">
        <span class="sidebar-icon"><i class="fas fa-arrow-up-from-bracket"></i></span> Retrait
      </a>
      <a href="/transactions.php" class="sidebar-item <?= $activePage==='transactions'?'active':'' ?>">
        <span class="sidebar-icon"><i class="fas fa-list"></i></span> Transactions
      </a>
      <a href="/referral.php" class="sidebar-item <?= $activePage==='referral'?'active':'' ?>">
        <span class="sidebar-icon"><i class="fas fa-users"></i></span> Équipe
      </a>
      <a href="/profile.php" class="sidebar-item <?= $activePage==='profile'?'active':'' ?>">
        <span class="sidebar-icon"><i class="fas fa-user"></i></span> Mon Profil
      </a>
    </nav>
    <div class="sidebar-bottom">
      <a href="/logout.php" class="sidebar-item" style="color:#ef4444">
        <span class="sidebar-icon"><i class="fas fa-sign-out-alt"></i></span> Déconnexion
      </a>
    </div>
  </aside>

  <!-- Main -->
  <main class="main-content">
    <!-- App Header -->
    <div class="app-header">
      <div>
        <div class="app-header-greeting">Bonjour, <span><?= e(explode(' ', $user['name'])[0]) ?></span> 👋</div>
        <div style="font-size:0.8rem;color:var(--text-muted)">
          <?= $user['status']==='active' ? '<span style="color:var(--success)">● Compte actif</span>' : '<span style="color:var(--danger)">● Compte suspendu</span>' ?>
        </div>
      </div>
      <div class="app-header-right">
        <a href="/notifications.php" class="notif-btn">
          <i class="fas fa-bell"></i>
          <?php if ($notifCount > 0): ?>
          <span class="notif-badge"><?= $notifCount > 9 ? '9+' : $notifCount ?></span>
          <?php endif; ?>
        </a>
        <a href="/profile.php" style="width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--primary-dark));color:white;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:1rem;">
          <?= strtoupper(substr($user['name'], 0, 1)) ?>
        </a>
      </div>
    </div>
<?php }

function renderBottomNav(string $activePage = ''): void { ?>
  <!-- Bottom Nav (mobile) -->
  <nav class="bottom-nav">
    <a href="/dashboard.php" class="bottom-nav-item <?= $activePage==='dashboard'?'active':'' ?>">
      <span class="bottom-nav-icon"><i class="fas fa-th-large"></i></span>
      <span>Accueil</span>
    </a>
    <a href="/vip.php" class="bottom-nav-item <?= $activePage==='vip'?'active':'' ?>">
      <span class="bottom-nav-icon"><i class="fas fa-crown"></i></span>
      <span>VIP</span>
    </a>
    <a href="/referral.php" class="bottom-nav-item <?= $activePage==='referral'?'active':'' ?>">
      <span class="bottom-nav-icon"><i class="fas fa-users"></i></span>
      <span>Équipe</span>
    </a>
    <a href="/profile.php" class="bottom-nav-item <?= $activePage==='profile'?'active':'' ?>">
      <span class="bottom-nav-icon"><i class="fas fa-user"></i></span>
      <span>Profil</span>
    </a>
  </nav>
  </main>
</div>
<script src="/assets/js/main.js"></script>
</body>
</html>
<?php }
