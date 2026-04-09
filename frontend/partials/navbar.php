<nav class="navbar" id="navbar">
  <div class="navbar-inner">

    <a href="/frontend/index.php" class="navbar-logo">
      SUPINFO<span>.TV</span>
    </a>

    <div class="navbar-links">
      <a href="/frontend/index.php"            class="<?= ($activePage??'')==='home'    ? 'active' : '' ?>">Accueil</a>
      <a href="/frontend/pages/movies.php"     class="<?= ($activePage??'')==='movies'  ? 'active' : '' ?>">Films</a>
      <a href="/frontend/pages/search.php"     class="<?= ($activePage??'')==='search'  ? 'active' : '' ?>">Recherche</a>
    </div>

    <div class="navbar-right">

      <a href="/frontend/pages/search.php" class="navbar-search-btn" title="Rechercher">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
          <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
        </svg>
      </a>

      <a href="/frontend/pages/cart.php" class="navbar-cart" title="Panier">
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
        <a href="/frontend/pages/profile.php" class="btn-nav-login">
          <?= htmlspecialchars($_SESSION['user_name'] ?? 'Profil') ?>
        </a>
      <?php else: ?>
        <a href="/frontend/pages/login.php"    class="btn-nav-login">Connexion</a>
        <a href="/frontend/pages/register.php" class="btn-nav-register">S'inscrire</a>
      <?php endif; ?>
    </div>

    <button class="navbar-hamburger" id="navbar-hamburger" aria-label="Menu">
      <span></span><span></span><span></span>
    </button>

  </div>
</nav>