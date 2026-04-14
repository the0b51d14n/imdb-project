<?php
// frontend/pages/error.php — Page d'erreur 500/503
// Pas d'includes qui pourraient elles-mêmes échouer (minimal intentionnel)
$code = http_response_code();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Erreur — Supinfo.TV</title>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body {
        font-family: 'DM Sans', system-ui, sans-serif;
        background: #081820;
        color: #e8f8f0;
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 24px;
    }
    .wrap { text-align: center; max-width: 480px; }
    .code {
        font-size: 96px;
        font-weight: 500;
        line-height: 1;
        color: #e05a6a;
        letter-spacing: -0.04em;
        margin-bottom: 16px;
    }
    h1 { font-size: 24px; font-weight: 500; color: #e8f8f0; margin-bottom: 12px; }
    p  { font-size: 14px; color: #6db8b9; line-height: 1.7; margin-bottom: 28px; }
    a  {
        display: inline-flex; align-items: center; gap: 8px;
        padding: 12px 28px;
        background: #57cc99; color: #fff;
        border-radius: 8px; text-decoration: none;
        font-size: 13px; font-weight: 500;
        letter-spacing: 0.06em; text-transform: uppercase;
    }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="code"><?= (int)($code ?: 500) ?></div>
    <h1>Une erreur est survenue</h1>
    <p>
      Nous avons rencontré un problème technique. Notre équipe en a été notifiée.
      Réessayez dans quelques instants.
    </p>
    <a href="/">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M19 12H5M12 19l-7-7 7-7"/>
      </svg>
      Retour à l'accueil
    </a>
  </div>
</body>
</html>