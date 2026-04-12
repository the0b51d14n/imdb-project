<?php
// frontend/pages/reset-password.php — redirection vers backend
$qs = $_SERVER['QUERY_STRING'] ?? '';
$basePath = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME']))), '/');
header('Location: ' . $basePath . '/backend/pages/reset-password.php' . ($qs ? '?' . $qs : ''));
exit;