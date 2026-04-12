<?php
/**
 * backend/config/security.php
 * ═══════════════════════════════════════════════════════════════════════════════
 * Headers de sécurité HTTP + configuration globale de sécurité.
 * À inclure en PREMIER dans chaque page PHP (avant tout output).
 * ═══════════════════════════════════════════════════════════════════════════════
 */

// ── Empêcher l'exécution directe ─────────────────────────────────────────────
if (!defined('SUPINFOTV')) {
    http_response_code(403);
    exit('Accès interdit.');
}


// ── Headers de sécurité HTTP ─────────────────────────────────────────────────
header_remove('X-Powered-By');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

// Content Security Policy
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

// HSTS — forcer HTTPS (activer uniquement en production)
if (getenv('APP_ENV') === 'production') {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
}


// ── Configuration des erreurs selon l'environnement ──────────────────────────
if (getenv('APP_DEBUG') === 'true' && getenv('APP_ENV') !== 'production') {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(0);
    ini_set('log_errors', '1');
    ini_set('error_log', '/var/log/php/error.log');
}


// ── Constantes de sécurité ───────────────────────────────────────────────────
define('PRICE_HMAC_SECRET', getenv('PRICE_HMAC_SECRET')
    ?: throw new RuntimeException('PRICE_HMAC_SECRET non défini dans .env'));

define('CSRF_SECRET', getenv('CSRF_SECRET')
    ?: throw new RuntimeException('CSRF_SECRET non défini dans .env'));

define('APP_SECRET', getenv('APP_SECRET')
    ?: throw new RuntimeException('APP_SECRET non défini dans .env'));

// Rate limiting
define('RATE_LIMIT_MAX_ATTEMPTS', (int)(getenv('RATE_LIMIT_MAX_ATTEMPTS') ?: 5));
define('RATE_LIMIT_DECAY_MINUTES', (int)(getenv('RATE_LIMIT_DECAY_MINUTES') ?: 15));
