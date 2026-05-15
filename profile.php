<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_once 'includes/layout.php';
startSession();
requireLogin();
$user = getCurrentUser();
$db = getDB();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) { $error = 'Token invalide.'; }
    elseif (isset($_POST['update_profile'])) {
        $name = trim($_POST['name'] ?? '');
        if (!$name) { $error = 'Le nom est requis.'; }
        else {
            $db->prepare("UPDATE users SET name = ? WHERE id = ?")->execute([$name, $user['id']]);
            $_SESSION['user_name'] = $name;
            $success = 'Profil mis à jour avec succès.';
            $user = getCurrentUser();
        }
    } elseif (isset($_POST['change_password'])) {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_new_password'] ?? '';
        if (!$current || !$new || !$confirm) { $error = 'Tous les champs sont requis.'; }
        elseif (!password_verify($current, $user['password'])) { $error = 'Mot de passe actuel incorrect.'; }
        elseif (strlen($new) < 6) { $error = 'Le nouveau mot de passe doit contenir au moins 6 caractères.'; }
        elseif ($new !== $confirm) { $error = 'Les mots de passe ne correspondent pas.'; }
        else {
            $db->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([password_hash($new, PASSWORD_DEFAULT), $user['id']]);
            $success = 'Mot de passe changé avec succès.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mon Profil — ChargePoint</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<?php renderAppLayout($user, 'profile'); ?>

<div class="page-title">Mon Profil</div>

<?php if ($error): ?><div class="alert alert-danger alert-auto"><?= e($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success alert-auto"><?= e($success) ?></div><?php endif; ?>

<div style="max-width:600px;margin:0 auto;width:100%;">

  <!-- Profile Header -->
  <div class="card-custom" style="margin-bottom:16px;text-align:center;">
    <div class="card-custom-body" style="padding:28px 24px;">
      <div style="width:76px;height:76px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--primary-dark));color:white;display:flex;align-items:center;justify-content:center;font-size:2rem;font-weight:800;margin:0 auto 12px;">
        <?= strtoupper(substr($user['name'], 0, 1)) ?>
      </div>
      <div style="font-size:1.2rem;font-weight:700;margin-bottom:4px;"><?= e($user['name']) ?></div>
      <div style="color:var(--text-muted);font-size:0.88rem;margin-bottom:10px;"><?= e($user['email']) ?></div>
      <?= getStatusBadge($user['status']) ?>
      <div style="margin-top:16px;display:flex;align-items:center;justify-content:center;gap:8px;flex-wrap:wrap;">
        <div style="background:var(--bg);border-radius:10px;padding:8px 14px;font-size:0.83rem;font-weight:600;">
          <i class="fas fa-link" style="color:var(--primary)"></i> <?= e($user['referral_code']) ?>
        </div>
        <button data-copy="<?= e(SITE_URL . '/register.php?ref=' . $user['referral_code']) ?>" style="background:var(--primary);color:white;border:none;border-radius:10px;padding:8px 14px;font-size:0.82rem;font-weight:600;cursor:pointer;">
          <i class="fas fa-copy"></i> Copier le lien
        </button>
      </div>
    </div>
  </div>

  <!-- Quick Navigation Menu -->
  <div class="card-custom" style="margin-bottom:16px;">
    <div style="padding:12px 20px;border-bottom:1px solid var(--border);font-size:0.75rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;">Mes Services</div>
    <a href="/deposit.php" class="profile-menu-row">
      <span class="profile-menu-icon" style="background:rgba(16,185,129,0.12);color:var(--success);"><i class="fas fa-circle-plus"></i></span>
      <span class="profile-menu-label">Faire un Dépôt</span>
      <i class="fas fa-chevron-right profile-menu-arrow"></i>
    </a>
    <a href="/withdraw.php" class="profile-menu-row">
      <span class="profile-menu-icon" style="background:rgba(59,130,246,0.12);color:var(--info);"><i class="fas fa-arrow-up-from-bracket"></i></span>
      <span class="profile-menu-label">Retrait</span>
      <i class="fas fa-chevron-right profile-menu-arrow"></i>
    </a>
    <a href="#wallet" onclick="document.getElementById('wallet').scrollIntoView({behavior:'smooth'});return false;" class="profile-menu-row">
      <span class="profile-menu-icon" style="background:rgba(255,107,0,0.12);color:var(--primary);"><i class="fas fa-wallet"></i></span>
      <span class="profile-menu-label">Mon Portefeuille</span>
      <i class="fas fa-chevron-right profile-menu-arrow"></i>
    </a>
    <a href="https://t.me/+_LljrVRXwGRlYWU0" target="_blank" class="profile-menu-row">
      <span class="profile-menu-icon" style="background:rgba(0,136,204,0.12);color:#0088cc;"><i class="fab fa-telegram"></i></span>
      <span class="profile-menu-label">Nous rejoindre sur Telegram</span>
      <i class="fas fa-chevron-right profile-menu-arrow"></i>
    </a>
    <a href="/transactions.php" class="profile-menu-row">
      <span class="profile-menu-icon" style="background:rgba(139,92,246,0.12);color:#8b5cf6;"><i class="fas fa-list"></i></span>
      <span class="profile-menu-label">Historiques des transactions</span>
      <i class="fas fa-chevron-right profile-menu-arrow"></i>
    </a>
    <a href="#password-section" onclick="document.getElementById('password-section').scrollIntoView({behavior:'smooth'});return false;" class="profile-menu-row" style="border-bottom:none;">
      <span class="profile-menu-icon" style="background:rgba(245,158,11,0.12);color:var(--warning);"><i class="fas fa-lock"></i></span>
      <span class="profile-menu-label">Modifier le mot de passe</span>
      <i class="fas fa-chevron-right profile-menu-arrow"></i>
    </a>
  </div>

  <!-- Personal Info -->
  <div class="card-custom" style="margin-bottom:16px;">
    <div class="card-custom-header"><h5><i class="fas fa-user" style="color:var(--primary)"></i> Informations Personnelles</h5></div>
    <div class="card-custom-body">
      <form method="POST">
        <?= csrf_field() ?>
        <div class="form-group">
          <label class="form-label">Nom complet</label>
          <input type="text" name="name" class="form-control" value="<?= e($user['name']) ?>" required>
        </div>
        <div class="form-group">
          <label class="form-label">Email</label>
          <input type="email" class="form-control" value="<?= e($user['email']) ?>" disabled style="background:var(--bg);">
        </div>
        <div class="form-group">
          <label class="form-label">Téléphone</label>
          <input type="text" class="form-control" value="<?= e($user['phone']) ?>" disabled style="background:var(--bg);">
        </div>
        <div class="form-group">
          <label class="form-label">Membre depuis</label>
          <input type="text" class="form-control" value="<?= date('d/m/Y', strtotime($user['created_at'])) ?>" disabled style="background:var(--bg);">
        </div>
        <button type="submit" name="update_profile" class="btn-auth"><i class="fas fa-save"></i> Mettre à jour</button>
      </form>
    </div>
  </div>

  <!-- Wallet -->
  <div class="card-custom" id="wallet" style="margin-bottom:16px;">
    <div class="card-custom-header"><h5><i class="fas fa-wallet" style="color:var(--primary)"></i> Mon Portefeuille</h5></div>
    <div class="card-custom-body">
      <?php if ($user['wallet_name']): ?>
      <div class="wallet-card" style="margin-bottom:16px;">
        <div class="wallet-label">Portefeuille</div>
        <div style="font-size:1.1rem;font-weight:700;"><?= e($user['wallet_name']) ?></div>
        <div style="margin-top:12px;font-size:0.85rem;opacity:0.7;"><?= e($user['wallet_country'] ?? '') ?> — <?= e($user['wallet_method'] ?? '') ?></div>
        <div style="font-size:1rem;font-weight:600;margin-top:4px;"><?= e($user['wallet_phone'] ?? '') ?></div>
        <?php if ($user['wallet_updated_at']): ?>
        <div style="font-size:0.78rem;opacity:0.6;margin-top:8px;">Dernière modif : <?= date('d/m/Y', strtotime($user['wallet_updated_at'])) ?></div>
        <?php endif; ?>
      </div>
      <?php else: ?>
      <div class="alert alert-warning" style="margin-bottom:16px;">Aucun portefeuille configuré. Ajoutez-en un pour effectuer des retraits.</div>
      <?php endif; ?>
      <a href="/withdraw.php#wallet" class="btn-primary-custom" style="width:100%;justify-content:center;">
        <i class="fas fa-pen-to-square"></i> Modifier le Portefeuille
      </a>
    </div>
  </div>

  <!-- Password -->
  <div class="card-custom" id="password-section" style="margin-bottom:16px;">
    <div class="card-custom-header"><h5><i class="fas fa-lock" style="color:var(--primary)"></i> Changer le mot de passe</h5></div>
    <div class="card-custom-body">
      <form method="POST">
        <?= csrf_field() ?>
        <div class="form-group">
          <label class="form-label">Mot de passe actuel</label>
          <input type="password" name="current_password" class="form-control" placeholder="Votre mot de passe actuel" required>
        </div>
        <div class="form-group">
          <label class="form-label">Nouveau mot de passe</label>
          <input type="password" name="new_password" class="form-control" placeholder="Minimum 6 caractères" required>
        </div>
        <div class="form-group">
          <label class="form-label">Confirmer le nouveau mot de passe</label>
          <input type="password" name="confirm_new_password" class="form-control" placeholder="Répétez le nouveau mot de passe" required>
        </div>
        <button type="submit" name="change_password" class="btn-auth"><i class="fas fa-key"></i> Changer le mot de passe</button>
      </form>
    </div>
  </div>

  <!-- Logout -->
  <div class="card-custom" style="margin-bottom:24px;overflow:hidden;">
    <a href="/logout.php" class="profile-menu-row" style="border-bottom:none;">
      <span class="profile-menu-icon" style="background:rgba(239,68,68,0.12);color:var(--danger);"><i class="fas fa-right-from-bracket"></i></span>
      <span class="profile-menu-label" style="color:var(--danger);font-weight:700;">Déconnexion</span>
      <i class="fas fa-chevron-right profile-menu-arrow" style="color:var(--danger);"></i>
    </a>
  </div>

</div>

<?php renderBottomNav('profile'); ?>
<style>
.profile-menu-row {
  display: flex; align-items: center; gap: 14px;
  padding: 14px 20px;
  border-bottom: 1px solid var(--border);
  transition: background 0.18s;
  color: var(--text);
  text-decoration: none;
}
.profile-menu-row:hover { background: var(--bg); }
.profile-menu-icon {
  width: 40px; height: 40px; border-radius: 11px;
  display: flex; align-items: center; justify-content: center;
  font-size: 1rem; flex-shrink: 0;
}
.profile-menu-label { flex: 1; font-weight: 500; font-size: 0.92rem; }
.profile-menu-arrow { color: var(--text-muted); font-size: 0.78rem; }
</style>
