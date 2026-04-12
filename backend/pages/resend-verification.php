<?php
// ══════════════════════════════════════════════════════════════════════════════
//  backend/pages/resend-verification.php — Supinfo.TV
//  Renvoie l'e-mail de vérification à l'utilisateur connecté.
// ══════════════════════════════════════════════════════════════════════════════

require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../services/auth.php';
require_once __DIR__ . '/../services/csrf.php';
require_once __DIR__ . '/../services/mailer.php';

auth_start_session();

$basePath = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME']))), '/');

// Doit être connecté
auth_require($basePath . '/pages/login.php');

// POST uniquement
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $basePath . '/pages/profile.php');
    exit;
}

csrf_verify();

$userId   = auth_id();
$userMail = $_SESSION[SESSION_USER_EMAIL] ?? '';
$userName = $_SESSION[SESSION_USER_NAME]  ?? '';

$result = auth_resend_verification($userId);

if ($result['ok'] && $result['token']) {
    mailer_send_verification($userMail, $userName, $result['token']);
    $_SESSION['auth_notice'] = "E-mail de vérification renvoyé. Vérifiez votre boîte de réception.";
} else {
    $_SESSION['auth_notice'] = $result['error'] ?? "Votre adresse e-mail est déjà vérifiée.";
}

header('Location: ' . $basePath . '/pages/profile.php');
exit;
