<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="<?= htmlspecialchars($pageDesc ?? 'Supinfo.TV') ?>">
  <title><?= htmlspecialchars($pageTitle ?? 'Supinfo.TV') ?> — Supinfo.TV</title>

<?php
if (!isset($basePath)) {
    $scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
    if (str_ends_with($scriptDir, '/pages')) {
        $basePath = dirname($scriptDir);
    } else {
        $basePath = $scriptDir;
    }
}
?>
  <link rel="stylesheet" href="<?= $basePath ?>/assets/css/app.css">

  <?php if (!empty($pageCSS)): ?>
  <link rel="stylesheet" href="<?= $basePath ?>/assets/css/<?= htmlspecialchars($pageCSS) ?>">
  <?php endif; ?>

  <link rel="icon" href="<?= $basePath ?>/assets/images/brand/favicon.ico" type="image/x-icon">

  <script src="https://cdn.jsdelivr.net/npm/gsap@3/dist/gsap.min.js" defer></script>
</head>
<body>