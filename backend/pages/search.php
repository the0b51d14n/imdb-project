<?php

session_start();

require_once __DIR__ . '/../config/tmdb.php';
require_once __DIR__ . '/../services/tmdb-service.php';
require_once __DIR__ . '/../services/auth.php';

auth_start_session();

// Correction : basePath pointe vers la racine
$basePath = rtrim(str_replace('\\', '/', dirname(dirname(dirname($_SERVER['SCRIPT_NAME'])))), '/');

$query  = trim($_GET['q']    ?? '');
$type   = in_array($_GET['type'] ?? '', ['title', 'director']) ? $_GET['type'] : 'title';
$page   = max(1, (int)($_GET['page'] ?? 1));

$movies     = [];
$totalPages = 0;
$searched   = false;

if ($query !== '') {
    $searched = true;

    if ($type === 'director') {
        $personData = tmdb_get('/search/person', ['query' => $query, 'page' => 1]);
        $movies = [];

        if (!empty($personData['results'])) {
            $personId   = $personData['results'][0]['id']   ?? null;
            $personName = $personData['results'][0]['name'] ?? $query;

            if ($personId) {
                $data = tmdb_get('/discover/movie', [
                    'with_crew' => $personId,
                    'sort_by'   => 'popularity.desc',
                    'page'      => $page,
                ]);
                if ($data && !empty($data['results'])) {
                    $movies     = tmdb_format_movies($data['results']);
                    $totalPages = min((int)($data['total_pages'] ?? 1), 10);
                }
            }
        }
    } else {
        $data = tmdb_get('/search/movie', ['query' => $query, 'page' => $page, 'include_adult' => 'false']);
        if ($data && !empty($data['results'])) {
            $movies     = tmdb_format_movies($data['results']);
            $totalPages = min((int)($data['total_pages'] ?? 1), 10);
        }
    }
}

$pageTitle  = $query ? 'Recherche : ' . htmlspecialchars($query) : 'Recherche';
$pageCSS    = 'pages/search.css';
$pageDesc   = 'Recherchez des films par titre ou réalisateur sur Supinfo.TV.';
$activePage = 'search';

include __DIR__ . '/../partials/head.php';
include __DIR__ . '/../partials/loader.php';
include __DIR__ . '/../partials/navbar.php';
?>

<main>
<div class="container search-page">

  <div class="search-header">
    <div class="search-eyebrow">Catalogue</div>
    <h1 class="search-title">Recherche</h1>

    <form method="GET" action="" class="search-form">
      <div class="search-input-wrap">
        <svg class="search-input-icon" width="16" height="16" viewBox="0 0 24 24"
             fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
        </svg>
        <input type="text" name="q" value="<?= htmlspecialchars($query) ?>"
               placeholder="Titre, réalisateur…" class="search-input" autocomplete="off">
      </div>

      <div class="search-type-toggle">
        <label class="search-type-label <?= $type === 'title' ? 'active' : '' ?>">
          <input type="radio" name="type" value="title" <?= $type === 'title' ? 'checked' : '' ?>>
          Titre
        </label>
        <label class="search-type-label <?= $type === 'director' ? 'active' : '' ?>">
          <input type="radio" name="type" value="director" <?= $type === 'director' ? 'checked' : '' ?>>
          Réalisateur
        </label>
      </div>

      <button type="submit" class="btn-primary">Rechercher</button>
    </form>
  </div>

  <?php if ($searched): ?>
    <?php if (!empty($movies)): ?>
    <p class="search-results-meta">
      <?= count($movies) ?> résultat<?= count($movies) > 1 ? 's' : '' ?>
      pour &laquo;&nbsp;<?= htmlspecialchars($query) ?>&nbsp;&raquo;
      <?php if ($type === 'director'): ?>
        — filmographie du réalisateur
      <?php endif; ?>
    </p>

    <div class="search-results-grid">
      <?php foreach ($movies as $movie): ?>
        <?php include __DIR__ . '/../../frontend/partials/movie-card.php'; ?>
      <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="search-pagination">
      <?php if ($page > 1): ?>
      <a href="?q=<?= urlencode($query) ?>&type=<?= $type ?>&page=<?= $page - 1 ?>" class="search-page-link">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <path d="M15 18l-6-6 6-6"/>
        </svg>
      </a>
      <?php endif; ?>
      <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
      <a href="?q=<?= urlencode($query) ?>&type=<?= $type ?>&page=<?= $i ?>"
         class="search-page-link <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
      <?php endfor; ?>
      <?php if ($page < $totalPages): ?>
      <a href="?q=<?= urlencode($query) ?>&type=<?= $type ?>&page=<?= $page + 1 ?>" class="search-page-link">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <path d="M9 18l6-6-6-6"/>
        </svg>
      </a>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php else: ?>
    <div class="search-empty">
      <div class="search-empty-icon">🔍</div>
      <h2 class="search-empty-title">Aucun résultat</h2>
      <p class="search-empty-text">
        Aucun film trouvé pour &laquo;&nbsp;<?= htmlspecialchars($query) ?>&nbsp;&raquo;.
        Essayez un autre terme ou changez le type de recherche.
      </p>
    </div>
    <?php endif; ?>

  <?php else: ?>
  <div class="search-initial">
    <div class="search-initial-icon">🎬</div>
    <p class="search-initial-text">
      Recherchez parmi des milliers de films par titre ou par réalisateur.
    </p>
  </div>
  <?php endif; ?>

</div>
</main>

<?php include __DIR__ . '/../partials/footer.php'; ?>
<script src="<?= $basePath ?>/assets/js/pages/search.js"></script>
</body>
</html>