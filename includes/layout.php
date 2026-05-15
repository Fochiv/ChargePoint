<?php
function renderHead(string $title = 'ChargePoint'): void {
    echo '<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>' . e($title) . ' — ChargePoint</title>
<link rel="icon" type="image/jpeg" href="/assets/logo.jpg">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>';
}

function renderAppLayout(array $user, string $activePage = ''): void {
    $notifCount = countUnreadNotifications($user['id']);
    $active = fn($p) => $activePage === $p ? 'active' : '';
    $statusHtml = $user['status'] === 'active'
        ? '<span style="color:var(--success);font-size:0.78rem;">● Compte actif</span>'
        : '<span style="color:var(--danger);font-size:0.78rem;">● Compte suspendu</span>';
    $firstName = e(explode(' ', $user['name'])[0]);
    $initial   = strtoupper(substr($user['name'], 0, 1));
    $notifBadge = $notifCount > 0
        ? '<span class="notif-badge">' . ($notifCount > 9 ? '9+' : $notifCount) . '</span>'
        : '';
    echo '
<div class="app-layout">
  <aside class="sidebar">
    <div class="sidebar-logo">
      <img src="/assets/logo.jpg" alt="Logo" style="width:38px;height:38px;border-radius:10px;object-fit:cover;flex-shrink:0;">
      <span><span style="color:var(--primary);font-weight:800;">Charge</span><span style="color:#9ca3af;font-weight:800;">Point</span></span>
    </div>
    <nav class="sidebar-nav">
      <a href="/dashboard.php" class="sidebar-item ' . $active('dashboard') . '"><span class="sidebar-icon"><i class="fas fa-th-large"></i></span> Tableau de Bord</a>
      <a href="/vip.php" class="sidebar-item ' . $active('vip') . '"><span class="sidebar-icon"><i class="fas fa-crown"></i></span> Plans VIP</a>
      <a href="/deposit.php" class="sidebar-item ' . $active('deposit') . '"><span class="sidebar-icon"><i class="fas fa-download"></i></span> Dépôt</a>
      <a href="/withdraw.php" class="sidebar-item ' . $active('withdraw') . '"><span class="sidebar-icon"><i class="fas fa-upload"></i></span> Retrait</a>
      <a href="/transactions.php" class="sidebar-item ' . $active('transactions') . '"><span class="sidebar-icon"><i class="fas fa-list"></i></span> Transactions</a>
      <a href="/referral.php" class="sidebar-item ' . $active('referral') . '"><span class="sidebar-icon"><i class="fas fa-users"></i></span> Équipe</a>
      <a href="/profile.php" class="sidebar-item ' . $active('profile') . '"><span class="sidebar-icon"><i class="fas fa-user"></i></span> Mon Profil</a>
    </nav>
    <div class="sidebar-bottom">
      <a href="/logout.php" class="sidebar-item" style="color:#ef4444"><span class="sidebar-icon"><i class="fas fa-right-from-bracket"></i></span> Déconnexion</a>
    </div>
  </aside>

  <main class="main-content">
    <div class="app-header">
      <a href="/dashboard.php" class="app-header-brand">
        <img src="/assets/logo.jpg" alt="Logo" style="width:32px;height:32px;border-radius:8px;object-fit:cover;flex-shrink:0;">
        <span><span style="color:var(--primary);font-weight:800;">Charge</span><span style="color:#9ca3af;font-weight:800;">Point</span></span>
      </a>
      <div class="app-header-greeting">Bonjour, <span>' . $firstName . '</span><br>' . $statusHtml . '</div>
      <div class="app-header-right">
        <a href="/notifications.php" class="notif-btn">
          <i class="fas fa-bell"></i>' . $notifBadge . '
        </a>
        <a href="/profile.php" style="width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--primary-dark));color:white;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:1rem;flex-shrink:0;">' . $initial . '</a>
      </div>
    </div>';
}

function renderBottomNav(string $activePage = ''): void {
    $active = fn($p) => $activePage === $p ? 'active' : '';
    echo '
  <nav class="bottom-nav">
    <a href="/dashboard.php" class="bottom-nav-item ' . $active('dashboard') . '"><span class="bottom-nav-icon"><i class="fas fa-th-large"></i></span><span>Accueil</span></a>
    <a href="/vip.php" class="bottom-nav-item ' . $active('vip') . '"><span class="bottom-nav-icon"><i class="fas fa-crown"></i></span><span>VIP</span></a>
    <a href="/referral.php" class="bottom-nav-item ' . $active('referral') . '"><span class="bottom-nav-icon"><i class="fas fa-users"></i></span><span>Équipe</span></a>
    <a href="/profile.php" class="bottom-nav-item ' . $active('profile') . '"><span class="bottom-nav-icon"><i class="fas fa-user"></i></span><span>Profil</span></a>
  </nav>
  </main>
</div>
<script src="/assets/js/main.js"></script>
</body>
</html>';
}
