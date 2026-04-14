<?php
// ══════════════════════════════════════════════════════════════════════════════
//  backend/services/watchlist.php — Supinfo.TV
//  Gestion de la liste de souhaits (films à voir / acheter plus tard).
// ══════════════════════════════════════════════════════════════════════════════

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth.php';

/**
 * Retourne tous les films de la watchlist de l'utilisateur connecté.
 */
function watchlist_get(): array
{
    $uid = auth_id();
    if (!$uid) return [];

    try {
        $stmt = db()->prepare(
            'SELECT tmdb_id, title, poster, year, note, added_at
             FROM watchlist
             WHERE user_id = :uid
             ORDER BY added_at DESC'
        );
        $stmt->execute([':uid' => $uid]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('[watchlist_get] ' . $e->getMessage());
        return [];
    }
}

/**
 * Compte les films dans la watchlist.
 */
function watchlist_count(): int
{
    $uid = auth_id();
    if (!$uid) return 0;

    try {
        $stmt = db()->prepare('SELECT COUNT(*) FROM watchlist WHERE user_id = :uid');
        $stmt->execute([':uid' => $uid]);
        return (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log('[watchlist_count] ' . $e->getMessage());
        return 0;
    }
}

/**
 * Vérifie si un film est dans la watchlist.
 */
function watchlist_has(int $tmdbId): bool
{
    $uid = auth_id();
    if (!$uid) return false;

    try {
        $stmt = db()->prepare(
            'SELECT 1 FROM watchlist WHERE user_id = :uid AND tmdb_id = :tid LIMIT 1'
        );
        $stmt->execute([':uid' => $uid, ':tid' => $tmdbId]);
        return (bool)$stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log('[watchlist_has] ' . $e->getMessage());
        return false;
    }
}

/**
 * Ajoute un film à la watchlist.
 */
function watchlist_add(int $tmdbId, string $title, string $poster, string $year, float $note): array
{
    $uid = auth_id();
    if (!$uid) return ['ok' => false, 'error' => 'Non connecté.'];

    // Max 200 films par watchlist
    if (watchlist_count() >= 200) {
        return ['ok' => false, 'error' => 'Votre liste de souhaits est pleine (200 films max).'];
    }

    try {
        db()->prepare(
            'INSERT IGNORE INTO watchlist (user_id, tmdb_id, title, poster, year, note)
             VALUES (:uid, :tid, :title, :poster, :year, :note)'
        )->execute([
            ':uid'    => $uid,
            ':tid'    => $tmdbId,
            ':title'  => $title,
            ':poster' => $poster,
            ':year'   => $year,
            ':note'   => $note,
        ]);
        return ['ok' => true, 'error' => null];
    } catch (PDOException $e) {
        error_log('[watchlist_add] ' . $e->getMessage());
        return ['ok' => false, 'error' => "Erreur lors de l'ajout."];
    }
}

/**
 * Retire un film de la watchlist.
 */
function watchlist_remove(int $tmdbId): array
{
    $uid = auth_id();
    if (!$uid) return ['ok' => false, 'error' => 'Non connecté.'];

    try {
        db()->prepare(
            'DELETE FROM watchlist WHERE user_id = :uid AND tmdb_id = :tid'
        )->execute([':uid' => $uid, ':tid' => $tmdbId]);
        return ['ok' => true, 'error' => null];
    } catch (PDOException $e) {
        error_log('[watchlist_remove] ' . $e->getMessage());
        return ['ok' => false, 'error' => "Erreur lors de la suppression."];
    }
}