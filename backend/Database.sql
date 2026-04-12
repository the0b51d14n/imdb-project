-- ══════════════════════════════════════════════════════════════════════════════
--  backend/Database_patch_cart_items.sql — Supinfo.TV
--
--  PROBLÈME : backend/services/auth.php::cart_sync_count() et
--  backend/services/cart.php référencent la table `cart_items`,
--  mais le schéma principal (Database.sql) définit une table `cart`.
--
--  Ce patch crée `cart_items` avec la structure attendue par les services.
--  À exécuter après Database.sql si vous utilisez le backend PHP complet.
--
--  docker compose exec mysql mysql -u supinfotv_user -p supinfotv \
--    < backend/Database_patch_cart_items.sql
-- ══════════════════════════════════════════════════════════════════════════════

SET NAMES utf8mb4;

-- ── Table cart_items (utilisée par backend/services/cart.php) ────────────────
CREATE TABLE IF NOT EXISTS cart_items (
    id         INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    user_id    INT UNSIGNED     NOT NULL,
    tmdb_id    INT UNSIGNED     NOT NULL,
    title      VARCHAR(255)     NOT NULL,
    poster     VARCHAR(512)     NULL DEFAULT NULL,
    price      DECIMAL(6,2)     NOT NULL,
    added_at   DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_cart_user_tmdb (user_id, tmdb_id),
    INDEX idx_cart_items_user (user_id),
    CONSTRAINT fk_ci_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table orders (version simplifiée pour cart_checkout()) ───────────────────
-- Le schéma principal a une version plus complète avec order_number, etc.
-- On crée une version compatible si la table n'existe pas encore.
CREATE TABLE IF NOT EXISTS orders (
    id           INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    user_id      INT UNSIGNED     NOT NULL,
    total_amount DECIMAL(8,2)     NOT NULL,
    created_at   DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    INDEX idx_orders_user (user_id),
    CONSTRAINT fk_orders_user_ci
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table order_items (pour cart_checkout() et orders_get_history()) ─────────
CREATE TABLE IF NOT EXISTS order_items (
    id         INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    order_id   INT UNSIGNED     NOT NULL,
    tmdb_id    INT UNSIGNED     NOT NULL,
    title      VARCHAR(255)     NOT NULL,
    poster     VARCHAR(512)     NULL DEFAULT NULL,
    price      DECIMAL(6,2)     NOT NULL,

    PRIMARY KEY (id),
    INDEX idx_oi_order (order_id),
    CONSTRAINT fk_oi_order_ci
        FOREIGN KEY (order_id) REFERENCES orders(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;