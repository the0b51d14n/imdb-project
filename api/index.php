<?php
// ══════════════════════════════════════════════════════════════════════════════
//  api/index.php — Supinfo.TV
//  Front controller pour l'hébergement Vercel (runtime vercel-php).
//
//  Rôle : reproduire exactement le routage de docker/nginx/default.conf, qui
//  utilise frontend/ comme racine web et expose backend/ sous /backend/.
//  Chaque page reste un script PHP autonome — aucune modification de la stack.
//
//  Le point clé est $_SERVER['SCRIPT_NAME'] : tout le site calcule ses URLs
//  ($basePath) à partir de cette valeur. On la force au chemin *logique* de la
//  page, identique à ce que php-fpm aurait reçu derrière nginx.
//
//  Fonctionne aussi en local : php -S localhost:8000 api/index.php
// ══════════════════════════════════════════════════════════════════════════════

define('SUPINFOTV_ROOT',     dirname(__DIR__));
define('SUPINFOTV_FRONTEND', SUPINFOTV_ROOT . '/frontend');

// ── 1. Normalisation des variables serveur derrière le proxy ──────────────────
// Vercel termine le TLS en amont : sans ça, les cookies ne sont pas marqués
// secure et l'URL de callback OAuth est construite en http://.
if (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https' && empty($_SERVER['HTTPS'])) {
    $_SERVER['HTTPS'] = 'on';
}

// IP réelle du visiteur (rate limiting + journal d'activité), sinon on ne verrait
// que l'IP du proxy et une seule IP épuiserait le quota de tout le monde.
if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    $forwarded = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
    if (filter_var($forwarded, FILTER_VALIDATE_IP)) {
        $_SERVER['REMOTE_ADDR'] = $forwarded;
    }
}

if (!empty($_SERVER['HTTP_X_FORWARDED_HOST'])) {
    $_SERVER['HTTP_HOST'] = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_HOST'])[0]);
}

// ── 2. Chemin demandé ─────────────────────────────────────────────────────────
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$requestPath = '/' . ltrim(rawurldecode((string)$requestPath), '/');
$requestPath = preg_replace('#/{2,}#', '/', $requestPath);

// ── 3. Assets statiques ───────────────────────────────────────────────────────
// Sur Vercel ces URLs sont servies par le CDN (voir vercel.json) et n'arrivent
// jamais ici ; ce bloc sert au serveur de développement PHP intégré.
if (str_starts_with($requestPath, '/assets/')) {
    supinfotv_serve_asset(substr($requestPath, strlen('/assets/')));
    return;
}

// ── 4. Résolution URL → script PHP ────────────────────────────────────────────
// Les noms de fichiers sont validés par liste blanche (pas de '/', pas de '.'),
// ce qui rend toute traversée de répertoire impossible.
$scriptName = null;
$scriptFile = null;

if ($requestPath === '/' || $requestPath === '/index.php') {
    // Racine → frontend/index.php (nginx : root frontend, index index.php)
    $scriptName = '/index.php';
    $scriptFile = SUPINFOTV_FRONTEND . '/index.php';

} elseif (preg_match('#^/pages/([A-Za-z0-9_-]+\.php)$#', $requestPath, $m)) {
    // Vues publiques → frontend/pages/
    $candidate = SUPINFOTV_FRONTEND . '/pages/' . $m[1];
    if (is_file($candidate)) {
        $scriptName = $requestPath;
        $scriptFile = $candidate;
    }

} elseif (preg_match('#^/backend/(pages|api)/([A-Za-z0-9_-]+\.php)$#', $requestPath, $m)) {
    // Contrôleurs et API → backend/pages/ et backend/api/
    $candidate = SUPINFOTV_ROOT . '/backend/' . $m[1] . '/' . $m[2];
    if (is_file($candidate)) {
        $scriptName = $requestPath;
        $scriptFile = $candidate;

        if ($m[1] === 'api') {
            header('Cache-Control: no-store, no-cache, must-revalidate');

            // Préflight CORS : réponse vide, comme le faisait nginx
            if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
                http_response_code(204);
                return;
            }
        }
    }
}

// ── 5. 404 ────────────────────────────────────────────────────────────────────
if ($scriptFile === null) {
    http_response_code(404);
    $scriptName = '/pages/404.php';
    $scriptFile = SUPINFOTV_FRONTEND . '/pages/404.php';

    if (!is_file($scriptFile)) {
        header('Content-Type: text/plain; charset=utf-8');
        echo "404 — Page introuvable";
        return;
    }
}

// ── 6. Exécution du script dans les mêmes conditions que php-fpm ──────────────
$_SERVER['SCRIPT_NAME']     = $scriptName;
$_SERVER['PHP_SELF']        = $scriptName;
$_SERVER['SCRIPT_FILENAME'] = $scriptFile;
$_SERVER['DOCUMENT_ROOT']   = SUPINFOTV_FRONTEND;

chdir(dirname($scriptFile));

// Inclusion au niveau global : les pages déclarent leurs variables ($pageTitle,
// $basePath, $movie…) en portée globale et les partials les relisent.
require $scriptFile;

// ── Utilitaires ───────────────────────────────────────────────────────────────

/**
 * Sert un fichier de frontend/assets/ (serveur PHP intégré uniquement).
 */
function supinfotv_serve_asset(string $relative): void
{
    $base = SUPINFOTV_FRONTEND . '/assets';
    $full = realpath($base . '/' . $relative);

    if ($full === false || !is_file($full) || !str_starts_with($full, (string)realpath($base))) {
        http_response_code(404);
        return;
    }

    static $types = [
        'css'   => 'text/css; charset=utf-8',
        'js'    => 'application/javascript; charset=utf-8',
        'json'  => 'application/json; charset=utf-8',
        'svg'   => 'image/svg+xml',
        'png'   => 'image/png',
        'jpg'   => 'image/jpeg',
        'jpeg'  => 'image/jpeg',
        'gif'   => 'image/gif',
        'ico'   => 'image/x-icon',
        'webp'  => 'image/webp',
        'woff'  => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf'   => 'font/ttf',
        'eot'   => 'application/vnd.ms-fontobject',
    ];

    $ext = strtolower(pathinfo($full, PATHINFO_EXTENSION));

    header('Content-Type: ' . ($types[$ext] ?? 'application/octet-stream'));
    header('Content-Length: ' . filesize($full));
    header('Cache-Control: public, max-age=31536000, immutable');

    readfile($full);
}
