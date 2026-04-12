<?php
// ══════════════════════════════════════════════════════════════════════════════
//  frontend/pages/login.php — Supinfo.TV
//  Formulaire de connexion / inscription avec validation MX et messages détaillés.
// ══════════════════════════════════════════════════════════════════════════════

session_start();

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

if (empty($_SESSION['_csrf_token'])) {
    $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['_csrf_token'];

$loginError    = null;
$registerError = null;
$authNotice    = $_SESSION['auth_notice'] ?? null;
$authOld       = [];
unset($_SESSION['auth_notice']);

// ── Validation email + vérification MX ───────────────────────────────────────
function validate_email_with_mx(string $email): array
{
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => "Le format de l'adresse e-mail est invalide."];
    }

    $domain = substr(strrchr($email, '@'), 1);

    // Vérifie enregistrement MX puis A en fallback
    if (!checkdnsrr($domain, 'MX') && !checkdnsrr($domain, 'A')) {
        return [
            'ok'    => false,
            'error' => "Le domaine <strong>@{$domain}</strong> n'existe pas ou n'accepte pas d'e-mails. Vérifiez votre adresse."
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

        // ── CONNEXION ─────────────────────────────────────────────────────────
        if ($action === 'login') {
            $email    = trim($_POST['email']    ?? '');
            $password = $_POST['password']      ?? '';
            $mode     = 'login';

            if (empty($email) || empty($password)) {
                $loginError = "Veuillez remplir tous les champs.";
            } else {
                $emailCheck = validate_email_with_mx($email);
                if (!$emailCheck['ok']) {
                    $loginError = $emailCheck['error'];
                } else {
                    $stmt = db()->prepare('SELECT id, password_hash FROM users WHERE email = :email LIMIT 1');
                    $stmt->execute([':email' => strtolower($email)]);
                    $user = $stmt->fetch();

                    if (!$user) {
                        $loginError = "Aucun compte n'est associé à cette adresse e-mail. <a href=\"{$basePath}/pages/login.php?mode=register\" style=\"color:var(--accent);text-decoration:underline;\">Créer un compte ?</a>";
                    } elseif (!password_verify($password, $user['password_hash'])) {
                        $loginError = "Mot de passe incorrect. Vous pouvez le <a href=\"{$basePath}/pages/forgot-password.php\" style=\"color:var(--accent);text-decoration:underline;\">réinitialiser ici</a>.";
                    } else {
                        $result = auth_login($email, $password);
                        if ($result['ok']) {
                            $redirect = filter_var($_POST['redirect'] ?? '', FILTER_SANITIZE_URL);
                            $dest = ($redirect && str_starts_with($redirect, '/')) ? $redirect : $basePath . '/index.php';
                            header('Location: ' . $dest);
                            exit;
                        } elseif (!empty($result['rate_limited'])) {
                            $loginError = "Trop de tentatives échouées. Veuillez réessayer dans quelques minutes.";
                        } else {
                            $loginError = $result['error'];
                        }
                    }
                }
            }
        }

        // ── INSCRIPTION ───────────────────────────────────────────────────────
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
                $emailCheck = validate_email_with_mx($email);
                if (!$emailCheck['ok']) {
                    $registerError = $emailCheck['error'];
                } else {
                    require_once __DIR__ . '/../../backend/services/mailer.php';
                    $result = auth_register($username, $email, $password);

                    if ($result['ok']) {
                        if ($result['token']) {
                            mailer_send_verification($email, $username, $result['token']);
                        }
                        $_SESSION['auth_notice'] = "✅ Compte créé ! Un e-mail de vérification a été envoyé à <strong>{$email}</strong>. Cliquez sur le lien pour activer votre compte.";
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

$pageTitle  = 'Connexion';
$pageCSS    = 'pages/login.css';
$pageDesc   = 'Connectez-vous à Supinfo.TV ou créez un compte.';
$activePage = '';

include __DIR__ . '/../partials/head.php';
include __DIR__ . '/../partials/loader.php';
?>

<link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">

<style>
.email-status {
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
.email-status.show   { display: flex; }
.email-status.valid   { color: var(--accent); background: rgba(87,204,153,0.1); border: 1px solid rgba(87,204,153,0.3); }
.email-status.invalid { color: var(--danger); background: rgba(224,90,106,0.08); border: 1px solid rgba(224,90,106,0.3); }
.email-status.checking{ color: var(--text-muted); background: var(--surface-2); border: 1px solid var(--border); }
@keyframes fadeInStatus { from { opacity:0; transform:translateY(-4px); } to { opacity:1; transform:translateY(0); } }

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

      <!-- ── CONNEXION ───────────────────────────────────────────────────── -->
      <div class="form-box login">
        <form method="POST" action="">
          <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
          <input type="hidden" name="action" value="login">

          <h1>Connexion</h1>

          <?php if ($loginError && $mode === 'login'): ?>
          <div class="auth-error-box">⚠️ <?= $loginError ?></div>
          <?php endif; ?>

          <div class="input-box">
            <input type="email" name="email" id="login-email"
                   placeholder="Adresse e-mail" required autocomplete="email"
                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            <i class="bx bxs-envelope"></i>
          </div>
          <div class="email-status" id="login-email-status"></div>

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
          <div class="email-status" id="pwd-match-status"></div>

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

    function showStatus(el, state, msg) {
        el.className = 'email-status show ' + state;
        const icons = { valid: '✅', invalid: '❌', checking: '⏳' };
        el.innerHTML = icons[state] + ' ' + msg;
    }

    function hideStatus(el) {
        el.className = 'email-status';
        el.innerHTML = '';
    }

    function validFormat(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email);
    }

    // Domaines toujours valides (pas besoin d'appel DNS)
    const KNOWN_VALID = new Set([
        'gmail.com','googlemail.com','yahoo.com','yahoo.fr','yahoo.co.uk',
        'hotmail.com','hotmail.fr','outlook.com','outlook.fr','live.com',
        'live.fr','icloud.com','me.com','mac.com','protonmail.com',
        'proton.me','laposte.net','orange.fr','sfr.fr','free.fr',
        'bbox.fr','wanadoo.fr','numericable.fr','bouyguestelecom.fr',
    ]);

    // ── Validation email avec DNS over HTTPS ──────────────────────────────
    function initEmailValidation(inputId, statusId) {
        const input  = document.getElementById(inputId);
        const status = document.getElementById(statusId);
        if (!input || !status) return;

        const check = debounce(async (value) => {
            if (!value) { hideStatus(status); return; }
            if (!validFormat(value)) {
                showStatus(status, 'invalid', "Format d'adresse e-mail invalide.");
                return;
            }

            const domain = value.split('@')[1].toLowerCase();

            if (KNOWN_VALID.has(domain)) {
                showStatus(status, 'valid', 'Adresse e-mail valide ✓');
                return;
            }

            showStatus(status, 'checking', 'Vérification du domaine…');

            try {
                // Vérifie MX d'abord
                let res  = await fetch('https://dns.google/resolve?name=' + encodeURIComponent(domain) + '&type=MX');
                let data = await res.json();

                if (data.Answer && data.Answer.length > 0) {
                    showStatus(status, 'valid', 'Adresse e-mail valide ✓');
                    return;
                }

                // Fallback sur enregistrement A
                res  = await fetch('https://dns.google/resolve?name=' + encodeURIComponent(domain) + '&type=A');
                data = await res.json();

                if (data.Answer && data.Answer.length > 0) {
                    showStatus(status, 'valid', 'Adresse e-mail valide ✓');
                } else {
                    showStatus(status, 'invalid',
                        'Le domaine <strong>@' + domain + '</strong> n\'existe pas ou n\'accepte pas d\'e-mails.');
                }
            } catch {
                // Erreur réseau → on laisse passer, PHP vérifiera côté serveur
                showStatus(status, 'valid', 'Adresse e-mail valide ✓');
            }
        }, 700);

        input.addEventListener('input', (e) => check(e.target.value.trim()));
        input.addEventListener('blur',  (e) => check(e.target.value.trim()));
    }

    initEmailValidation('login-email',    'login-email-status');
    initEmailValidation('register-email', 'register-email-status');

    // ── Vérification correspondance mots de passe ─────────────────────────
    const pwd1  = document.getElementById('reg-pwd');
    const pwd2  = document.getElementById('reg-pwd2');
    const pwdSt = document.getElementById('pwd-match-status');

    function checkPwd() {
        if (!pwd2 || !pwd2.value) { hideStatus(pwdSt); return; }
        if (pwd1.value === pwd2.value) {
            showStatus(pwdSt, 'valid', 'Les mots de passe correspondent.');
        } else {
            showStatus(pwdSt, 'invalid', 'Les mots de passe ne correspondent pas.');
        }
    }
    pwd1?.addEventListener('input', checkPwd);
    pwd2?.addEventListener('input', checkPwd);

    // ── Auto-dismiss notice après 7s ──────────────────────────────────────
    const notice = document.querySelector('.auth-notice');
    if (notice) {
        setTimeout(() => {
            notice.style.transition = 'opacity 0.5s ease';
            notice.style.opacity = '0';
            setTimeout(() => notice.remove(), 500);
        }, 7000);
    }

})();
</script>

</body>
</html>