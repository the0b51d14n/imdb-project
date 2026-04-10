<?php
/**
 * partials/movie-card.php
 * Variable attendue : $movie
 *   id, title, poster, year, note, price['unit'], type?
 */
$cardType  = $movie['type'] ?? 'movie';
$cardPrice = $movie['price']['unit'] ?? null;
$cardNote  = $movie['note'] ?? null;

// basePath est calculé dans head.php — fallback de sécurité
if (!isset($basePath)) {
    $scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
    $basePath  = str_ends_with($scriptDir, '/pages') ? dirname($scriptDir) : $scriptDir;
}
?>
<article class="movie-card">
  <a href="<?= $basePath ?>/pages/movie-detail.php?id=<?= (int)$movie['id'] ?>&type=<?= htmlspecialchars($cardType) ?>">

    <div class="movie-card-poster">
      <?php if (!empty($movie['poster'])): ?>
        <img
          class="movie-card-img"
          src="<?= htmlspecialchars($movie['poster']) ?>"
          alt="<?= htmlspecialchars($movie['title']) ?>"
          loading="lazy"
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
</article>