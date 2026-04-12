<?php
// ══════════════════════════════════════════════════════════════════════════════
//  backend/config/security.php — Supinfo.TV
//  Headers de sécurité HTTP + configuration globale.
//  À inclure EN PREMIER dans chaque page backend (avant tout output).
// ══════════════════════════════════════════════════════════════════════════════

// ── Chargement du .env si pas encore fait ────────────────────────────────────
if (!function_exists('_supinfotv_load_env')) {
    function _supinfotv_load_env(): void {
        $envFile = dirname(__DIR__, 2) . '/.env';
        if (!file_exists($envFile)) {
            $envFile = dirname(__DIR__) . '/.env';
            if (!file_exists($envFile)) return;
        }

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) continue;
            if (!str_contains($line, '=')) continue;

            [$key, $value] = explode('=', $line, 2);
            $key   = trim($key);
            $value = trim($value);

            if (!isset($_ENV[$key]) && getenv($key) === false) {
                putenv("{$key}={$value}");
                $_ENV[$key] = $value;
            }
        }
    }
}
_supinfotv_load_env();

// ── Headers de sécurité HTTP ──────────────────────────────────────────────────
// Exécuté une seule fois par requête
if (!defined('SUPINFOTV_SECURITY_HEADERS_SENT')) {
    define('SUPINFOTV_SECURITY_HEADERS_SENT', true);

    header_remove('X-Powered-By');
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

    header(
        "Content-Security-Policy: " .
        "default-src 'self'; " .
        "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; " .
        "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://unpkg.com; " .
        "font-src 'self' https://fonts.gstatic.com https://unpkg.com; " .
        "img-src 'self' data: https://image.tmdb.org https://www.themoviedb.org; " .
        "connect-src 'self' https://api.themoviedb.org; " .
        "frame-src https://www.youtube.com; " .
        "object-src 'none'; " .
        "base-uri 'self';"
    );

    // HSTS — uniquement en production HTTPS
    if (getenv('APP_ENV') === 'production') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
    }
}

// ── Affichage des erreurs selon l'environnement ───────────────────────────────
if (!defined('SUPINFOTV_ERROR_CONFIG_DONE')) {
    define('SUPINFOTV_ERROR_CONFIG_DONE', true);

    if (getenv('APP_DEBUG') === 'true' && getenv('APP_ENV') !== 'production') {
        ini_set('display_errors', '1');
        ini_set('display_startup_errors', '1');
        error_reporting(E_ALL);
    } else {
        ini_set('display_errors', '0');
        ini_set('display_startup_errors', '0');
        error_reporting(0);
        ini_set('log_errors', '1');
    }
}

// ── Constantes de sécurité ────────────────────────────────────────────────────
if (!defined('PRICE_HMAC_SECRET')) {
    $hmac = getenv('PRICE_HMAC_SECRET');
    if (empty($hmac)) {
        error_log('[SECURITY] PRICE_HMAC_SECRET manquant dans le .env');
        throw new RuntimeException('PRICE_HMAC_SECRET non défini.');
    }
    define('PRICE_HMAC_SECRET', $hmac);
}

if (!defined('CSRF_SECRET')) {
    $csrf = getenv('CSRF_SECRET');
    if (empty($csrf)) {
        error_log('[SECURITY] CSRF_SECRET manquant dans le .env');
        throw new RuntimeException('CSRF_SECRET non défini.');
    }
    define('CSRF_SECRET', $csrf);
}

if (!defined('APP_SECRET')) {
    $app = getenv('APP_SECRET');
    if (empty($app)) {
        error_log('[SECURITY] APP_SECRET manquant dans le .env');
        throw new RuntimeException('APP_SECRET non défini.');
    }
    define('APP_SECRET', $app);
}

// ── Rate limiting ─────────────────────────────────────────────────────────────
if (!defined('RATE_LIMIT_MAX_ATTEMPTS')) {
    define('RATE_LIMIT_MAX_ATTEMPTS',  (int)(getenv('RATE_LIMIT_MAX_ATTEMPTS')  ?: 5));
    define('RATE_LIMIT_DECAY_MINUTES', (int)(getenv('RATE_LIMIT_DECAY_MINUTES') ?: 15));
}