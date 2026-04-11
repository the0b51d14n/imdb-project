<?php

require_once __DIR__ . '/../services/auth.php';
require_once __DIR__ . '/../services/csrf.php';
 
auth_start_session();

if (auth_check()) {
    header('Location: ../index.php');
    exit;
}
 
$error   = null;
$success = false;
$old     = ['username' => '', 'email' => ''];
 
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
 
    $username  = trim($_POST['username']  ?? '');
    $email     = trim($_POST['email']     ?? '');
    $password  = $_POST['password']       ?? '';
    $password2 = $_POST['password2']      ?? '';
 
    if ($password !== $password2) {
        $error = "Les mots de passe ne correspondent pas.";
    } else {
        $result = auth_register($username, $email, $password);
        if ($result['ok']) {
            $redirect = $_GET['redirect'] ?? '../index.php';
            header('Location: ' . filter_var($redirect, FILTER_SANITIZE_URL));
            exit;
        }
        $error = $result['error'];
    }
 
    $old = ['username' => htmlspecialchars($username), 'email' => htmlspecialchars($email)];
}
 
$pageTitle  = 'Inscription';
$pageCSS    = 'pages/register.css';
$pageDesc   = 'Créez votre compte Supinfo.TV et accédez à des milliers de films.';
$activePage = 'register';
 
$basePath = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME']))), '/');
 
include __DIR__ . '/../partials/head.php';

?>