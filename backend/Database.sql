-- ══════════════════════════════════════════════════════════════════════════════
--  backend/Database.sql — Supinfo.TV
--  Schéma complet de la base de données.
--  Remplace l'ancien fichier qui ne contenait que le patch cart_items.
--
--  Initialisation :
--    docker compose exec mysql mysql -u supinfotv_user -p supinfotv < backend/Database.sql
--  Ou au premier démarrage Docker, il est chargé automatiquement.
-- ══════════════════════════════════════════════════════════════════════════════

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ── Table : users ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
    id                    INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    username              VARCHAR(60)      NOT NULL,
    email                 VARCHAR(255)     NOT NULL,
    password_hash         VARCHAR(255)     NOT NULL,

    -- Vérification e-mail
    email_verified_at     DATETIME         NULL DEFAULT NULL,
    email_verify_token    VARCHAR(64)      NULL DEFAULT NULL,
    email_verify_expires  DATETIME         NULL DEFAULT NULL,

    created_at            DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at            DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email    (email),
    UNIQUE KEY uq_users_username (username),
    INDEX idx_users_email_verify_token (email_verify_token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table : login_attempts (rate limiting) ────────────────────────────────────
CREATE TABLE IF NOT EXISTS login_attempts (
    id           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    ip_address   VARCHAR(45)   NOT NULL,
    attempted_at DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    INDEX idx_login_attempts_ip (ip_address),
    INDEX idx_login_attempts_at (attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table : password_resets ───────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS password_resets (
    id         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    user_id    INT UNSIGNED  NOT NULL,
    token_hash VARCHAR(64)   NOT NULL,
    expires_at DATETIME      NOT NULL,
    used_at    DATETIME      NULL DEFAULT NULL,
    created_at DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_pr_user (user_id),
    INDEX idx_pr_token (token_hash),
    CONSTRAINT fk_pr_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table : cart_items ────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS cart_items (
    id         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    user_id    INT UNSIGNED  NOT NULL,
    tmdb_id    INT UNSIGNED  NOT NULL,
    title      VARCHAR(255)  NOT NULL,
    poster     VARCHAR(512)  NULL DEFAULT NULL,
    price      DECIMAL(6,2)  NOT NULL,
    added_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_cart_user_tmdb (user_id, tmdb_id),
    INDEX idx_cart_items_user (user_id),
    CONSTRAINT fk_ci_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table : orders ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS orders (
    id           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    user_id      INT UNSIGNED  NOT NULL,
    total_amount DECIMAL(8,2)  NOT NULL,
    created_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    INDEX idx_orders_user (user_id),
    CONSTRAINT fk_orders_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table : order_items ───────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS order_items (
    id         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    order_id   INT UNSIGNED  NOT NULL,
    tmdb_id    INT UNSIGNED  NOT NULL,
    title      VARCHAR(255)  NOT NULL,
    poster     VARCHAR(512)  NULL DEFAULT NULL,
    price      DECIMAL(6,2)  NOT NULL,

    PRIMARY KEY (id),
    INDEX idx_oi_order (order_id),
    CONSTRAINT fk_oi_order
        FOREIGN KEY (order_id) REFERENCES orders(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;