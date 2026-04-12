<?php
// ══════════════════════════════════════════════════════════════════════════════
//  backend/pages/verify-email.php — Supinfo.TV
// ══════════════════════════════════════════════════════════════════════════════

require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../services/auth.php';

auth_start_session();

$basePath = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME']))), '/');
$token    = trim($_GET['token'] ?? '');
$result   = ['ok' => false, 'error' => 'Token manquant.'];

if ($token !== '') {
    $result = auth_verify_email($token);
}

$pageTitle  = 'Vérification e-mail';
$pageCSS    = 'pages/login.css';
$pageDesc   = 'Vérification de votre adresse e-mail — Supinfo.TV';
$activePage = '';

include __DIR__ . '/../partials/head.php';
include __DIR__ . '/../partials/loader.php';
include __DIR__ . '/../partials/navbar.php';
?>

<main>
<div style="min-height:80vh;display:flex;align-items:center;justify-content:center;padding:40px 16px;">
  <div style="max-width:480px;width:100%;text-align:center;">

    <?php if ($result['ok']): ?>
      <div style="width:72px;height:72px;border-radius:50%;background:rgba(87,204,153,0.1);
                  border:1px solid var(--accent);display:flex;align-items:center;justify-content:center;
                  margin:0 auto 24px;">
        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2">
          <polyline points="20 6 9 17 4 12"/>
        </svg>
      </div>
      <h1 style="font-size:26px;color:var(--text);margin-bottom:12px;">E-mail vérifié !</h1>
      <p style="color:var(--text-muted);line-height:1.7;margin-bottom:28px;">
        Votre adresse e-mail a été confirmée avec succès. Vous pouvez maintenant profiter
        de toutes les fonctionnalités de Supinfo.TV.
      </p>
      <a href="<?= $basePath ?>/index.php" class="btn-primary">Retour à l'accueil</a>

    <?php else: ?>
      <div style="width:72px;height:72px;border-radius:50%;background:rgba(224,90,106,0.1);
                  border:1px solid var(--danger);display:flex;align-items:center;justify-content:center;
                  margin:0 auto 24px;">
        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="var(--danger)" stroke-width="2">
          <circle cx="12" cy="12" r="10"/>
          <line x1="15" y1="9" x2="9" y2="15"/>
          <line x1="9" y1="9" x2="15" y2="15"/>
        </svg>
      </div>
      <h1 style="font-size:26px;color:var(--text);margin-bottom:12px;">Lien invalide</h1>
      <p style="color:var(--text-muted);line-height:1.7;margin-bottom:28px;">
        <?= htmlspecialchars($result['error']) ?>
      </p>
      <?php if (auth_check()): ?>
        <form method="POST" action="<?= $basePath ?>/pages/resend-verification.php">
          <?= csrf_field() ?>
          <button type="submit" class="btn-primary">Renvoyer l'e-mail de vérification</button>
        </form>
      <?php else: ?>
        <a href="<?= $basePath ?>/pages/login.php" class="btn-primary">Se connecter</a>
      <?php endif; ?>
    <?php endif; ?>

  </div>
</div>
</main>

<?php include __DIR__ . '/../partials/footer.php'; ?>
</body>
</html>
