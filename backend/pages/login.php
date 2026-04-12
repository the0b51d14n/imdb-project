<?php
// ══════════════════════════════════════════════════════════════════════════════
//  backend/pages/login.php — Supinfo.TV
//  Handler unique pour connexion ET inscription (POST uniquement).
//  GET → redirige vers le formulaire frontend.
// ══════════════════════════════════════════════════════════════════════════════

require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../services/auth.php';
require_once __DIR__ . '/../services/csrf.php';
require_once __DIR__ . '/../services/mailer.php';

auth_start_session();

$basePath = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME']))), '/');

// Utilisateur déjà connecté
if (auth_check()) {
    header('Location: ' . $basePath . '/index.php');
    exit;
}

// GET → afficher le formulaire (frontend/pages/login.php)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $basePath . '/pages/login.php');
    exit;
}

csrf_verify();

$action = $_POST['action'] ?? 'login'; // 'login' ou 'register'
$error  = null;
$redirect = filter_var($_POST['redirect'] ?? $_GET['redirect'] ?? '', FILTER_SANITIZE_URL);

// ── CONNEXION ─────────────────────────────────────────────────────────────────
if ($action === 'login') {
    $email    = trim($_POST['email']    ?? '');
    $password = $_POST['password']      ?? '';

    $result = auth_login($email, $password);

    if ($result['ok']) {
        $dest = ($redirect && str_starts_with($redirect, '/'))
            ? $redirect
            : $basePath . '/index.php';
        header('Location: ' . $dest);
        exit;
    }

    // Erreur : retour au formulaire avec message
    $_SESSION['auth_error'] = $result['error'];
    $_SESSION['auth_email'] = htmlspecialchars($email);
    $q = $redirect ? '?redirect=' . urlencode($redirect) : '';
    header('Location: ' . $basePath . '/pages/login.php' . $q);
    exit;
}

// ── INSCRIPTION ───────────────────────────────────────────────────────────────
if ($action === 'register') {
    $username  = trim($_POST['username']  ?? '');
    $email     = trim($_POST['email']     ?? '');
    $password  = $_POST['password']       ?? '';
    $password2 = $_POST['password2']      ?? '';

    if ($password !== $password2) {
        $_SESSION['auth_error'] = "Les mots de passe ne correspondent pas.";
        $_SESSION['auth_old']   = ['username' => htmlspecialchars($username), 'email' => htmlspecialchars($email)];
        header('Location: ' . $basePath . '/pages/login.php?mode=register');
        exit;
    }

    $result = auth_register($username, $email, $password);

    if ($result['ok']) {
        // Envoyer l'e-mail de vérification
        if ($result['token']) {
            mailer_send_verification($email, $username, $result['token']);
        }
        $_SESSION['auth_notice'] = "Compte créé ! Vérifiez votre e-mail pour activer votre compte.";
        header('Location: ' . $basePath . '/index.php');
        exit;
    }

    $_SESSION['auth_error'] = $result['error'];
    $_SESSION['auth_old']   = ['username' => htmlspecialchars($username), 'email' => htmlspecialchars($email)];
    header('Location: ' . $basePath . '/pages/login.php?mode=register');
    exit;
}

// Action inconnue
header('Location: ' . $basePath . '/pages/login.php');
exit;
