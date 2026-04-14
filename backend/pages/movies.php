<?php
// ══════════════════════════════════════════════════════════════════════════════
//  backend/pages/movies.php — Supinfo.TV v2
//  Catalogue films avec filtres avancés (tri, note, prix, année).
// ══════════════════════════════════════════════════════════════════════════════

session_start();

require_once __DIR__ . '/../config/tmdb.php';
require_once __DIR__ . '/../services/tmdb-service.php';
require_once __DIR__ . '/../services/auth.php';

auth_start_session();

$basePath = rtrim(str_replace('\\', '/', dirname(dirname(dirname($_SERVER['SCRIPT_NAME'])))), '/');

$genres = [
    ['id' => 28,  'label' => 'Action',          'icon' => '💥'],
    ['id' => 18,  'label' => 'Drame',           'icon' => '🎭'],
    ['id' => 35,  'label' => 'Comédie',         'icon' => '😄'],
    ['id' => 878, 'label' => 'Science-Fiction', 'icon' => '🚀'],
    ['id' => 27,  'label' => 'Horreur',         'icon' => '👻'],
    ['id' => 12,  'label' => 'Aventure',        'icon' => '🗺️'],
    ['id' => 16,  'label' => 'Animation',       'icon' => '✨'],
    ['id' => 10749,'label' => 'Romance',        'icon' => '❤️'],
    ['id' => 53,  'label' => 'Thriller',        'icon' => '😱'],
    ['id' => 99,  'label' => 'Documentaire',    'icon' => '📽️'],
];

$activeGenreId = filter_input(INPUT_GET, 'genre', FILTER_VALIDATE_INT) ?: 0;
$page          = max(1, (int)filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT));

// Paramètres de tri et filtres (pour la pagination)
$sortBy   = in_array($_GET['sort'] ?? '', ['popularity.desc','vote_average.desc','release_date.desc','revenue.desc']) ? $_GET['sort'] : 'popularity.desc';
$voteMin  = max(0, min(10, (float)($_GET['vote_min'] ?? 0)));
$yearMin  = max(1900, min((int)date('Y'), (int)($_GET['year_min'] ?? 1900)));

function tmdb_get_by_genre(int $genreId, int $page, string $sortBy, float $voteMin, int $yearMin): array
{
    $params = [
        'sort_by'              => $sortBy,
        'page'                 => $page,
        'vote_count.gte'       => 50, // Éviter les films sans votes
    ];

    if ($genreId > 0) $params['with_genres'] = $genreId;
    if ($voteMin > 0) $params['vote_average.gte'] = $voteMin;
    if ($yearMin > 1900) $params['primary_release_date.gte'] = $yearMin . '-01-01';

    $data = tmdb_get('/discover/movie', $params, 900); // Cache 15 min
    if (!$data || empty($data['results'])) return ['movies' => [], 'total_pages' => 1, 'total_results' => 0];

    return [
        'movies'        => tmdb_format_movies($data['results']),
        'total_pages'   => min((int)($data['total_pages'] ?? 1), 20),
        'total_results' => (int)($data['total_results'] ?? 0),
    ];
}

$result        = tmdb_get_by_genre($activeGenreId, $page, $sortBy, $voteMin, $yearMin);
$movies        = $result['movies'];
$totalPages    = $result['total_pages'];
$totalResults  = $result['total_results'];

// Synchroniser la watchlist en session pour les movie-cards
if (auth_check()) {
    require_once __DIR__ . '/../services/watchlist.php';
    $wlItems = watchlist_get();
    $watchlistIds = [];
    foreach ($wlItems as $w) $watchlistIds[$w['tmdb_id']] = true;
    $_SESSION['watchlist_ids'] = $watchlistIds;
}

$activeGenreLabel = 'Tous les films';
foreach ($genres as $g) {
    if ($g['id'] === $activeGenreId) {
        $activeGenreLabel = $g['label'];
        break;
    }
}

$sortLabels = [
    'popularity.desc'    => 'Popularité',
    'vote_average.desc'  => 'Mieux notés',
    'release_date.desc'  => 'Récents',
    'revenue.desc'       => 'Box-office',
];

$pageTitle  = 'Films';
$pageCSS    = 'pages/movies.css';
$pageDesc   = 'Explorez le catalogue Supinfo.TV — des milliers de films à découvrir et acheter.';
$activePage = 'movies';

include __DIR__ . '/../partials/head.php';
include __DIR__ . '/../partials/loader.php';
include __DIR__ . '/../partials/navbar.php';
?>

<link rel="stylesheet" href="<?= $basePath ?>/assets/css/components/advanced-filters.css">

<main>
<div class="container">

  <div class="movies-page-header">
    <div class="movies-page-eyebrow">Catalogue</div>
    <h1 class="movies-page-title"><?= htmlspecialchars($activeGenreLabel) ?></h1>
    <p class="movies-page-count" data-movies-count>
      <?= $totalResults > 0 ? number_format($totalResults, 0, ',', ' ') . ' films disponibles' : count($movies) . ' film' . (count($movies) > 1 ? 's' : '') . ' trouvé' . (count($movies) > 1 ? 's' : '') ?>
    </p>
  </div>

  <!-- ── Filtres genres ───────────────────────────────────────────────────── -->
  <div class="movies-filters">
    <a href="<?= $basePath ?>/backend/pages/movies.php"
       class="movies-filter-btn <?= $activeGenreId === 0 ? 'active' : '' ?>">
      Tous
    </a>
    <?php foreach ($genres as $g): ?>
    <a href="<?= $basePath ?>/backend/pages/movies.php?genre=<?= $g['id'] ?>&sort=<?= urlencode($sortBy) ?>"
       class="movies-filter-btn <?= $activeGenreId === $g['id'] ? 'active' : '' ?>">
      <?= $g['icon'] ?> <?= htmlspecialchars($g['label']) ?>
    </a>
    <?php endforeach; ?>
  </div>

  <!-- ── Filtres avancés ─────────────────────────────────────────────────── -->
  <div class="filters-bar">
    <span class="filters-bar-label">
      Filtrer
      <span class="filter-active-count" style="display:none;margin-left:4px;">0</span>
    </span>

    <!-- Tri -->
    <div class="filter-group">
      <label class="filter-group-label" for="filter-sort">Tri</label>
      <select id="filter-sort" class="filter-select">
        <option value="default">Par défaut</option>
        <option value="note-desc">Mieux notés d'abord</option>
        <option value="note-asc">Moins bien notés</option>
        <option value="price-asc">Prix croissant</option>
        <option value="price-desc">Prix décroissant</option>
        <option value="year-desc">Plus récents</option>
        <option value="year-asc">Plus anciens</option>
        <option value="title-asc">Titre A→Z</option>
      </select>
    </div>

    <!-- Note minimum -->
    <div class="filter-group">
      <label class="filter-group-label" for="filter-note">Note min.</label>
      <div class="filter-range-wrap">
        <input type="range" id="filter-note" class="filter-range"
               min="0" max="9" step="0.5" value="0">
        <span class="filter-range-value" id="filter-note-value">Toutes</span>
      </div>
    </div>

    <!-- Prix maximum -->
    <div class="filter-group">
      <label class="filter-group-label" for="filter-price">Prix max.</label>
      <div class="filter-range-wrap">
        <input type="range" id="filter-price" class="filter-range"
               min="5" max="25" step="0.5" value="25">
        <span class="filter-range-value" id="filter-price-value">Tous</span>
      </div>
    </div>

    <!-- Année minimum (côté serveur via reload) -->
    <div class="filter-group">
      <label class="filter-group-label" for="filter-year-select">Après</label>
      <select id="filter-year-select" class="filter-select"
              onchange="window.location.href='?genre=<?= $activeGenreId ?>&sort=<?= urlencode($sortBy) ?>&year_min='+this.value"
              style="min-width:80px;">
        <option value="1900" <?= $yearMin === 1900 ? 'selected' : '' ?>>Toutes</option>
        <?php for ($y = (int)date('Y'); $y >= 1980; $y -= 5): ?>
        <option value="<?= $y ?>" <?= $yearMin === $y ? 'selected' : '' ?>><?= $y ?></option>
        <?php endfor; ?>
      </select>
    </div>

    <!-- Tri côté serveur -->
    <div class="filter-group">
      <label class="filter-group-label" for="filter-server-sort">Ordre TMDB</label>
      <select id="filter-server-sort" class="filter-select"
              onchange="window.location.href='?genre=<?= $activeGenreId ?>&sort='+this.value+'&year_min=<?= $yearMin ?>&vote_min=<?= $voteMin ?>'">
        <?php foreach ($sortLabels as $val => $label): ?>
        <option value="<?= $val ?>" <?= $sortBy === $val ? 'selected' : '' ?>>
          <?= htmlspecialchars($label) ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>

    <button class="filter-reset" onclick="resetFilters()" disabled>
      <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <polyline points="1 4 1 10 7 10"/>
        <path d="M3.51 15a9 9 0 1 0 .49-3.51"/>
      </svg>
      Réinitialiser
    </button>
  </div>

  <!-- ── Grille films ─────────────────────────────────────────────────────── -->
  <?php if (!empty($movies)): ?>
  <div class="movies-grid">
    <?php foreach ($movies as $movie): ?>
      <?php include __DIR__ . '/../../frontend/partials/movie-card.php'; ?>
    <?php endforeach; ?>
  </div>
  <?php else: ?>
  <div class="movies-empty">
    <div class="movies-empty-icon">🎬</div>
    <p style="color:var(--text-muted);font-size:15px;margin-bottom:20px;">Aucun film disponible pour ces critères.</p>
    <a href="<?= $basePath ?>/backend/pages/movies.php" class="btn-more">Réinitialiser</a>
  </div>
  <?php endif; ?>

  <!-- ── Pagination ───────────────────────────────────────────────────────── -->
  <?php if ($totalPages > 1): ?>
  <div class="movies-pagination">

    <?php if ($page > 1): ?>
    <a href="?genre=<?= $activeGenreId ?>&sort=<?= urlencode($sortBy) ?>&year_min=<?= $yearMin ?>&page=<?= $page - 1 ?>" class="movies-page-link" title="Page précédente">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
        <path d="M15 18l-6-6 6-6"/>
      </svg>
    </a>
    <?php endif; ?>

    <?php
    // Pagination intelligente avec ellipses
    $range  = 2;
    $start  = max(1, $page - $range);
    $end    = min($totalPages, $page + $range);

    if ($start > 1): ?>
      <a href="?genre=<?= $activeGenreId ?>&sort=<?= urlencode($sortBy) ?>&year_min=<?= $yearMin ?>&page=1" class="movies-page-link">1</a>
      <?php if ($start > 2): ?><span style="color:var(--text-faint);padding:0 4px;">…</span><?php endif; ?>
    <?php endif; ?>

    <?php for ($i = $start; $i <= $end; $i++): ?>
    <a href="?genre=<?= $activeGenreId ?>&sort=<?= urlencode($sortBy) ?>&year_min=<?= $yearMin ?>&page=<?= $i ?>"
       class="movies-page-link <?= $i === $page ? 'active' : '' ?>">
      <?= $i ?>
    </a>
    <?php endfor; ?>

    <?php if ($end < $totalPages): ?>
      <?php if ($end < $totalPages - 1): ?><span style="color:var(--text-faint);padding:0 4px;">…</span><?php endif; ?>
      <a href="?genre=<?= $activeGenreId ?>&sort=<?= urlencode($sortBy) ?>&year_min=<?= $yearMin ?>&page=<?= $totalPages ?>" class="movies-page-link"><?= $totalPages ?></a>
    <?php endif; ?>

    <?php if ($page < $totalPages): ?>
    <a href="?genre=<?= $activeGenreId ?>&sort=<?= urlencode($sortBy) ?>&year_min=<?= $yearMin ?>&page=<?= $page + 1 ?>" class="movies-page-link" title="Page suivante">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
        <path d="M9 18l6-6-6-6"/>
      </svg>
    </a>
    <?php endif; ?>

  </div>
  <?php endif; ?>

</div>
</main>

<?php include __DIR__ . '/../partials/footer.php'; ?>
<script src="<?= $basePath ?>/assets/js/pages/movies.js"></script>
<script src="<?= $basePath ?>/assets/js/pages/movies-filters.js"></script>
</body>
</html>