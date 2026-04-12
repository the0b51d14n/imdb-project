<?php
// ══════════════════════════════════════════════════════════════════════════════
//  frontend/pages/login.php — Supinfo.TV
//  Formulaire de connexion / inscription.
//  Le traitement se fait dans backend/pages/login.php.
// ══════════════════════════════════════════════════════════════════════════════

session_start();

if (!empty($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

// Messages transmis via session par le handler backend
$authError  = $_SESSION['auth_error']  ?? null;
$authNotice = $_SESSION['auth_notice'] ?? null;
$authOld    = $_SESSION['auth_old']    ?? [];
$authEmail  = $_SESSION['auth_email']  ?? '';
unset($_SESSION['auth_error'], $_SESSION['auth_notice'], $_SESSION['auth_old'], $_SESSION['auth_email']);

$mode = ($_GET['mode'] ?? '') === 'register' ? 'register' : 'login';

$pageTitle  = 'Connexion';
$pageCSS    = 'pages/login.css';
$pageDesc   = 'Connectez-vous à Supinfo.TV ou créez un compte.';
$activePage = '';

$basePath = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME']))), '/');

// CSRF token pour le formulaire
// Le handler backend gère la vérification — on passe le token via session
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['_csrf_token'])) {
    $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['_csrf_token'];

include __DIR__ . '/../partials/head.php';
include __DIR__ . '/../partials/loader.php';
?>

<link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">

<main>

  <a href="<?= $basePath ?>/index.php" class="auth-back">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
      <path d="M19 12H5M12 5l-7 7 7 7"/>
    </svg>
    Retour
  </a>

  <?php if ($authNotice): ?>
  <div style="position:fixed;top:80px;left:50%;transform:translateX(-50%);z-index:200;
              background:rgba(87,204,153,0.12);border:1px solid rgba(87,204,153,0.4);
              color:var(--accent);font-size:13px;padding:12px 24px;border-radius:var(--radius);
              backdrop-filter:blur(12px);box-shadow:var(--shadow-md);">
    <?= htmlspecialchars($authNotice) ?>
  </div>
  <?php endif; ?>

  <div class="auth-page">
    <div class="auth-container" id="auth-container">

      <!-- ── CONNEXION ───────────────────────────────────────────────────── -->
      <div class="form-box login">
        <form action="<?= $basePath ?>/backend/pages/login.php" method="POST">
          <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
          <input type="hidden" name="action" value="login">

          <h1>Connexion</h1>

          <?php if ($authError && $mode === 'login'): ?>
          <div style="background:rgba(224,90,106,0.1);border:1px solid var(--danger);
                      border-radius:var(--radius);padding:10px 14px;margin-bottom:12px;">
            <p style="color:var(--danger);font-size:13px;margin:0;"><?= htmlspecialchars($authError) ?></p>
          </div>
          <?php endif; ?>

          <div class="input-box">
            <input type="email" name="email" placeholder="Adresse e-mail"
                   required autocomplete="email"
                   value="<?= htmlspecialchars($authEmail) ?>">
            <i class="bx bxs-envelope"></i>
          </div>

          <div class="input-box">
            <input type="password" name="password" placeholder="Mot de passe"
                   required autocomplete="current-password">
            <i class="bx bxs-lock-alt"></i>
          </div>

          <div class="forgot-link">
            <a href="<?= $basePath ?>/pages/forgot-password.php">Mot de passe oublié ?</a>
          </div>

          <button type="submit" class="auth-btn">Se connecter</button>

          <div class="auth-divider">ou continuer avec</div>

          <div class="social-icons">
            <a href="#" title="Google"><i class="bx bxl-google"></i></a>
            <a href="#" title="GitHub"><i class="bx bxl-github"></i></a>
          </div>
        </form>
      </div>

      <!-- ── INSCRIPTION ─────────────────────────────────────────────────── -->
      <div class="form-box register">
        <form action="<?= $basePath ?>/backend/pages/login.php" method="POST">
          <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
          <input type="hidden" name="action" value="register">

          <h1>Inscription</h1>

          <?php if ($authError && $mode === 'register'): ?>
          <div style="background:rgba(224,90,106,0.1);border:1px solid var(--danger);
                      border-radius:var(--radius);padding:10px 14px;margin-bottom:12px;">
            <p style="color:var(--danger);font-size:13px;margin:0;"><?= htmlspecialchars($authError) ?></p>
          </div>
          <?php endif; ?>

          <div class="input-box">
            <input type="text" name="username" placeholder="Nom d'utilisateur"
                   required autocomplete="username" minlength="3" maxlength="60"
                   value="<?= htmlspecialchars($authOld['username'] ?? '') ?>">
            <i class="bx bxs-user"></i>
          </div>

          <div class="input-box">
            <input type="email" name="email" placeholder="Adresse e-mail"
                   required autocomplete="email"
                   value="<?= htmlspecialchars($authOld['email'] ?? '') ?>">
            <i class="bx bxs-envelope"></i>
          </div>

          <div class="input-box">
            <input type="password" name="password" placeholder="Mot de passe (min. 8 car.)"
                   required autocomplete="new-password" minlength="8">
            <i class="bx bxs-lock-alt"></i>
          </div>

          <div class="input-box">
            <input type="password" name="password2" placeholder="Confirmer le mot de passe"
                   required autocomplete="new-password" minlength="8">
            <i class="bx bxs-lock-alt"></i>
          </div>

          <button type="submit" class="auth-btn">Créer un compte</button>

          <div class="auth-divider">ou continuer avec</div>

          <div class="social-icons">
            <a href="#" title="Google"><i class="bx bxl-google"></i></a>
            <a href="#" title="GitHub"><i class="bx bxl-github"></i></a>
          </div>
        </form>
      </div>

      <!-- ── TOGGLE ──────────────────────────────────────────────────────── -->
      <div class="toggle-box">
        <div class="toggle-panel toggle-left">
          <h1>Bienvenue !</h1>
          <p>Pas encore de compte ?</p>
          <button class="toggle-btn register-btn">S'inscrire</button>
        </div>
        <div class="toggle-panel toggle-right">
          <h1>Bon retour !</h1>
          <p>Déjà membre ?</p>
          <button class="toggle-btn login-btn">Se connecter</button>
        </div>
      </div>

    </div>
  </div>
</main>

<script src="<?= $basePath ?>/assets/js/components/loader.js"></script>
<script>
  const container   = document.getElementById('auth-container');
  const registerBtn = container.querySelector('.register-btn');
  const loginBtn    = container.querySelector('.login-btn');

  registerBtn.addEventListener('click', () => container.classList.add('active'));
  loginBtn.addEventListener('click',    () => container.classList.remove('active'));

  const mode = '<?= $mode ?>';
  if (mode === 'register') container.classList.add('active');

  // Auto-dismiss notice
  const notice = document.querySelector('[data-notice]');
  if (notice) setTimeout(() => notice.style.opacity = '0', 4000);
</script>

</body>
</html>
