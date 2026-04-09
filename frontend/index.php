<?php
session_start();

require_once __DIR__ . '/config/tmdb.php';
require_once __DIR__ . '/services/tmdb-service.php';

$pageTitle  = 'Accueil';
$pageCSS    = 'pages/home.css';
$pageDesc   = 'Supinfo.TV — Achetez et découvrez des films et séries en ligne.';
$activePage = 'home';

$lastPurchasedMovieId = $_SESSION['last_purchased_movie_id'] ?? null;

$mainSection   = tmdb_get_recommendations($lastPurchasedMovieId, 12);
$featuredId    = $mainSection['movies'][0]['id'] ?? null;
$featuredMovie = $featuredId ? tmdb_get_movie_detail($featuredId) : null;
$nowPlaying    = tmdb_get_now_playing(12);

// Chemin de base dynamique (compatible XAMPP quel que soit le nom du dossier projet)
$basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');

include __DIR__ . '/partials/head.php';
include __DIR__ . '/partials/loader.php';
include __DIR__ . '/partials/navbar.php';
?>

<main style="padding-top:0;">

<?php if ($featuredMovie): ?>
<section class="hero" aria-label="Film à la une">

  <?php if (!empty($featuredMovie['backdrop'])): ?>
  <img
    src="<?= htmlspecialchars($featuredMovie['backdrop']) ?>"
    class="hero-bg"
    alt=""
    role="presentation"
  >
  <?php endif; ?>
  <div class="hero-overlay"></div>

  <div class="hero-content">

    <div class="hero-eyebrow">
      <div class="hero-eyebrow-dot"></div>
      <span class="hero-eyebrow-text">
        <?= $mainSection['based_on'] ? 'Recommandé pour vous' : 'Tendance cette semaine' ?>
      </span>
    </div>

    <h1 class="hero-title"><?= htmlspecialchars($featuredMovie['title']) ?></h1>

    <div class="hero-meta-row">
      <?php if (!empty($featuredMovie['year'])): ?>
        <span class="hero-tag"><?= htmlspecialchars($featuredMovie['year']) ?></span>
      <?php endif; ?>
      <?php if (!empty($featuredMovie['genre'])): ?>
        <div class="hero-sep"></div>
        <span class="hero-tag"><?= htmlspecialchars($featuredMovie['genre']) ?></span>
      <?php endif; ?>
      <?php if (!empty($featuredMovie['note'])): ?>
        <div class="hero-sep"></div>
        <span class="hero-note">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="var(--gold)" stroke="none">
            <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
          </svg>
          <?= number_format((float)$featuredMovie['note'], 1) ?>/10
        </span>
      <?php endif; ?>
      <?php if (!empty($featuredMovie['duration'])): ?>
        <div class="hero-sep"></div>
        <span class="hero-tag"><?= (int)$featuredMovie['duration'] ?> min</span>
      <?php endif; ?>
      <?php if (!empty($featuredMovie['director'])): ?>
        <div class="hero-sep"></div>
        <span style="font-size:13px;color:var(--text-muted);">
          Réal.&nbsp;<strong style="color:var(--text);font-weight:500;"><?= htmlspecialchars($featuredMovie['director']) ?></strong>
        </span>
      <?php endif; ?>
    </div>

    <?php if (!empty($featuredMovie['synopsis'])): ?>
    <p class="hero-synopsis"><?= htmlspecialchars($featuredMovie['synopsis']) ?></p>
    <?php endif; ?>

    <?php if (!empty($featuredMovie['price'])): ?>
    <div class="hero-price-row">
      <span class="hero-price-unit">
        <?= number_format((float)$featuredMovie['price']['unit'], 2, ',', '') ?>€
      </span>
      <?php if (!empty($featuredMovie['price']['bundle'])): ?>
      <span class="hero-price-bundle"><?= htmlspecialchars($featuredMovie['price']['label']) ?></span>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="hero-actions">
      <a href="<?= $basePath ?>/pages/movie-detail.php?id=<?= (int)$featuredMovie['id'] ?>" class="btn-primary">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="12" cy="12" r="10"/><polygon points="10 8 16 12 10 16 10 8"/>
        </svg>
        Voir le film
      </a>
      <?php if (!empty($featuredMovie['trailer_key'])): ?>
      <button class="btn-ghost" onclick="openTrailer('<?= htmlspecialchars($featuredMovie['trailer_key']) ?>')">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
          <polygon points="5 3 19 12 5 21 5 3"/>
        </svg>
        Bande-annonce
      </button>
      <?php endif; ?>
    </div>

    <?php if (!empty($featuredMovie['cast'])): ?>
    <div class="hero-cast">
      <span class="hero-cast-label">Avec</span>
      <?php foreach ($featuredMovie['cast'] as $i => $actor): ?>
        <?php if ($i > 0): ?><span class="hero-cast-sep">·</span><?php endif; ?>
        <span class="hero-cast-name"><?= htmlspecialchars($actor['name']) ?></span>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

  </div>
</section>
<?php endif; ?>

<div class="container home-sections">

  <section class="section-cat" id="section-main">
    <div class="section-header">
      <div class="section-header-left">
        <div class="section-label"><?= $mainSection['based_on'] ? 'Pour vous' : 'Cette semaine' ?></div>
        <h2 class="section-title"><?= htmlspecialchars($mainSection['label']) ?></h2>
        <p class="section-subtitle"><?= htmlspecialchars($mainSection['subtitle']) ?></p>
      </div>
      <a href="<?= $basePath ?>/pages/movies.php" class="btn-more">
        Voir plus
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <path d="M5 12h14M12 5l7 7-7 7"/>
        </svg>
      </a>
    </div>

    <?php if (!empty($mainSection['movies'])): ?>
    <div class="carousel-wrap">
      <button class="carousel-btn prev" aria-label="Précédent">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <path d="M15 18l-6-6 6-6"/>
        </svg>
      </button>
      <div class="carousel-track">
        <?php foreach ($mainSection['movies'] as $movie): ?>
          <?php include __DIR__ . '/partials/movie-card.php'; ?>
        <?php endforeach; ?>
      </div>
      <button class="carousel-btn next" aria-label="Suivant">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <path d="M9 18l6-6-6-6"/>
        </svg>
      </button>
    </div>
    <?php else: ?>
    <p style="color:var(--text-muted);font-size:14px;">Aucun film disponible.</p>
    <?php endif; ?>
  </section>

  <?php if (!empty($nowPlaying)): ?>
  <section class="section-cat" id="section-sorties">
    <div class="section-header">
      <div class="section-header-left">
        <div class="section-label">Au cinéma</div>
        <h2 class="section-title">Sorties récentes</h2>
        <p class="section-subtitle">Films actuellement en salle</p>
      </div>
      <a href="<?= $basePath ?>/pages/movies.php" class="btn-more">
        Voir plus
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <path d="M5 12h14M12 5l7 7-7 7"/>
        </svg>
      </a>
    </div>
    <div class="carousel-wrap">
      <button class="carousel-btn prev" aria-label="Précédent">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <path d="M15 18l-6-6 6-6"/>
        </svg>
      </button>
      <div class="carousel-track">
        <?php foreach ($nowPlaying as $movie): ?>
          <?php include __DIR__ . '/partials/movie-card.php'; ?>
        <?php endforeach; ?>
      </div>
      <button class="carousel-btn next" aria-label="Suivant">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <path d="M9 18l6-6-6-6"/>
        </svg>
      </button>
    </div>
  </section>
  <?php endif; ?>

</div>

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

<?php include __DIR__ . '/partials/footer.php'; ?>

<script src="<?= $basePath ?>/assets/js/components/loader.js"></script>
<script src="<?= $basePath ?>/assets/js/components/navbar.js"></script>
<script src="<?= $basePath ?>/assets/js/pages/home.js"></script>

</body>
</html>