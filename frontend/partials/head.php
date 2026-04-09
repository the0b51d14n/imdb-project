<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="<?= htmlspecialchars($pageDesc ?? 'Supinfo.TV') ?>">
  <title><?= htmlspecialchars($pageTitle ?? 'Supinfo.TV') ?> — Supinfo.TV</title>

  <?php
  $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
  $basePath  = rtrim($scriptDir, '/');
  ?>

  <link rel="stylesheet" href="<?= $basePath ?>/assets/css/app.css">

  <?php if (!empty($pageCSS)): ?>
  <link rel="stylesheet" href="<?= $basePath ?>/assets/css/<?= htmlspecialchars($pageCSS) ?>">
  <?php endif; ?>

</head>
<body>