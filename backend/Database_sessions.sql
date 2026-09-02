-- ══════════════════════════════════════════════════════════════════════════════
--  backend/Database_sessions.sql — Supinfo.TV
--  Patch : stockage des sessions PHP en base de données.
--
--  Requis pour tout hébergement sans disque persistant (Vercel, Lambda…) où
--  les sessions par fichiers sont perdues entre deux requêtes.
--  Voir backend/services/session.php
--
--  Application :
--    docker compose exec mysql mysql -u supinfotv_user -p supinfotv \
--      < backend/Database_sessions.sql
--
--    ou, sur une base managée :
--    mysql -h <host> -u <user> -p <db> < backend/Database_sessions.sql
-- ══════════════════════════════════════════════════════════════════════════════

SET NAMES utf8mb4;

-- ── Table : sessions ──────────────────────────────────────────────────────────
-- Une ligne par session PHP active.
-- payload      : données sérialisées par PHP (session_encode)
-- last_activity: timestamp UNIX du dernier accès, sert au TTL et au GC
CREATE TABLE IF NOT EXISTS sessions (
    session_id    VARCHAR(128)  NOT NULL,
    payload       MEDIUMBLOB    NOT NULL,
    last_activity INT UNSIGNED  NOT NULL,

    PRIMARY KEY (session_id),
    INDEX idx_sessions_last_activity (last_activity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Sessions PHP persistées en base (hébergement serverless)';
