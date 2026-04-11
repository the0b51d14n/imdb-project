<?php
session_start();
 
require_once __DIR__ . '/../services/auth.php';
require_once __DIR__ . '/../services/cart.php';
require_once __DIR__ . '/../services/csrf.php';
 
auth_start_session();
 

$basePath = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME']))), '/');
auth_require($basePath . '/pages/cart.php'); 

$actionMsg   = null;
$actionError = null;
 
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
 
    if ($action === 'remove') {
        $tid = (int)($_POST['tmdb_id'] ?? 0);
        if ($tid > 0) {
            $r = cart_remove($tid);
            $actionMsg = $r['ok'] ? 'Film retiré du panier.' : null;
            $actionError = $r['error'] ?? null;
        }
    } elseif ($action === 'clear') {
        cart_clear();
        $actionMsg = 'Panier vidé.';
    } elseif ($action === 'checkout') {
        $r = cart_checkout();
        if ($r['ok']) {
            header('Location: ' . $basePath . '/pages/profile.php?order=success');
            exit;
        }
        $actionError = $r['error'];
    }
}
 
$items = cart_get_items();
$total = cart_total();
 
$pageTitle  = 'Mon panier';
$pageCSS    = 'pages/cart.css';
$pageDesc   = 'Votre panier Supinfo.TV.';
$activePage = 'cart';
 
include __DIR__ . '/../partials/head.php';
include __DIR__ . '/../partials/loader.php';
include __DIR__ . '/../partials/navbar.php';

?>