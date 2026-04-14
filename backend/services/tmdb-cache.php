<?php
// ══════════════════════════════════════════════════════════════════════════════
//  backend/services/tmdb-cache.php — Supinfo.TV
//  Cache TMDB en base de données avec TTL configurable.
//  Réduit les appels API de ~80% et protège contre les limites de taux.
//
//  Utilisation :
//    $data = tmdb_cache_get('trending_week');
//    if ($data === null) {
//        $data = tmdb_get('/trending/movie/week');
//        tmdb_cache_set('trending_week', $data, 1800); // 30 min
//    }
// ══════════════════════════════════════════════════════════════════════════════

require_once __DIR__ . '/../config/database.php';

/**
 * Récupère une valeur du cache TMDB.
 * Retourne null si la clé n'existe pas ou a expiré.
 */
function tmdb_cache_get(string $key): mixed
{
    try {
        $stmt = db()->prepare(
            'SELECT payload FROM tmdb_cache
             WHERE cache_key = :key AND expires_at > NOW()
             LIMIT 1'
        );
        $stmt->execute([':key' => $key]);
        $row = $stmt->fetch();

        if (!$row) return null;

        $data = json_decode($row['payload'], true);
        return $data !== null ? $data : null;

    } catch (PDOException $e) {
        // Table inexistante ou erreur DB → on ignore et laisse passer l'appel API
        error_log('[tmdb_cache_get] ' . $e->getMessage());
        return null;
    }
}

/**
 * Stocke une valeur dans le cache TMDB.
 *
 * @param string $key   Clé de cache (ex: 'trending_week', 'movie_550')
 * @param mixed  $data  Données à cacher (sera sérialisé en JSON)
 * @param int    $ttl   Durée de vie en secondes (défaut: 1800 = 30 min)
 */
function tmdb_cache_set(string $key, mixed $data, int $ttl = 1800): void
{
    try {
        $expires = date('Y-m-d H:i:s', time() + $ttl);
        $payload = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        db()->prepare(
            'INSERT INTO tmdb_cache (cache_key, payload, expires_at)
             VALUES (:key, :payload, :expires)
             ON DUPLICATE KEY UPDATE
                 payload    = VALUES(payload),
                 expires_at = VALUES(expires_at),
                 updated_at = NOW()'
        )->execute([
            ':key'     => $key,
            ':payload' => $payload,
            ':expires' => $expires,
        ]);
    } catch (PDOException $e) {
        error_log('[tmdb_cache_set] ' . $e->getMessage());
    }
}

/**
 * Invalide une clé de cache spécifique.
 */
function tmdb_cache_delete(string $key): void
{
    try {
        db()->prepare('DELETE FROM tmdb_cache WHERE cache_key = :key')
            ->execute([':key' => $key]);
    } catch (PDOException $e) {
        error_log('[tmdb_cache_delete] ' . $e->getMessage());
    }
}

/**
 * Invalide toutes les entrées de cache correspondant à un préfixe.
 * Ex: tmdb_cache_invalidate_prefix('movie_') efface tous les films.
 */
function tmdb_cache_invalidate_prefix(string $prefix): void
{
    try {
        db()->prepare('DELETE FROM tmdb_cache WHERE cache_key LIKE :prefix')
            ->execute([':prefix' => $prefix . '%']);
    } catch (PDOException $e) {
        error_log('[tmdb_cache_invalidate_prefix] ' . $e->getMessage());
    }
}

/**
 * Purge les entrées expirées. À appeler périodiquement (ex: 1/100 requêtes).
 */
function tmdb_cache_gc(): void
{
    if (mt_rand(1, 100) !== 1) return; // GC probabiliste 1%

    try {
        db()->exec('DELETE FROM tmdb_cache WHERE expires_at <= NOW()');
    } catch (PDOException $e) {
        error_log('[tmdb_cache_gc] ' . $e->getMessage());
    }
}

/**
 * Wrapper pratique : get-or-fetch avec mise en cache automatique.
 *
 * @param string   $key     Clé de cache
 * @param callable $fetcher Fonction appelée si cache miss (doit retourner les données)
 * @param int      $ttl     TTL en secondes
 */
function tmdb_cache_remember(string $key, callable $fetcher, int $ttl = 1800): mixed
{
    $cached = tmdb_cache_get($key);
    if ($cached !== null) return $cached;

    $data = $fetcher();
    if ($data !== null) {
        tmdb_cache_set($key, $data, $ttl);
    }

    return $data;
}

// GC au chargement du service (probabiliste)
tmdb_cache_gc();