<?php
// ══════════════════════════════════════════════════════════════════════════════
//  backend/api/ratings.php — Supinfo.TV
//  Endpoint JSON pour la notation des films.
//
//  GET  ?tmdb_id=X            → statistiques publiques du film
//  GET  ?tmdb_id=X&mine=1     → note de l'utilisateur courant (null si absente)
//  POST {action:submit, …}    → enregistre ou met à jour une note
//  POST {action:delete, …}    → supprime la note de l'utilisateur
//
//  Contrat consommé par frontend/assets/js/components/rating-widget.js
// ══════════════════════════════════════════════════════════════════════════════

require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../services/auth.php';
require_once __DIR__ . '/../services/csrf.php';
require_once __DIR__ . '/../services/ratings.php';

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

$method = $_SERVER['REQUEST_METHOD'];
$body   = [];
$raw    = file_get_contents('php://input');
if ($raw && str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
    $body = json_decode($raw, true) ?? [];
}

$action = $_GET['action'] ?? $body['action'] ?? '';
$tmdbId = (int)($body['tmdb_id'] ?? $_GET['tmdb_id'] ?? 0);

if ($tmdbId <= 0) {
    json_out(['ok' => false, 'error' => 'ID de film invalide.'], 400);
}

// ── GET mine : la note de l'utilisateur courant ───────────────────────────────
// Volontairement tolérant : un visiteur non connecté reçoit rating = null plutôt
// qu'une erreur, ce qui laisse le widget s'afficher vide.
if ($method === 'GET' && !empty($_GET['mine'])) {
    json_out([
        'ok'     => true,
        'rating' => auth_check() ? rating_get_user($tmdbId) : null,
    ]);
}

// ── GET : statistiques publiques du film ──────────────────────────────────────
if ($method === 'GET') {
    $stats = rating_get_for_movie($tmdbId);
    json_out([
        'ok'           => true,
        'avg'          => $stats['avg'],
        'count'        => $stats['count'],
        'distribution' => $stats['distribution'],
        'reviews'      => $stats['reviews'],
    ]);
}

// ── À partir d'ici : mutations, connexion et CSRF obligatoires ────────────────
if (!auth_check()) {
    json_out(['ok' => false, 'error' => 'Vous devez être connecté.'], 401);
}

if (!verify_csrf()) {
    json_out(['ok' => false, 'error' => 'Token CSRF invalide.'], 403);
}

// ── POST submit ───────────────────────────────────────────────────────────────
// La vérification « avoir acheté le film » est faite par rating_submit().
if ($method === 'POST' && $action === 'submit') {
    $r = rating_submit(
        $tmdbId,
        (int)($body['rating'] ?? 0),
        (string)($body['comment'] ?? '')
    );

    $stats = rating_get_for_movie($tmdbId);

    json_out([
        'ok'    => $r['ok'],
        'error' => $r['error'],
        'avg'   => $stats['avg'],
        'count' => $stats['count'],
    ], $r['ok'] ? 200 : 400);
}

// ── POST delete ───────────────────────────────────────────────────────────────
if ($method === 'POST' && $action === 'delete') {
    $r = rating_delete($tmdbId);

    $stats = rating_get_for_movie($tmdbId);

    json_out([
        'ok'    => $r['ok'],
        'error' => $r['error'],
        'avg'   => $stats['avg'],
        'count' => $stats['count'],
    ], $r['ok'] ? 200 : 400);
}

json_out(['ok' => false, 'error' => 'Action inconnue.'], 400);
