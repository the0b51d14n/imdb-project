<?php
// ══════════════════════════════════════════════════════════════════════════════
//  backend/pages/oauth-facebook.php — Supinfo.TV
//  Callback OAuth Facebook — échange le code contre un token,
//  récupère le profil et connecte / crée le compte.
// ══════════════════════════════════════════════════════════════════════════════

require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../services/auth.php';
require_once __DIR__ . '/../config/database.php';

auth_start_session();

$basePath = '';
if (preg_match('#^(.+?)/backend/pages/[^/]+$#', str_replace('\\', '/', $_SERVER['SCRIPT_NAME']), $m)) {
    $basePath = rtrim($m[1], '/');
}

// ── Vérification state CSRF ───────────────────────────────────────────────────
$state         = $_GET['state']            ?? '';
$expectedState = $_SESSION['oauth_fb_state'] ?? '';
unset($_SESSION['oauth_fb_state']);

if (!$state || !hash_equals($expectedState, $state)) {
    $_SESSION['auth_notice'] = "❌ Erreur de sécurité OAuth. Réessayez.";
    header('Location: ' . $basePath . '/pages/login.php');
    exit;
}

$code  = $_GET['code']  ?? '';
$error = $_GET['error'] ?? '';

if ($error || !$code) {
    $_SESSION['auth_notice'] = "❌ Connexion Facebook annulée.";
    header('Location: ' . $basePath . '/pages/login.php');
    exit;
}

// ── Credentials ───────────────────────────────────────────────────────────────
$appId       = getenv('FACEBOOK_APP_ID')     ?: '';
$appSecret   = getenv('FACEBOOK_APP_SECRET') ?: '';
$redirectUri = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $basePath . '/backend/pages/oauth-facebook.php';

if (!$appId || !$appSecret) {
    $_SESSION['auth_notice'] = "❌ Facebook OAuth non configuré. Ajoutez FACEBOOK_APP_ID et FACEBOOK_APP_SECRET dans .env.";
    header('Location: ' . $basePath . '/pages/login.php');
    exit;
}

// ── Échange code → token ──────────────────────────────────────────────────────
$tokenUrl = 'https://graph.facebook.com/v19.0/oauth/access_token?' . http_build_query([
    'client_id'     => $appId,
    'redirect_uri'  => $redirectUri,
    'client_secret' => $appSecret,
    'code'          => $code,
]);

$tokenData = _fb_get($tokenUrl);

if (!$tokenData || empty($tokenData['access_token'])) {
    $_SESSION['auth_notice'] = "❌ Erreur lors de l'échange du token Facebook.";
    header('Location: ' . $basePath . '/pages/login.php');
    exit;
}

// ── Récupérer le profil Facebook ──────────────────────────────────────────────
$profileUrl = 'https://graph.facebook.com/me?' . http_build_query([
    'fields'       => 'id,name,email',
    'access_token' => $tokenData['access_token'],
]);

$profile = _fb_get($profileUrl);

if (!$profile || empty($profile['email'])) {
    // Facebook peut ne pas retourner l'email si l'utilisateur l'a refusé
    $_SESSION['auth_notice'] = "❌ Votre compte Facebook n'a pas partagé d'adresse e-mail. Veuillez vous inscrire manuellement.";
    header('Location: ' . $basePath . '/pages/login.php');
    exit;
}

$email = strtolower(trim($profile['email']));
$name  = trim($profile['name'] ?? explode('@', $email)[0]);
$fbId  = $profile['id'] ?? '';

// ── Connexion ou création (réutilisation de la logique Google) ────────────────
require_once __DIR__ . '/oauth-google.php'; // charge _oauth_login_or_register

_oauth_login_or_register($email, $name, 'facebook', $fbId, $basePath);


// ── Helper ────────────────────────────────────────────────────────────────────
function _fb_get(string $url): ?array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $res  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if (!$res || $code !== 200) return null;
    return json_decode($res, true);
}