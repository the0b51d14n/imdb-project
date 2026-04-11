<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth.php';
 
/**
 * Ajoute un film au panier de l'utilisateur connecté.
 * Idempotent : si le film est déjà dans le panier, rien ne se passe.
 *
 * @return array ['ok' => bool, 'error' => string|null]
 */
function cart_add(int $tmdbId, string $title, string $poster, float $price): array
{
    $uid = auth_id();
    if (!$uid) {
        return ['ok' => false, 'error' => 'Vous devez être connecté pour ajouter un film au panier.'];
    }
 
    try {
        $stmt = db()->prepare(
            'INSERT IGNORE INTO cart_items (user_id, tmdb_id, title, poster, price)
             VALUES (:uid, :tid, :title, :poster, :price)'
        );
        $stmt->execute([
            ':uid'    => $uid,
            ':tid'    => $tmdbId,
            ':title'  => mb_substr($title, 0, 255),
            ':poster' => mb_substr($poster, 0, 512),
            ':price'  => round($price, 2),
        ]);
        cart_sync_count();
        return ['ok' => true, 'error' => null];
 
    } catch (PDOException $e) {
        error_log('cart_add error: ' . $e->getMessage());
        return ['ok' => false, 'error' => 'Erreur lors de l\'ajout au panier.'];
    }
}
 
/**
 * Supprime un film du panier de l'utilisateur connecté.
 *
 * @return array ['ok' => bool, 'error' => string|null]
 */
function cart_remove(int $tmdbId): array
{
    $uid = auth_id();
    if (!$uid) {
        return ['ok' => false, 'error' => 'Non authentifié.'];
    }
 
    $stmt = db()->prepare(
        'DELETE FROM cart_items WHERE user_id = :uid AND tmdb_id = :tid'
    );
    $stmt->execute([':uid' => $uid, ':tid' => $tmdbId]);
    cart_sync_count();
 
    return ['ok' => true, 'error' => null];
}

function cart_clear(): array
{
    $uid = auth_id();
    if (!$uid) {
        return ['ok' => false, 'error' => 'Non authentifié.'];
    }
 
    $stmt = db()->prepare('DELETE FROM cart_items WHERE user_id = :uid');
    $stmt->execute([':uid' => $uid]);
    cart_sync_count();
 
    return ['ok' => true, 'error' => null];
}
 
/**
 * Retourne tous les articles du panier de l'utilisateur connecté.
 *
 * @return array  Tableau d'articles [id, tmdb_id, title, poster, price, added_at]
 */
function cart_get_items(): array
{
    $uid = auth_id();
    if (!$uid) return [];
 
    $stmt = db()->prepare(
        'SELECT id, tmdb_id, title, poster, price, added_at
         FROM cart_items
         WHERE user_id = :uid
         ORDER BY added_at DESC'
    );
    $stmt->execute([':uid' => $uid]);
    return $stmt->fetchAll();
}

function cart_total(): float
{
    $uid = auth_id();
    if (!$uid) return 0.0;
 
    $stmt = db()->prepare('SELECT COALESCE(SUM(price), 0) FROM cart_items WHERE user_id = :uid');
    $stmt->execute([':uid' => $uid]);
    return (float)$stmt->fetchColumn();
}

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
 * Transforme le panier en commande (checkout).
 * Crée une entrée dans `orders` + `order_items`, puis vide le panier.
 *
 * @return array ['ok' => bool, 'order_id' => int|null, 'error' => string|null]
 */
function cart_checkout(): array
{
    $uid   = auth_id();
    $items = cart_get_items();
 
    if (!$uid || empty($items)) {
        return ['ok' => false, 'order_id' => null, 'error' => 'Panier vide ou non authentifié.'];
    }
 
    $pdo   = db();
    $total = array_sum(array_column($items, 'price'));
 
    try {
        $pdo->beginTransaction();
 
        $order = $pdo->prepare(
            'INSERT INTO orders (user_id, total_amount) VALUES (:uid, :total)'
        );
        $order->execute([':uid' => $uid, ':total' => $total]);
        $orderId = (int)$pdo->lastInsertId();
 
        $item = $pdo->prepare(
            'INSERT INTO order_items (order_id, tmdb_id, title, poster, price)
             VALUES (:oid, :tid, :title, :poster, :price)'
        );
        foreach ($items as $i) {
            $item->execute([
                ':oid'    => $orderId,
                ':tid'    => $i['tmdb_id'],
                ':title'  => $i['title'],
                ':poster' => $i['poster'],
                ':price'  => $i['price'],
            ]);
        }
 
        $del = $pdo->prepare('DELETE FROM cart_items WHERE user_id = :uid');
        $del->execute([':uid' => $uid]);
 
        $pdo->commit();
 
        $_SESSION['last_purchased_movie_id'] = (int)$items[0]['tmdb_id'];
        cart_sync_count();
 
        return ['ok' => true, 'order_id' => $orderId, 'error' => null];
 
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log('cart_checkout error: ' . $e->getMessage());
        return ['ok' => false, 'order_id' => null, 'error' => 'Erreur lors du paiement. Veuillez réessayer.'];
    }
}
 
?>