<?php
// frontend/pages/logout.php — redirige vers backend
$basePath = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME']))), '/');
header('Location: ' . $basePath . '/backend/pages/logout.php');
exit;