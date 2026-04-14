<?php
// ══════════════════════════════════════════════════════════════════════════════
//  backend/pages/oauth-google.php — Supinfo.TV
//  Callback OAuth 2.0 Google — échange le code contre un token,
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
$state         = $_GET['state']         ?? '';
$expectedState = $_SESSION['oauth_state'] ?? '';
unset($_SESSION['oauth_state']);

if (!$state || !hash_equals($expectedState, $state)) {
    $_SESSION['auth_notice'] = "❌ Erreur de sécurité OAuth. Réessayez.";
    header('Location: ' . $basePath . '/pages/login.php');
    exit;
}

$code  = $_GET['code']  ?? '';
$error = $_GET['error'] ?? '';

if ($error || !$code) {
    $_SESSION['auth_notice'] = "❌ Connexion Google annulée.";
    header('Location: ' . $basePath . '/pages/login.php');
    exit;
}

// ── Récupérer les credentials depuis .env ────────────────────────────────────
$clientId     = getenv('GOOGLE_CLIENT_ID')     ?: '';
$clientSecret = getenv('GOOGLE_CLIENT_SECRET') ?: '';
$redirectUri  = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $basePath . '/backend/pages/oauth-google.php';

if (!$clientId || !$clientSecret) {
    $_SESSION['auth_notice'] = "❌ Google OAuth non configuré. Ajoutez GOOGLE_CLIENT_ID et GOOGLE_CLIENT_SECRET dans .env.";
    header('Location: ' . $basePath . '/pages/login.php');
    exit;
}

// ── Échange code → access token ───────────────────────────────────────────────
$tokenResponse = _oauth_post('https://oauth2.googleapis.com/token', [
    'code'          => $code,
    'client_id'     => $clientId,
    'client_secret' => $clientSecret,
    'redirect_uri'  => $redirectUri,
    'grant_type'    => 'authorization_code',
]);

if (!$tokenResponse || empty($tokenResponse['access_token'])) {
    $_SESSION['auth_notice'] = "❌ Erreur lors de l'échange du token Google.";
    header('Location: ' . $basePath . '/pages/login.php');
    exit;
}

// ── Récupérer le profil Google ────────────────────────────────────────────────
$profile = _oauth_get('https://www.googleapis.com/oauth2/v2/userinfo', $tokenResponse['access_token']);

if (!$profile || empty($profile['email'])) {
    $_SESSION['auth_notice'] = "❌ Impossible de récupérer votre profil Google.";
    header('Location: ' . $basePath . '/pages/login.php');
    exit;
}

$email    = strtolower(trim($profile['email']));
$name     = trim($profile['name'] ?? explode('@', $email)[0]);
$googleId = $profile['id'] ?? '';

// ── Connexion ou création de compte ──────────────────────────────────────────
_oauth_login_or_register($email, $name, 'google', $googleId, $basePath);


// ── Helpers ───────────────────────────────────────────────────────────────────
function _oauth_post(string $url, array $data): ?array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($data),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $res  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if (!$res || $code !== 200) return null;
    return json_decode($res, true);
}

function _oauth_get(string $url, string $token): ?array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $token],
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $res  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if (!$res || $code !== 200) return null;
    return json_decode($res, true);
}

function _oauth_login_or_register(string $email, string $name, string $provider, string $providerId, string $basePath): void
{
    // 1. Chercher un compte existant par email
    $stmt = db()->prepare('SELECT id, username, email_verified_at FROM users WHERE email = :email LIMIT 1');
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    if ($user) {
        // Compte existant → connexion directe
        // Marquer l'email comme vérifié si ce n'est pas déjà le cas (Google vérifie ses emails)
        if ($provider === 'google' && empty($user['email_verified_at'])) {
            db()->prepare('UPDATE users SET email_verified_at = NOW() WHERE id = :id')
                ->execute([':id' => $user['id']]);
        }

        session_regenerate_id(true);
        $_SESSION[SESSION_USER_ID]       = $user['id'];
        $_SESSION[SESSION_USER_NAME]     = $user['username'];
        $_SESSION[SESSION_USER_EMAIL]    = $email;
        $_SESSION[SESSION_USER_VERIFIED] = true;
        $_SESSION['_created']            = time();

        cart_sync_count();

        header('Location: ' . $basePath . '/index.php');
        exit;
    }

    // 2. Créer un nouveau compte
    // Générer un username unique depuis le nom
    $baseUsername = preg_replace('/[^a-zA-Z0-9_]/', '', str_replace(' ', '_', $name));
    $baseUsername = substr($baseUsername ?: 'user', 0, 30);
    $username     = $baseUsername;
    $suffix       = 1;

    while (true) {
        $check = db()->prepare('SELECT 1 FROM users WHERE username = :u LIMIT 1');
        $check->execute([':u' => $username]);
        if (!$check->fetchColumn()) break;
        $username = $baseUsername . $suffix++;
    }

    // Mot de passe aléatoire (inutilisable directement — login uniquement via OAuth)
    $randomPassword = bin2hex(random_bytes(32));
    $hash           = password_hash($randomPassword, PASSWORD_BCRYPT, ['cost' => 12]);

    try {
        $stmt = db()->prepare(
            'INSERT INTO users (username, email, password_hash, email_verified_at)
             VALUES (:username, :email, :hash, NOW())'
        );
        $stmt->execute([
            ':username' => $username,
            ':email'    => $email,
            ':hash'     => $hash,
        ]);
        $userId = (int)db()->lastInsertId();

        session_regenerate_id(true);
        $_SESSION[SESSION_USER_ID]       = $userId;
        $_SESSION[SESSION_USER_NAME]     = $username;
        $_SESSION[SESSION_USER_EMAIL]    = $email;
        $_SESSION[SESSION_USER_VERIFIED] = true;
        $_SESSION['_created']            = time();

        cart_sync_count();

        $_SESSION['auth_notice'] = "✅ Compte créé avec votre compte " . ucfirst($provider) . " !";
        header('Location: ' . $basePath . '/index.php');
        exit;

    } catch (PDOException $e) {
        error_log('[oauth] Erreur création compte : ' . $e->getMessage());
        $_SESSION['auth_notice'] = "❌ Erreur lors de la création de votre compte. Réessayez.";
        header('Location: ' . $basePath . '/pages/login.php');
        exit;
    }
}