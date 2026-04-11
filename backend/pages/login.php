<?php

require_once __DIR__ . '/../services/auth.php';
require_once __DIR__ . '/../services/csrf.php';
 
auth_start_session();
 
if (auth_check()) {
    header('Location: ../index.php');
    exit;
}
 
$error = null;
$email = '';
 
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
 
    $email    = trim($_POST['email']    ?? '');
    $password = $_POST['password']      ?? '';
 
    $result = auth_login($email, $password);
 
    if ($result['ok']) {
        $redirect = $_GET['redirect'] ?? '';
       
        if ($redirect && str_starts_with($redirect, '/')) {
            header('Location: ' . filter_var($redirect, FILTER_SANITIZE_URL));
        } else {
            $base = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME']))), '/');
            header('Location: ' . $base . '/index.php');
        }
        exit;
    }
 
    $error = $result['error'];
    $email = htmlspecialchars($email);
}
 
$pageTitle  = 'Connexion';
$pageCSS    = 'pages/register.css';
$pageDesc   = 'Connectez-vous à votre compte Supinfo.TV.';
$activePage = 'login';
 
$basePath = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME']))), '/');
 
include __DIR__ . '/../partials/head.php';

?>