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
$countries = getDefaultCountries();
function getDialCode(string $code): string {
    $codes=['CM'=>'237','SN'=>'221','CI'=>'225','BJ'=>'229','BF'=>'226','CG'=>'242','CD'=>'243','GA'=>'241','GN'=>'224','GQ'=>'240','GW'=>'245','ML'=>'223','NE'=>'227','CF'=>'236','TD'=>'235','TG'=>'228'];
    return $codes[$code] ?? '';
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
<div class="page-subtitle">Gérez vos informations personnelles et paramètres de sécurité.</div>

<?php if ($error): ?><div class="alert alert-danger alert-auto"><?= e($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success alert-auto"><?= e($success) ?></div><?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;" class="profile-grid">

  <!-- PROFILE INFO -->
  <div class="card-custom">
    <div class="card-custom-header"><h5><i class="fas fa-user" style="color:var(--primary)"></i> Informations Personnelles</h5></div>
    <div class="card-custom-body">
      <div style="text-align:center;margin-bottom:20px;">
        <div class="profile-avatar"><?= strtoupper(substr($user['name'], 0, 1)) ?></div>
        <div style="font-size:1.1rem;font-weight:700;"><?= e($user['name']) ?></div>
        <div style="color:var(--text-muted);font-size:0.85rem;"><?= e($user['email']) ?></div>
      </div>
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
          <label class="form-label">Code de parrainage</label>
          <div style="display:flex;gap:8px;">
            <input type="text" class="form-control" value="<?= e($user['referral_code']) ?>" id="my_ref_code" readonly>
            <button type="button" class="btn-primary-custom" style="padding:8px 16px;white-space:nowrap;" data-copy="<?= e($user['referral_code']) ?>">Copier</button>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Statut du compte</label>
          <div><?= getStatusBadge($user['status']) ?></div>
        </div>
        <div class="form-group">
          <label class="form-label">Date d'inscription</label>
          <input type="text" class="form-control" value="<?= date('d/m/Y', strtotime($user['created_at'])) ?>" disabled style="background:var(--bg);">
        </div>
        <button type="submit" name="update_profile" class="btn-auth">Mettre à jour</button>
      </form>
    </div>
  </div>

  <div style="display:flex;flex-direction:column;gap:20px;">
    <!-- PASSWORD -->
    <div class="card-custom">
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
          <button type="submit" name="change_password" class="btn-auth">Changer le mot de passe</button>
        </form>
      </div>
    </div>

    <!-- WALLET -->
    <div class="card-custom">
      <div class="card-custom-header"><h5><i class="fas fa-wallet" style="color:var(--primary)"></i> Mon Portefeuille</h5></div>
      <div class="card-custom-body">
        <?php if ($user['wallet_name']): ?>
        <div class="wallet-card" style="margin-bottom:16px;">
          <div class="wallet-label">Portefeuille</div>
          <div style="font-size:1.1rem;font-weight:700;"><?= e($user['wallet_name']) ?></div>
          <div style="margin-top:12px;font-size:0.85rem;opacity:0.7;"><?= e($user['wallet_country'] ?? '') ?> — <?= e($user['wallet_method'] ?? '') ?></div>
          <div style="font-size:1rem;font-weight:600;margin-top:4px;"><?= e($user['wallet_phone'] ?? '') ?></div>
          <?php if ($user['wallet_updated_at']): ?>
          <div style="font-size:0.78rem;opacity:0.6;margin-top:8px;">Dernière modif: <?= date('d/m/Y', strtotime($user['wallet_updated_at'])) ?></div>
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
  </div>
</div>

<?php renderBottomNav('profile'); ?>
<style>
@media(max-width:767px){.profile-grid{grid-template-columns:1fr!important;}}
</style>
<script src="/assets/js/main.js"></script>
</body>
</html>
