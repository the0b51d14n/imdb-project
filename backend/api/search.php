<?php
// ══════════════════════════════════════════════════════════════════════════════
//  backend/api/search.php — Supinfo.TV
//  Endpoint JSON pour la recherche instantanée / autocomplete.
//  Retourne suggestions de titres + réalisateurs en < 200ms (avec cache).
// ══════════════════════════════════════════════════════════════════════════════

require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../config/tmdb.php';
require_once __DIR__ . '/../services/tmdb-service.php';
require_once __DIR__ . '/../services/tmdb-cache.php';

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=60');

$q    = trim($_GET['q']    ?? '');
$type = in_array($_GET['type'] ?? 'all', ['movie', 'person', 'all']) ? ($_GET['type'] ?? 'all') : 'all';

if (strlen($q) < 2) {
    echo json_encode(['ok' => true, 'results' => []]);
    exit;
}

// Limite stricte pour l'autocomplete — vitesse avant tout
$limit = min((int)($_GET['limit'] ?? 6), 10);

$results = [];
$cacheKey = 'search_auto_' . md5($q . '_' . $type);

// Essayer le cache d'abord
$cached = tmdb_cache_get($cacheKey);
if ($cached !== null) {
    echo json_encode(['ok' => true, 'results' => $cached, 'cached' => true]);
    exit;
}

// Films
if ($type === 'movie' || $type === 'all') {
    $data = tmdb_get('/search/movie', [
        'query'          => $q,
        'page'           => 1,
        'include_adult'  => 'false',
    ]);

    if ($data && !empty($data['results'])) {
        foreach (array_slice($data['results'], 0, $type === 'all' ? 4 : $limit) as $m) {
            if (empty($m['title'])) continue;
            $results[] = [
                'type'   => 'movie',
                'id'     => $m['id'],
                'label'  => $m['title'],
                'year'   => substr($m['release_date'] ?? '', 0, 4),
                'poster' => !empty($m['poster_path'])
                    ? tmdb_image_url($m['poster_path'], 'w92')
                    : null,
                'note'   => round((float)($m['vote_average'] ?? 0), 1),
                'url'    => '/backend/pages/movie-detail.php?id=' . $m['id'],
            ];
        }
    }
}

// Réalisateurs / personnes
if ($type === 'person' || $type === 'all') {
    $pData = tmdb_get('/search/person', [
        'query' => $q,
        'page'  => 1,
    ]);

    if ($pData && !empty($pData['results'])) {
        $directors = array_filter(
            $pData['results'],
            fn($p) => ($p['known_for_department'] ?? '') === 'Directing'
        );

        foreach (array_slice(array_values($directors), 0, $type === 'all' ? 2 : $limit) as $p) {
            $results[] = [
                'type'   => 'director',
                'id'     => $p['id'],
                'label'  => $p['name'],
                'year'   => null,
                'poster' => !empty($p['profile_path'])
                    ? tmdb_image_url($p['profile_path'], 'w92')
                    : null,
                'note'   => null,
                'url'    => '/backend/pages/director.php?name=' . urlencode($p['name']),
            ];
        }
    }
}

// Trier par popularité implicite (les films TMDB sont déjà triés par pertinence)
$results = array_slice($results, 0, $limit);

// Mettre en cache 5 minutes
tmdb_cache_set($cacheKey, $results, 300);

echo json_encode([
    'ok'      => true,
    'results' => $results,
    'query'   => $q,
]);
exit;
