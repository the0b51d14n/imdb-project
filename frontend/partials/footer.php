<?php
if (!isset($basePath)) {
    $scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
    $basePath  = str_ends_with($scriptDir, '/pages') ? dirname($scriptDir) : $scriptDir;
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
      <a href="<?= $basePath ?>/pages/movies.php">Films</a>
      <a href="<?= $basePath ?>/pages/search.php">Recherche</a>
      <a href="<?= $basePath ?>/pages/login.php">Connexion</a>
    </div>

  </div>
</footer>

<script src="<?= $basePath ?>/assets/js/components/loader.js"></script>
<script src="<?= $basePath ?>/assets/js/components/navbar.js"></script>
<script src="<?= $basePath ?>/assets/js/components/logout-button.js"></script>