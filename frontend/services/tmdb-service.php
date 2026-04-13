<?php

require_once __DIR__ . '/../config/tmdb.php';

/**
 * Effectue une requête GET vers l'API TMDB.
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
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $httpCode !== 200) {
        error_log("TMDB API Error [{$httpCode}] on {$endpoint}");
        return null;
    }

    return json_decode($response, true);
}

/**
 * Construit l'URL complète d'une image TMDB.
 */
function tmdb_image_url(?string $path, string $size = TMDB_POSTER_SIZE): string
{
    if (empty($path)) return '';
    return TMDB_IMAGE_BASE . $size . $path;
}

/**
 * Films tendance (semaine en cours).
 */
function tmdb_get_trending(int $limit = 10): array
{
    $data = tmdb_get('/trending/movie/week');
    if (!$data || empty($data['results'])) return [];
    return tmdb_format_movies(array_slice($data['results'], 0, $limit));
}

/**
 * Films les plus populaires du moment.
 */
function tmdb_get_popular(int $limit = 10): array
{
    $data = tmdb_get('/movie/popular');
    if (!$data || empty($data['results'])) return [];
    return tmdb_format_movies(array_slice($data['results'], 0, $limit));
}

/**
 * Sorties récentes (actuellement en salle).
 */
function tmdb_get_now_playing(int $limit = 10): array
{
    $data = tmdb_get('/movie/now_playing');
    if (!$data || empty($data['results'])) return [];
    return tmdb_format_movies(array_slice($data['results'], 0, $limit));
}

/**
 * Détails complets d'un film (infos + crédits + vidéos).
 */
function tmdb_get_movie_detail(int $movieId): ?array
{
    $data = tmdb_get("/movie/{$movieId}", [
        'append_to_response' => 'credits,videos,belongs_to_collection',
    ]);

    if (!$data) return null;

    $cast      = $data['credits']['cast'] ?? [];
    $priceData = tmdb_calculate_price($data, 'movie', $cast);
    $trailer   = tmdb_extract_trailer($data['videos']['results'] ?? []);

    // Signer le prix et le mettre en session pour validation HMAC côté POST
    if (session_status() === PHP_SESSION_ACTIVE && !empty($priceData['unit'])) {
        $_SESSION['price_sig'][$data['id']]   = tmdb_sign_price($data['id'], $priceData['unit'], 'movie');
        $_SESSION['price_cache'][$data['id']] = $priceData['unit'];
    }

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
        'director'    => tmdb_extract_director($data['credits']['crew'] ?? []),
        'cast'        => array_slice($cast, 0, 5),
        'trailer_key' => $trailer,
        'collection'  => $data['belongs_to_collection'] ?? null,
        'price'       => $priceData,
    ];
}

/**
 * Formate un tableau de films bruts TMDB en tableau simplifié.
 */
function tmdb_format_movies(array $movies): array
{
    return array_map(function (array $m): array {
        $priceData = tmdb_calculate_price($m, 'movie');

        // Signer le prix en session pour validation HMAC côté ajout panier
        if (session_status() === PHP_SESSION_ACTIVE && !empty($priceData['unit']) && !empty($m['id'])) {
            $_SESSION['price_sig'][$m['id']]   = tmdb_sign_price((int)$m['id'], $priceData['unit'], 'movie');
            $_SESSION['price_cache'][$m['id']] = $priceData['unit'];
        }

        return [
            'id'       => $m['id'],
            'title'    => $m['title'] ?? $m['name'] ?? 'Titre inconnu',
            'poster'   => tmdb_image_url($m['poster_path'] ?? null),
            'backdrop' => tmdb_image_url($m['backdrop_path'] ?? null, TMDB_BACKDROP_SIZE),
            'year'     => substr($m['release_date'] ?? $m['first_air_date'] ?? '', 0, 4),
            'genre'    => '',
            'note'     => round((float)($m['vote_average'] ?? 0), 1),
            'price'    => $priceData,
        ];
    }, $movies);
}

/**
 * Séries tendance de la semaine.
 */
function tmdb_get_trending_tv(int $limit = 10): array
{
    $data = tmdb_get('/trending/tv/week');
    if (!$data || empty($data['results'])) return [];
    return tmdb_format_tv(array_slice($data['results'], 0, $limit));
}

/**
 * Formate un tableau de séries brutes TMDB.
 */
function tmdb_format_tv(array $shows): array
{
    return array_map(function (array $s): array {
        $priceData = tmdb_calculate_price($s, 'tv');

        if (session_status() === PHP_SESSION_ACTIVE && !empty($priceData['unit']) && !empty($s['id'])) {
            $_SESSION['price_sig'][$s['id']]   = tmdb_sign_price((int)$s['id'], $priceData['unit'], 'tv');
            $_SESSION['price_cache'][$s['id']] = $priceData['unit'];
        }

        return [
            'id'       => $s['id'],
            'title'    => $s['name'] ?? $s['title'] ?? 'Titre inconnu',
            'poster'   => tmdb_image_url($s['poster_path'] ?? null),
            'backdrop' => tmdb_image_url($s['backdrop_path'] ?? null, TMDB_BACKDROP_SIZE),
            'year'     => substr($s['first_air_date'] ?? '', 0, 4),
            'genre'    => '',
            'note'     => round((float)($s['vote_average'] ?? 0), 1),
            'price'    => $priceData,
            'type'     => 'tv',
        ];
    }, $shows);
}

/**
 * Recommandations basées sur le dernier film acheté.
 * Fallback → tendances de la semaine si aucun achat.
 */
function tmdb_get_recommendations(?int $lastPurchasedMovieId, int $limit = 10): array
{
    if (!$lastPurchasedMovieId) {
        return [
            'movies'   => tmdb_get_trending($limit),
            'based_on' => null,
            'label'    => 'Tendances du moment',
            'subtitle' => 'Les films les plus regardés actuellement',
        ];
    }

    $data     = tmdb_get("/movie/{$lastPurchasedMovieId}/recommendations");
    $refFilm  = tmdb_get("/movie/{$lastPurchasedMovieId}");
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
 * Extrait le réalisateur depuis les crédits crew.
 */
function tmdb_extract_director(array $crew): string
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
 */
function tmdb_extract_trailer(array $videos): ?string
{
    if (empty($videos)) return null;

    $trailers = array_values(array_filter($videos, fn($v) =>
        ($v['type'] ?? '') === 'Trailer' && ($v['site'] ?? '') === 'YouTube'
    ));

    if (empty($trailers)) return null;

    usort($trailers, function (array $a, array $b): int {
        $score = fn($v) => ($v['official'] ?? false ? 2 : 0) + ($v['iso_639_1'] === 'fr' ? 1 : 0);
        return $score($b) <=> $score($a);
    });

    return $trailers[0]['key'] ?? null;
}