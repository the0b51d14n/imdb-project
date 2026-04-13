<?php
// frontend/pages/cart.php — redirige vers backend (panier en base de données)
$qs       = $_SERVER['QUERY_STRING'] ?? '';
$basePath = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME']))), '/');
header('Location: ' . $basePath . '/backend/pages/cart.php' . ($qs ? '?' . $qs : ''));
exit;