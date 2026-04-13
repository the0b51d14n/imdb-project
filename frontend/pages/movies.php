<?php
// frontend/pages/movies.php — redirige vers backend
$qs       = $_SERVER['QUERY_STRING'] ?? '';
$basePath = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME']))), '/');
header('Location: ' . $basePath . '/backend/pages/movies.php' . ($qs ? '?' . $qs : ''));
exit;