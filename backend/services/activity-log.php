<?php
// ══════════════════════════════════════════════════════════════════════════════
//  backend/services/activity-log.php — Supinfo.TV
//  Journalisation des actions utilisateur sensibles.
//  Utile pour la sécurité, le support, et la détection de comportements anormaux.
// ══════════════════════════════════════════════════════════════════════════════

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth.php';

// Types d'événements supportés
const LOG_LOGIN_SUCCESS     = 'login_success';
const LOG_LOGIN_FAIL        = 'login_fail';
const LOG_LOGOUT            = 'logout';
const LOG_REGISTER          = 'register';
const LOG_PASSWORD_CHANGE   = 'password_change';
const LOG_PASSWORD_RESET    = 'password_reset';
const LOG_EMAIL_VERIFIED    = 'email_verified';
const LOG_CART_ADD          = 'cart_add';
const LOG_CART_REMOVE       = 'cart_remove';
const LOG_ORDER_PLACED      = 'order_placed';
const LOG_WATCHLIST_ADD     = 'watchlist_add';
const LOG_WATCHLIST_REMOVE  = 'watchlist_remove';
const LOG_PROFILE_UPDATE    = 'profile_update';
const LOG_RATE_LIMITED      = 'rate_limited';

/**
 * Enregistre une action dans le journal d'activité.
 *
 * @param string   $event    Type d'événement (constante LOG_*)
 * @param array    $meta     Métadonnées additionnelles (ne pas stocker de données sensibles)
 * @param int|null $userId   ID utilisateur (null = utilisateur non connecté)
 */
function activity_log(string $event, array $meta = [], ?int $userId = null): void
{
    // Utilise l'utilisateur connecté par défaut
    if ($userId === null) {
        $userId = auth_id();
    }

    $ip        = _activity_get_ip();
    $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
    $metaJson  = !empty($meta) ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null;

    try {
        db()->prepare(
            'INSERT INTO user_activity_log (user_id, event, ip_address, user_agent, meta)
             VALUES (:uid, :event, :ip, :ua, :meta)'
        )->execute([
            ':uid'   => $userId,
            ':event' => $event,
            ':ip'    => $ip,
            ':ua'    => $userAgent,
            ':meta'  => $metaJson,
        ]);
    } catch (PDOException $e) {
        // Ne jamais bloquer l'exécution à cause des logs
        error_log('[activity_log] ' . $e->getMessage());
    }
}

/**
 * Récupère l'historique d'activité d'un utilisateur (les N dernières entrées).
 *
 * @param int $userId
 * @param int $limit  Nombre max d'entrées (défaut: 50)
 * @return array
 */
function activity_get_for_user(int $userId, int $limit = 50): array
{
    try {
        $stmt = db()->prepare(
            'SELECT event, ip_address, user_agent, meta, created_at
             FROM user_activity_log
             WHERE user_id = :uid
             ORDER BY created_at DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':uid',   $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit,  PDO::PARAM_INT);
        $stmt->execute();

        return array_map(function (array $row): array {
            $row['meta'] = !empty($row['meta']) ? json_decode($row['meta'], true) : null;
            return $row;
        }, $stmt->fetchAll());

    } catch (PDOException $e) {
        error_log('[activity_get_for_user] ' . $e->getMessage());
        return [];
    }
}

/**
 * Compte les occurrences d'un événement pour un utilisateur dans une fenêtre de temps.
 * Utile pour détecter des comportements anormaux.
 *
 * @param int    $userId
 * @param string $event
 * @param int    $windowMinutes
 */
function activity_count_recent(int $userId, string $event, int $windowMinutes = 60): int
{
    try {
        $stmt = db()->prepare(
            'SELECT COUNT(*) FROM user_activity_log
             WHERE user_id = :uid
               AND event = :event
               AND created_at > DATE_SUB(NOW(), INTERVAL :window MINUTE)'
        );
        $stmt->execute([':uid' => $userId, ':event' => $event, ':window' => $windowMinutes]);
        return (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log('[activity_count_recent] ' . $e->getMessage());
        return 0;
    }
}

/**
 * Nettoie les entrées de log plus vieilles que N jours (GC probabiliste).
 */
function activity_gc(int $keepDays = 90): void
{
    if (mt_rand(1, 200) !== 1) return; // 0.5% de chance par requête

    try {
        db()->prepare(
            'DELETE FROM user_activity_log
             WHERE created_at < DATE_SUB(NOW(), INTERVAL :days DAY)'
        )->execute([':days' => $keepDays]);
    } catch (PDOException $e) {
        error_log('[activity_gc] ' . $e->getMessage());
    }
}

/**
 * @internal Détermine l'IP réelle de la requête.
 */
function _activity_get_ip(): string
{
    // Derrière un proxy/reverse proxy
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR'] as $header) {
        $val = $_SERVER[$header] ?? '';
        if (!empty($val)) {
            // X-Forwarded-For peut contenir une liste — prendre la première IP
            $ip = trim(explode(',', $val)[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

// GC probabiliste
activity_gc();
