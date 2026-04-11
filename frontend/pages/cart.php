<?php
session_start();

require_once __DIR__ . '/../config/tmdb.php';

if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];

$action = $_POST['action'] ?? $_GET['action'] ?? null;

if ($action && $_SERVER['REQUEST_METHOD'] === 'POST') {

    $movieId = (int)($_POST['movie_id'] ?? 0);

    if ($action === 'add' && $movieId > 0) {
        $submittedPrice = (float)($_POST['price'] ?? 0);
        $type           = $_POST['type'] ?? 'movie';
        $storedSig      = $_SESSION['price_sig'][$movieId] ?? '';

        $priceValid = !empty($storedSig)
            && tmdb_verify_price($movieId, $submittedPrice, $type, $storedSig);

        if (!$priceValid) {
            error_log("[PRICE_TAMPER] movie_id={$movieId} price={$submittedPrice} ip=" . ($_SERVER['REMOTE_ADDR'] ?? '?'));

            $submittedPrice = $_SESSION['price_cache'][$movieId] ?? $submittedPrice;
        }

        if (isset($_SESSION['cart'][$movieId])) {
        } else {
            $_SESSION['cart'][$movieId] = [
                'id'      => $movieId,
                'title'   => htmlspecialchars($_POST['title']  ?? 'Film'),
                'poster'  => htmlspecialchars($_POST['poster'] ?? ''),
                'year'    => htmlspecialchars($_POST['year']   ?? ''),
                'type'    => $type,
                'price'   => $submittedPrice,
                'added'   => time(),
            ];
        }

        // Mise à jour badge navbar
        $_SESSION['cart_count'] = count($_SESSION['cart']);

        header('Location: ' . ($_POST['redirect'] ?? 'cart.php'));
        exit;
    }

    if ($action === 'remove' && $movieId > 0) {
        unset($_SESSION['cart'][$movieId]);
        $_SESSION['cart_count'] = count($_SESSION['cart']);
        header('Location: cart.php');
        exit;
    }

    if ($action === 'clear') {
        $_SESSION['cart']       = [];
        $_SESSION['cart_count'] = 0;
        header('Location: cart.php');
        exit;
    }
}

$cartItems = $_SESSION['cart'] ?? [];
$subtotal  = array_sum(array_column($cartItems, 'price'));
$itemCount = count($cartItems);

$pageTitle  = 'Panier';
$pageCSS    = 'pages/cart.css';
$pageDesc   = 'Votre panier Supinfo.TV';
$activePage = '';

$basePath = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME']))), '/');

include __DIR__ . '/../partials/head.php';
include __DIR__ . '/../partials/loader.php';
include __DIR__ . '/../partials/navbar.php';
?>

<main>
<div class="cart-page">

  <?php if (empty($cartItems)): ?>
  <div class="cart-empty">
    <div class="cart-empty-icon">
      <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2">
        <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/>
        <line x1="3" y1="6" x2="21" y2="6"/>
        <path d="M16 10a4 4 0 0 1-8 0"/>
      </svg>
    </div>
    <h1 class="cart-empty-title">Votre panier est vide</h1>
    <p class="cart-empty-sub">Découvrez notre catalogue et ajoutez des films à votre collection.</p>
    <a href="<?= $basePath ?>/pages/movies.php" class="btn-primary">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
        <path d="M5 12h14M12 5l7 7-7 7"/>
      </svg>
      Explorer les films
    </a>
  </div>

  <?php else: ?>
  <div class="cart-layout">

    <section class="cart-items-section">

      <div class="cart-header">
        <div>
          <h1 class="cart-title">Mon panier</h1>
          <p class="cart-count"><?= $itemCount ?> film<?= $itemCount > 1 ? 's' : '' ?></p>
        </div>
        <form method="POST" action="cart.php">
          <input type="hidden" name="action" value="clear">
          <button type="submit" class="cart-clear-btn" onclick="return confirm('Vider le panier ?')">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <polyline points="3 6 5 6 21 6"/>
              <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>
              <path d="M10 11v6M14 11v6"/>
              <path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>
            </svg>
            Vider le panier
          </button>
        </form>
      </div>

      <div class="cart-items">
        <?php foreach ($cartItems as $item): ?>
        <article class="cart-item" id="cart-item-<?= $item['id'] ?>">

          <a href="<?= $basePath ?>/pages/movie-detail.php?id=<?= $item['id'] ?>&type=<?= htmlspecialchars($item['type']) ?>"
             class="cart-item-poster-wrap">
            <?php if (!empty($item['poster'])): ?>
              <img src="<?= $item['poster'] ?>" alt="<?= $item['title'] ?>" class="cart-item-poster">
            <?php else: ?>
              <div class="cart-item-poster-placeholder">🎬</div>
            <?php endif; ?>
            <?php if ($item['type'] === 'tv'): ?>
              <span class="cart-item-type-badge">Série</span>
            <?php endif; ?>
          </a>

          <div class="cart-item-info">
            <a href="<?= $basePath ?>/pages/movie-detail.php?id=<?= $item['id'] ?>&type=<?= htmlspecialchars($item['type']) ?>"
               class="cart-item-title"><?= $item['title'] ?></a>
            <?php if (!empty($item['year'])): ?>
              <span class="cart-item-year"><?= $item['year'] ?></span>
            <?php endif; ?>
            <div class="cart-item-tag">
              <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
              </svg>
              Achat définitif — accès illimité
            </div>
          </div>

          <div class="cart-item-right">
            <span class="cart-item-price"><?= number_format($item['price'], 2, ',', '') ?>€</span>
            <form method="POST" action="cart.php">
              <input type="hidden" name="action"   value="remove">
              <input type="hidden" name="movie_id" value="<?= $item['id'] ?>">
              <button type="submit" class="cart-item-remove" aria-label="Supprimer">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <line x1="18" y1="6" x2="6" y2="18"/>
                  <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
              </button>
            </form>
          </div>

        </article>
        <?php endforeach; ?>
      </div>

      <a href="<?= $basePath ?>/pages/movies.php" class="cart-continue">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <path d="M19 12H5M12 19l-7-7 7-7"/>
        </svg>
        Continuer mes achats
      </a>

    </section>

    <aside class="cart-summary">

      <div class="cart-summary-inner">
        <h2 class="cart-summary-title">Récapitulatif</h2>

        <div class="cart-summary-lines">
          <div class="cart-summary-line">
            <span>Sous-total</span>
            <span><?= number_format($subtotal, 2, ',', '') ?>€</span>
          </div>
          <div class="cart-summary-line">
            <span>Frais de service</span>
            <span class="cart-free">Offerts</span>
          </div>
          <div class="cart-summary-line cart-summary-line--total">
            <span>Total</span>
            <span><?= number_format($subtotal, 2, ',', '') ?>€</span>
          </div>
        </div>

        <button class="btn-order" type="button" id="btn-checkout">
          <span class="default">Commander</span>
          <span class="success">
            Commande envoyée
            <svg viewBox="0 0 12 10"><polyline points="1.5 6 4.5 9 10.5 1"/></svg>
          </span>
          <svg class="truck" viewBox="0 0 72 28">
            <g class="front"><path class="front" d="M0 0h47v28H0z"/></g>
            <g transform="translate(0,0)">
              <rect class="back" x="0" y="0" width="47" height="28" rx="1"/>
            </g>
            <g class="front">
              <path d="M47 0h15.5l8 11v17H47V0z"/>
              <path class="front" d="M47 0h15.5l8 11v17H47V0z"/>
            </g>
            <circle class="wheel" cx="14" cy="28" r="5"/>
            <circle class="wheel" cx="57" cy="28" r="5"/>
            <rect class="box" x="11" y="6" width="13" height="13" rx="1"/>
          </svg>
        </button>

        <p class="cart-secure-note">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
          </svg>
          Paiement 100% sécurisé — Prix vérifiés côté serveur
        </p>

        <div class="cart-summary-items">
          <?php foreach ($cartItems as $item): ?>
          <div class="cart-summary-item">
            <?php if (!empty($item['poster'])): ?>
              <img src="<?= $item['poster'] ?>" alt="<?= $item['title'] ?>" class="cart-summary-thumb">
            <?php endif; ?>
            <span class="cart-summary-item-title"><?= $item['title'] ?></span>
            <span class="cart-summary-item-price"><?= number_format($item['price'], 2, ',', '') ?>€</span>
          </div>
          <?php endforeach; ?>
        </div>

      </div>
    </aside>

  </div>
  <?php endif; ?>

</div>
</main>

<?php include __DIR__ . '/../partials/footer.php'; ?>
<script src="<?= $basePath ?>/assets/js/components/order-button.js"></script>
<script src="<?= $basePath ?>/assets/js/pages/cart.js"></script>
</body>
</html>