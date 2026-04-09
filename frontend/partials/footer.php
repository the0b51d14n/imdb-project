<?php if (!isset($basePath)) {
    $basePath = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME']))), '/');
} ?>
<footer class="footer">
  <div class="footer-inner">

    <div class="footer-logo">SUPINFO<span>.TV</span></div>

    <p class="footer-copy">
      &copy; <?= date('Y') ?> Supinfo.TV — Projet académique.
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