<?php

session_start();

require_once __DIR__ . '/../config/tmdb.php';
require_once __DIR__ . '/../services/tmdb-service.php';
require_once __DIR__ . '/../services/auth.php';

auth_start_session();

// Correction : basePath pointe vers la racine (pas /backend)
$basePath = rtrim(str_replace('\\', '/', dirname(dirname(dirname($_SERVER['SCRIPT_NAME'])))), '/');

$genres = [
    ['id' => 28,  'label' => 'Action',          'icon' => '💥'],
    ['id' => 18,  'label' => 'Drame',           'icon' => '🎭'],
    ['id' => 35,  'label' => 'Comédie',         'icon' => '😄'],
    ['id' => 878, 'label' => 'Science-Fiction', 'icon' => '🚀'],
    ['id' => 27,  'label' => 'Horreur',         'icon' => '👻'],
];

$activeGenreId = filter_input(INPUT_GET, 'genre', FILTER_VALIDATE_INT) ?: 0;
$page          = max(1, (int)filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT));

function tmdb_get_by_genre(int $genreId, int $page = 1): array
{
    $params = ['sort_by' => 'popularity.desc', 'page' => $page];
    if ($genreId > 0) {
        $params['with_genres'] = $genreId;
    }
    $data = tmdb_get('/discover/movie', $params);
    if (!$data || empty($data['results'])) return ['movies' => [], 'total_pages' => 1];

    return [
        'movies'      => tmdb_format_movies($data['results']),
        'total_pages' => min((int)($data['total_pages'] ?? 1), 20),
    ];
}

$result     = tmdb_get_by_genre($activeGenreId, $page);
$movies     = $result['movies'];
$totalPages = $result['total_pages'];

$activeGenreLabel = 'Tous les films';
foreach ($genres as $g) {
    if ($g['id'] === $activeGenreId) {
        $activeGenreLabel = $g['label'];
        break;
    }
}

$pageTitle  = 'Films';
$pageCSS    = 'pages/movies.css';
$pageDesc   = 'Explorez le catalogue Supinfo.TV — des milliers de films à découvrir et acheter.';
$activePage = 'movies';

include __DIR__ . '/../partials/head.php';
include __DIR__ . '/../partials/loader.php';
include __DIR__ . '/../partials/navbar.php';
?>

<main>
<div class="container">

  <div class="movies-page-header">
    <div class="movies-page-eyebrow">Catalogue</div>
    <h1 class="movies-page-title"><?= htmlspecialchars($activeGenreLabel) ?></h1>
    <p class="movies-page-count"><?= count($movies) ?> film<?= count($movies) > 1 ? 's' : '' ?> trouvé<?= count($movies) > 1 ? 's' : '' ?></p>
  </div>

  <!-- Filtres genres -->
  <div class="movies-filters">
    <a href="<?= $basePath ?>/pages/movies.php"
       class="movies-filter-btn <?= $activeGenreId === 0 ? 'active' : '' ?>">
      Tous
    </a>
    <?php foreach ($genres as $g): ?>
    <a href="<?= $basePath ?>/pages/movies.php?genre=<?= $g['id'] ?>"
       class="movies-filter-btn <?= $activeGenreId === $g['id'] ? 'active' : '' ?>">
      <?= $g['icon'] ?> <?= htmlspecialchars($g['label']) ?>
    </a>
    <?php endforeach; ?>
  </div>

  <!-- Grille films -->
  <?php if (!empty($movies)): ?>
  <div class="movies-grid">
    <?php foreach ($movies as $movie): ?>
      <?php include __DIR__ . '/../../frontend/partials/movie-card.php'; ?>
    <?php endforeach; ?>
  </div>
  <?php else: ?>
  <div class="movies-empty">
    <div class="movies-empty-icon">🎬</div>
    <p style="color:var(--text-muted);font-size:15px;">Aucun film disponible pour ce filtre.</p>
  </div>
  <?php endif; ?>

  <!-- Pagination -->
  <?php if ($totalPages > 1): ?>
  <div class="movies-pagination">
    <?php if ($page > 1): ?>
    <a href="?genre=<?= $activeGenreId ?>&page=<?= $page - 1 ?>" class="movies-page-link">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
        <path d="M15 18l-6-6 6-6"/>
      </svg>
    </a>
    <?php endif; ?>

    <?php
    $start = max(1, $page - 2);
    $end   = min($totalPages, $page + 2);
    for ($i = $start; $i <= $end; $i++):
    ?>
    <a href="?genre=<?= $activeGenreId ?>&page=<?= $i ?>"
       class="movies-page-link <?= $i === $page ? 'active' : '' ?>">
      <?= $i ?>
    </a>
    <?php endfor; ?>

    <?php if ($page < $totalPages): ?>
    <a href="?genre=<?= $activeGenreId ?>&page=<?= $page + 1 ?>" class="movies-page-link">
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
</body>
</html>