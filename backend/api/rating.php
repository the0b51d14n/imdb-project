<?php
// ══════════════════════════════════════════════════════════════════════════════
//  backend/services/ratings.php — Supinfo.TV
//  Système de notation des films (réservé aux acheteurs).
// ══════════════════════════════════════════════════════════════════════════════

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/orders.php';

/**
 * Soumet ou met à jour la note d'un utilisateur pour un film.
 * Seuls les acheteurs peuvent noter.
 *
 * @param int    $tmdbId  ID TMDB du film
 * @param int    $rating  Note de 1 à 5
 * @param string $comment Commentaire optionnel (max 1000 chars)
 */
function rating_submit(int $tmdbId, int $rating, string $comment = ''): array
{
    $uid = auth_id();
    if (!$uid) return ['ok' => false, 'error' => 'Vous devez être connecté.'];

    if ($rating < 1 || $rating > 5) {
        return ['ok' => false, 'error' => 'La note doit être entre 1 et 5 étoiles.'];
    }

    // Vérification : l'utilisateur doit avoir acheté ce film
    if (!orders_has_purchased($tmdbId)) {
        return ['ok' => false, 'error' => 'Vous devez avoir acheté ce film pour pouvoir le noter.'];
    }

    $comment = mb_substr(trim($comment), 0, 1000);

    try {
        db()->prepare(
            'INSERT INTO movie_ratings (user_id, tmdb_id, rating, comment)
             VALUES (:uid, :tid, :rating, :comment)
             ON DUPLICATE KEY UPDATE
                 rating     = VALUES(rating),
                 comment    = VALUES(comment),
                 updated_at = NOW()'
        )->execute([
            ':uid'     => $uid,
            ':tid'     => $tmdbId,
            ':rating'  => $rating,
            ':comment' => $comment ?: null,
        ]);

        return ['ok' => true, 'error' => null];

    } catch (PDOException $e) {
        error_log('[rating_submit] ' . $e->getMessage());
        return ['ok' => false, 'error' => 'Erreur lors de l\'enregistrement.'];
    }
}

/**
 * Récupère la note d'un utilisateur pour un film.
 */
function rating_get_user(int $tmdbId): ?array
{
    $uid = auth_id();
    if (!$uid) return null;

    try {
        $stmt = db()->prepare(
            'SELECT rating, comment, created_at, updated_at
             FROM movie_ratings
             WHERE user_id = :uid AND tmdb_id = :tid
             LIMIT 1'
        );
        $stmt->execute([':uid' => $uid, ':tid' => $tmdbId]);
        return $stmt->fetch() ?: null;
    } catch (PDOException $e) {
        error_log('[rating_get_user] ' . $e->getMessage());
        return null;
    }
}

/**
 * Récupère les notes et avis pour un film (tous utilisateurs).
 *
 * @param int $tmdbId
 * @param int $limit  Nombre max d'avis à retourner
 * @return array ['avg' => float, 'count' => int, 'reviews' => array]
 */
function rating_get_for_movie(int $tmdbId, int $limit = 10): array
{
    try {
        // Stats globales
        $stmt = db()->prepare(
            'SELECT AVG(rating) AS avg_rating, COUNT(*) AS total
             FROM movie_ratings WHERE tmdb_id = :tid'
        );
        $stmt->execute([':tid' => $tmdbId]);
        $stats = $stmt->fetch();

        // Distribution des notes (1 à 5)
        $distStmt = db()->prepare(
            'SELECT rating, COUNT(*) AS cnt
             FROM movie_ratings WHERE tmdb_id = :tid
             GROUP BY rating ORDER BY rating DESC'
        );
        $distStmt->execute([':tid' => $tmdbId]);
        $distribution = [];
        foreach ($distStmt->fetchAll() as $row) {
            $distribution[$row['rating']] = (int)$row['cnt'];
        }

        // Avis avec texte
        $reviewStmt = db()->prepare(
            'SELECT mr.rating, mr.comment, mr.created_at, u.username
             FROM movie_ratings mr
             JOIN users u ON u.id = mr.user_id
             WHERE mr.tmdb_id = :tid AND mr.comment IS NOT NULL AND mr.comment != ""
             ORDER BY mr.updated_at DESC
             LIMIT :limit'
        );
        $reviewStmt->bindValue(':tid',   $tmdbId, PDO::PARAM_INT);
        $reviewStmt->bindValue(':limit', $limit,  PDO::PARAM_INT);
        $reviewStmt->execute();
        $reviews = $reviewStmt->fetchAll();

        return [
            'avg'          => round((float)($stats['avg_rating'] ?? 0), 1),
            'count'        => (int)($stats['total'] ?? 0),
            'distribution' => $distribution,
            'reviews'      => $reviews,
        ];

    } catch (PDOException $e) {
        error_log('[rating_get_for_movie] ' . $e->getMessage());
        return ['avg' => 0, 'count' => 0, 'distribution' => [], 'reviews' => []];
    }
}

/**
 * Supprime la note d'un utilisateur pour un film.
 */
function rating_delete(int $tmdbId): array
{
    $uid = auth_id();
    if (!$uid) return ['ok' => false, 'error' => 'Non connecté.'];

    try {
        db()->prepare(
            'DELETE FROM movie_ratings WHERE user_id = :uid AND tmdb_id = :tid'
        )->execute([':uid' => $uid, ':tid' => $tmdbId]);
        return ['ok' => true, 'error' => null];
    } catch (PDOException $e) {
        error_log('[rating_delete] ' . $e->getMessage());
        return ['ok' => false, 'error' => 'Erreur lors de la suppression.'];
    }
}
