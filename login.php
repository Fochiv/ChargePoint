<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
startSession();
if (isLoggedIn()) { redirect('/dashboard.php'); }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) { $error = 'Token invalide. Veuillez réessayer.'; }
    else {
        $identifier = trim($_POST['identifier'] ?? '');
        $password = $_POST['password'] ?? '';
        if (!$identifier || !$password) {
            $error = 'Veuillez remplir tous les champs.';
        } else {
            $result = loginUser($identifier, $password);
            if ($result['success']) {
                redirect('/dashboard.php');
            } else {
                $error = $result['message'];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Connexion — ChargePoint</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<div class="auth-page">
  <div class="auth-card">
    <div class="auth-logo"><a href="/" style="font-size:1.6rem;font-weight:800;color:var(--primary);">⚡ CHARGEPOINT</a></div>
    <h1 class="auth-title">Bon retour !</h1>
    <p class="auth-subtitle">Connectez-vous à votre espace investisseur</p>

    <?php if ($error): ?><div class="alert alert-danger alert-auto"><?= e($error) ?></div><?php endif; ?>
    <?php if ($s = flash('success')): ?><div class="alert alert-success alert-auto"><?= e($s) ?></div><?php endif; ?>

    <form method="POST" action="">
      <?= csrf_field() ?>
      <div class="form-group">
        <label class="form-label">Email ou téléphone</label>
        <div class="input-with-icon">
          <i class="fas fa-user"></i>
          <input type="text" name="identifier" class="form-control" placeholder="Email ou numéro de téléphone" required autofocus value="<?= e($_POST['identifier'] ?? '') ?>">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label" style="display:flex;justify-content:space-between;">
          Mot de passe
          <a href="#" style="color:var(--primary);font-size:0.85rem;">Mot de passe oublié ?</a>
        </label>
        <div class="input-with-icon">
          <i class="fas fa-lock"></i>
          <input type="password" name="password" class="form-control" placeholder="Votre mot de passe" required>
        </div>
      </div>
      <button type="submit" class="btn-auth">Se connecter <i class="fas fa-arrow-right"></i></button>
    </form>

    <div class="auth-divider">Pas encore de compte ? <a href="/register.php" style="color:var(--primary);font-weight:600;">S'inscrire gratuitement</a></div>
  </div>
</div>
<script src="/assets/js/main.js"></script>
</body>
</html>
