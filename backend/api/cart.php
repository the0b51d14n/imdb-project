<?php
// ══════════════════════════════════════════════════════════════════════════════
//  backend/api/cart.php — Supinfo.TV
//  Endpoint JSON pour les actions panier (AJAX, sans rechargement de page).
//  Méthodes supportées : GET (contenu), POST (add/remove/clear/count)
// ══════════════════════════════════════════════════════════════════════════════

require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../services/auth.php';
require_once __DIR__ . '/../services/cart.php';
require_once __DIR__ . '/../services/csrf.php';
require_once __DIR__ . '/../services/orders.php';

auth_start_session();

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');

// Réponse JSON utilitaire
function json_response(array $data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// Vérification CSRF pour les mutations
function verify_csrf_header(): bool
{
    $token = $_SERVER['HTTP_X_CSRF_TOKEN']
          ?? $_POST['_csrf_token']
          ?? '';
    return !empty($token) && hash_equals(csrf_token(), $token);
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// ── GET : contenu du panier ───────────────────────────────────────────────────
if ($method === 'GET' && $action === 'count') {
    json_response([
        'ok'    => true,
        'count' => auth_check() ? (int)($_SESSION['cart_count'] ?? 0) : 0,
    ]);
}

if ($method === 'GET') {
    if (!auth_check()) {
        json_response(['ok' => false, 'error' => 'Non connecté.', 'items' => [], 'total' => 0, 'count' => 0]);
    }

    $items = cart_get_items();
    $total = cart_total();

    json_response([
        'ok'    => true,
        'count' => count($items),
        'total' => round($total, 2),
        'items' => array_map(fn($i) => [
            'tmdb_id'  => (int)$i['tmdb_id'],
            'title'    => $i['title'],
            'poster'   => $i['poster'] ?? '',
            'price'    => (float)$i['price'],
            'added_at' => $i['added_at'],
        ], $items),
    ]);
}

// ── POST : mutations ──────────────────────────────────────────────────────────
if ($method !== 'POST') {
    json_response(['ok' => false, 'error' => 'Méthode non supportée.'], 405);
}

// Lire le body JSON ou les données POST classiques
$body = [];
$raw  = file_get_contents('php://input');
if ($raw && str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
    $body = json_decode($raw, true) ?? [];
}

$action  = $action ?: ($body['action'] ?? '');
$tmdbId  = (int)($body['tmdb_id'] ?? $_POST['tmdb_id'] ?? 0);

// Vérification CSRF
if (!verify_csrf_header()) {
    json_response(['ok' => false, 'error' => 'Token CSRF invalide.'], 403);
}

// Auth requise pour toutes les mutations
if (!auth_check()) {
    json_response(['ok' => false, 'error' => 'Vous devez être connecté.', 'redirect' => '/pages/login.php'], 401);
}

// ── ADD ───────────────────────────────────────────────────────────────────────
if ($action === 'add') {
    if ($tmdbId <= 0) {
        json_response(['ok' => false, 'error' => 'ID film invalide.'], 400);
    }

    // Prix toujours pris depuis la session (calculé + signé côté serveur)
    $price = (float)($_SESSION['price_cache'][$tmdbId] ?? 0);
    if ($price <= 0) {
        json_response(['ok' => false, 'error' => 'Prix introuvable. Rechargez la page.'], 400);
    }

    $title  = trim($body['title'] ?? '');
    $poster = trim($body['poster'] ?? $_SESSION['poster_cache'][$tmdbId] ?? '');

    $r = cart_add($tmdbId, $title, $poster, $price);

    json_response([
        'ok'    => $r['ok'],
        'error' => $r['error'] ?? null,
        'count' => (int)($_SESSION['cart_count'] ?? 0),
        'total' => auth_check() ? round(cart_total(), 2) : 0,
    ], $r['ok'] ? 200 : 422);
}

// ── REMOVE ────────────────────────────────────────────────────────────────────
if ($action === 'remove') {
    if ($tmdbId <= 0) {
        json_response(['ok' => false, 'error' => 'ID film invalide.'], 400);
    }

    $r = cart_remove($tmdbId);

    json_response([
        'ok'    => $r['ok'],
        'error' => $r['error'] ?? null,
        'count' => (int)($_SESSION['cart_count'] ?? 0),
        'total' => round(cart_total(), 2),
    ], $r['ok'] ? 200 : 422);
}

// ── CLEAR ─────────────────────────────────────────────────────────────────────
if ($action === 'clear') {
    cart_clear();
    json_response(['ok' => true, 'count' => 0, 'total' => 0]);
}

// ── CHECK (film dans le panier ?) ─────────────────────────────────────────────
if ($action === 'check') {
    json_response([
        'ok'        => true,
        'in_cart'   => $tmdbId > 0 && cart_has($tmdbId),
        'purchased' => $tmdbId > 0 && orders_has_purchased($tmdbId),
    ]);
}

json_response(['ok' => false, 'error' => 'Action inconnue.'], 400);
