<?php

require_once __DIR__ . '/../config/tmdb.php';

/**
 * Effectue une requête GET vers l'API TMDB.
 * C'est la fonction de base utilisée par toutes les autres.
 *
 * @param  string $endpoint   Ex: '/movie/popular'
 * @param  array  $params     Paramètres GET supplémentaires
 * @return array|null         Données JSON décodées, ou null en cas d'erreur
 */
function tmdb_get(string $endpoint, array $params = []): ?array
{
    $params['api_key']  = TMDB_API_KEY;
    $params['language'] = TMDB_LANG;
    $url = TMDB_BASE_URL . $endpoint . '?' . http_build_query($params);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,           // 10 secondes max
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Vérification : réponse valide ?
    if ($response === false || $httpCode !== 200) {
        error_log("TMDB API Error [{$httpCode}] on {$endpoint}");
        return null;
    }

    return json_decode($response, true);
}

/**
 * Construit l'URL complète d'une image TMDB.
 *
 * @param  string|null $path   Chemin retourné par TMDB (ex: '/abc123.jpg')
 * @param  string      $size   Taille (voir config : TMDB_POSTER_SIZE, etc.)
 * @return string              URL complète ou chaîne vide si pas d'image
 */
function tmdb_image_url(?string $path, string $size = TMDB_POSTER_SIZE): string
{
    if (empty($path)) return '';
    return TMDB_IMAGE_BASE . $size . $path;
}


/**
 * Récupère les films tendance (semaine en cours).
 * Utilisé pour la section "Tendances du moment".
 *
 * @param  int $limit  Nombre de films à retourner (max 20 par page TMDB)
 * @return array       Tableau de films formatés
 */
function tmdb_get_trending(int $limit = 10): array
{
    $data = tmdb_get('/trending/movie/week');
    if (!$data || empty($data['results'])) return [];

    return tmdb_format_movies(array_slice($data['results'], 0, $limit));
}

/**
 * Récupère les films les plus populaires du moment.
 *
 * @param  int $limit
 * @return array
 */
function tmdb_get_popular(int $limit = 10): array
{
    $data = tmdb_get('/movie/popular');
    if (!$data || empty($data['results'])) return [];

    return tmdb_format_movies(array_slice($data['results'], 0, $limit));
}

/**
 * Récupère les sorties récentes (now_playing = en salle actuellement).
 *
 * @param  int $limit
 * @return array
 */
function tmdb_get_now_playing(int $limit = 10): array
{
    $data = tmdb_get('/movie/now_playing');
    if (!$data || empty($data['results'])) return [];

    return tmdb_format_movies(array_slice($data['results'], 0, $limit));
}

/**
 * Récupère les détails complets d'un film par son ID TMDB.
 * Inclut : infos générales + crédits (acteurs/réalisateur) + vidéos (bandes-annonces).
 *
 * @param  int $movieId  ID TMDB du film
 * @return array|null    Film formaté complet, ou null si introuvable
 */
function tmdb_get_movie_detail(int $movieId): ?array
{
    // On utilise append_to_response pour tout récupérer en 1 seul appel API
    $data = tmdb_get("/movie/{$movieId}", [
        'append_to_response' => 'credits,videos,belongs_to_collection',
    ]);

    if (!$data) return null;

    $cast = $data['credits']['cast'] ?? [];

    $priceData = tmdb_calculate_price($data, 'movie', $cast);


    $trailer = tmdb_extract_trailer($data['videos']['results'] ?? []);

    return [
        'id'          => $data['id'],
        'title'       => $data['title'],
        'synopsis'    => $data['overview'],
        'poster'      => tmdb_image_url($data['poster_path']),
        'backdrop'    => tmdb_image_url($data['backdrop_path'], TMDB_BACKDROP_SIZE),
        'year'        => substr($data['release_date'] ?? '', 0, 4),
        'genre'       => implode(', ', array_column(array_slice($data['genres'] ?? [], 0, 2), 'name')),
        'note'        => round((float)($data['vote_average'] ?? 0), 1),
        'duration'    => $data['runtime'] ?? null,
        'director'    => tmdb_extract_director($cast, $data['credits']['crew'] ?? []),
        'cast'        => array_slice($cast, 0, 5),
        'trailer_key' => $trailer,
        'collection'  => $data['belongs_to_collection'] ?? null,
        'price'       => $priceData,
    ];
}

/**
 * Formate un tableau de films bruts TMDB en tableau simplifié.
 * Utilisé pour les listes (home, catalogue).
 *
 * @param  array $movies  Résultats bruts de TMDB
 * @return array          Films formatés
 */
function tmdb_format_movies(array $movies): array
{
    $formatted = [];
    foreach ($movies as $m) {
        $priceData = tmdb_calculate_price($m, 'movie');
        $formatted[] = [
            'id'      => $m['id'],
            'title'   => $m['title'] ?? $m['name'] ?? 'Titre inconnu',
            'poster'  => tmdb_image_url($m['poster_path'] ?? null),
            'backdrop'=> tmdb_image_url($m['backdrop_path'] ?? null, TMDB_BACKDROP_SIZE),
            'year'    => substr($m['release_date'] ?? $m['first_air_date'] ?? '', 0, 4),
            'genre'   => '',   // non disponible en mode liste (économie d'appels API)
            'note'    => round((float)($m['vote_average'] ?? 0), 1),
            'price'   => $priceData,
        ];
    }
    return $formatted;
}


/**
 * Récupère les séries tendance de la semaine.
 *
 * @param  int $limit
 * @return array
 */
function tmdb_get_trending_tv(int $limit = 10): array
{
    $data = tmdb_get('/trending/tv/week');
    if (!$data || empty($data['results'])) return [];

    return tmdb_format_tv(array_slice($data['results'], 0, $limit));
}

/**
 * Formate un tableau de séries brutes TMDB.
 *
 * @param  array $shows
 * @return array
 */
function tmdb_format_tv(array $shows): array
{
    $formatted = [];
    foreach ($shows as $s) {
        $priceData = tmdb_calculate_price($s, 'tv');
        $formatted[] = [
            'id'      => $s['id'],
            'title'   => $s['name'] ?? $s['title'] ?? 'Titre inconnu',
            'poster'  => tmdb_image_url($s['poster_path'] ?? null),
            'backdrop'=> tmdb_image_url($s['backdrop_path'] ?? null, TMDB_BACKDROP_SIZE),
            'year'    => substr($s['first_air_date'] ?? '', 0, 4),
            'genre'   => '',
            'note'    => round((float)($s['vote_average'] ?? 0), 1),
            'price'   => $priceData,
            'type'    => 'tv',
        ];
    }
    return $formatted;
}

/**
 * Récupère des recommandations basées sur le dernier film acheté.
 * Si aucun achat → retourne les tendances de la semaine.
 *
 * @param  int|null $lastPurchasedMovieId  ID TMDB du dernier film acheté (depuis la BDD)
 * @param  int      $limit
 * @return array    ['movies' => [...], 'based_on' => string]
 */
function tmdb_get_recommendations(?int $lastPurchasedMovieId, int $limit = 10): array
{
    // Aucun achat → tendances générales
    if (!$lastPurchasedMovieId) {
        return [
            'movies'   => tmdb_get_trending($limit),
            'based_on' => null,
            'label'    => 'Tendances du moment',
            'subtitle' => 'Les films les plus regardés actuellement',
        ];
    }

    $data = tmdb_get("/movie/{$lastPurchasedMovieId}/recommendations");

    $refFilm = tmdb_get("/movie/{$lastPurchasedMovieId}");
    $refTitle = $refFilm['title'] ?? 'votre dernier achat';

    if (!$data || empty($data['results'])) {
        return [
            'movies'   => tmdb_get_trending($limit),
            'based_on' => $refTitle,
            'label'    => 'Tendances du moment',
            'subtitle' => 'Les films les plus regardés actuellement',
        ];
    }

    return [
        'movies'   => tmdb_format_movies(array_slice($data['results'], 0, $limit)),
        'based_on' => $refTitle,
        'label'    => 'Recommandés pour vous',
        'subtitle' => "Parce que vous avez aimé « {$refTitle} »",
    ];
}

/**
 * Extrait le réalisateur depuis les crédits.
 *
 * @param  array $cast
 * @param  array $crew
 * @return string
 */
function tmdb_extract_director(array $cast, array $crew): string
{
    foreach ($crew as $member) {
        if (($member['job'] ?? '') === 'Director') {
            return $member['name'];
        }
    }
    return 'Inconnu';
}

/**
 * Extrait la clé YouTube du meilleur trailer disponible.
 * Priorité : trailer officiel FR → trailer officiel EN → premier trailer.
 *
 * @param  array $videos  Résultats TMDB videos
 * @return string|null    Clé YouTube (ex: 'dQw4w9WgXcQ') ou null
 */
function tmdb_extract_trailer(array $videos): ?string
{
    if (empty($videos)) return null;

    $trailers = array_filter($videos, fn($v) =>
        ($v['type'] ?? '') === 'Trailer' && ($v['site'] ?? '') === 'YouTube'
    );

    if (empty($trailers)) return null;

    usort($trailers, function($a, $b) {
        $aScore = ($a['official'] ?? false ? 2 : 0) + ($a['iso_639_1'] === 'fr' ? 1 : 0);
        $bScore = ($b['official'] ?? false ? 2 : 0) + ($b['iso_639_1'] === 'fr' ? 1 : 0);
        return $bScore <=> $aScore;
    });

    return array_values($trailers)[0]['key'] ?? null;
}