<?php

require_once __DIR__ . '/../config/database.php';

define('SESSION_USER_ID',   'user_id');
define('SESSION_USER_NAME', 'user_name');
define('SESSION_USER_EMAIL','user_email');
define('BCRYPT_COST', 12);

function auth_start_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

function auth_check(): bool
{
    auth_start_session();
    return !empty($_SESSION[SESSION_USER_ID]);
}

function auth_id(): ?int
{
    return auth_check() ? (int)$_SESSION[SESSION_USER_ID] : null;
}

/**
 * Redirige vers la page de connexion si l'utilisateur n'est pas authentifié.
 *
 * @param string $redirect  URL de destination après connexion (encodée en query string)
 */
function auth_require(string $redirect = ''): void
{
    if (!auth_check()) {
        $base = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME']))), '/');
        $url  = $base . '/pages/login.php';
        if ($redirect !== '') {
            $url .= '?redirect=' . urlencode($redirect);
        }
        header('Location: ' . $url);
        exit;
    }
}

/**
 * Inscrit un nouvel utilisateur.
 *
 * @param  string $username
 * @param  string $email
 * @param  string $password  Mot de passe en clair (min 8 caractères)
 * @return array  ['ok' => bool, 'error' => string|null, 'user_id' => int|null]
 */
function auth_register(string $username, string $email, string $password): array
{
    $username = trim($username);
    $email    = trim(strtolower($email));

    if (strlen($username) < 3 || strlen($username) > 60) {
        return ['ok' => false, 'error' => "Le nom d'utilisateur doit contenir entre 3 et 60 caractères.", 'user_id' => null];
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => "Adresse e-mail invalide.", 'user_id' => null];
    }
    if (strlen($password) < 8) {
        return ['ok' => false, 'error' => "Le mot de passe doit contenir au moins 8 caractères.", 'user_id' => null];
    }
    if (!preg_match('/[A-Z]/', $password)) {
        return ['ok' => false, 'error' => "Le mot de passe doit contenir au moins une lettre majuscule.", 'user_id' => null];
    }
    if (!preg_match('/[0-9]/', $password)) {
        return ['ok' => false, 'error' => "Le mot de passe doit contenir au moins un chiffre.", 'user_id' => null];
    }

    $pdo  = db();
    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO users (username, email, password_hash) VALUES (:username, :email, :hash)'
        );
        $stmt->execute([':username' => $username, ':email' => $email, ':hash' => $hash]);
        $userId = (int)$pdo->lastInsertId();

        auth_start_session();
        session_regenerate_id(true);
        $_SESSION[SESSION_USER_ID]    = $userId;
        $_SESSION[SESSION_USER_NAME]  = $username;
        $_SESSION[SESSION_USER_EMAIL] = $email;

        return ['ok' => true, 'error' => null, 'user_id' => $userId];

    } catch (PDOException $e) {
        
        if ($e->getCode() === '23000') {
            if (str_contains($e->getMessage(), 'uq_users_email')) {
                return ['ok' => false, 'error' => "Cette adresse e-mail est déjà utilisée.", 'user_id' => null];
            }
            return ['ok' => false, 'error' => "Ce nom d'utilisateur est déjà pris.", 'user_id' => null];
        }
        error_log('auth_register error: ' . $e->getMessage());
        return ['ok' => false, 'error' => "Une erreur est survenue. Veuillez réessayer.", 'user_id' => null];
    }
}

/**
 * Connecte un utilisateur via email + mot de passe.
 *
 * @return array ['ok' => bool, 'error' => string|null]
 */
function auth_login(string $email, string $password): array
{
    $email = trim(strtolower($email));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => "Identifiants incorrects."];
    }

    $stmt = db()->prepare('SELECT id, username, password_hash FROM users WHERE email = :email LIMIT 1');
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        return ['ok' => false, 'error' => "Identifiants incorrects."];
    }

    auth_start_session();
    session_regenerate_id(true);
    $_SESSION[SESSION_USER_ID]    = $user['id'];
    $_SESSION[SESSION_USER_NAME]  = $user['username'];
    $_SESSION[SESSION_USER_EMAIL] = $email;

    cart_sync_count();

    return ['ok' => true, 'error' => null];
}

function auth_logout(): void
{
    auth_start_session();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/**
 * Change le mot de passe d'un utilisateur connecté.
 *
 * @return array ['ok' => bool, 'error' => string|null]
 */
function auth_change_password(int $userId, string $current, string $newPass): array
{
    if (strlen($newPass) < 8) {
        return ['ok' => false, 'error' => "Le nouveau mot de passe doit contenir au moins 8 caractères."];
    }
    if (!preg_match('/[A-Z]/', $newPass)) {
        return ['ok' => false, 'error' => "Le nouveau mot de passe doit contenir au moins une lettre majuscule."];
    }
    if (!preg_match('/[0-9]/', $newPass)) {
        return ['ok' => false, 'error' => "Le nouveau mot de passe doit contenir au moins un chiffre."];
    }

    $stmt = db()->prepare('SELECT password_hash FROM users WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $userId]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($current, $user['password_hash'])) {
        return ['ok' => false, 'error' => "Mot de passe actuel incorrect."];
    }

    $hash  = password_hash($newPass, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
    $upd   = db()->prepare('UPDATE users SET password_hash = :hash WHERE id = :id');
    $upd->execute([':hash' => $hash, ':id' => $userId]);

    return ['ok' => true, 'error' => null];
}

function cart_sync_count(): void
{
    $uid = auth_id();
    if (!$uid) { $_SESSION['cart_count'] = 0; return; }

    $stmt = db()->prepare('SELECT COUNT(*) FROM cart_items WHERE user_id = :uid');
    $stmt->execute([':uid' => $uid]);
    $_SESSION['cart_count'] = (int)$stmt->fetchColumn();
}

?>