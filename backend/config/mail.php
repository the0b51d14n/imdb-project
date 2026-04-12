<?php
// ══════════════════════════════════════════════════════════════════════════════
//  backend/config/mail.php — Supinfo.TV
//  Configuration SMTP — valeurs lues exclusivement depuis le .env
// ══════════════════════════════════════════════════════════════════════════════

if (!defined('MAIL_HOST')) {
    $host = getenv('MAIL_HOST');
    $port = getenv('MAIL_PORT');
    $user = getenv('MAIL_USER');
    $pass = getenv('MAIL_PASS');
    $from = getenv('MAIL_FROM');
    $name = getenv('MAIL_FROM_NAME');

    if (empty($host) || empty($user) || empty($pass)) {
        // En dev, on accepte une config incomplète (log uniquement)
        if (getenv('APP_ENV') === 'production') {
            throw new RuntimeException('Configuration mail incomplète dans le .env.');
        }
        error_log('[MAIL] Configuration SMTP incomplète — les e-mails ne seront pas envoyés.');
    }

    define('MAIL_HOST',      $host ?: 'localhost');
    define('MAIL_PORT',      (int)($port ?: 587));
    define('MAIL_USER',      $user ?: '');
    define('MAIL_PASS',      $pass ?: '');
    define('MAIL_FROM',      $from ?: 'noreply@supinfo.tv');
    define('MAIL_FROM_NAME', $name ?: 'Supinfo.TV');
    define('MAIL_ENCRYPTION','tls'); // 'tls' (STARTTLS port 587) ou 'ssl' (port 465)
}
