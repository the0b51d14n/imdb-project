<?php

session_start();
 
require_once __DIR__ . '/../config/tmdb.php';
require_once __DIR__ . '/../services/tmdb-service.php';
require_once __DIR__ . '/../services/auth.php';
 
auth_start_session();
 
$basePath = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME']))), '/');

$query    = trim($_GET['q']    ?? '');
$type     = in_array($_GET['type'] ?? '', ['title', 'director']) ? $_GET['type'] : 'title';
$page     = max(1, (int)($_GET['page'] ?? 1));
 
$movies     = [];
$totalPages = 0;
$searched   = false;
 
if ($query !== '') {
    $searched = true;
 
    if ($type === 'director') {
        
        $personData = tmdb_get('/search/person', ['query' => $query, 'page' => 1]);
        $movies = [];
 
        if (!empty($personData['results'])) {
            
            $personId   = $personData['results'][0]['id'] ?? null;
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