<?php

session_start();
 
require_once __DIR__ . '/../config/tmdb.php';
require_once __DIR__ . '/../services/tmdb-service.php';
require_once __DIR__ . '/../services/auth.php';
require_once __DIR__ . '/../services/cart.php';
require_once __DIR__ . '/../services/orders.php';
require_once __DIR__ . '/../services/csrf.php';
 
auth_start_session();
 
$basePath = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME']))), '/');

$movieId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$movieId || $movieId <= 0) {
    header('Location: ' . $basePath . '/pages/movies.php');
    exit;
}
 
$movie = tmdb_get_movie_detail($movieId);
if (!$movie) {
    http_response_code(404);
    $pageTitle = 'Film introuvable';
    include __DIR__ . '/../partials/head.php';
    echo '<main><div class="container" style="padding:80px 0;text-align:center;">
      <h1 style="font-size:32px;margin-bottom:12px;">Film introuvable</h1>
      <p style="color:var(--text-muted);">Ce film n\'existe pas ou n\'est plus disponible.</p>
      <a href="'.$basePath.'/pages/movies.php" class="btn-primary" style="margin-top:24px;display:inline-flex;">Retour au catalogue</a>
      </div></main>';
    include __DIR__ . '/../partials/footer.php';
    echo '</body></html>';
    exit;
}

$cartMsg   = null;
$cartError = null;
 
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_to_cart') {
    if (!auth_check()) {
        
        header('Location: ' . $basePath . '/pages/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
    csrf_verify();
 
    $r = cart_add(
        $movie['id'],
        $movie['title'],
        $movie['poster'],
        $movie['price']['unit']
    );
 
    if ($r['ok']) {
        $cartMsg = 'Film ajouté au panier !';
    } else {
        $cartError = $r['error'];
    }
}
 

$inCart    = auth_check() && cart_has($movie['id']);
$purchased = auth_check() && orders_has_purchased($movie['id']);
 
$recommended = [];
$recData = tmdb_get('/movie/' . $movie['id'] . '/recommendations', ['page' => 1]);
if ($recData && !empty($recData['results'])) {
    $recommended = tmdb_format_movies(array_slice($recData['results'], 0, 6));
}
 
$pageTitle  = $movie['title'];
$pageCSS    = 'pages/movie-detail.css';
$pageDesc   = mb_substr($movie['synopsis'] ?? ('Découvrez ' . $movie['title'] . ' sur Supinfo.TV.'), 0, 155);
$activePage = 'movies';
 
include __DIR__ . '/../partials/head.php';
include __DIR__ . '/../partials/loader.php';
include __DIR__ . '/../partials/navbar.php';

?>