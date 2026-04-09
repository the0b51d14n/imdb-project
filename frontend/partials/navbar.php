<?php
if (!isset($basePath)) {
    $scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
    $basePath  = str_ends_with($scriptDir, '/pages') ? dirname($scriptDir) : $scriptDir;
}
?>
<nav class="navbar" id="navbar">
  <div class="navbar-inner">

    <a href="<?= $basePath ?>/index.php" class="navbar-logo">
      <img src="<?= $basePath ?>/assets/images/brand/logo-blanc.png" alt="Supinfo.TV">
    </a>

    <div class="navbar-links" id="navbar-links">
      <a href="<?= $basePath ?>/index.php"        class="<?= ($activePage??'')==='home'   ? 'active' : '' ?>">Accueil</a>
      <a href="<?= $basePath ?>/pages/movies.php" class="<?= ($activePage??'')==='movies' ? 'active' : '' ?>">Films</a>
      <a href="<?= $basePath ?>/pages/search.php" class="<?= ($activePage??'')==='search' ? 'active' : '' ?>">Recherche</a>
    </div>

    <div class="navbar-right">

      <a href="<?= $basePath ?>/pages/search.php" class="navbar-search-btn" title="Rechercher">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
          <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
        </svg>
      </a>

      <a href="<?= $basePath ?>/pages/cart.php" class="navbar-cart" title="Panier">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
          <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/>
          <line x1="3" y1="6" x2="21" y2="6"/>
          <path d="M16 10a4 4 0 0 1-8 0"/>
        </svg>
        <?php
          $cartCount = $_SESSION['cart_count'] ?? 0;
          if ($cartCount > 0):
        ?>
        <span class="cart-badge"><?= (int)$cartCount ?></span>
        <?php endif; ?>
      </a>

      <?php if (!empty($_SESSION['user_id'])): ?>
        <a href="<?= $basePath ?>/pages/profile.php" class="btn-nav-login">
          <?= htmlspecialchars($_SESSION['user_name'] ?? 'Profil') ?>
        </a>
        <button class="logout-btn-animated" id="logout-btn"
                data-href="<?= $basePath ?>/pages/logout.php">
          <svg class="logout-doorway" viewBox="0 0 100 100">
            <path d="M93.4 86.3H58.6c-1.9 0-3.4-1.5-3.4-3.4V17.1c0-1.9 1.5-3.4 3.4-3.4h34.8c1.9 0 3.4 1.5 3.4 3.4v65.8c0 1.9-1.5 3.4-3.4 3.4z"/>
            <path class="logout-bang" d="M40.5 43.7L26.6 31.4l-2.5 6.7zM41.9 50.4l-19.5-4-1.4 6.3zM40 57.4l-17.7 3.9 3.9 5.7z"/>
          </svg>
          <svg class="logout-figure" viewBox="0 0 100 100">
            <circle cx="52.1" cy="32.4" r="6.4"/>
            <path d="M50.7 62.8c-1.2 2.5-3.6 5-7.2 4-3.2-.9-4.9-3.5-4-7.8.7-3.4 3.1-13.8 4.1-15.8 1.7-3.4 1.6-4.6 7-3.7 4.3.7 4.6 2.5 4.3 5.4-.4 3.7-2.8 15.1-4.2 17.9z"/>
            <g class="logout-arm1">
              <path d="M55.5 56.5l-6-9.5c-1-1.5-.6-3.5.9-4.4 1.5-1 3.7-1.1 4.6.4l6.1 10c1 1.5.3 3.5-1.1 4.4-1.5.9-3.5.5-4.5-.9z"/>
              <path class="logout-wrist1" d="M69.4 59.9L58.1 58c-1.7-.3-2.9-1.9-2.6-3.7.3-1.7 1.9-2.9 3.7-2.6l11.4 1.9c1.7.3 2.9 1.9 2.6 3.7-.4 1.7-2 2.9-3.8 2.6z"/>
            </g>
            <g class="logout-arm2">
              <path d="M34.2 43.6L45 40.3c1.7-.6 3.5.3 4 2 .6 1.7-.3 4-2 4.5l-10.8 2.8c-1.7.6-3.5-.3-4-2-.6-1.6.3-3.4 2-4z"/>
              <path class="logout-wrist2" d="M27.1 56.2L32 45.7c.7-1.6 2.6-2.3 4.2-1.6 1.6.7 2.3 2.6 1.6 4.2L33 58.8c-.7 1.6-2.6 2.3-4.2 1.6-1.7-.7-2.4-2.6-1.7-4.2z"/>
            </g>
            <g class="logout-leg1">
              <path d="M52.1 73.2s-7-5.7-7.9-6.5c-.9-.9-1.2-3.5-.1-4.9 1.1-1.4 3.8-1.9 5.2-.9l7.9 7c1.4 1.1 1.7 3.5.7 4.9-1.1 1.4-4.4 1.5-5.8.4z"/>
              <path class="logout-calf1" d="M52.6 84.4l-1-12.8c-.1-1.9 1.5-3.6 3.5-3.7 2-.1 3.7 1.4 3.8 3.4l1 12.8c.1 1.9-1.5 3.6-3.5 3.7-2 0-3.7-1.5-3.8-3.4z"/>
            </g>
            <g class="logout-leg2">
              <path d="M37.8 72.7s1.3-10.2 1.6-11.4 2.4-2.8 4.1-2.6c1.7.2 3.6 2.3 3.4 4l-1.8 11.1c-.2 1.7-1.7 3.3-3.4 3.1-1.8-.2-4.1-2.4-3.9-4.2z"/>
              <path class="logout-calf2" d="M29.5 82.3l9.6-10.9c1.3-1.4 3.6-1.5 5.1-.1 1.5 1.4.4 4.9-.9 6.3l-8.5 9.6c-1.3 1.4-3.6 1.5-5.1.1-1.4-1.3-1.5-3.5-.2-5z"/>
            </g>
          </svg>
          <svg class="logout-door" viewBox="0 0 100 100">
            <path d="M93.4 86.3H58.6c-1.9 0-3.4-1.5-3.4-3.4V17.1c0-1.9 1.5-3.4 3.4-3.4h34.8c1.9 0 3.4 1.5 3.4 3.4v65.8c0 1.9-1.5 3.4-3.4 3.4z"/>
            <circle cx="66" cy="50" r="3.7"/>
          </svg>
          <span class="logout-text">Déconnexion</span>
        </button>
      <?php else: ?>
        <a href="<?= $basePath ?>/pages/login.php"    class="btn-nav-login">Connexion</a>
        <a href="<?= $basePath ?>/pages/register.php" class="btn-nav-register" id="btn-register">
          <strong>S'inscrire</strong>
        </a>
      <?php endif; ?>
    </div>

    <button class="navbar-hamburger" id="navbar-hamburger" aria-label="Menu">
      <span></span><span></span><span></span>
    </button>

  </div>
</nav>