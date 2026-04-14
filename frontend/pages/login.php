<?php
// ══════════════════════════════════════════════════════════════════════════════
//  frontend/pages/login.php — Supinfo.TV
// ══════════════════════════════════════════════════════════════════════════════

require_once __DIR__ . '/../../backend/config/security.php';
require_once __DIR__ . '/../../backend/services/auth.php';
require_once __DIR__ . '/../../backend/services/csrf.php';
require_once __DIR__ . '/../../backend/config/database.php';

auth_start_session();

if (auth_check()) {
    $basePath = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME']))), '/');
    header('Location: ' . $basePath . '/index.php');
    exit;
}

$basePath = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME']))), '/');
$mode     = ($_GET['mode'] ?? '') === 'register' ? 'register' : 'login';

$csrfToken = csrf_token();

$loginError    = null;
$registerError = null;
$authNotice    = $_SESSION['auth_notice'] ?? null;
$authOld       = [];
unset($_SESSION['auth_notice']);

// ── Validation email (inscription) ───────────────────────────────────────────
function validate_email_for_register(string $email): array
{
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => "Le format de l'adresse e-mail est invalide."];
    }

    $domain = strtolower(substr(strrchr($email, '@'), 1));

    $disposable = [
        'mailinator.com','guerrillamail.com','tempmail.com','throwam.com',
        'yopmail.com','sharklasers.com','grr.la','guerrillamail.info',
        'guerrillamail.biz','guerrillamail.de','guerrillamail.net',
        'guerrillamail.org','spam4.me','trashmail.com','trashmail.me',
        'trashmail.net','dispostable.com','fakeinbox.com','mailnull.com',
        'maildrop.cc','tempr.email','discard.email','mailnesia.com',
        'filzmail.com','getairmail.com','trashmail.at','trashmail.io',
        'spambox.us','mintemail.com','tempinbox.com','fakemail.net',
        'temp-mail.org','mailtemp.info','getonemail.com','despam.it',
    ];

    if (in_array($domain, $disposable, true)) {
        return ['ok' => false, 'error' => "Les adresses e-mail temporaires ne sont pas acceptées."];
    }

    if (!checkdnsrr($domain, 'MX')) {
        return [
            'ok'    => false,
            'error' => "Le domaine <strong>@{$domain}</strong> n'accepte pas d'e-mails. Vérifiez votre adresse."
        ];
    }

    return ['ok' => true, 'error' => null];
}

// ── Traitement POST ───────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action    = $_POST['action'] ?? '';
    $submitted = $_POST['_csrf_token'] ?? '';

    if (!hash_equals($csrfToken, $submitted)) {
        $loginError = "Requête invalide. Veuillez réessayer.";
    } else {

        if ($action === 'login') {
            $email    = trim($_POST['email']    ?? '');
            $password = $_POST['password']      ?? '';
            $mode     = 'login';

            if (empty($email) || empty($password)) {
                $loginError = "Veuillez remplir tous les champs.";
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $loginError = "Format d'adresse e-mail invalide.";
            } else {
                $result = auth_login($email, $password);
                if ($result['ok']) {
                    $redirect = filter_var($_POST['redirect'] ?? '', FILTER_SANITIZE_URL);
                    $dest = ($redirect && str_starts_with($redirect, '/')) ? $redirect : $basePath . '/index.php';
                    header('Location: ' . $dest);
                    exit;
                } elseif (!empty($result['rate_limited'])) {
                    $loginError = "Trop de tentatives échouées. Réessayez dans quelques minutes.";
                } else {
                    $loginError = "Identifiants incorrects.";
                }
            }
        }

        if ($action === 'register') {
            $username  = trim($_POST['username']  ?? '');
            $email     = trim($_POST['email']     ?? '');
            $password  = $_POST['password']       ?? '';
            $password2 = $_POST['password2']      ?? '';
            $mode      = 'register';
            $authOld   = ['username' => $username, 'email' => $email];

            if (empty($username) || empty($email) || empty($password) || empty($password2)) {
                $registerError = "Veuillez remplir tous les champs.";
            } elseif ($password !== $password2) {
                $registerError = "Les mots de passe ne correspondent pas.";
            } else {
                $emailCheck = validate_email_for_register($email);
                if (!$emailCheck['ok']) {
                    $registerError = $emailCheck['error'];
                } else {
                    require_once __DIR__ . '/../../backend/services/mailer.php';
                    $result = auth_register($username, $email, $password);

                    if ($result['ok']) {
                        if (!empty($result['token'])) {
                            mailer_send_verification($email, $username, $result['token']);
                        }
                        $_SESSION['auth_notice'] = "✅ Compte créé ! Un e-mail de vérification a été envoyé à <strong>" . htmlspecialchars($email) . "</strong>.";
                        header('Location: ' . $basePath . '/pages/login.php');
                        exit;
                    } else {
                        $registerError = $result['error'];
                    }
                }
            }
        }
    }
}

// ── URL OAuth Google ──────────────────────────────────────────────────────────
$googleClientId = getenv('GOOGLE_CLIENT_ID') ?: '';
$googleAuthUrl  = '';
if ($googleClientId) {
    $googleState    = bin2hex(random_bytes(16));
    $_SESSION['oauth_state'] = $googleState;
    $googleAuthUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
        'client_id'     => $googleClientId,
        'redirect_uri'  => (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $basePath . '/backend/pages/oauth-google.php',
        'response_type' => 'code',
        'scope'         => 'openid email profile',
        'state'         => $googleState,
        'prompt'        => 'select_account',
    ]);
}

// ── URL OAuth Facebook ────────────────────────────────────────────────────────
$fbAppId   = getenv('FACEBOOK_APP_ID') ?: '';
$fbAuthUrl = '';
if ($fbAppId) {
    $fbState    = bin2hex(random_bytes(16));
    $_SESSION['oauth_fb_state'] = $fbState;
    $fbAuthUrl = 'https://www.facebook.com/v19.0/dialog/oauth?' . http_build_query([
        'client_id'     => $fbAppId,
        'redirect_uri'  => (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $basePath . '/backend/pages/oauth-facebook.php',
        'state'         => $fbState,
        'scope'         => 'email,public_profile',
    ]);
}

$pageTitle  = 'Connexion';
$pageCSS    = 'pages/login.css';
$pageDesc   = 'Connectez-vous à Supinfo.TV ou créez un compte.';
$activePage = '';

include __DIR__ . '/../partials/head.php';
include __DIR__ . '/../partials/loader.php';
?>

<link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">

<style>
/* ── Statuts email / mot de passe ─────────────────────────────────── */
.email-status, .pwd-status {
    display: none;
    align-items: center;
    gap: 6px;
    font-size: 11px;
    margin-top: 5px;
    margin-bottom: 4px;
    padding: 6px 10px;
    border-radius: var(--radius-sm);
    animation: fadeInStatus 0.2s ease;
}
.email-status.show, .pwd-status.show { display: flex; }
.email-status.invalid  { color: var(--danger); background: rgba(224,90,106,0.08); border: 1px solid rgba(224,90,106,0.3); }
.email-status.checking { color: var(--text-muted); background: var(--surface-2); border: 1px solid var(--border); }
.pwd-status.valid   { color: var(--accent); background: rgba(87,204,153,0.1); border: 1px solid rgba(87,204,153,0.3); }
.pwd-status.invalid { color: var(--danger); background: rgba(224,90,106,0.08); border: 1px solid rgba(224,90,106,0.3); }

@keyframes fadeInStatus {
    from { opacity: 0; transform: translateY(-4px); }
    to   { opacity: 1; transform: translateY(0); }
}

/* ── Notice ───────────────────────────────────────────────────────── */
.auth-notice {
    position: fixed;
    top: 80px;
    left: 50%;
    transform: translateX(-50%);
    z-index: 200;
    background: rgba(87,204,153,0.12);
    border: 1px solid rgba(87,204,153,0.4);
    color: var(--accent);
    font-size: 13px;
    padding: 14px 24px;
    border-radius: var(--radius);
    backdrop-filter: blur(12px);
    box-shadow: var(--shadow-md);
    max-width: 480px;
    text-align: center;
    line-height: 1.6;
}

/* ── Erreur ───────────────────────────────────────────────────────── */
.auth-error-box {
    background: rgba(224,90,106,0.1);
    border: 1px solid var(--danger);
    border-radius: var(--radius);
    padding: 12px 14px;
    margin-bottom: 14px;
    font-size: 13px;
    color: var(--danger);
    line-height: 1.6;
    text-align: left;
}

/* ── Séparateur "ou" ──────────────────────────────────────────────── */
.auth-divider {
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 6px 0;
    color: var(--text-faint);
    font-size: 12px;
    letter-spacing: 0.06em;
}
.auth-divider::before, .auth-divider::after {
    content: '';
    flex: 1;
    height: 1px;
    background: var(--border);
}

/* ── Boutons sociaux ──────────────────────────────────────────────── */
.social-icons {
    display: flex;
    justify-content: center;
    gap: 10px;
    margin-top: 4px;
}
.social-icons a {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    height: 42px;
    border: 1px solid var(--border);
    border-radius: var(--radius);
    font-size: 13px;
    color: var(--text-muted);
    background: var(--surface-2);
    text-decoration: none;
    padding: 0 16px;
    transition:
        color var(--transition),
        border-color var(--transition),
        background var(--transition),
        transform var(--transition-snap),
        box-shadow var(--transition-slow);
    white-space: nowrap;
    font-family: var(--font);
    font-weight: 500;
}
.social-icons a:hover {
    transform: translateY(-2px);
    box-shadow: 0 0 12px var(--accent-glow);
}
.social-icons a.btn-google {
    flex: 1;
}
.social-icons a.btn-google:hover {
    color: #fff;
    border-color: #4285f4;
    background: rgba(66,133,244,0.15);
}
.social-icons a.btn-facebook {
    flex: 1;
}
.social-icons a.btn-facebook:hover {
    color: #fff;
    border-color: #1877f2;
    background: rgba(24,119,242,0.15);
}
.social-icons a.btn-social-disabled {
    opacity: 0.45;
    cursor: not-allowed;
    pointer-events: none;
}
.social-icon-svg {
    display: block;
    flex-shrink: 0;
}
</style>

<main>

  <a href="<?= $basePath ?>/index.php" class="auth-back">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
      <path d="M19 12H5M12 5l-7 7 7 7"/>
    </svg>
    Retour
  </a>

  <?php if ($authNotice): ?>
  <div class="auth-notice"><?= $authNotice ?></div>
  <?php endif; ?>

  <div class="auth-page">
    <div class="auth-container <?= $mode === 'register' ? 'active' : '' ?>" id="auth-container">

      <!-- ══ CONNEXION ════════════════════════════════════════════════════════ -->
      <div class="form-box login">
        <form method="POST" action="">
          <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
          <input type="hidden" name="action" value="login">

          <h1>Connexion</h1>

          <?php if ($loginError && $mode === 'login'): ?>
          <div class="auth-error-box">⚠️ <?= $loginError ?></div>
          <?php endif; ?>

          <div class="input-box">
            <input type="email" name="email" placeholder="Adresse e-mail"
                   required autocomplete="email"
                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
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
            <!-- Google -->
            <?php if ($googleAuthUrl): ?>
            <a href="<?= htmlspecialchars($googleAuthUrl) ?>" class="btn-google">
              <svg class="social-icon-svg" width="18" height="18" viewBox="0 0 24 24">
                <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
              </svg>
              Google
            </a>
            <?php else: ?>
            <a href="#" class="btn-google btn-social-disabled" title="Configurez GOOGLE_CLIENT_ID dans .env">
              <svg class="social-icon-svg" width="18" height="18" viewBox="0 0 24 24">
                <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
              </svg>
              Google
            </a>
            <?php endif; ?>

            <!-- Facebook -->
            <?php if ($fbAuthUrl): ?>
            <a href="<?= htmlspecialchars($fbAuthUrl) ?>" class="btn-facebook">
              <svg class="social-icon-svg" width="18" height="18" viewBox="0 0 24 24" fill="#1877F2">
                <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
              </svg>
              Facebook
            </a>
            <?php else: ?>
            <a href="#" class="btn-facebook btn-social-disabled" title="Configurez FACEBOOK_APP_ID dans .env">
              <svg class="social-icon-svg" width="18" height="18" viewBox="0 0 24 24" fill="#1877F2">
                <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
              </svg>
              Facebook
            </a>
            <?php endif; ?>
          </div>
        </form>
      </div>

      <!-- ══ INSCRIPTION ══════════════════════════════════════════════════════ -->
      <div class="form-box register">
        <form method="POST" action="?mode=register">
          <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
          <input type="hidden" name="action" value="register">

          <h1>Inscription</h1>

          <?php if ($registerError && $mode === 'register'): ?>
          <div class="auth-error-box">⚠️ <?= $registerError ?></div>
          <?php endif; ?>

          <div class="input-box">
            <input type="text" name="username" placeholder="Nom d'utilisateur"
                   required autocomplete="username" minlength="3" maxlength="60"
                   value="<?= htmlspecialchars($authOld['username'] ?? '') ?>">
            <i class="bx bxs-user"></i>
          </div>

          <div class="input-box">
            <input type="email" name="email" id="register-email"
                   placeholder="Adresse e-mail" required autocomplete="email"
                   value="<?= htmlspecialchars($authOld['email'] ?? '') ?>">
            <i class="bx bxs-envelope"></i>
          </div>
          <div class="email-status" id="register-email-status"></div>

          <div class="input-box">
            <input type="password" name="password" id="reg-pwd"
                   placeholder="Mot de passe (min. 8 car.)"
                   required autocomplete="new-password" minlength="8">
            <i class="bx bxs-lock-alt"></i>
          </div>

          <div class="input-box">
            <input type="password" name="password2" id="reg-pwd2"
                   placeholder="Confirmer le mot de passe"
                   required autocomplete="new-password" minlength="8">
            <i class="bx bxs-lock-alt"></i>
          </div>
          <div class="pwd-status" id="pwd-match-status"></div>

          <button type="submit" class="auth-btn">Créer un compte</button>

          <div class="auth-divider">ou continuer avec</div>

          <div class="social-icons">
            <?php if ($googleAuthUrl): ?>
            <a href="<?= htmlspecialchars($googleAuthUrl) ?>" class="btn-google">
              <svg class="social-icon-svg" width="18" height="18" viewBox="0 0 24 24">
                <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
              </svg>
              Google
            </a>
            <?php else: ?>
            <a href="#" class="btn-google btn-social-disabled">
              <svg class="social-icon-svg" width="18" height="18" viewBox="0 0 24 24">
                <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
              </svg>
              Google
            </a>
            <?php endif; ?>

            <?php if ($fbAuthUrl): ?>
            <a href="<?= htmlspecialchars($fbAuthUrl) ?>" class="btn-facebook">
              <svg class="social-icon-svg" width="18" height="18" viewBox="0 0 24 24" fill="#1877F2">
                <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
              </svg>
              Facebook
            </a>
            <?php else: ?>
            <a href="#" class="btn-facebook btn-social-disabled">
              <svg class="social-icon-svg" width="18" height="18" viewBox="0 0 24 24" fill="#1877F2">
                <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
              </svg>
              Facebook
            </a>
            <?php endif; ?>
          </div>
        </form>
      </div>

      <!-- ══ TOGGLE ════════════════════════════════════════════════════════════ -->
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
(function () {
    'use strict';

    // ── Toggle login / register ───────────────────────────────────────────
    const container   = document.getElementById('auth-container');
    const registerBtn = container.querySelector('.register-btn');
    const loginBtn    = container.querySelector('.login-btn');
    registerBtn.addEventListener('click', () => container.classList.add('active'));
    loginBtn.addEventListener('click',    () => container.classList.remove('active'));

    // ── Utilitaires ──────────────────────────────────────────────────────
    function debounce(fn, delay) {
        let t;
        return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), delay); };
    }

    function showEmailStatus(state, msg) {
        const el = document.getElementById('register-email-status');
        if (!el) return;
        el.className = 'email-status show ' + state;
        el.innerHTML = { invalid: '❌', checking: '⏳' }[state] + ' ' + msg;
    }

    function hideEmailStatus() {
        const el = document.getElementById('register-email-status');
        if (!el) return;
        el.className = 'email-status';
        el.innerHTML = '';
    }

    const DISPOSABLE = new Set([
        'mailinator.com','guerrillamail.com','tempmail.com','throwam.com',
        'yopmail.com','sharklasers.com','grr.la','spam4.me','trashmail.com',
        'trashmail.me','trashmail.net','dispostable.com','fakeinbox.com',
        'maildrop.cc','tempr.email','discard.email','mailnesia.com',
        'filzmail.com','getairmail.com','spambox.us','mintemail.com',
        'tempinbox.com','fakemail.net','temp-mail.org','mailtemp.info',
    ]);

    const KNOWN_VALID = new Set([
        'gmail.com','googlemail.com','yahoo.com','yahoo.fr','yahoo.co.uk',
        'hotmail.com','hotmail.fr','outlook.com','outlook.fr','live.com',
        'live.fr','icloud.com','me.com','mac.com','protonmail.com',
        'proton.me','laposte.net','orange.fr','sfr.fr','free.fr',
        'bbox.fr','wanadoo.fr','bouyguestelecom.fr',
    ]);

    // ── Validation email inscription ──────────────────────────────────────
    const registerEmailInput = document.getElementById('register-email');
    if (registerEmailInput) {
        const checkEmail = debounce(async (value) => {
            if (!value) { hideEmailStatus(); return; }
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(value)) {
                showEmailStatus('invalid', "Format d'adresse e-mail invalide.");
                return;
            }
            const domain = value.split('@')[1].toLowerCase();
            if (DISPOSABLE.has(domain)) {
                showEmailStatus('invalid', 'Les adresses e-mail temporaires ne sont pas acceptées.');
                return;
            }
            if (KNOWN_VALID.has(domain)) { hideEmailStatus(); return; }
            showEmailStatus('checking', 'Vérification du domaine…');
            try {
                const res  = await fetch('https://dns.google/resolve?name=' + encodeURIComponent(domain) + '&type=MX');
                const data = await res.json();
                if (data.Answer && data.Answer.length > 0) {
                    hideEmailStatus();
                } else {
                    showEmailStatus('invalid', 'Le domaine <strong>@' + domain + '</strong> n\'accepte pas d\'e-mails.');
                }
            } catch { hideEmailStatus(); }
        }, 700);

        registerEmailInput.addEventListener('input', (e) => checkEmail(e.target.value.trim()));
        registerEmailInput.addEventListener('blur',  (e) => checkEmail(e.target.value.trim()));
    }

    // ── Correspondance mots de passe ──────────────────────────────────────
    const pwd1  = document.getElementById('reg-pwd');
    const pwd2  = document.getElementById('reg-pwd2');
    const pwdSt = document.getElementById('pwd-match-status');

    function checkPwd() {
        if (!pwdSt || !pwd2 || !pwd2.value) {
            if (pwdSt) { pwdSt.className = 'pwd-status'; pwdSt.innerHTML = ''; }
            return;
        }
        if (pwd1.value === pwd2.value) {
            pwdSt.className = 'pwd-status show valid';
            pwdSt.innerHTML = '✅ Les mots de passe correspondent.';
        } else {
            pwdSt.className = 'pwd-status show invalid';
            pwdSt.innerHTML = '❌ Les mots de passe ne correspondent pas.';
        }
    }
    pwd1?.addEventListener('input', checkPwd);
    pwd2?.addEventListener('input', checkPwd);

    // ── Auto-dismiss notice ───────────────────────────────────────────────
    const notice = document.querySelector('.auth-notice');
    if (notice) {
        setTimeout(() => {
            notice.style.transition = 'opacity 0.5s ease';
            notice.style.opacity    = '0';
            setTimeout(() => notice.remove(), 500);
        }, 7000);
    }

})();
</script>

</body>
</html>