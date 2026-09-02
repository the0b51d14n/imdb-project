<?php
// ══════════════════════════════════════════════════════════════════════════════
//  backend/services/session.php — Supinfo.TV
//  Démarrage de session centralisé + handler de session stocké en MySQL.
//
//  Pourquoi : en hébergement serverless (Vercel), le système de fichiers est
//  éphémère et non partagé entre deux invocations. Les sessions PHP par fichiers
//  (/tmp) sont donc perdues d'une requête à l'autre → impossible de rester
//  connecté. On persiste la session dans la même base MySQL que le reste.
//
//  Utilisation :
//    require_once __DIR__ . '/../services/session.php';
//    app_session_start();
//
//  Table requise : voir backend/Database_sessions.sql
//  Driver : SESSION_DRIVER=db (défaut) ou SESSION_DRIVER=files (fichiers PHP)
// ══════════════════════════════════════════════════════════════════════════════

require_once __DIR__ . '/../config/database.php';

if (!defined('SESSION_DEFAULT_TTL')) {
    define('SESSION_DEFAULT_TTL', 1800); // 30 minutes
}

/**
 * Handler de session persistant en base de données.
 * Toutes les erreurs SQL sont loguées et dégradées silencieusement :
 * une session illisible vaut mieux qu'une page blanche.
 */
final class MySQLSessionHandler implements SessionHandlerInterface, SessionUpdateTimestampHandlerInterface
{
    private int $ttl;

    public function __construct(int $ttl = SESSION_DEFAULT_TTL)
    {
        $this->ttl = $ttl > 0 ? $ttl : SESSION_DEFAULT_TTL;
    }

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string|false
    {
        try {
            $stmt = db()->prepare(
                'SELECT payload FROM sessions
                 WHERE session_id = :id AND last_activity > :min
                 LIMIT 1'
            );
            $stmt->execute([':id' => $id, ':min' => time() - $this->ttl]);
            $row = $stmt->fetch();

            return $row ? (string)$row['payload'] : '';

        } catch (Throwable $e) {
            error_log('[session:read] ' . $e->getMessage());
            return '';
        }
    }

    public function write(string $id, string $data): bool
    {
        try {
            $stmt = db()->prepare(
                'INSERT INTO sessions (session_id, payload, last_activity)
                 VALUES (:id, :payload, :now)
                 ON DUPLICATE KEY UPDATE
                     payload       = VALUES(payload),
                     last_activity = VALUES(last_activity)'
            );

            return $stmt->execute([
                ':id'      => $id,
                ':payload' => $data,
                ':now'     => time(),
            ]);

        } catch (Throwable $e) {
            error_log('[session:write] ' . $e->getMessage());
            return false;
        }
    }

    public function destroy(string $id): bool
    {
        try {
            db()->prepare('DELETE FROM sessions WHERE session_id = :id')
                ->execute([':id' => $id]);
            return true;

        } catch (Throwable $e) {
            error_log('[session:destroy] ' . $e->getMessage());
            return false;
        }
    }

    public function gc(int $max_lifetime): int|false
    {
        try {
            $stmt = db()->prepare('DELETE FROM sessions WHERE last_activity < :min');
            $stmt->execute([':min' => time() - max($max_lifetime, $this->ttl)]);
            return $stmt->rowCount();

        } catch (Throwable $e) {
            error_log('[session:gc] ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Indique à PHP si l'ID fourni par le cookie existe déjà.
     * Retourner false force la génération d'un nouvel ID (anti session fixation).
     */
    public function validateId(string $id): bool
    {
        try {
            $stmt = db()->prepare(
                'SELECT 1 FROM sessions
                 WHERE session_id = :id AND last_activity > :min
                 LIMIT 1'
            );
            $stmt->execute([':id' => $id, ':min' => time() - $this->ttl]);
            return (bool)$stmt->fetchColumn();

        } catch (Throwable $e) {
            error_log('[session:validateId] ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Session inchangée : on ne réécrit que la date d'activité (lazy_write).
     */
    public function updateTimestamp(string $id, string $data): bool
    {
        try {
            db()->prepare('UPDATE sessions SET last_activity = :now WHERE session_id = :id')
                ->execute([':now' => time(), ':id' => $id]);
            return true;

        } catch (Throwable $e) {
            error_log('[session:updateTimestamp] ' . $e->getMessage());
            return false;
        }
    }
}

/**
 * Démarre la session applicative (idempotent).
 * Point d'entrée unique : ne jamais appeler session_start() directement.
 */
function app_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $ttl = (int)(getenv('SESSION_TTL') ?: SESSION_DEFAULT_TTL);
    if ($ttl <= 0) $ttl = SESSION_DEFAULT_TTL;

    ini_set('session.gc_maxlifetime', (string)$ttl);
    ini_set('session.use_strict_mode', '1');

    // ── Cookie de session ─────────────────────────────────────────────────────
    // secure : HTTPS direct, HTTPS derrière le proxy Vercel, ou APP_ENV=production
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
           || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
           || getenv('APP_ENV') === 'production';

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    // ── Handler ───────────────────────────────────────────────────────────────
    if ((getenv('SESSION_DRIVER') ?: 'db') === 'db') {
        try {
            session_set_save_handler(new MySQLSessionHandler($ttl), true);
        } catch (Throwable $e) {
            // Base injoignable → on retombe sur le handler fichiers par défaut
            error_log('[session] handler MySQL indisponible, fallback fichiers : ' . $e->getMessage());
        }
    }

    session_start();

    // ── Rotation périodique de l'ID de session ────────────────────────────────
    if (!isset($_SESSION['_created'])) {
        $_SESSION['_created'] = time();
    } elseif (time() - $_SESSION['_created'] > 1800) {
        session_regenerate_id(true);
        $_SESSION['_created'] = time();
    }
}
