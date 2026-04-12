<?php
// ══════════════════════════════════════════════════════════════════════════════
//  backend/pages/reset-password.php — Supinfo.TV
// ══════════════════════════════════════════════════════════════════════════════

require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../services/auth.php';
require_once __DIR__ . '/../services/csrf.php';

auth_start_session();

$basePath = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME']))), '/');
$token    = trim($_GET['token'] ?? '');

if (empty($token)) {
    header('Location: ' . $basePath . '/pages/forgot-password.php');
    exit;
}

$success = false;
$error   = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $newPassword = $_POST['password']         ?? '';
    $confirmPwd  = $_POST['confirm_password'] ?? '';
    $postToken   = $_POST['token']            ?? '';

    if ($newPassword !== $confirmPwd) {
        $error = "Les mots de passe ne correspondent pas.";
    } else {
        $result = auth_reset_password($postToken, $newPassword);
        if ($result['ok']) {
            $success = true;
        } else {
            $error = $result['error'];
        }
    }
}

$pageTitle  = 'Nouveau mot de passe';
$pageCSS    = 'pages/login.css';
$pageDesc   = 'Réinitialisation du mot de passe — Supinfo.TV';
$activePage = '';

include __DIR__ . '/../partials/head.php';
include __DIR__ . '/../partials/loader.php';
include __DIR__ . '/../partials/navbar.php';
?>

<main>
<div style="min-height:80vh;display:flex;align-items:center;justify-content:center;padding:40px 16px;">
  <div style="max-width:420px;width:100%;">

    <?php if ($success): ?>

      <div style="text-align:center;">
        <div style="width:72px;height:72px;border-radius:50%;background:rgba(87,204,153,0.1);
                    border:1px solid var(--accent);display:flex;align-items:center;justify-content:center;
                    margin:0 auto 24px;">
          <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2">
            <polyline points="20 6 9 17 4 12"/>
          </svg>
        </div>
        <h1 style="font-size:26px;color:var(--text);margin-bottom:12px;">Mot de passe modifié !</h1>
        <p style="color:var(--text-muted);line-height:1.7;margin-bottom:28px;">
          Votre mot de passe a été réinitialisé avec succès. Vous pouvez maintenant
          vous connecter avec votre nouveau mot de passe.
        </p>
        <a href="<?= $basePath ?>/pages/login.php" class="btn-primary">Se connecter</a>
      </div>

    <?php else: ?>

      <h1 style="font-size:28px;color:var(--text);margin-bottom:8px;">Nouveau mot de passe</h1>
      <p style="color:var(--text-muted);margin-bottom:28px;line-height:1.7;">
        Choisissez un nouveau mot de passe sécurisé pour votre compte.
      </p>

      <?php if ($error): ?>
        <div style="background:rgba(224,90,106,0.08);border:1px solid var(--danger);
                    border-radius:var(--radius);padding:12px 16px;margin-bottom:20px;">
          <p style="color:var(--danger);font-size:13px;margin:0;"><?= htmlspecialchars($error) ?></p>
        </div>
      <?php endif; ?>

      <form method="POST" action="">
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

        <div style="margin-bottom:16px;">
          <input type="password" name="password" placeholder="Nouveau mot de passe"
                 required autocomplete="new-password" minlength="8"
                 style="width:100%;padding:13px 18px;background:var(--surface-2);
                        border:1px solid var(--border);border-radius:var(--radius);
                        color:var(--text);font-family:var(--font);font-size:14px;outline:none;">
        </div>

        <div style="margin-bottom:20px;">
          <input type="password" name="confirm_password" placeholder="Confirmer le mot de passe"
                 required autocomplete="new-password" minlength="8"
                 style="width:100%;padding:13px 18px;background:var(--surface-2);
                        border:1px solid var(--border);border-radius:var(--radius);
                        color:var(--text);font-family:var(--font);font-size:14px;outline:none;">
        </div>

        <p style="font-size:12px;color:var(--text-faint);margin-bottom:20px;">
          8 caractères minimum · 1 majuscule · 1 chiffre
        </p>

        <button type="submit" class="btn-primary" style="width:100%;justify-content:center;">
          Réinitialiser le mot de passe
        </button>
      </form>

    <?php endif; ?>

  </div>
</div>
</main>

<?php include __DIR__ . '/../partials/footer.php'; ?>
</body>
</html>
