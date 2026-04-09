<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="<?= htmlspecialchars($pageDesc ?? 'Supinfo.TV') ?>">
  <title><?= htmlspecialchars($pageTitle ?? 'Supinfo.TV') ?> — Supinfo.TV</title>

  <link rel="stylesheet" href="/frontend/assets/css/app.css">

  <?php if (!empty($pageCSS)): ?>
  <link rel="stylesheet" href="/frontend/assets/css/<?= htmlspecialchars($pageCSS) ?>">
  <?php endif; ?>

  <link rel="icon" href="/frontend/assets/images/brand/favicon.ico" type="image/x-icon">

  <script src="https://cdn.jsdelivr.net/npm/gsap@3/dist/gsap.min.js" defer></script>
</head>
<body>