<?php
/**
 * frontend/partials/movie-card.php — v2
 * Variable attendue : $movie
 *   id, title, poster, year, note, price['unit'], type?
 *
 * Nouveautés v2 :
 *   - data-attributes pour les filtres côté client
 *   - Bouton watchlist (si utilisateur connecté)
 *   - Lazy loading blur-up (w92 → w500)
 *   - Session pour les posters cache AJAX
 */
$cardType  = $movie['type'] ?? 'movie';
$cardPrice = $movie['price']['unit'] ?? null;
$cardNote  = $movie['note'] ?? null;

if (!isset($basePath)) {
    $scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
    $basePath  = str_ends_with($scriptDir, '/pages') ? dirname($scriptDir) : $scriptDir;
    if (str_ends_with($basePath, '/backend')) {
        $basePath = dirname($basePath);
    }
}

// Stocker le poster en session pour les appels AJAX cart/watchlist
if (!empty($movie['id']) && !empty($movie['poster']) && session_status() === PHP_SESSION_ACTIVE) {
    $_SESSION['poster_cache'][$movie['id']] = $movie['poster'];
}

// Image basse résolution pour le blur-up (w92)
$posterLow = '';
if (!empty($movie['poster'])) {
    // Remplacer la taille dans l'URL TMDB (w500 → w92)
    $posterLow = preg_replace('~/[a-z]\d+/~', '/w92/', $movie['poster']);
    if ($posterLow === $movie['poster']) {
        $posterLow = ''; // Pas de remplacement → pas de blur-up
    }
}

// CSRF pour les boutons AJAX
$csrfToken = $_SESSION['_csrf_token'] ?? '';
$isLoggedIn = !empty($_SESSION['user_id']);
?>
<article class="movie-card"
         data-note="<?= (float)($cardNote ?? 0) ?>"
         data-price="<?= (float)($cardPrice ?? 0) ?>"
         data-year="<?= htmlspecialchars($movie['year'] ?? '') ?>"
         data-title="<?= htmlspecialchars($movie['title'] ?? '') ?>">
  <a href="<?= $basePath ?>/backend/pages/movie-detail.php?id=<?= (int)$movie['id'] ?>&type=<?= htmlspecialchars($cardType) ?>">

    <div class="movie-card-poster">
      <?php if (!empty($movie['poster'])): ?>
        <img
          class="movie-card-img<?= $posterLow ? ' blur-up' : '' ?>"
          <?= $posterLow ? 'src="' . htmlspecialchars($posterLow) . '" data-src="' . htmlspecialchars($movie['poster']) . '"' : 'src="' . htmlspecialchars($movie['poster']) . '"' ?>
          alt="<?= htmlspecialchars($movie['title']) ?>"
          loading="lazy"
          width="180"
          height="270"
          decoding="async"
        >
      <?php else: ?>
        <div class="movie-card-placeholder">
          🎬<span>Pas d'affiche</span>
        </div>
      <?php endif; ?>

      <?php if ($cardNote): ?>
      <div class="movie-card-badge">
        <svg width="10" height="10" viewBox="0 0 24 24" fill="var(--gold)" stroke="none">
          <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
        </svg>
        <?= number_format((float)$cardNote, 1) ?>
      </div>
      <?php endif; ?>

      <?php if ($cardType === 'tv'): ?>
      <div class="movie-card-type">Série</div>
      <?php endif; ?>

      <span class="movie-card-glow-bubble"></span>

      <div class="movie-card-overlay">
        <div class="movie-card-overlay-title"><?= htmlspecialchars($movie['title']) ?></div>
        <div class="movie-card-overlay-btn">
          <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <path d="M5 12h14M12 5l7 7-7 7"/>
          </svg>
          Voir
        </div>
      </div>
    </div>

    <div class="movie-card-info">
      <div class="movie-card-title"><?= htmlspecialchars($movie['title']) ?></div>
      <div class="movie-card-meta">
        <span class="movie-card-year"><?= htmlspecialchars($movie['year'] ?? '') ?></span>
        <?php if ($cardPrice): ?>
        <span class="movie-card-price"><?= number_format((float)$cardPrice, 2, ',', '') ?>€</span>
        <?php endif; ?>
      </div>
    </div>

  </a>

  <?php if ($isLoggedIn && !empty($movie['id'])): ?>
  <button class="btn-watchlist movie-card-watchlist"
          data-tmdb-id="<?= (int)$movie['id'] ?>"
          data-title="<?= htmlspecialchars($movie['title'] ?? '') ?>"
          data-poster="<?= htmlspecialchars($movie['poster'] ?? '') ?>"
          data-year="<?= htmlspecialchars($movie['year'] ?? '') ?>"
          data-note="<?= (float)($cardNote ?? 0) ?>"
          data-in-list="<?= !empty($_SESSION['watchlist_ids'][$movie['id']]) ? '1' : '0' ?>"
          data-csrf="<?= htmlspecialchars($csrfToken) ?>"
          title="Ajouter à ma liste"
          aria-label="Ajouter à ma liste"
          style="position:absolute;top:8px;right:<?= $cardNote ? '40px' : '8px' ?>;z-index:4;
                 width:28px;height:28px;padding:0;border-radius:50%;
                 display:flex;align-items:center;justify-content:center;
                 font-size:11px;min-width:unset;letter-spacing:0;text-transform:none;">
    <span class="wl-icon">
      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
      </svg>
    </span>
  </button>
  <?php endif; ?>

</article>