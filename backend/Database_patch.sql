-- ══════════════════════════════════════════════════════════════════════════════
--  backend/Database_patch_v2.sql — Supinfo.TV
--  Patch d'évolution : cache TMDB, watchlist, logs d'activité.
--
--  Application :
--    docker compose exec mysql mysql -u supinfotv_user -p supinfotv \
--      < backend/Database_patch_v2.sql
-- ══════════════════════════════════════════════════════════════════════════════

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ── Table : tmdb_cache ────────────────────────────────────────────────────────
-- Cache des réponses API TMDB avec TTL configurable.
-- Réduit les appels API de ~80% sur les pages populaires.
CREATE TABLE IF NOT EXISTS tmdb_cache (
    cache_key  VARCHAR(128)   NOT NULL,
    payload    MEDIUMTEXT     NOT NULL,
    expires_at DATETIME       NOT NULL,
    updated_at DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (cache_key),
    INDEX idx_tmdb_cache_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Cache des réponses API TMDB avec TTL';

-- ── Table : watchlist ─────────────────────────────────────────────────────────
-- Liste de souhaits par utilisateur.
CREATE TABLE IF NOT EXISTS watchlist (
    id         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    user_id    INT UNSIGNED  NOT NULL,
    tmdb_id    INT UNSIGNED  NOT NULL,
    title      VARCHAR(255)  NOT NULL,
    poster     VARCHAR(512)  NULL DEFAULT NULL,
    year       VARCHAR(4)    NULL DEFAULT NULL,
    note       DECIMAL(3,1)  NULL DEFAULT NULL,
    added_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_watchlist_user_tmdb (user_id, tmdb_id),
    INDEX idx_watchlist_user (user_id),
    CONSTRAINT fk_wl_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Liste de souhaits des utilisateurs';

-- ── Table : user_activity_log ─────────────────────────────────────────────────
-- Journal des actions utilisateur (connexions, achats, etc.).
-- Conservé 90 jours (GC automatique via le service PHP).
CREATE TABLE IF NOT EXISTS user_activity_log (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    INT UNSIGNED    NULL DEFAULT NULL,  -- NULL = non authentifié
    event      VARCHAR(64)     NOT NULL,
    ip_address VARCHAR(45)     NULL DEFAULT NULL,
    user_agent VARCHAR(255)    NULL DEFAULT NULL,
    meta       JSON            NULL DEFAULT NULL,
    created_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    INDEX idx_ual_user       (user_id),
    INDEX idx_ual_event      (event),
    INDEX idx_ual_created_at (created_at),
    INDEX idx_ual_user_event (user_id, event),
    CONSTRAINT fk_ual_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Journal des actions utilisateur (rétention 90 jours)';

-- ── Table : movie_ratings ─────────────────────────────────────────────────────
-- Notations et commentaires des acheteurs.
-- Réservé aux utilisateurs ayant acheté le film (validé côté PHP).
CREATE TABLE IF NOT EXISTS movie_ratings (
    id         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    user_id    INT UNSIGNED  NOT NULL,
    tmdb_id    INT UNSIGNED  NOT NULL,
    rating     TINYINT       NOT NULL CHECK (rating BETWEEN 1 AND 5),
    comment    TEXT          NULL DEFAULT NULL,
    created_at DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_rating_user_tmdb (user_id, tmdb_id),
    INDEX idx_rating_tmdb (tmdb_id),
    INDEX idx_rating_user (user_id),
    CONSTRAINT fk_rating_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Notations et avis des acheteurs';

SET FOREIGN_KEY_CHECKS = 1;

-- ── Vue : stats films populaires ──────────────────────────────────────────────
-- Vue utile pour les recommandations basées sur les achats réels de la plateforme.
CREATE OR REPLACE VIEW v_popular_movies AS
SELECT
    oi.tmdb_id,
    oi.title,
    oi.poster,
    COUNT(DISTINCT oi.order_id)  AS purchase_count,
    AVG(mr.rating)               AS avg_rating,
    COUNT(DISTINCT mr.id)        AS rating_count,
    COUNT(DISTINCT wl.id)        AS watchlist_count
FROM order_items oi
LEFT JOIN movie_ratings mr ON mr.tmdb_id = oi.tmdb_id
LEFT JOIN watchlist     wl ON wl.tmdb_id = oi.tmdb_id
GROUP BY oi.tmdb_id, oi.title, oi.poster
ORDER BY purchase_count DESC, avg_rating DESC;
