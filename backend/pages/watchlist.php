<?php
// ══════════════════════════════════════════════════════════════════════════════
//  backend/pages/watchlist.php — Supinfo.TV
//  Page "Ma liste" : affiche les films de la watchlist de l'utilisateur.
// ══════════════════════════════════════════════════════════════════════════════

require_once __DIR__ . '/../services/session.php';
app_session_start();

require_once __DIR__ . '/../services/auth.php';
require_once __DIR__ . '/../services/watchlist.php';
require_once __DIR__ . '/../services/csrf.php';

auth_start_session();

$basePath = rtrim(str_replace('\\', '/', dirname(dirname(dirname($_SERVER['SCRIPT_NAME'])))), '/');

if (!auth_check()) {
    header('Location: ' . $basePath . '/pages/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

$items = watchlist_get();

// Précalculer les IDs watchlist pour la movie-card
$watchlistIds = [];
foreach ($items as $item) {
    $watchlistIds[$item['tmdb_id']] = true;
}
$_SESSION['watchlist_ids'] = $watchlistIds;

$pageTitle  = 'Ma liste';
$pageCSS    = 'pages/movies.css';
$pageDesc   = 'Vos films sauvegardés pour plus tard sur Supinfo.TV.';
$activePage = 'watchlist';

include __DIR__ . '/../partials/head.php';
include __DIR__ . '/../partials/loader.php';
include __DIR__ . '/../partials/navbar.php';
?>

<main>
<div class="container" style="padding-top:48px;padding-bottom:80px;">

  <div style="margin-bottom:40px;">
    <div style="font-size:11px;font-weight:500;letter-spacing:0.12em;text-transform:uppercase;
                color:var(--accent);margin-bottom:8px;">Bibliothèque</div>
    <h1 style="font-size:clamp(28px,4vw,42px);font-weight:500;letter-spacing:-0.02em;
               color:var(--text);margin-bottom:8px;display:flex;align-items:baseline;gap:12px;">
      Ma liste
      <span style="font-size:16px;color:var(--text-muted);font-weight:400;">
        <?= count($items) ?> film<?= count($items) > 1 ? 's' : '' ?>
      </span>
    </h1>
    <p style="font-size:14px;color:var(--text-muted);">
      Vos films sauvegardés pour plus tard. Cliquez sur ➕ pour en ajouter depuis le catalogue.
    </p>
  </div>

  <?php if (empty($items)): ?>
  <div style="padding:64px 24px;background:var(--surface);border:1px solid var(--border);
              border-radius:var(--radius-lg);text-align:center;">
    <div style="font-size:48px;margin-bottom:20px;opacity:0.4;">🎬</div>
    <h2 style="font-size:20px;color:var(--text);margin-bottom:12px;">Votre liste est vide</h2>
    <p style="font-size:14px;color:var(--text-muted);max-width:360px;margin:0 auto 24px;line-height:1.7;">
      Survolez une affiche de film et cliquez sur ➕ pour l'ajouter à votre liste.
    </p>
    <a href="<?= $basePath ?>/backend/pages/movies.php" class="btn-primary">
      Explorer le catalogue
    </a>
  </div>

  <?php else: ?>
  <div class="movies-grid">
    <?php foreach ($items as $item):
      $movie = [
          'id'     => $item['tmdb_id'],
          'title'  => $item['title'],
          'poster' => $item['poster'],
          'year'   => $item['year'],
          'note'   => (float)($item['note'] ?? 0),
          'price'  => ['unit' => null],
          'type'   => 'movie',
      ];
    ?>
      <?php include __DIR__ . '/../../frontend/partials/movie-card.php'; ?>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

</div>
</main>

<?php include __DIR__ . '/../partials/footer.php'; ?>
<script src="<?= $basePath ?>/assets/js/pages/movies.js"></script>
</body>
</html>
