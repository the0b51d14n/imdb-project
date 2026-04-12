<?php

session_start();

require_once __DIR__ . '/../config/tmdb.php';
require_once __DIR__ . '/../services/tmdb-service.php';
require_once __DIR__ . '/../services/auth.php';
require_once __DIR__ . '/../services/cart.php';
require_once __DIR__ . '/../services/orders.php';
require_once __DIR__ . '/../services/csrf.php';

auth_start_session();

// Correction : basePath pointe vers la racine
$basePath = rtrim(str_replace('\\', '/', dirname(dirname(dirname($_SERVER['SCRIPT_NAME'])))), '/');

$movieId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$movieId || $movieId <= 0) {
    header('Location: ' . $basePath . '/pages/movies.php');
    exit;
}

$movie = tmdb_get_movie_detail($movieId);
if (!$movie) {
    http_response_code(404);
    $pageTitle = 'Film introuvable';
    include __DIR__ . '/../partials/head.php';
    echo '<main><div class="container" style="padding:80px 0;text-align:center;">
      <h1 style="font-size:32px;margin-bottom:12px;">Film introuvable</h1>
      <p style="color:var(--text-muted);">Ce film n\'existe pas ou n\'est plus disponible.</p>
      <a href="' . $basePath . '/pages/movies.php" class="btn-primary" style="margin-top:24px;display:inline-flex;">Retour au catalogue</a>
      </div></main>';
    include __DIR__ . '/../partials/footer.php';
    echo '</body></html>';
    exit;
}

$cartMsg   = null;
$cartError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_to_cart') {
    if (!auth_check()) {
        header('Location: ' . $basePath . '/pages/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
    csrf_verify();

    $r = cart_add(
        $movie['id'],
        $movie['title'],
        $movie['poster'],
        $movie['price']['unit']
    );

    if ($r['ok']) {
        $cartMsg = 'Film ajouté au panier !';
    } else {
        $cartError = $r['error'];
    }
}

$inCart    = auth_check() && cart_has($movie['id']);
$purchased = auth_check() && orders_has_purchased($movie['id']);

$recommended = [];
$recData = tmdb_get('/movie/' . $movie['id'] . '/recommendations', ['page' => 1]);
if ($recData && !empty($recData['results'])) {
    $recommended = tmdb_format_movies(array_slice($recData['results'], 0, 6));
}

$pageTitle  = $movie['title'];
$pageCSS    = 'pages/movie-detail.css';
$pageDesc   = mb_substr($movie['synopsis'] ?? ('Découvrez ' . $movie['title'] . ' sur Supinfo.TV.'), 0, 155);
$activePage = 'movies';

include __DIR__ . '/../partials/head.php';
include __DIR__ . '/../partials/loader.php';
include __DIR__ . '/../partials/navbar.php';
?>

<main style="padding-top:0;">

  <!-- Hero backdrop -->
  <?php if (!empty($movie['backdrop'])): ?>
  <div class="movie-hero">
    <img src="<?= htmlspecialchars($movie['backdrop']) ?>"
         alt="" class="movie-hero-bg" role="presentation">
    <div class="movie-hero-overlay"></div>
  </div>
  <?php else: ?>
  <div style="height:200px;background:var(--surface);"></div>
  <?php endif; ?>

  <div class="container">
    <div class="movie-detail-layout">

      <!-- Colonne poster + achat -->
      <div>
        <div class="movie-poster-wrap">
          <?php if (!empty($movie['poster'])): ?>
          <img src="<?= htmlspecialchars($movie['poster']) ?>"
               alt="Affiche de <?= htmlspecialchars($movie['title']) ?>">
          <?php else: ?>
          <div class="movie-poster-placeholder">🎬</div>
          <?php endif; ?>
        </div>

        <div class="movie-buy-panel">
          <?php if ($purchased): ?>
            <div class="movie-owned-badge">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="20 6 9 17 4 12"/>
              </svg>
              Déjà dans votre bibliothèque
            </div>
          <?php elseif ($inCart): ?>
            <div class="movie-incart-badge">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/>
                <line x1="3" y1="6" x2="21" y2="6"/>
                <path d="M16 10a4 4 0 0 1-8 0"/>
              </svg>
              Déjà dans le panier
            </div>
            <a href="<?= $basePath ?>/pages/cart.php" class="btn-ghost" style="width:100%;justify-content:center;">
              Voir le panier
            </a>
          <?php else: ?>
            <?php if (!empty($movie['price'])): ?>
            <div class="movie-price-big">
              <?= number_format((float)$movie['price']['unit'], 2, ',', '') ?>€
            </div>
            <?php if (!empty($movie['price']['bundle'])): ?>
            <p class="movie-price-bundle"><?= htmlspecialchars($movie['price']['label']) ?></p>
            <?php endif; ?>
            <?php endif; ?>

            <?php if ($cartMsg): ?>
            <div class="movie-alert movie-alert-success"><?= htmlspecialchars($cartMsg) ?></div>
            <?php endif; ?>
            <?php if ($cartError): ?>
            <div class="movie-alert movie-alert-error"><?= htmlspecialchars($cartError) ?></div>
            <?php endif; ?>

            <form method="POST" action="">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="add_to_cart">
              <button type="submit" class="btn-primary" style="width:100%;justify-content:center;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/>
                  <line x1="3" y1="6" x2="21" y2="6"/>
                  <path d="M16 10a4 4 0 0 1-8 0"/>
                </svg>
                Ajouter au panier
              </button>
            </form>
          <?php endif; ?>

          <?php if (!empty($movie['trailer_key'])): ?>
          <button class="btn-ghost" style="width:100%;justify-content:center;"
                  onclick="openTrailer('<?= htmlspecialchars($movie['trailer_key']) ?>')">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
              <polygon points="5 3 19 12 5 21 5 3"/>
            </svg>
            Bande-annonce
          </button>
          <?php endif; ?>
        </div>
      </div>

      <!-- Colonne infos -->
      <div class="movie-info">

        <div class="movie-tags">
          <?php if (!empty($movie['year'])): ?>
          <span class="movie-tag"><?= htmlspecialchars($movie['year']) ?></span>
          <?php endif; ?>
          <?php if (!empty($movie['genre'])): ?>
          <span class="movie-tag"><?= htmlspecialchars($movie['genre']) ?></span>
          <?php endif; ?>
          <?php if (!empty($movie['duration'])): ?>
          <span class="movie-tag"><?= (int)$movie['duration'] ?> min</span>
          <?php endif; ?>
          <?php if (!empty($movie['note'])): ?>
          <span class="movie-rating">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="var(--gold)" stroke="none">
              <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
            </svg>
            <?= number_format((float)$movie['note'], 1) ?>/10
          </span>
          <?php endif; ?>
        </div>

        <h1 class="movie-title"><?= htmlspecialchars($movie['title']) ?></h1>

        <?php if (!empty($movie['director'])): ?>
        <p class="movie-director-line">
          Réalisé par&nbsp;
          <a href="<?= $basePath ?>/pages/director.php?name=<?= urlencode($movie['director']) ?>"
             class="movie-director-link">
            <?= htmlspecialchars($movie['director']) ?>
          </a>
        </p>
        <?php endif; ?>

        <?php if (!empty($movie['synopsis'])): ?>
        <p class="movie-synopsis"><?= htmlspecialchars($movie['synopsis']) ?></p>
        <?php endif; ?>

        <?php if (!empty($movie['collection'])): ?>
        <div class="movie-collection">
          <?php if (!empty($movie['collection']['poster_path'])): ?>
          <img src="<?= tmdb_image_url($movie['collection']['poster_path'], 'w92') ?>"
               alt="" class="movie-collection-poster">
          <?php endif; ?>
          <div>
            <div class="movie-collection-label">Saga</div>
            <div class="movie-collection-name"><?= htmlspecialchars($movie['collection']['name']) ?></div>
          </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($movie['cast'])): ?>
        <div class="movie-cast-section">
          <div class="movie-cast-label">Casting</div>
          <div class="movie-cast-grid">
            <?php foreach ($movie['cast'] as $actor): ?>
            <div class="movie-actor">
              <div class="movie-actor-avatar">
                <?php if (!empty($actor['profile_path'])): ?>
                <img src="<?= tmdb_image_url($actor['profile_path'], 'w185') ?>"
                     alt="<?= htmlspecialchars($actor['name']) ?>" loading="lazy">
                <?php else: ?>
                <div style="width:100%;height:100%;display:flex;align-items:center;
                            justify-content:center;font-size:20px;">👤</div>
                <?php endif; ?>
              </div>
              <span class="movie-actor-name"><?= htmlspecialchars($actor['name']) ?></span>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

      </div>
    </div>

    <!-- Recommandations -->
    <?php if (!empty($recommended)): ?>
    <div class="movie-recommendations">
      <div class="movie-rec-header">
        <div class="movie-rec-label">Vous aimerez aussi</div>
        <h2 class="movie-rec-title">Films similaires</h2>
      </div>
      <div class="movie-rec-grid">
        <?php foreach ($recommended as $movie): ?>
          <?php include __DIR__ . '/../../frontend/partials/movie-card.php'; ?>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

  </div>
</main>

<!-- Trailer modal -->
<div id="trailer-modal"
     style="display:none;position:fixed;inset:0;z-index:1000;
            background:rgba(0,0,0,0.9);backdrop-filter:blur(10px);
            align-items:center;justify-content:center;"
     onclick="closeTrailerOnBg(event)">
  <div class="trailer-modal-inner">
    <button class="trailer-close" onclick="closeTrailer()" aria-label="Fermer">&#x2715;</button>
    <div class="trailer-embed">
      <iframe id="trailer-iframe" src="" frameborder="0"
              allowfullscreen allow="autoplay; encrypted-media"></iframe>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../partials/footer.php'; ?>

<script>
(function () {
    'use strict';
    const modal  = document.getElementById('trailer-modal');
    const iframe = document.getElementById('trailer-iframe');

    window.openTrailer = function (key) {
        if (!modal || !iframe || !key) return;
        iframe.src = 'https://www.youtube.com/embed/' + key + '?autoplay=1&rel=0&modestbranding=1';
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    };
    window.closeTrailer = function () {
        if (!modal || !iframe) return;
        iframe.src = '';
        modal.style.display = 'none';
        document.body.style.overflow = '';
    };
    window.closeTrailerOnBg = function (e) {
        if (e.target.id === 'trailer-modal') window.closeTrailer();
    };
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') window.closeTrailer();
    });
})();
</script>
</body>
</html>