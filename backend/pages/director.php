<?php

session_start();
 
require_once __DIR__ . '/../config/tmdb.php';
require_once __DIR__ . '/../services/tmdb-service.php';
require_once __DIR__ . '/../services/auth.php';
 
auth_start_session();
 
$basePath    = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME']))), '/');
$directorName = trim($_GET['name'] ?? '');
 
if ($directorName === '') {
    header('Location: ' . $basePath . '/pages/movies.php');
    exit;
}
 
$movies      = [];
$directorInfo = null; 

$personSearch = tmdb_get('/search/person', ['query' => $directorName]);
if (!empty($personSearch['results'])) {
    $person = $personSearch['results'][0];
 
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