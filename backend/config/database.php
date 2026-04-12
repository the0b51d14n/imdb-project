<?php
// ══════════════════════════════════════════════════════════════════════════════
//  backend/config/database.php — Supinfo.TV
//  Connexion PDO MySQL — les credentials viennent exclusivement du .env
//  ❌ Ne jamais hardcoder d'identifiants ici
// ══════════════════════════════════════════════════════════════════════════════

// ── Chargement du .env si pas encore fait ────────────────────────────────────
if (!function_exists('_supinfotv_load_env')) {
    function _supinfotv_load_env(): void {
        // backend/config/ → dirname x1 = backend/ → dirname x2 = Supinfo.TV/
        $envFile = dirname(__DIR__, 2) . '/.env';
        if (!file_exists($envFile)) {
            $envFile = dirname(__DIR__) . '/.env';
            if (!file_exists($envFile)) return;
        }

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) continue;
            if (!str_contains($line, '=')) continue;

            [$key, $value] = explode('=', $line, 2);
            $key   = trim($key);
            $value = trim($value);

            if (!isset($_ENV[$key]) && getenv($key) === false) {
                putenv("{$key}={$value}");
                $_ENV[$key] = $value;
            }
        }
    }
}
_supinfotv_load_env();

// ── Singleton PDO ─────────────────────────────────────────────────────────────
// Une seule connexion par requête PHP, réutilisée partout via db()

function db(): PDO
{
    static $pdo = null;

    if ($pdo !== null) return $pdo;

    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $port = getenv('DB_PORT') ?: '3306';
    $name = getenv('DB_NAME') ?: 'supinfotv';
    $user = getenv('DB_USER') ?: '';
    $pass = getenv('DB_PASS') ?: '';

    if (empty($user) || empty($pass)) {
        error_log('[DB] DB_USER ou DB_PASS manquant dans le .env');
        throw new RuntimeException('Configuration base de données incomplète.');
    }

    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
    ];

    try {
        $pdo = new PDO($dsn, $user, $pass, $options);
    } catch (PDOException $e) {
        error_log('[DB] Connexion échouée : ' . $e->getMessage());
        throw new RuntimeException('Impossible de se connecter à la base de données.');
    }

    return $pdo;
}