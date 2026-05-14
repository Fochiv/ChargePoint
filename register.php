<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
startSession();
if (isLoggedIn()) { redirect('/dashboard.php'); }

$ref = $_GET['ref'] ?? $_POST['ref_code'] ?? '';
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) { $error = 'Token invalide. Veuillez réessayer.'; }
    else {
        $name = trim($_POST['name'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $phone = trim($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        $refCode = trim($_POST['ref_code'] ?? '');
        $terms = $_POST['terms'] ?? '';

        if (!$name || !$email || !$phone || !$password || !$confirm) {
            $error = 'Tous les champs obligatoires doivent être remplis.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Adresse email invalide.';
        } elseif (strlen($password) < 6) {
            $error = 'Le mot de passe doit contenir au moins 6 caractères.';
        } elseif ($password !== $confirm) {
            $error = 'Les mots de passe ne correspondent pas.';
        } elseif (!$terms) {
            $error = "Vous devez accepter les conditions d'utilisation.";
        } else {
            $db = getDB();
            $emailExists = $db->prepare("SELECT id FROM users WHERE email = ?");
            $emailExists->execute([$email]);
            $phoneExists = $db->prepare("SELECT id FROM users WHERE phone = ?");
            $phoneExists->execute([$phone]);
            if ($emailExists->fetch()) {
                $error = 'Cette adresse email est déjà utilisée.';
            } elseif ($phoneExists->fetch()) {
                $error = 'Ce numéro de téléphone est déjà utilisé.';
            } else {
                $referredBy = null;
                if ($refCode) {
                    $refUser = $db->prepare("SELECT id FROM users WHERE referral_code = ?");
                    $refUser->execute([$refCode]);
                    $refRow = $refUser->fetch();
                    if ($refRow) $referredBy = $refRow['id'];
                }
                $newRefCode = generateReferralCode();
                $hashedPw = password_hash($password, PASSWORD_DEFAULT);
                $db->prepare("INSERT INTO users (name, email, phone, password, referral_code, referred_by) VALUES (?,?,?,?,?,?)")
                   ->execute([$name, $email, $phone, $hashedPw, $newRefCode, $referredBy]);
                $userId = $db->lastInsertId();
                addNotification($userId, "Bienvenue sur ChargePoint ! Choisissez un plan VIP pour commencer à investir.");
                startSession();
                session_regenerate_id(true);
                $_SESSION['user_id'] = $userId;
                $_SESSION['user_name'] = $name;
                redirect('/dashboard.php?welcome=1');
            }
        }
    }
}
$countries = getDefaultCountries();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Inscription — ChargePoint</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<div class="auth-page">
  <div class="auth-card" style="max-width:520px;">
    <div class="auth-logo"><a href="/" style="display:inline-flex;align-items:center;gap:10px;text-decoration:none;">
      <img src="/assets/logo.jpg" alt="Logo" style="width:36px;height:36px;border-radius:9px;object-fit:cover;">
      <span style="font-size:1.6rem;font-weight:800;"><span style="color:var(--primary);">Charge</span><span style="color:#6b7280;">Point</span></span>
    </a></div>
    <h1 class="auth-title">Créer un compte</h1>
    <p class="auth-subtitle">Rejoignez des milliers d'investisseurs africains</p>

    <?php if ($error): ?><div class="alert alert-danger alert-auto"><?= e($error) ?></div><?php endif; ?>

    <form method="POST" action="">
      <?= csrf_field() ?>
      <input type="hidden" name="ref_code" value="<?= e($ref) ?>">

      <div class="form-group">
        <label class="form-label">Nom complet *</label>
        <div class="input-with-icon">
          <i class="fas fa-user"></i>
          <input type="text" name="name" class="form-control" placeholder="Votre nom complet" required value="<?= e($_POST['name'] ?? '') ?>">
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Adresse email *</label>
        <div class="input-with-icon">
          <i class="fas fa-envelope"></i>
          <input type="email" name="email" class="form-control" placeholder="votre@email.com" required value="<?= e($_POST['email'] ?? '') ?>">
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Pays</label>
        <select id="reg_country" class="form-select" onchange="updateDialCode()">
          <option value="">Sélectionner votre pays</option>
          <?php foreach ($countries as $c): ?>
          <option value="<?= e($c['code']) ?>" data-dial="+<?= getDialCode($c['code']) ?>"><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label class="form-label">Numéro de téléphone *</label>
        <div style="display:flex;gap:8px;">
          <input type="text" id="dial_code" style="width:80px;" class="form-control" placeholder="+237" readonly>
          <input type="text" name="phone" id="phone_num" class="form-control" placeholder="Numéro sans indicatif" required value="<?= e($_POST['phone'] ?? '') ?>">
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Mot de passe *</label>
        <div class="input-with-icon">
          <i class="fas fa-lock"></i>
          <input type="password" name="password" class="form-control" placeholder="Minimum 6 caractères" required>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Confirmer le mot de passe *</label>
        <div class="input-with-icon">
          <i class="fas fa-lock"></i>
          <input type="password" name="confirm_password" class="form-control" placeholder="Répétez votre mot de passe" required>
        </div>
      </div>

      <?php if ($ref): ?>
      <div class="form-group">
        <label class="form-label">Code de parrainage</label>
        <div class="input-with-icon">
          <i class="fas fa-gift"></i>
          <input type="text" name="ref_code" class="form-control" value="<?= e($ref) ?>" readonly style="background:rgba(255,107,0,0.05);">
        </div>
      </div>
      <?php else: ?>
      <div class="form-group">
        <label class="form-label">Code de parrainage (optionnel)</label>
        <div class="input-with-icon">
          <i class="fas fa-gift"></i>
          <input type="text" name="ref_code" class="form-control" placeholder="Code parrainage si vous en avez un" value="<?= e($_POST['ref_code'] ?? '') ?>">
        </div>
      </div>
      <?php endif; ?>

      <div class="form-group" style="display:flex;align-items:flex-start;gap:10px;">
        <input type="checkbox" name="terms" id="terms" style="margin-top:4px;accent-color:var(--primary);" required>
        <label for="terms" style="font-size:0.88rem;color:var(--text-muted);">
          J'accepte les <a href="#" style="color:var(--primary);">Conditions d'utilisation</a> et la <a href="#" style="color:var(--primary);">Politique de confidentialité</a>
        </label>
      </div>

      <button type="submit" class="btn-auth">S'inscrire <i class="fas fa-arrow-right"></i></button>
    </form>

    <div class="auth-divider">Déjà un compte ? <a href="/login.php" style="color:var(--primary);font-weight:600;">Se connecter</a></div>
  </div>
</div>

<script>
const dialCodes = <?= json_encode(array_combine(array_column(getDefaultCountries(), 'code'), array_map('getDialCode', array_column(getDefaultCountries(), 'code')))) ?>;
function updateDialCode() {
  const sel = document.getElementById('reg_country');
  const code = sel.value;
  const opt = sel.options[sel.selectedIndex];
  document.getElementById('dial_code').value = opt.dataset.dial || '';
}
function getDialCode(code) { return dialCodes[code] || ''; }
</script>
<script src="/assets/js/main.js"></script>
</body>
</html>

<?php
function getDialCode(string $code): string {
    $codes = ['CM'=>'237','SN'=>'221','CI'=>'225','BJ'=>'229','BF'=>'226','CG'=>'242','CD'=>'243','GA'=>'241','GN'=>'224','GQ'=>'240','GW'=>'245','ML'=>'223','NE'=>'227','CF'=>'236','TD'=>'235','TG'=>'228'];
    return $codes[$code] ?? '0';
}
?>
