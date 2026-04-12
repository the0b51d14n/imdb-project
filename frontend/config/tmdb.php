<?php
// ══════════════════════════════════════════════════════════════════════════════
//  frontend/config/tmdb.php — Supinfo.TV
//  Configuration TMDB + calcul/vérification des prix côté serveur (HMAC)
//  ❌ Ne jamais hardcoder de clé ici — tout vient du .env via getenv()
// ══════════════════════════════════════════════════════════════════════════════

// ── Chargement du .env si pas encore chargé ──────────────────────────────────
// (XAMPP n'injecte pas le .env automatiquement — on le parse manuellement)
if (!function_exists('_supinfotv_load_env')) {
    function _supinfotv_load_env(): void {
        // Cherche .env à la racine du projet
        // frontend/config/tmdb.php → dirname x1 = frontend/ → dirname x2 = Supinfo.TV/
        $envFile = dirname(__DIR__, 2) . '/.env';
        if (!file_exists($envFile)) {
            // Fallback : si frontend/ est directement à la racine du vhost
            $envFile = dirname(__DIR__) . '/.env';
            if (!file_exists($envFile)) return;
        }

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            // Ignorer commentaires et lignes vides
            if ($line === '' || str_starts_with($line, '#')) continue;
            if (!str_contains($line, '=')) continue;

            [$key, $value] = explode('=', $line, 2);
            $key   = trim($key);
            $value = trim($value);

            // Ne pas écraser les variables déjà définies (ex: vraies vars d'env serveur)
            if (!isset($_ENV[$key]) && getenv($key) === false) {
                putenv("{$key}={$value}");
                $_ENV[$key] = $value;
            }
        }
    }
}
_supinfotv_load_env();

// ── Constantes TMDB ───────────────────────────────────────────────────────────
define('TMDB_API_KEY',      getenv('TMDB_API_KEY')  ?: '');
define('TMDB_BASE_URL',     'https://api.themoviedb.org/3');
define('TMDB_IMAGE_BASE',   'https://image.tmdb.org/t/p/');
define('TMDB_LANG',         'fr-FR');
define('TMDB_POSTER_SIZE',  'w500');
define('TMDB_BACKDROP_SIZE','w1280');

// ── Clé HMAC pour signature des prix ─────────────────────────────────────────
define('PRICE_HMAC_SECRET', getenv('PRICE_HMAC_SECRET') ?: '');

// ── Grille tarifaire ──────────────────────────────────────────────────────────
// Les prix sont calculés côté serveur à partir des métadonnées TMDB.
// Jamais envoyés en clair depuis le client sans vérification HMAC.

/**
 * Calcule le prix d'un film/série à partir de ses métadonnées TMDB.
 *
 * @param  array  $movie  Données brutes TMDB
 * @param  string $type   'movie' ou 'tv'
 * @param  array  $cast   Tableau de cast (optionnel, pour le bonus popularité)
 * @return array  ['unit' => float, 'bundle' => float|null, 'label' => string]
 */
function tmdb_calculate_price(array $movie, string $type = 'movie', array $cast = []): array
{
    // Base selon le type
    $base = ($type === 'tv') ? 12.99 : 9.99;

    // Bonus note (vote_average)
    $note = (float)($movie['vote_average'] ?? 0);
    if ($note >= 8.0)      $base += 3.00;
    elseif ($note >= 7.0)  $base += 1.50;
    elseif ($note >= 6.0)  $base += 0.50;

    // Bonus popularité
    $popularity = (float)($movie['popularity'] ?? 0);
    if ($popularity >= 500)      $base += 2.00;
    elseif ($popularity >= 100)  $base += 1.00;

    // Bonus récence (films des 2 dernières années)
    $releaseDate = $movie['release_date'] ?? $movie['first_air_date'] ?? '';
    $releaseYear = (int)substr($releaseDate, 0, 4);
    $currentYear = (int)date('Y');
    if ($releaseYear >= $currentYear - 1) $base += 2.00;
    elseif ($releaseYear >= $currentYear - 2) $base += 1.00;

    // Plafond
    $unit = min(round($base, 2), 24.99);

    // Bundle (collection) — réduction 15 % si appartient à une saga
    $bundle = null;
    $label  = '';
    if (!empty($movie['belongs_to_collection'])) {
        $bundle = round($unit * 0.85, 2);
        $label  = 'Prix saga : ' . number_format($bundle, 2, ',', '') . '€';
    }

    return [
        'unit'   => $unit,
        'bundle' => $bundle,
        'label'  => $label,
    ];
}

/**
 * Génère la signature HMAC d'un prix pour un film donné.
 * Utilisée côté serveur pour signer le prix avant de l'envoyer au client.
 *
 * @param  int    $movieId
 * @param  float  $price
 * @param  string $type    'movie' ou 'tv'
 * @return string          Signature hexadécimale
 */
function tmdb_sign_price(int $movieId, float $price, string $type = 'movie'): string
{
    $payload = $movieId . '|' . number_format($price, 2, '.', '') . '|' . $type;
    return hash_hmac('sha256', $payload, PRICE_HMAC_SECRET);
}

/**
 * Vérifie qu'un prix soumis par le client correspond bien à la signature.
 * Protège contre la manipulation du prix côté client.
 *
 * @param  int    $movieId
 * @param  float  $price
 * @param  string $type
 * @param  string $signature  Signature reçue du client
 * @return bool
 */
function tmdb_verify_price(int $movieId, float $price, string $type, string $signature): bool
{
    if (empty($signature) || empty(PRICE_HMAC_SECRET)) return false;
    $expected = tmdb_sign_price($movieId, $price, $type);
    return hash_equals($expected, $signature);
}