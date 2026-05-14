<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
startSession();

if (isAdminLoggedIn()) { header('Location: /admin/dashboard.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if (!$username || !$password) { $error = 'Veuillez remplir tous les champs.'; }
    else {
        $stmt = getDB()->prepare("SELECT * FROM admin_users WHERE username = ?");
        $stmt->execute([$username]);
        $admin = $stmt->fetch();
        if ($admin && password_verify($password, $admin['password'])) {
            session_regenerate_id(true);
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            header('Location: /admin/dashboard.php');
            exit;
        } else {
            $error = 'Identifiants incorrects.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin — ChargePoint</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<div class="auth-page" style="background:linear-gradient(135deg,#1a1a2e,#0f3460);">
  <div class="auth-card">
    <div class="auth-logo">
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:4px;">
        <img src="/assets/logo.jpg" alt="Logo" style="width:36px;height:36px;border-radius:9px;object-fit:cover;">
        <span style="font-size:1.6rem;font-weight:800;"><span style="color:var(--primary);">Charge</span><span style="color:#6b7280;">Point</span></span>
      </div>
      <span style="font-size:0.85rem;color:var(--text-muted);">Administration</span>
    </div>
    <h1 class="auth-title">Panneau Admin</h1>
    <p class="auth-subtitle">Accès réservé aux administrateurs</p>
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
    <form method="POST">
      <div class="form-group">
        <label class="form-label">Identifiant</label>
        <div class="input-with-icon">
          <i class="fas fa-user-shield"></i>
          <input type="text" name="username" class="form-control" placeholder="Nom d'administrateur" required autofocus>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Mot de passe</label>
        <div class="input-with-icon">
          <i class="fas fa-lock"></i>
          <input type="password" name="password" class="form-control" placeholder="Mot de passe" required>
        </div>
      </div>
      <button type="submit" class="btn-auth"><i class="fas fa-shield-halved"></i> Accéder au panneau</button>
    </form>
  </div>
</div>
</body>
</html>
