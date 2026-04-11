<?php

require_once __DIR__ . '/../services/auth.php';
 
auth_start_session();
auth_logout();
 
$basePath = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME']))), '/');
header('Location: ' . $basePath . '/index.php');
exit;
 
?>