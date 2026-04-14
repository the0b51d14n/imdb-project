<?php
// ══════════════════════════════════════════════════════════════════════════════
//  backend/api/watchlist.php — Supinfo.TV
//  Endpoint JSON pour la liste de souhaits (watchlist).
//  Méthodes : GET (liste), POST (toggle add/remove), DELETE (remove)
// ══════════════════════════════════════════════════════════════════════════════

require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../services/auth.php';
require_once __DIR__ . '/../services/csrf.php';
require_once __DIR__ . '/../services/watchlist.php';

auth_start_session();

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');

function json_out(array $data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function verify_csrf(): bool
{
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['_csrf_token'] ?? '';
    return !empty($token) && hash_equals(csrf_token(), $token);
}

if (!auth_check()) {
    json_out(['ok' => false, 'error' => 'Non connecté.'], 401);
}

$method = $_SERVER['REQUEST_METHOD'];
$body   = [];
$raw    = file_get_contents('php://input');
if ($raw && str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
    $body = json_decode($raw, true) ?? [];
}

$action = $_GET['action'] ?? $body['action'] ?? '';
$tmdbId = (int)($body['tmdb_id'] ?? $_GET['tmdb_id'] ?? 0);

// ── GET : liste complète ──────────────────────────────────────────────────────
if ($method === 'GET' && $action !== 'check') {
    $items = watchlist_get();
    json_out(['ok' => true, 'count' => count($items), 'items' => $items]);
}

// ── GET check : film dans la watchlist ? ──────────────────────────────────────
if ($method === 'GET' && $action === 'check') {
    json_out(['ok' => true, 'in_watchlist' => $tmdbId > 0 && watchlist_has($tmdbId)]);
}

// Vérification CSRF pour toutes les mutations
if (!verify_csrf()) {
    json_out(['ok' => false, 'error' => 'Token CSRF invalide.'], 403);
}

// ── POST toggle ───────────────────────────────────────────────────────────────
if ($method === 'POST' && $action === 'toggle') {
    if ($tmdbId <= 0) json_out(['ok' => false, 'error' => 'ID invalide.'], 400);

    if (watchlist_has($tmdbId)) {
        $r = watchlist_remove($tmdbId);
        json_out([
            'ok'           => $r['ok'],
            'in_watchlist' => false,
            'count'        => watchlist_count(),
            'error'        => $r['error'] ?? null,
        ]);
    } else {
        $title  = trim($body['title']  ?? '');
        $poster = trim($body['poster'] ?? '');
        $year   = trim($body['year']   ?? '');
        $note   = (float)($body['note'] ?? 0);

        $r = watchlist_add($tmdbId, $title, $poster, $year, $note);
        json_out([
            'ok'           => $r['ok'],
            'in_watchlist' => $r['ok'],
            'count'        => watchlist_count(),
            'error'        => $r['error'] ?? null,
        ]);
    }
}

// ── POST add ──────────────────────────────────────────────────────────────────
if ($method === 'POST' && $action === 'add') {
    if ($tmdbId <= 0) json_out(['ok' => false, 'error' => 'ID invalide.'], 400);
    $r = watchlist_add($tmdbId, $body['title'] ?? '', $body['poster'] ?? '', $body['year'] ?? '', (float)($body['note'] ?? 0));
    json_out(['ok' => $r['ok'], 'in_watchlist' => $r['ok'], 'count' => watchlist_count(), 'error' => $r['error'] ?? null]);
}

// ── POST remove ───────────────────────────────────────────────────────────────
if (($method === 'POST' || $method === 'DELETE') && $action === 'remove') {
    if ($tmdbId <= 0) json_out(['ok' => false, 'error' => 'ID invalide.'], 400);
    $r = watchlist_remove($tmdbId);
    json_out(['ok' => $r['ok'], 'in_watchlist' => false, 'count' => watchlist_count(), 'error' => $r['error'] ?? null]);
}

json_out(['ok' => false, 'error' => 'Action inconnue.'], 400);
