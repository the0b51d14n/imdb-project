-- ══════════════════════════════════════════════════════════════════════════════
--  backend/Database.sql — Supinfo.TV (patch sécurité)
--  Ajoute les tables et colonnes nécessaires au système auth renforcé.
--  À exécuter après le schéma de base ou à intégrer dans init.sql.
-- ══════════════════════════════════════════════════════════════════════════════

-- ── 1. Colonnes de vérification e-mail (table users) ─────────────────────────
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS email_verified_at   DATETIME     NULL DEFAULT NULL AFTER password_hash,
    ADD COLUMN IF NOT EXISTS email_verify_token  VARCHAR(64)  NULL DEFAULT NULL AFTER email_verified_at,
    ADD COLUMN IF NOT EXISTS email_verify_expires DATETIME    NULL DEFAULT NULL AFTER email_verify_token;

-- Index sur le token de vérification
CREATE INDEX IF NOT EXISTS idx_users_verify_token ON users (email_verify_token);

-- ── 2. Table de rate limiting (tentatives de connexion) ───────────────────────
CREATE TABLE IF NOT EXISTS login_attempts (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    ip_address   VARCHAR(45)  NOT NULL,
    attempted_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    INDEX idx_attempts_ip_time (ip_address, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 3. Table de reset de mot de passe ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS password_resets (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    INT UNSIGNED NOT NULL,
    token_hash VARCHAR(64)  NOT NULL,
    expires_at DATETIME     NOT NULL,
    used_at    DATETIME     NULL DEFAULT NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_reset_user (user_id),
    INDEX idx_reset_token (token_hash),
    CONSTRAINT fk_reset_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
