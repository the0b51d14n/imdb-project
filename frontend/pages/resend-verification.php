<?php
// frontend/pages/resend-verification.php — redirection vers backend
$qs = $_SERVER['QUERY_STRING'] ?? '';
$basePath = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME']))), '/');
header('Location: ' . $basePath . '/backend/pages/resend-verification.php' . ($qs ? '?' . $qs : ''));
exit;