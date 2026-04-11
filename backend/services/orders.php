<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth.php';
 
/**
 * Retourne toutes les commandes de l'utilisateur connecté avec leurs articles.
 *
 * @return array  Tableau de commandes, chacune avec 'items'
 */
function orders_get_history(): array
{
    $uid = auth_id();
    if (!$uid) return [];
 
    $stmt = db()->prepare(
        'SELECT id, total_amount, created_at
         FROM orders
         WHERE user_id = :uid
         ORDER BY created_at DESC'
    );
    $stmt->execute([':uid' => $uid]);
    $orders = $stmt->fetchAll();
 
    if (empty($orders)) return [];
 
    $ids   = array_column($orders, 'id');
    $in    = implode(',', array_fill(0, count($ids), '?'));
 
    $items = db()->prepare(
        "SELECT order_id, tmdb_id, title, poster, price
         FROM order_items
         WHERE order_id IN ({$in})
         ORDER BY id ASC"
    );
    $items->execute($ids);
    $allItems = $items->fetchAll();
 
    $grouped = [];
    foreach ($allItems as $item) {
        $grouped[$item['order_id']][] = $item;
    }
 
    foreach ($orders as &$order) {
        $order['items'] = $grouped[$order['id']] ?? [];
    }
 
    return $orders;
}
 
/**
 * Retourne la liste de tous les films distincts achetés par l'utilisateur.
 * Utile pour la page profil "Mes films".
 *
 * @return array  [tmdb_id, title, poster, price, purchased_at]
 */
function orders_get_purchased_movies(): array
{
    $uid = auth_id();
    if (!$uid) return [];
 
    $stmt = db()->prepare(
        'SELECT oi.tmdb_id, oi.title, oi.poster, oi.price, o.created_at AS purchased_at
         FROM order_items oi
         JOIN orders o ON o.id = oi.order_id
         WHERE o.user_id = :uid
         GROUP BY oi.tmdb_id
         ORDER BY o.created_at DESC'
    );
    $stmt->execute([':uid' => $uid]);
    return $stmt->fetchAll();
}

function orders_has_purchased(int $tmdbId): bool
{
    $uid = auth_id();
    if (!$uid) return false;
 
    $stmt = db()->prepare(
        'SELECT 1
         FROM order_items oi
         JOIN orders o ON o.id = oi.order_id
         WHERE o.user_id = :uid AND oi.tmdb_id = :tid
         LIMIT 1'
    );
    $stmt->execute([':uid' => $uid, ':tid' => $tmdbId]);
    return (bool)$stmt->fetchColumn();
}

?>