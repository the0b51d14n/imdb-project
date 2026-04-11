<?php

session_start();
 
require_once __DIR__ . '/../config/tmdb.php';
require_once __DIR__ . '/../services/tmdb-service.php';
require_once __DIR__ . '/../services/auth.php';
 
auth_start_session();
 
$basePath = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME']))), '/');
 
$genres = [
    ['id' => 28,  'label' => 'Action',         'icon' => '💥'],
    ['id' => 18,  'label' => 'Drame',          'icon' => '🎭'],
    ['id' => 35,  'label' => 'Comédie',        'icon' => '😄'],
    ['id' => 878, 'label' => 'Science-Fiction','icon' => '🚀'],
    ['id' => 27,  'label' => 'Horreur',        'icon' => '👻'],
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
        'total_pages' => min((int)($data['total_pages'] ?? 1), 20), // max 20 pages
    ];
}
 
$result      = tmdb_get_by_genre($activeGenreId, $page);
$movies      = $result['movies'];
$totalPages  = $result['total_pages'];
 
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