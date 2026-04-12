<?php

session_start();

require_once __DIR__ . '/../config/tmdb.php';
require_once __DIR__ . '/../services/tmdb-service.php';
require_once __DIR__ . '/../services/auth.php';

auth_start_session();

// Correction : basePath pointe vers la racine du projet, pas /backend
$basePath     = rtrim(str_replace('\\', '/', dirname(dirname(dirname($_SERVER['SCRIPT_NAME'])))), '/');
$directorName = trim($_GET['name'] ?? '');

if ($directorName === '') {
    header('Location: ' . $basePath . '/pages/movies.php');
    exit;
}

$movies       = [];
$directorInfo = null;

$personSearch = tmdb_get('/search/person', ['query' => $directorName]);
if (!empty($personSearch['results'])) {
    $person  = $personSearch['results'][0];
    $credits = tmdb_get('/person/' . $person['id'] . '/movie_credits');

    if ($credits) {
        $directed = array_filter($credits['crew'] ?? [], fn($c) => $c['job'] === 'Director');
        usort($directed, fn($a, $b) => ($b['popularity'] ?? 0) <=> ($a['popularity'] ?? 0));
        $movies = tmdb_format_movies(array_slice(array_values($directed), 0, 24));
    }

    $directorInfo = [
        'id'         => $person['id'],
        'name'       => $person['name'],
        'photo'      => !empty($person['profile_path'])
            ? tmdb_image_url($person['profile_path'], 'w185')
            : null,
        'known_for'  => $person['known_for_department'] ?? 'Réalisation',
        'popularity' => $person['popularity'] ?? 0,
    ];
}

$safeName = htmlspecialchars($directorName);

$pageTitle  = $directorInfo ? $directorInfo['name'] : $safeName;
$pageCSS    = 'pages/movies.css';
$pageDesc   = "Découvrez tous les films réalisés par {$safeName} sur Supinfo.TV.";
$activePage = 'movies';

include __DIR__ . '/../partials/head.php';
include __DIR__ . '/../partials/loader.php';
include __DIR__ . '/../partials/navbar.php';
?>

<main>
<div class="container" style="padding-top:48px;padding-bottom:80px;">

  <?php if ($directorInfo): ?>
  <div style="display:flex;align-items:center;gap:24px;margin-bottom:48px;flex-wrap:wrap;">
    <?php if ($directorInfo['photo']): ?>
    <img src="<?= htmlspecialchars($directorInfo['photo']) ?>"
         alt="<?= htmlspecialchars($directorInfo['name']) ?>"
         style="width:80px;height:80px;border-radius:50%;object-fit:cover;
                border:2px solid var(--border-bright);box-shadow:0 0 24px var(--accent-glow);">
    <?php else: ?>
    <div style="width:80px;height:80px;border-radius:50%;background:var(--surface-2);
                border:2px solid var(--border);display:flex;align-items:center;
                justify-content:center;font-size:28px;">🎬</div>
    <?php endif; ?>
    <div>
      <div style="font-size:10px;font-weight:500;letter-spacing:0.12em;text-transform:uppercase;
                  color:var(--accent);margin-bottom:6px;">Réalisateur</div>
      <h1 style="font-size:clamp(24px,4vw,36px);font-weight:500;color:var(--text);
                 letter-spacing:-0.02em;margin-bottom:4px;">
        <?= htmlspecialchars($directorInfo['name']) ?>
      </h1>
      <p style="font-size:13px;color:var(--text-muted);margin:0;">
        <?= count($movies) ?> film<?= count($movies) > 1 ? 's' : '' ?> réalisé<?= count($movies) > 1 ? 's' : '' ?>
      </p>
    </div>
  </div>
  <?php else: ?>
  <div style="margin-bottom:40px;">
    <h1 style="font-size:28px;font-weight:500;color:var(--text);">
      Filmographie de <?= $safeName ?>
    </h1>
  </div>
  <?php endif; ?>

  <?php if (!empty($movies)): ?>
  <div class="movies-grid">
    <?php foreach ($movies as $movie): ?>
      <?php include __DIR__ . '/../../frontend/partials/movie-card.php'; ?>
    <?php endforeach; ?>
  </div>
  <?php else: ?>
  <div class="movies-empty">
    <div class="movies-empty-icon">🎬</div>
    <p style="color:var(--text-muted);font-size:15px;">Aucun film trouvé pour ce réalisateur.</p>
    <a href="<?= $basePath ?>/pages/movies.php" class="btn-primary" style="margin-top:20px;display:inline-flex;">
      Retour au catalogue
    </a>
  </div>
  <?php endif; ?>

</div>
</main>

<?php include __DIR__ . '/../partials/footer.php'; ?>
<script src="<?= $basePath ?>/assets/js/pages/movies.js"></script>
</body>
</html>