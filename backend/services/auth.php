<?php
// ══════════════════════════════════════════════════════════════════════════════
//  backend/services/auth.php — Supinfo.TV
// ══════════════════════════════════════════════════════════════════════════════

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/security.php';

if (!defined('SESSION_USER_ID'))       define('SESSION_USER_ID',       'user_id');
if (!defined('SESSION_USER_NAME'))     define('SESSION_USER_NAME',     'user_name');
if (!defined('SESSION_USER_EMAIL'))    define('SESSION_USER_EMAIL',    'user_email');
if (!defined('SESSION_USER_VERIFIED')) define('SESSION_USER_VERIFIED', 'user_verified');
if (!defined('BCRYPT_COST'))           define('BCRYPT_COST', 12);

function auth_start_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => isset($_SERVER['HTTPS']) || getenv('APP_ENV') === 'production',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    if (!isset($_SESSION['_created'])) {
        $_SESSION['_created'] = time();
    } elseif (time() - $_SESSION['_created'] > 1800) {
        session_regenerate_id(true);
        $_SESSION['_created'] = time();
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

function auth_require(string $redirect = ''): void
{
    if (!auth_check()) {
        $base = rtrim(str_replace('\\', '/', dirname(dirname(dirname($_SERVER['SCRIPT_NAME'])))), '/');
        $url  = $base . '/pages/login.php';
        if ($redirect !== '') {
            $url .= '?redirect=' . urlencode($redirect);
        }
        header('Location: ' . $url);
        exit;
    }
}

function auth_validate_password(string $password): ?string
{
    if (strlen($password) < 8)             return "Le mot de passe doit contenir au moins 8 caractères.";
    if (!preg_match('/[A-Z]/', $password)) return "Le mot de passe doit contenir au moins une majuscule.";
    if (!preg_match('/[0-9]/', $password)) return "Le mot de passe doit contenir au moins un chiffre.";
    return null;
}

/**
 * Valide qu'un domaine e-mail est réel (DNS) et non jetable.
 * Retourne un message d'erreur ou null si tout est OK.
 */
function auth_validate_email_domain(string $email): ?string
{
    $domain = strtolower(substr(strrchr($email, '@'), 1));

    // Blacklist des domaines e-mail jetables connus
    $disposableDomains = [
        'mailinator.com', 'guerrillamail.com', 'tempmail.com', 'throwam.com',
        'yopmail.com', 'sharklasers.com', 'guerrillamailblock.com', 'grr.la',
        'guerrillamail.info', 'guerrillamail.biz', 'guerrillamail.de',
        'guerrillamail.net', 'guerrillamail.org', 'spam4.me', 'trashmail.com',
        'trashmail.me', 'trashmail.net', 'dispostable.com', 'fakeinbox.com',
        'mailnull.com', 'spamgourmet.com', 'spamgourmet.net', 'spamgourmet.org',
        'maildrop.cc', 'tempr.email', 'discard.email', 'spamthisplease.com',
        'mailnesia.com', 'filzmail.com', 'easytrashmail.com', 'getairmail.com',
        'trashmail.at', 'trashmail.io', 'spambox.us', 'bobmail.info',
        'chammy.info', 'drdrb.net', 'smellfear.com', 'objectmail.com',
        'meltmail.com', 'zetmail.com', 'rmqkr.net', 'courriel.fr.nf',
        'cool.fr.nf', 'jetable.fr.nf', 'nospam.ze.tc', 'nomail.xl.cx',
        'mega.zik.dj', 'speed.1s.fr', 'moncourrier.fr.nf', 'monemail.fr.nf',
        'monmail.fr.nf', 'mintemail.com', 'tempinbox.com', 'throwam.com',
        'spamgrap.com', 'fakemail.net', 'temp-mail.org', 'throwam.com',
        'mailtemp.info', 'getonemail.com', 'despam.it', 'mailscrap.com',
        'spamavert.com', 'incognitomail.com', 'dodgeit.com', 'tempe-mail.com',
    ];

    if (in_array($domain, $disposableDomains, true)) {
        return "Les adresses e-mail temporaires ne sont pas acceptées.";
    }

    // Vérification DNS : le domaine doit avoir un enregistrement MX réel
    // On ne fait PAS de fallback sur A record — un domaine sans MX ne peut pas recevoir d'e-mails
    if (!checkdnsrr($domain, 'MX')) {
        return "Adresse e-mail invalide.";
    }

    return null;
}

function auth_is_rate_limited(string $ip): bool
{
    $max    = defined('RATE_LIMIT_MAX_ATTEMPTS')  ? RATE_LIMIT_MAX_ATTEMPTS  : 5;
    $decay  = defined('RATE_LIMIT_DECAY_MINUTES') ? RATE_LIMIT_DECAY_MINUTES : 15;
    $window = $decay * 60;

    try {
        db()->prepare(
            'DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL :sec SECOND)'
        )->execute([':sec' => $window]);

        $stmt = db()->prepare(
            'SELECT COUNT(*) FROM login_attempts WHERE ip_address = :ip
             AND attempted_at > DATE_SUB(NOW(), INTERVAL :sec SECOND)'
        );
        $stmt->execute([':ip' => $ip, ':sec' => $window]);
        return (int)$stmt->fetchColumn() >= $max;
    } catch (PDOException) {
        return false;
    }
}

function auth_record_attempt(string $ip): void
{
    try {
        db()->prepare('INSERT INTO login_attempts (ip_address) VALUES (:ip)')
            ->execute([':ip' => $ip]);
    } catch (PDOException) {}
}

function auth_clear_attempts(string $ip): void
{
    try {
        db()->prepare('DELETE FROM login_attempts WHERE ip_address = :ip')
            ->execute([':ip' => $ip]);
    } catch (PDOException) {}
}

function auth_generate_token(): string     { return bin2hex(random_bytes(32)); }
function auth_hash_token(string $t): string { return hash('sha256', $t); }

function auth_register(string $username, string $email, string $password): array
{
    $username = trim($username);
    $email    = trim(strtolower($email));

    if (strlen($username) < 3 || strlen($username) > 60)
        return ['ok' => false, 'error' => "Le nom d'utilisateur doit contenir entre 3 et 60 caractères.", 'user_id' => null, 'token' => null];

    if (!filter_var($email, FILTER_VALIDATE_EMAIL))
        return ['ok' => false, 'error' => "Adresse e-mail invalide.", 'user_id' => null, 'token' => null];

    // Validation domaine : DNS réel + pas de domaine jetable
    $domainError = auth_validate_email_domain($email);
    if ($domainError !== null)
        return ['ok' => false, 'error' => $domainError, 'user_id' => null, 'token' => null];

    $pwdErr = auth_validate_password($password);
    if ($pwdErr)
        return ['ok' => false, 'error' => $pwdErr, 'user_id' => null, 'token' => null];

    $hash            = password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
    $verifyToken     = auth_generate_token();
    $verifyTokenHash = auth_hash_token($verifyToken);
    $verifyExpiry    = date('Y-m-d H:i:s', time() + 86400);

    try {
        $pdo  = db();
        $stmt = $pdo->prepare(
            'INSERT INTO users (username, email, password_hash, email_verify_token, email_verify_expires)
             VALUES (:username, :email, :hash, :token, :expires)'
        );
        $stmt->execute([
            ':username' => $username,
            ':email'    => $email,
            ':hash'     => $hash,
            ':token'    => $verifyTokenHash,
            ':expires'  => $verifyExpiry,
        ]);
        $userId = (int)$pdo->lastInsertId();

        auth_start_session();
        session_regenerate_id(true);
        $_SESSION[SESSION_USER_ID]       = $userId;
        $_SESSION[SESSION_USER_NAME]     = $username;
        $_SESSION[SESSION_USER_EMAIL]    = $email;
        $_SESSION[SESSION_USER_VERIFIED] = false;
        $_SESSION['_created']            = time();

        cart_sync_count();

        return ['ok' => true, 'error' => null, 'user_id' => $userId, 'token' => $verifyToken];

    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            if (str_contains($e->getMessage(), 'uq_users_email'))
                return ['ok' => false, 'error' => "Cette adresse e-mail est déjà utilisée.", 'user_id' => null, 'token' => null];
            return ['ok' => false, 'error' => "Ce nom d'utilisateur est déjà pris.", 'user_id' => null, 'token' => null];
        }
        error_log('auth_register error: ' . $e->getMessage());
        return ['ok' => false, 'error' => "Une erreur est survenue. Veuillez réessayer.", 'user_id' => null, 'token' => null];
    }
}

function auth_login(string $email, string $password): array
{
    $ip    = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $email = trim(strtolower($email));

    if (auth_is_rate_limited($ip)) {
        $decay = defined('RATE_LIMIT_DECAY_MINUTES') ? RATE_LIMIT_DECAY_MINUTES : 15;
        return ['ok' => false, 'error' => "Trop de tentatives. Réessayez dans {$decay} minutes.", 'rate_limited' => true];
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        auth_record_attempt($ip);
        return ['ok' => false, 'error' => "Identifiants incorrects.", 'rate_limited' => false];
    }

    $stmt = db()->prepare(
        'SELECT id, username, password_hash, email_verified_at FROM users WHERE email = :email LIMIT 1'
    );
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        auth_record_attempt($ip);
        if (!$user) password_verify('dummy', '$2y$12$invalid.hash.to.prevent.timing.attack');
        return ['ok' => false, 'error' => "Identifiants incorrects.", 'rate_limited' => false];
    }

    auth_clear_attempts($ip);

    if (password_needs_rehash($user['password_hash'], PASSWORD_BCRYPT, ['cost' => BCRYPT_COST])) {
        $newHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
        db()->prepare('UPDATE users SET password_hash = :h WHERE id = :id')
            ->execute([':h' => $newHash, ':id' => $user['id']]);
    }

    auth_start_session();
    session_regenerate_id(true);
    $_SESSION[SESSION_USER_ID]       = $user['id'];
    $_SESSION[SESSION_USER_NAME]     = $user['username'];
    $_SESSION[SESSION_USER_EMAIL]    = $email;
    $_SESSION[SESSION_USER_VERIFIED] = !empty($user['email_verified_at']);
    $_SESSION['_created']            = time();

    cart_sync_count();

    return ['ok' => true, 'error' => null, 'rate_limited' => false];
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

function auth_verify_email(string $token): array
{
    $tokenHash = auth_hash_token($token);

    $stmt = db()->prepare(
        'SELECT id, email_verify_expires FROM users
         WHERE email_verify_token = :token AND email_verified_at IS NULL LIMIT 1'
    );
    $stmt->execute([':token' => $tokenHash]);
    $user = $stmt->fetch();

    if (!$user)
        return ['ok' => false, 'error' => "Lien de vérification invalide ou déjà utilisé."];
    if (strtotime($user['email_verify_expires']) < time())
        return ['ok' => false, 'error' => "Ce lien a expiré. Demandez un nouveau lien."];

    db()->prepare(
        'UPDATE users SET email_verified_at = NOW(), email_verify_token = NULL, email_verify_expires = NULL WHERE id = :id'
    )->execute([':id' => $user['id']]);

    if (auth_check() && (int)$_SESSION[SESSION_USER_ID] === (int)$user['id']) {
        $_SESSION[SESSION_USER_VERIFIED] = true;
    }

    return ['ok' => true, 'error' => null];
}

function auth_resend_verification(int $userId): array
{
    $token     = auth_generate_token();
    $tokenHash = auth_hash_token($token);
    $expires   = date('Y-m-d H:i:s', time() + 86400);

    $stmt = db()->prepare(
        'UPDATE users SET email_verify_token = :token, email_verify_expires = :expires
         WHERE id = :id AND email_verified_at IS NULL'
    );
    $stmt->execute([':token' => $tokenHash, ':expires' => $expires, ':id' => $userId]);

    if ($stmt->rowCount() === 0)
        return ['ok' => false, 'error' => "E-mail déjà vérifié ou utilisateur introuvable.", 'token' => null];

    return ['ok' => true, 'error' => null, 'token' => $token];
}

function auth_create_password_reset(string $email): array
{
    $email = trim(strtolower($email));
    $stmt  = db()->prepare('SELECT id, username FROM users WHERE email = :email LIMIT 1');
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    if (!$user) {
        usleep(random_int(100000, 300000));
        return ['ok' => true, 'email' => null, 'username' => null, 'token' => null];
    }

    $token     = auth_generate_token();
    $tokenHash = auth_hash_token($token);
    $expires   = date('Y-m-d H:i:s', time() + 3600);

    try {
        db()->prepare(
            'INSERT INTO password_resets (user_id, token_hash, expires_at)
             VALUES (:uid, :token, :expires)
             ON DUPLICATE KEY UPDATE token_hash = :token2, expires_at = :expires2, created_at = NOW()'
        )->execute([
            ':uid'      => $user['id'],
            ':token'    => $tokenHash,
            ':expires'  => $expires,
            ':token2'   => $tokenHash,
            ':expires2' => $expires,
        ]);
        return ['ok' => true, 'email' => $email, 'username' => $user['username'], 'token' => $token];
    } catch (PDOException $e) {
        error_log('auth_create_password_reset: ' . $e->getMessage());
        return ['ok' => false, 'email' => null, 'username' => null, 'token' => null];
    }
}

function auth_reset_password(string $token, string $newPassword): array
{
    $pwdErr = auth_validate_password($newPassword);
    if ($pwdErr) return ['ok' => false, 'error' => $pwdErr];

    $tokenHash = auth_hash_token($token);
    $stmt = db()->prepare(
        'SELECT pr.user_id, pr.expires_at FROM password_resets pr
         WHERE pr.token_hash = :token AND pr.used_at IS NULL LIMIT 1'
    );
    $stmt->execute([':token' => $tokenHash]);
    $reset = $stmt->fetch();

    if (!$reset)
        return ['ok' => false, 'error' => "Lien invalide ou déjà utilisé."];
    if (strtotime($reset['expires_at']) < time())
        return ['ok' => false, 'error' => "Ce lien a expiré. Effectuez une nouvelle demande."];

    $hash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
    $pdo  = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare('UPDATE users SET password_hash = :hash WHERE id = :id')
            ->execute([':hash' => $hash, ':id' => $reset['user_id']]);
        $pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE token_hash = :token')
            ->execute([':token' => $tokenHash]);
        $pdo->commit();
        return ['ok' => true, 'error' => null];
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log('auth_reset_password: ' . $e->getMessage());
        return ['ok' => false, 'error' => "Erreur serveur. Veuillez réessayer."];
    }
}

function auth_change_password(int $userId, string $current, string $newPass): array
{
    $pwdErr = auth_validate_password($newPass);
    if ($pwdErr) return ['ok' => false, 'error' => $pwdErr];

    $stmt = db()->prepare('SELECT password_hash FROM users WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $userId]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($current, $user['password_hash']))
        return ['ok' => false, 'error' => "Mot de passe actuel incorrect."];

    $hash = password_hash($newPass, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
    db()->prepare('UPDATE users SET password_hash = :hash WHERE id = :id')
        ->execute([':hash' => $hash, ':id' => $userId]);

    return ['ok' => true, 'error' => null];
}

/**
 * Synchronise le compteur panier en session depuis la DB.
 */
function cart_sync_count(): void
{
    $uid = auth_id();
    if (!$uid) {
        $_SESSION['cart_count'] = 0;
        return;
    }

    try {
        $stmt = db()->prepare('SELECT COUNT(*) FROM cart_items WHERE user_id = :uid');
        $stmt->execute([':uid' => $uid]);
        $_SESSION['cart_count'] = (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log('[cart_sync_count] Table cart_items inaccessible : ' . $e->getMessage());
        $_SESSION['cart_count'] = 0;
    }
}