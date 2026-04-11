<?php

session_start();
 
require_once __DIR__ . '/../services/auth.php';
require_once __DIR__ . '/../services/orders.php';
require_once __DIR__ . '/../services/csrf.php';
 
auth_start_session();
 
$basePath = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME']))), '/');
auth_require($basePath . '/pages/profile.php');
 
$userId   = auth_id();
$userName = $_SESSION[SESSION_USER_NAME]  ?? 'Utilisateur';
$userMail = $_SESSION[SESSION_USER_EMAIL] ?? '';
 
$pwdError   = null;
$pwdSuccess = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'change_password') {
    csrf_verify();
 
    $current  = $_POST['current_password']  ?? '';
    $new      = $_POST['new_password']      ?? '';
    $confirm  = $_POST['confirm_password']  ?? '';
 
    if ($new !== $confirm) {
        $pwdError = "Les nouveaux mots de passe ne correspondent pas.";
    } else {
        $r = auth_change_password($userId, $current, $new);
        if ($r['ok']) {
            $pwdSuccess = true;
        } else {
            $pwdError = $r['error'];
        }
    }
}
 
$purchasedMovies = orders_get_purchased_movies();
$orderSuccess    = isset($_GET['order']) && $_GET['order'] === 'success';
 
$pageTitle  = 'Mon profil';
$pageCSS    = 'pages/profile.css';
$pageDesc   = 'Gérez votre compte Supinfo.TV — films achetés et paramètres.';
$activePage = 'profile';
 
include __DIR__ . '/../partials/head.php';
include __DIR__ . '/../partials/loader.php';
include __DIR__ . '/../partials/navbar.php';

?>