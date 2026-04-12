<?php
// ══════════════════════════════════════════════════════════════════════════════
//  backend/pages/forgot-password.php — Supinfo.TV
// ══════════════════════════════════════════════════════════════════════════════

require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../services/auth.php';
require_once __DIR__ . '/../services/csrf.php';
require_once __DIR__ . '/../services/mailer.php';

auth_start_session();

$basePath = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME']))), '/');

if (auth_check()) {
    header('Location: ' . $basePath . '/pages/profile.php');
    exit;
}

$sent  = false;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $email  = trim($_POST['email'] ?? '');
    $result = auth_create_password_reset($email);

    if ($result['ok'] && $result['token'] !== null) {
        // Envoyer l'e-mail uniquement si l'utilisateur existe
        mailer_send_password_reset($result['email'], $result['username'], $result['token']);
    }

    // Toujours afficher le même message (anti user-enumeration)
    $sent = true;
}

$pageTitle  = 'Mot de passe oublié';
$pageCSS    = 'pages/login.css';
$pageDesc   = 'Réinitialisation de mot de passe — Supinfo.TV';
$activePage = '';

include __DIR__ . '/../partials/head.php';
include __DIR__ . '/../partials/loader.php';
include __DIR__ . '/../partials/navbar.php';
?>

<main>
<div style="min-height:80vh;display:flex;align-items:center;justify-content:center;padding:40px 16px;">
  <div style="max-width:420px;width:100%;">

    <a href="<?= $basePath ?>/pages/login.php" style="
      display:inline-flex;align-items:center;gap:6px;font-size:13px;
      color:var(--text-muted);margin-bottom:32px;
      transition:color var(--transition);" onmouseover="this.style.color='var(--accent)'" onmouseout="this.style.color='var(--text-muted)'">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M19 12H5M12 5l-7 7 7 7"/>
      </svg>
      Retour à la connexion
    </a>

    <h1 style="font-size:28px;color:var(--text);margin-bottom:8px;">Mot de passe oublié</h1>

    <?php if ($sent): ?>
      <div style="background:rgba(87,204,153,0.08);border:1px solid rgba(87,204,153,0.3);
                  border-radius:var(--radius);padding:20px 24px;margin-top:16px;">
        <p style="color:var(--accent);font-size:14px;line-height:1.7;margin:0;">
          ✅ Si cette adresse est associée à un compte, vous recevrez un e-mail
          avec un lien de réinitialisation sous quelques minutes.
        </p>
      </div>
      <p style="font-size:13px;color:var(--text-muted);margin-top:20px;">
        Pensez à vérifier vos spams.
      </p>

    <?php else: ?>
      <p style="color:var(--text-muted);margin-bottom:28px;line-height:1.7;">
        Saisissez votre adresse e-mail et nous vous enverrons un lien pour réinitialiser
        votre mot de passe.
      </p>

      <?php if ($error): ?>
        <div style="background:rgba(224,90,106,0.08);border:1px solid var(--danger);
                    border-radius:var(--radius);padding:12px 16px;margin-bottom:20px;">
          <p style="color:var(--danger);font-size:13px;margin:0;"><?= htmlspecialchars($error) ?></p>
        </div>
      <?php endif; ?>

      <form method="POST" action="">
        <?= csrf_field() ?>

        <div class="input-box" style="margin-bottom:20px;">
          <input type="email" name="email" placeholder="Votre adresse e-mail"
                 required autocomplete="email"
                 style="width:100%;padding:13px 18px;background:var(--surface-2);
                        border:1px solid var(--border);border-radius:var(--radius);
                        color:var(--text);font-family:var(--font);font-size:14px;outline:none;">
        </div>

        <button type="submit" class="btn-primary" style="width:100%;justify-content:center;">
          Envoyer le lien de réinitialisation
        </button>
      </form>
    <?php endif; ?>

  </div>
</div>
</main>

<?php include __DIR__ . '/../partials/footer.php'; ?>
</body>
</html>
