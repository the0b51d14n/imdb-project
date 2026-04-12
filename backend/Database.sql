-- ══════════════════════════════════════════════════════════════════════════════
--  backend/Database.sql — Supinfo.TV
--  Schéma complet : auth, catalogue, e-commerce, sécurité
--  Chargé automatiquement par Docker au premier démarrage
--  docker compose up -d
-- ══════════════════════════════════════════════════════════════════════════════

SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;

-- ══════════════════════════════════════════════════════════════════════════════
--  1. UTILISATEURS & AUTH
-- ══════════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS users (
    id                    INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    username              VARCHAR(50)      NOT NULL,
    email                 VARCHAR(255)     NOT NULL,
    password_hash         VARCHAR(255)     NOT NULL,

    -- Vérification e-mail
    email_verified_at     DATETIME         NULL DEFAULT NULL,
    email_verify_token    VARCHAR(64)      NULL DEFAULT NULL,
    email_verify_expires  DATETIME         NULL DEFAULT NULL,

    -- Profil
    avatar_url            VARCHAR(500)     NULL DEFAULT NULL,
    first_name            VARCHAR(100)     NULL DEFAULT NULL,
    last_name             VARCHAR(100)     NULL DEFAULT NULL,

    -- Rôle
    role                  ENUM('user','admin') NOT NULL DEFAULT 'user',

    -- Statut du compte
    is_active             TINYINT(1)       NOT NULL DEFAULT 1,
    banned_at             DATETIME         NULL DEFAULT NULL,
    ban_reason            TEXT             NULL DEFAULT NULL,

    -- Timestamps
    created_at            DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at            DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login_at         DATETIME         NULL DEFAULT NULL,

    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email    (email),
    UNIQUE KEY uq_users_username (username),
    INDEX idx_users_verify_token (email_verify_token),
    INDEX idx_users_role         (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Sessions serveur (alternative sécurisée aux sessions PHP par défaut) ──────
CREATE TABLE IF NOT EXISTS sessions (
    id            VARCHAR(128)     NOT NULL,
    user_id       INT UNSIGNED     NULL DEFAULT NULL,
    ip_address    VARCHAR(45)      NOT NULL,
    user_agent    VARCHAR(500)     NULL DEFAULT NULL,
    payload       TEXT             NOT NULL,
    last_activity DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    INDEX idx_sessions_user      (user_id),
    INDEX idx_sessions_activity  (last_activity),
    CONSTRAINT fk_sessions_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Rate limiting — tentatives de connexion ───────────────────────────────────
CREATE TABLE IF NOT EXISTS login_attempts (
    id           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    ip_address   VARCHAR(45)   NOT NULL,
    email        VARCHAR(255)  NULL DEFAULT NULL,
    attempted_at DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    success      TINYINT(1)    NOT NULL DEFAULT 0,

    PRIMARY KEY (id),
    INDEX idx_attempts_ip_time    (ip_address, attempted_at),
    INDEX idx_attempts_email_time (email, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Reset de mot de passe ─────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS password_resets (
    id         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    user_id    INT UNSIGNED  NOT NULL,
    token_hash VARCHAR(64)   NOT NULL,
    expires_at DATETIME      NOT NULL,
    used_at    DATETIME      NULL DEFAULT NULL,
    created_at DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_reset_user  (user_id),
    INDEX idx_reset_token     (token_hash),
    CONSTRAINT fk_reset_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ══════════════════════════════════════════════════════════════════════════════
--  2. CATALOGUE — RÉALISATEURS, ACTEURS, CATÉGORIES
-- ══════════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS directors (
    id          INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    tmdb_id     INT UNSIGNED   NULL DEFAULT NULL COMMENT 'ID TMDB pour synchronisation',
    full_name   VARCHAR(200)   NOT NULL,
    biography   TEXT           NULL DEFAULT NULL,
    birth_date  DATE           NULL DEFAULT NULL,
    photo_url   VARCHAR(500)   NULL DEFAULT NULL,
    nationality VARCHAR(100)   NULL DEFAULT NULL,
    created_at  DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_directors_tmdb (tmdb_id),
    INDEX idx_directors_name     (full_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS actors (
    id          INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    tmdb_id     INT UNSIGNED   NULL DEFAULT NULL COMMENT 'ID TMDB pour synchronisation',
    full_name   VARCHAR(200)   NOT NULL,
    biography   TEXT           NULL DEFAULT NULL,
    birth_date  DATE           NULL DEFAULT NULL,
    photo_url   VARCHAR(500)   NULL DEFAULT NULL,
    nationality VARCHAR(100)   NULL DEFAULT NULL,
    created_at  DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_actors_tmdb (tmdb_id),
    INDEX idx_actors_name     (full_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS categories (
    id          INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    tmdb_genre_id INT UNSIGNED NULL DEFAULT NULL COMMENT 'ID genre TMDB',
    name        VARCHAR(100)   NOT NULL,
    slug        VARCHAR(100)   NOT NULL,
    description TEXT           NULL DEFAULT NULL,
    created_at  DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_categories_slug    (slug),
    UNIQUE KEY uq_categories_tmdb    (tmdb_genre_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Catégories de base
INSERT IGNORE INTO categories (tmdb_genre_id, name, slug, description) VALUES
(28,    'Action',     'action',     'Films d''action et d''aventure'),
(18,    'Drama',      'drama',      'Films dramatiques et émotionnels'),
(35,    'Comédie',    'comedie',    'Films comiques et humoristiques'),
(27,    'Horreur',    'horreur',    'Films d''horreur et de suspense'),
(878,   'Sci-Fi',     'sci-fi',     'Science-fiction et futurisme'),
(10749, 'Romance',    'romance',    'Films romantiques'),
(53,    'Thriller',   'thriller',   'Thrillers et films à suspense'),
(12,    'Aventure',   'aventure',   'Films d''aventure et d''exploration');

-- ══════════════════════════════════════════════════════════════════════════════
--  3. FILMS
-- ══════════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS movies (
    id              INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    tmdb_id         INT UNSIGNED      NULL DEFAULT NULL COMMENT 'ID TMDB pour synchronisation',
    director_id     INT UNSIGNED      NULL DEFAULT NULL,

    title           VARCHAR(500)      NOT NULL,
    original_title  VARCHAR(500)      NULL DEFAULT NULL,
    slug            VARCHAR(550)      NOT NULL,
    synopsis        TEXT              NULL DEFAULT NULL,
    tagline         VARCHAR(500)      NULL DEFAULT NULL,

    -- Médias
    poster_url      VARCHAR(500)      NULL DEFAULT NULL,
    backdrop_url    VARCHAR(500)      NULL DEFAULT NULL,
    trailer_url     VARCHAR(500)      NULL DEFAULT NULL,

    -- Infos film
    release_date    DATE              NULL DEFAULT NULL,
    duration_min    SMALLINT UNSIGNED NULL DEFAULT NULL COMMENT 'Durée en minutes',
    language        VARCHAR(10)       NULL DEFAULT NULL DEFAULT 'fr',
    country         VARCHAR(100)      NULL DEFAULT NULL,
    rating_tmdb     DECIMAL(3,1)      NULL DEFAULT NULL COMMENT 'Note TMDB /10',
    rating_count    INT UNSIGNED      NULL DEFAULT 0,
    maturity_rating VARCHAR(10)       NULL DEFAULT NULL COMMENT 'Tous publics, -12, -16, -18',

    -- E-commerce
    price           DECIMAL(6,2)      NOT NULL DEFAULT 3.99,
    price_hd        DECIMAL(6,2)      NOT NULL DEFAULT 5.99,
    price_4k        DECIMAL(6,2)      NOT NULL DEFAULT 7.99,
    price_hmac      VARCHAR(64)       NULL DEFAULT NULL COMMENT 'HMAC signé côté serveur',

    -- Disponibilité
    is_available    TINYINT(1)        NOT NULL DEFAULT 1,
    is_featured     TINYINT(1)        NOT NULL DEFAULT 0 COMMENT 'Affiché en homepage',
    featured_order  TINYINT UNSIGNED  NULL DEFAULT NULL,

    -- Timestamps
    created_at      DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_movies_tmdb  (tmdb_id),
    UNIQUE KEY uq_movies_slug  (slug),
    INDEX idx_movies_director  (director_id),
    INDEX idx_movies_release   (release_date),
    INDEX idx_movies_featured  (is_featured, featured_order),
    INDEX idx_movies_available (is_available),
    FULLTEXT idx_movies_search (title, original_title, synopsis),
    CONSTRAINT fk_movies_director
        FOREIGN KEY (director_id) REFERENCES directors(id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Liaisons films ↔ acteurs ──────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS movie_actors (
    movie_id    INT UNSIGNED   NOT NULL,
    actor_id    INT UNSIGNED   NOT NULL,
    role_name   VARCHAR(200)   NULL DEFAULT NULL COMMENT 'Nom du personnage',
    cast_order  TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Ordre d''affichage',

    PRIMARY KEY (movie_id, actor_id),
    INDEX idx_ma_actor (actor_id),
    CONSTRAINT fk_ma_movie
        FOREIGN KEY (movie_id) REFERENCES movies(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_ma_actor
        FOREIGN KEY (actor_id) REFERENCES actors(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Liaisons films ↔ catégories ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS movie_categories (
    movie_id    INT UNSIGNED   NOT NULL,
    category_id INT UNSIGNED   NOT NULL,

    PRIMARY KEY (movie_id, category_id),
    INDEX idx_mc_category (category_id),
    CONSTRAINT fk_mc_movie
        FOREIGN KEY (movie_id) REFERENCES movies(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_mc_category
        FOREIGN KEY (category_id) REFERENCES categories(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Avis utilisateurs ─────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS reviews (
    id          INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    movie_id    INT UNSIGNED   NOT NULL,
    user_id     INT UNSIGNED   NOT NULL,
    rating      TINYINT UNSIGNED NOT NULL COMMENT 'Note de 1 à 5',
    comment     TEXT           NULL DEFAULT NULL,
    is_approved TINYINT(1)     NOT NULL DEFAULT 1,
    created_at  DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_review_user_movie (user_id, movie_id),
    INDEX idx_reviews_movie  (movie_id),
    CONSTRAINT fk_reviews_movie
        FOREIGN KEY (movie_id) REFERENCES movies(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_reviews_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT chk_rating CHECK (rating BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ══════════════════════════════════════════════════════════════════════════════
--  4. E-COMMERCE — PANIER, COMMANDES, PAIEMENTS
-- ══════════════════════════════════════════════════════════════════════════════

-- ── Panier persistant ─────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS cart (
    id          INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    user_id     INT UNSIGNED     NOT NULL,
    movie_id    INT UNSIGNED     NOT NULL,
    quality     ENUM('sd','hd','4k') NOT NULL DEFAULT 'hd',
    price       DECIMAL(6,2)     NOT NULL COMMENT 'Prix au moment de l''ajout',
    added_at    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_cart_user_movie_quality (user_id, movie_id, quality),
    INDEX idx_cart_user  (user_id),
    CONSTRAINT fk_cart_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_cart_movie
        FOREIGN KEY (movie_id) REFERENCES movies(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Commandes ─────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS orders (
    id              INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    user_id         INT UNSIGNED     NOT NULL,
    order_number    VARCHAR(20)      NOT NULL COMMENT 'Référence lisible ex: ORD-2024-00042',
    status          ENUM('pending','paid','failed','refunded','cancelled')
                                     NOT NULL DEFAULT 'pending',

    -- Montants
    subtotal        DECIMAL(8,2)     NOT NULL,
    tax_rate        DECIMAL(4,2)     NOT NULL DEFAULT 20.00 COMMENT 'TVA en %',
    tax_amount      DECIMAL(8,2)     NOT NULL,
    total           DECIMAL(8,2)     NOT NULL,

    -- Infos paiement (jamais de données de carte en clair)
    payment_method  VARCHAR(50)      NULL DEFAULT NULL COMMENT 'card, paypal...',
    card_last4      CHAR(4)          NULL DEFAULT NULL COMMENT '4 derniers chiffres masqués',
    card_brand      VARCHAR(20)      NULL DEFAULT NULL COMMENT 'Visa, Mastercard...',

    -- Timestamps
    created_at      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    paid_at         DATETIME         NULL DEFAULT NULL,
    updated_at      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_orders_number (order_number),
    INDEX idx_orders_user       (user_id),
    INDEX idx_orders_status     (status),
    CONSTRAINT fk_orders_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Lignes de commande ────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS order_items (
    id           INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    order_id     INT UNSIGNED     NOT NULL,
    movie_id     INT UNSIGNED     NOT NULL,
    movie_title  VARCHAR(500)     NOT NULL COMMENT 'Snapshot du titre au moment de l''achat',
    quality      ENUM('sd','hd','4k') NOT NULL DEFAULT 'hd',
    unit_price   DECIMAL(6,2)     NOT NULL COMMENT 'Prix unitaire HT',
    tax_rate     DECIMAL(4,2)     NOT NULL DEFAULT 20.00,

    PRIMARY KEY (id),
    UNIQUE KEY uq_order_movie (order_id, movie_id, quality),
    INDEX idx_oi_order  (order_id),
    INDEX idx_oi_movie  (movie_id),
    CONSTRAINT fk_oi_order
        FOREIGN KEY (order_id) REFERENCES orders(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_oi_movie
        FOREIGN KEY (movie_id) REFERENCES movies(id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Tentatives de paiement (audit trail) ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS payment_attempts (
    id              INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    order_id        INT UNSIGNED     NOT NULL,
    status          ENUM('success','failure','pending') NOT NULL DEFAULT 'pending',
    gateway_ref     VARCHAR(100)     NULL DEFAULT NULL COMMENT 'Référence retournée par le gateway',
    error_code      VARCHAR(50)      NULL DEFAULT NULL,
    error_message   VARCHAR(500)     NULL DEFAULT NULL,
    ip_address      VARCHAR(45)      NOT NULL,
    attempted_at    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    INDEX idx_pa_order  (order_id),
    CONSTRAINT fk_pa_order
        FOREIGN KEY (order_id) REFERENCES orders(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Accès aux films achetés (licence par utilisateur) ─────────────────────────
CREATE TABLE IF NOT EXISTS user_library (
    id          INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    user_id     INT UNSIGNED     NOT NULL,
    movie_id    INT UNSIGNED     NOT NULL,
    order_id    INT UNSIGNED     NOT NULL,
    quality     ENUM('sd','hd','4k') NOT NULL DEFAULT 'hd',
    purchased_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_library_user_movie (user_id, movie_id),
    INDEX idx_lib_user  (user_id),
    INDEX idx_lib_movie (movie_id),
    CONSTRAINT fk_lib_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_lib_movie
        FOREIGN KEY (movie_id) REFERENCES movies(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_lib_order
        FOREIGN KEY (order_id) REFERENCES orders(id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ══════════════════════════════════════════════════════════════════════════════
--  5. WISHLIST (bonus — liste de souhaits)
-- ══════════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS wishlist (
    user_id    INT UNSIGNED   NOT NULL,
    movie_id   INT UNSIGNED   NOT NULL,
    added_at   DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (user_id, movie_id),
    CONSTRAINT fk_wl_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_wl_movie
        FOREIGN KEY (movie_id) REFERENCES movies(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ══════════════════════════════════════════════════════════════════════════════
--  6. REMISE EN ROUTE DES CLÉS ÉTRANGÈRES
-- ══════════════════════════════════════════════════════════════════════════════

SET foreign_key_checks = 1;