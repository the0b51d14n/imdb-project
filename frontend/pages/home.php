<?php
// frontend/pages/home.php — redirige vers l'accueil
$basePath = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME']))), '/');
header('Location: ' . $basePath . '/index.php');
exit;