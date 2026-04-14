<?php
// frontend/partials/footer.php — v2 (avec nouveaux composants)
if (!isset($basePath)) {
    $scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
    $basePath  = str_ends_with($scriptDir, '/pages') ? dirname($scriptDir) : $scriptDir;
    if (str_ends_with($basePath, '/backend')) {
        $basePath = dirname($basePath);
    }
}
?>
<footer class="footer">
  <div class="footer-inner">

    <a href="<?= $basePath ?>/index.php" class="footer-logo">
      SUPINFO<span>.TV</span>
    </a>

    <p class="footer-copy">
      &copy; <?= date('Y') ?> Supinfo.TV &mdash; Projet académique.
      Données fournies par
      <a href="https://www.themoviedb.org/" target="_blank" rel="noopener"
         style="color:var(--accent);text-decoration:underline;text-underline-offset:3px;">TMDB</a>.
    </p>

    <div class="footer-links">
      <a href="<?= $basePath ?>/backend/pages/movies.php">Films</a>
      <a href="<?= $basePath ?>/backend/pages/search.php">Recherche</a>
      <?php if (!empty($_SESSION['user_id'])): ?>
      <a href="<?= $basePath ?>/backend/pages/watchlist.php">Ma liste</a>
      <?php endif; ?>
      <a href="<?= $basePath ?>/pages/login.php">Connexion</a>
    </div>

  </div>
</footer>

<!-- ── Scripts core ──────────────────────────────────────────────────────── -->
<script src="<?= $basePath ?>/assets/js/components/loader.js"></script>
<script src="<?= $basePath ?>/assets/js/components/navbar.js"></script>
<script src="<?= $basePath ?>/assets/js/components/logout-button.js"></script>
<script src="<?= $basePath ?>/assets/js/components/movie-card.js"></script>

<!-- ── Scripts v2 ────────────────────────────────────────────────────────── -->
<script src="<?= $basePath ?>/assets/js/components/blur-up.js"></script>
<script src="<?= $basePath ?>/assets/js/components/search-autocomplete.js"></script>
<script src="<?= $basePath ?>/assets/js/components/cart-ajax.js"></script>
<?php if (!empty($_SESSION['user_id'])): ?>
<script src="<?= $basePath ?>/assets/js/components/watchlist-button.js"></script>
<?php endif; ?>