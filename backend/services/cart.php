<?php
// ══════════════════════════════════════════════════════════════════════════════
//  backend/services/cart.php — Supinfo.TV
//  Gestion du panier en base de données (utilisateurs connectés).
// ══════════════════════════════════════════════════════════════════════════════

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth.php';

/**
 * Retourne tous les articles du panier de l'utilisateur connecté.
 * @return array
 */
function cart_get_items(): array
{
    $uid = auth_id();
    if (!$uid) return [];

    $stmt = db()->prepare(
        'SELECT tmdb_id, title, poster, price, added_at
         FROM cart_items
         WHERE user_id = :uid
         ORDER BY added_at DESC'
    );
    $stmt->execute([':uid' => $uid]);
    return $stmt->fetchAll();
}

/**
 * Calcule le total du panier.
 * @return float
 */
function cart_total(): float
{
    $uid = auth_id();
    if (!$uid) return 0.0;

    $stmt = db()->prepare(
        'SELECT COALESCE(SUM(price), 0) FROM cart_items WHERE user_id = :uid'
    );
    $stmt->execute([':uid' => $uid]);
    return (float)$stmt->fetchColumn();
}

/**
 * Vérifie si un film est déjà dans le panier.
 * @param int $tmdbId
 * @return bool
 */
function cart_has(int $tmdbId): bool
{
    $uid = auth_id();
    if (!$uid) return false;

    $stmt = db()->prepare(
        'SELECT 1 FROM cart_items WHERE user_id = :uid AND tmdb_id = :tid LIMIT 1'
    );
    $stmt->execute([':uid' => $uid, ':tid' => $tmdbId]);
    return (bool)$stmt->fetchColumn();
}

/**
 * Ajoute un film au panier.
 * @param int    $tmdbId
 * @param string $title
 * @param string $poster
 * @param float  $price
 * @return array ['ok' => bool, 'error' => string|null]
 */
function cart_add(int $tmdbId, string $title, string $poster, float $price): array
{
    $uid = auth_id();
    if (!$uid) return ['ok' => false, 'error' => 'Vous devez être connecté.'];

    // Vérifier si déjà acheté
    require_once __DIR__ . '/orders.php';
    if (orders_has_purchased($tmdbId)) {
        return ['ok' => false, 'error' => 'Vous possédez déjà ce film.'];
    }

    try {
        $stmt = db()->prepare(
            'INSERT IGNORE INTO cart_items (user_id, tmdb_id, title, poster, price)
             VALUES (:uid, :tid, :title, :poster, :price)'
        );
        $stmt->execute([
            ':uid'    => $uid,
            ':tid'    => $tmdbId,
            ':title'  => $title,
            ':poster' => $poster,
            ':price'  => $price,
        ]);

        cart_sync_count();
        return ['ok' => true, 'error' => null];

    } catch (PDOException $e) {
        error_log('cart_add error: ' . $e->getMessage());
        return ['ok' => false, 'error' => 'Erreur lors de l\'ajout au panier.'];
    }
}

/**
 * Supprime un film du panier.
 * @param int $tmdbId
 * @return array ['ok' => bool, 'error' => string|null]
 */
function cart_remove(int $tmdbId): array
{
    $uid = auth_id();
    if (!$uid) return ['ok' => false, 'error' => 'Non connecté.'];

    try {
        $stmt = db()->prepare(
            'DELETE FROM cart_items WHERE user_id = :uid AND tmdb_id = :tid'
        );
        $stmt->execute([':uid' => $uid, ':tid' => $tmdbId]);
        cart_sync_count();
        return ['ok' => true, 'error' => null];
    } catch (PDOException $e) {
        error_log('cart_remove error: ' . $e->getMessage());
        return ['ok' => false, 'error' => 'Erreur lors de la suppression.'];
    }
}

/**
 * Vide entièrement le panier.
 * @return void
 */
function cart_clear(): void
{
    $uid = auth_id();
    if (!$uid) return;

    try {
        db()->prepare('DELETE FROM cart_items WHERE user_id = :uid')
            ->execute([':uid' => $uid]);
        cart_sync_count();
    } catch (PDOException $e) {
        error_log('cart_clear error: ' . $e->getMessage());
    }
}

/**
 * Valide la commande : transfère le panier en commande en base.
 * @return array ['ok' => bool, 'error' => string|null]
 */
function cart_checkout(): array
{
    $uid   = auth_id();
    $items = cart_get_items();
    $total = cart_total();

    if (!$uid)         return ['ok' => false, 'error' => 'Non connecté.'];
    if (empty($items)) return ['ok' => false, 'error' => 'Votre panier est vide.'];

    $pdo = db();
    $pdo->beginTransaction();

    try {
        // Créer la commande
        $stmt = $pdo->prepare(
            'INSERT INTO orders (user_id, total_amount) VALUES (:uid, :total)'
        );
        $stmt->execute([':uid' => $uid, ':total' => $total]);
        $orderId = (int)$pdo->lastInsertId();

        // Insérer les articles
        $ins = $pdo->prepare(
            'INSERT INTO order_items (order_id, tmdb_id, title, poster, price)
             VALUES (:order_id, :tmdb_id, :title, :poster, :price)'
        );
        foreach ($items as $item) {
            $ins->execute([
                ':order_id' => $orderId,
                ':tmdb_id'  => $item['tmdb_id'],
                ':title'    => $item['title'],
                ':poster'   => $item['poster'] ?? '',
                ':price'    => $item['price'],
            ]);
        }

        // Sauvegarder l'id du dernier film pour les recommandations
        $lastTmdbId = $items[0]['tmdb_id'] ?? null;

        // Vider le panier
        $pdo->prepare('DELETE FROM cart_items WHERE user_id = :uid')
            ->execute([':uid' => $uid]);

        $pdo->commit();

        // Mettre à jour la session
        $_SESSION['cart_count'] = 0;
        if ($lastTmdbId) {
            $_SESSION['last_purchased_movie_id'] = (int)$lastTmdbId;
        }

        return ['ok' => true, 'error' => null, 'order_id' => $orderId];

    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log('cart_checkout error: ' . $e->getMessage());
        return ['ok' => false, 'error' => 'Erreur lors de la commande. Veuillez réessayer.'];
    }
}