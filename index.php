<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
startSession();
if (isLoggedIn()) { header('Location: /dashboard.php'); exit; }
$plans = getDB()->query("SELECT * FROM vip_plans WHERE is_active = 1 ORDER BY amount ASC")->fetchAll();
$ref = $_GET['ref'] ?? '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ChargePoint — Investissez aujourd'hui, Construisez votre avenir</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar-custom">
  <a href="/" class="logo"><span class="lightning">⚡</span> CHARGEPOINT</a>
  <div class="nav-links" id="nav_links">
    <a href="/">Accueil</a>
    <a href="#plans">Plans</a>
    <a href="#avantages">À propos</a>
    <a href="#contact">Contact</a>
  </div>
  <div class="nav-actions">
    <a href="/login.php" class="btn-login">Se connecter</a>
    <a href="/register.php<?= $ref ? '?ref='.e($ref) : '' ?>" class="btn-register">S'inscrire</a>
    <button id="burger_menu" style="display:none;background:none;border:none;font-size:1.5rem;cursor:pointer;padding:4px 8px;"><i class="fas fa-bars"></i></button>
  </div>
</nav>

<!-- HERO -->
<section class="hero">
  <div class="container" style="max-width:1200px;margin:0 auto;padding:0 24px;">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:60px;align-items:center;" class="hero-grid">
      <div class="hero-content">
        <div style="display:inline-block;background:rgba(255,107,0,0.15);color:var(--primary);padding:6px 16px;border-radius:20px;font-size:0.85rem;font-weight:700;margin-bottom:20px;">
          ⚡ Plateforme d'Investissement #1 en Afrique
        </div>
        <h1>Investissez <span>aujourd'hui</span>,<br>Construisez votre <span>avenir</span></h1>
        <p style="margin:20px 0 32px;">Rejoignez plus de 50 000 investisseurs africains qui génèrent des revenus journaliers avec nos plans VIP sécurisés.</p>
        <div style="display:flex;gap:16px;flex-wrap:wrap;">
          <a href="/register.php<?= $ref ? '?ref='.e($ref) : '' ?>" class="btn-primary-custom" style="font-size:1rem;padding:14px 32px;">
            <i class="fas fa-rocket"></i> Commencer maintenant
          </a>
          <a href="#plans" style="background:rgba(255,255,255,0.1);color:white;padding:14px 28px;border-radius:12px;font-weight:600;backdrop-filter:blur(10px);border:1px solid rgba(255,255,255,0.2);">
            Voir les plans
          </a>
        </div>
        <div style="display:flex;gap:24px;margin-top:32px;flex-wrap:wrap;">
          <div style="color:white;"><span style="display:block;font-size:1.4rem;font-weight:800;color:var(--primary)">+50K</span><span style="font-size:0.8rem;opacity:0.7">Investisseurs</span></div>
          <div style="color:white;"><span style="display:block;font-size:1.4rem;font-weight:800;color:var(--primary)">+2M</span><span style="font-size:0.8rem;opacity:0.7">FCFA Investis</span></div>
          <div style="color:white;"><span style="display:block;font-size:1.4rem;font-weight:800;color:var(--primary)">100%</span><span style="font-size:0.8rem;opacity:0.7">Sécurisé</span></div>
        </div>
      </div>
      <div style="text-align:center;" class="hero-img-container">
        <div style="width:100%;max-width:440px;margin:0 auto;background:rgba(255,255,255,0.05);border-radius:24px;padding:30px;border:1px solid rgba(255,255,255,0.1);animation:float 4s ease-in-out infinite;">
          <div style="font-size:5rem;margin-bottom:16px;">⚡🔋</div>
          <div style="color:white;font-size:1.3rem;font-weight:700;margin-bottom:8px;">Votre argent travaille pour vous</div>
          <div style="color:rgba(255,255,255,0.6);font-size:0.9rem;">Gains journaliers automatiques</div>
          <div style="margin-top:20px;background:rgba(255,107,0,0.2);border-radius:12px;padding:16px;">
            <div style="color:var(--primary);font-size:1.8rem;font-weight:800;">+315 FCFA/jour</div>
            <div style="color:rgba(255,255,255,0.6);font-size:0.8rem;">Dès 3 000 FCFA investis</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- STATS -->
<div class="stats-section">
  <div style="max-width:1200px;margin:0 auto;padding:0 24px;">
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:20px;">
      <div class="stat-item"><div class="stat-number">+50K</div><div class="stat-label">Investisseurs actifs</div></div>
      <div class="stat-item"><div class="stat-number">+2M FCFA</div><div class="stat-label">Total investis</div></div>
      <div class="stat-item"><div class="stat-number">+1.5M FCFA</div><div class="stat-label">Total retirés</div></div>
      <div class="stat-item"><div class="stat-number">100%</div><div class="stat-label">Sécurisé & Fiable</div></div>
    </div>
  </div>
</div>

<!-- PLANS VIP -->
<section class="section" id="plans" style="background:#f5f6fa;">
  <div style="max-width:1200px;margin:0 auto;padding:0 24px;">
    <h2 class="section-title">Plans d'Investissement <span>VIP</span></h2>
    <p class="section-subtitle">Choisissez le plan adapté à vos objectifs. Gains journaliers garantis pendant 125 jours.</p>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:20px;">
      <?php foreach ($plans as $i => $plan): ?>
      <div class="vip-card <?= $i === 3 ? 'popular' : '' ?>">
        <?php if ($i === 3): ?><span class="vip-badge">🔥 Populaire</span><?php endif; ?>
        <div class="vip-name"><?= e($plan['name']) ?></div>
        <div class="vip-amount"><?= formatAmount($plan['amount']) ?></div>
        <div class="vip-gain">+<?= formatAmount($plan['daily_gain']) ?> / jour</div>
        <div class="vip-details">
          <div class="vip-detail-row"><span>Gain total</span><span style="color:var(--success)"><?= formatAmount($plan['total_gain']) ?></span></div>
          <div class="vip-detail-row"><span>Durée</span><span><?= $plan['duration_days'] ?> jours</span></div>
          <div class="vip-detail-row"><span>ROI</span><span><?= round(($plan['total_gain']/$plan['amount']-1)*100) ?>%</span></div>
        </div>
        <a href="/register.php" style="display:block;text-align:center;margin-top:16px;background:linear-gradient(135deg,var(--primary),var(--primary-dark));color:white;padding:10px;border-radius:10px;font-weight:700;font-size:0.9rem;">
          Investir →
        </a>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- AVANTAGES -->
<section class="section" id="avantages">
  <div style="max-width:1200px;margin:0 auto;padding:0 24px;">
    <h2 class="section-title">Pourquoi <span>ChargePoint</span> ?</h2>
    <p class="section-subtitle">Une plateforme conçue pour maximiser vos gains en toute sécurité.</p>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:24px;">
      <div class="advantage-card"><div class="advantage-icon">🔒</div><div class="advantage-title">100% Sécurisée</div><div class="advantage-text">Vos investissements sont protégés avec les meilleures technologies de sécurité.</div></div>
      <div class="advantage-card"><div class="advantage-icon">✅</div><div class="advantage-title">Fiable & Transparent</div><div class="advantage-text">Chaque transaction est tracée et vérifiable. Historique complet disponible.</div></div>
      <div class="advantage-card"><div class="advantage-icon">⚡</div><div class="advantage-title">Retraits Rapides</div><div class="advantage-text">Retirez vos gains facilement via Mobile Money dans 16 pays africains.</div></div>
      <div class="advantage-card"><div class="advantage-icon">🎧</div><div class="advantage-title">Support 24/7</div><div class="advantage-text">Notre équipe est disponible à toute heure pour vous accompagner.</div></div>
    </div>
  </div>
</section>

<!-- PARRAINAGE -->
<section class="section referral-section">
  <div style="max-width:1000px;margin:0 auto;padding:0 24px;text-align:center;">
    <h2 class="section-title" style="color:white;">Programme de <span>Parrainage</span></h2>
    <p style="color:rgba(255,255,255,0.7);margin-bottom:48px;">Gagnez des commissions sur 3 niveaux en invitant vos proches.</p>
    <div style="display:flex;justify-content:center;gap:30px;flex-wrap:wrap;margin-bottom:32px;">
      <div style="text-align:center;">
        <div style="color:rgba(255,255,255,0.5);font-size:0.8rem;margin-bottom:12px;">VOUS</div>
        <div style="width:60px;height:60px;background:var(--primary);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1.5rem;margin:0 auto;">👤</div>
      </div>
      <div style="display:flex;align-items:center;color:rgba(255,255,255,0.3);">→→→</div>
      <div class="referral-node"><div class="percent">20%</div><div class="level-label">Niveau 1</div><div style="color:rgba(255,255,255,0.5);font-size:0.75rem;margin-top:4px;">Filleuls directs</div></div>
      <div class="referral-node"><div class="percent">5%</div><div class="level-label">Niveau 2</div><div style="color:rgba(255,255,255,0.5);font-size:0.75rem;margin-top:4px;">Filleuls de filleuls</div></div>
      <div class="referral-node"><div class="percent">2%</div><div class="level-label">Niveau 3</div><div style="color:rgba(255,255,255,0.5);font-size:0.75rem;margin-top:4px;">3ème niveau</div></div>
    </div>
    <div style="background:rgba(255,255,255,0.1);border-radius:16px;padding:20px;display:inline-block;">
      <span style="color:white;font-size:1.1rem;font-weight:700;">Jusqu'à <span style="color:var(--primary)">27%</span> de commission sur chaque dépôt</span>
    </div>
    <div style="margin-top:32px;">
      <a href="/register.php" class="btn-primary-custom" style="font-size:1rem;padding:14px 32px;">Commencer à parrainer <i class="fas fa-arrow-right"></i></a>
    </div>
  </div>
</section>

<!-- FOOTER -->
<footer id="contact">
  <div style="max-width:1200px;margin:0 auto;padding:0 24px;">
    <div style="display:grid;grid-template-columns:2fr 1fr 1fr 1fr;gap:40px;margin-bottom:40px;" class="footer-grid">
      <div>
        <div class="footer-brand">⚡ CHARGEPOINT</div>
        <p style="margin-top:12px;font-size:0.88rem;max-width:280px;">Votre partenaire d'investissement de confiance pour construire un avenir financier solide en Afrique.</p>
        <div style="margin-top:20px;">
          <a href="#" class="social-link"><i class="fab fa-facebook-f"></i></a>
          <a href="#" class="social-link"><i class="fab fa-twitter"></i></a>
          <a href="#" class="social-link"><i class="fab fa-instagram"></i></a>
          <a href="#" class="social-link"><i class="fab fa-whatsapp"></i></a>
          <a href="#" class="social-link"><i class="fab fa-telegram"></i></a>
        </div>
      </div>
      <div>
        <div class="footer-title">Navigation</div>
        <a href="/" class="footer-link">Accueil</a>
        <a href="#plans" class="footer-link">Plans VIP</a>
        <a href="#avantages" class="footer-link">À propos</a>
        <a href="/register.php" class="footer-link">S'inscrire</a>
      </div>
      <div>
        <div class="footer-title">Légal</div>
        <a href="#" class="footer-link">Conditions d'utilisation</a>
        <a href="#" class="footer-link">Politique de confidentialité</a>
        <a href="#" class="footer-link">Mentions légales</a>
      </div>
      <div>
        <div class="footer-title">Contact</div>
        <a href="mailto:support@chargepoint.zya.me" class="footer-link"><i class="fas fa-envelope"></i> support@chargepoint.zya.me</a>
        <a href="#" class="footer-link"><i class="fab fa-whatsapp"></i> WhatsApp</a>
        <a href="#" class="footer-link"><i class="fab fa-telegram"></i> Telegram</a>
      </div>
    </div>
    <div class="footer-bottom">
      <p>© 2026 ChargePoint. Tous droits réservés. | <a href="#" style="color:var(--primary);">Conditions d'utilisation</a></p>
    </div>
  </div>
</footer>

<style>
@media (max-width: 991px) {
  .hero-grid { grid-template-columns: 1fr !important; }
  .hero-img-container { display: none; }
  .footer-grid { grid-template-columns: 1fr 1fr !important; gap: 24px !important; }
  #nav_links.show-mobile { display: flex !important; flex-direction: column; position: absolute; top: 70px; left: 0; right: 0; background: white; padding: 20px; border-bottom: 1px solid var(--border); z-index: 999; }
}
@media (max-width: 767px) {
  .footer-grid { grid-template-columns: 1fr !important; }
  #burger_menu { display: block !important; }
}
@media (max-width: 575px) {
  div[style*="grid-template-columns:repeat(4"] { grid-template-columns: repeat(2,1fr) !important; }
}
</style>
<script src="/assets/js/main.js"></script>
</body>
</html>
