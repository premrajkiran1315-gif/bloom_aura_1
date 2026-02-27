-- ════════════════════════════════════════════════════════════════
-- bloom-aura/sql/bloom_aura.sql
-- Full database schema for Bloom Aura e-commerce platform.
-- Run once to set up the database:
--   mysql -u root -p < bloom_aura.sql
-- ════════════════════════════════════════════════════════════════

CREATE DATABASE IF NOT EXISTS bloom_aura_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE bloom_aura_db;

-- ── USERS ────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(120)  NOT NULL,
    email         VARCHAR(255)  NOT NULL UNIQUE,
    password_hash VARCHAR(255)  NOT NULL,
    role          ENUM('customer','admin') NOT NULL DEFAULT 'customer',
    is_active     TINYINT(1)    NOT NULL DEFAULT 1,
    created_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email       (email),
    INDEX idx_role        (role),
    INDEX idx_is_active   (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── CATEGORIES ───────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS categories (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    slug        VARCHAR(120) NOT NULL UNIQUE,
    description TEXT,
    INDEX idx_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── BOUQUETS ─────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS bouquets (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(200)   NOT NULL,
    slug        VARCHAR(220)   NOT NULL UNIQUE,
    description TEXT,
    price       DECIMAL(10,2)  NOT NULL,
    stock       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    category_id INT UNSIGNED   NOT NULL,
    image       VARCHAR(255)   NOT NULL DEFAULT 'default.jpg',
    is_active   TINYINT(1)     NOT NULL DEFAULT 1,
    created_at  DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT,
    INDEX idx_slug        (slug),
    INDEX idx_category    (category_id),
    INDEX idx_price       (price),
    INDEX idx_is_active   (is_active),
    INDEX idx_created_at  (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── ORDERS ───────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS orders (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id          INT UNSIGNED NOT NULL,
    total            DECIMAL(10,2) NOT NULL,
    status           ENUM('pending','processing','shipped','delivered','cancelled')
                     NOT NULL DEFAULT 'pending',
    delivery_name    VARCHAR(120)  NOT NULL,
    delivery_address VARCHAR(255)  NOT NULL,
    delivery_city    VARCHAR(100)  NOT NULL,
    delivery_pincode CHAR(6)       NOT NULL,
    delivery_phone   VARCHAR(15)   NOT NULL,
    payment_method   ENUM('cod','upi','card') NOT NULL,
    created_at       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_user_id   (user_id),
    INDEX idx_status    (status),
    INDEX idx_created_at(created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── ORDER ITEMS ──────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS order_items (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id    INT UNSIGNED   NOT NULL,
    bouquet_id  INT UNSIGNED   NOT NULL,
    quantity    SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    unit_price  DECIMAL(10,2)  NOT NULL,  -- snapshot of price at time of purchase
    FOREIGN KEY (order_id)   REFERENCES orders(id)  ON DELETE CASCADE,
    FOREIGN KEY (bouquet_id) REFERENCES bouquets(id) ON DELETE RESTRICT,
    INDEX idx_order_id   (order_id),
    INDEX idx_bouquet_id (bouquet_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── WISHLIST ─────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS wishlist (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    bouquet_id  INT UNSIGNED NOT NULL,
    added_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE,
    FOREIGN KEY (bouquet_id) REFERENCES bouquets(id) ON DELETE CASCADE,
    UNIQUE KEY uq_user_bouquet (user_id, bouquet_id),  -- prevent duplicates
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── REVIEWS ──────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS reviews (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    bouquet_id  INT UNSIGNED NOT NULL,
    rating      TINYINT UNSIGNED NOT NULL CHECK (rating BETWEEN 1 AND 5),
    comment     TEXT,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE,
    FOREIGN KEY (bouquet_id) REFERENCES bouquets(id) ON DELETE CASCADE,
    UNIQUE KEY uq_user_review (user_id, bouquet_id), -- one review per product per user
    INDEX idx_bouquet_id (bouquet_id),
    INDEX idx_rating     (rating)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── LOGIN ATTEMPTS (rate limiting) ───────────────────────────────
CREATE TABLE IF NOT EXISTS login_attempts (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email        VARCHAR(255) NOT NULL,
    ip_address   VARCHAR(45)  NOT NULL,  -- supports IPv6
    attempted_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email        (email),
    INDEX idx_ip           (ip_address),
    INDEX idx_attempted_at (attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ════════════════════════════════════════════════════════════════
-- SEED DATA
-- ════════════════════════════════════════════════════════════════

-- Default admin user
-- Password is: Admin@1234  (bcrypt hash)
INSERT IGNORE INTO users (name, email, password_hash, role, is_active) VALUES
(
    'Munisha Admin',
    'admin@bloomaura.in',
    '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- bcrypt of 'Admin@1234'
    'admin',
    1
);

-- Seed categories
INSERT IGNORE INTO categories (name, slug, description) VALUES
('Bouquets',   'bouquets',   'Fresh hand-tied flower bouquets for every occasion'),
('Hampers',    'hampers',    'Curated gift hampers with flowers and treats'),
('Chocolates', 'chocolates', 'Premium chocolates paired with blooms'),
('Perfumes',   'perfumes',   'Floral-inspired fragrances'),
('Plants',     'plants',     'Indoor plants and succulents');

-- Seed bouquets (sample)
INSERT IGNORE INTO bouquets (name, slug, description, price, stock, category_id, image) VALUES
('Rose Romance',     'rose-romance',     '12 red roses for that special someone.',    699.00, 25, 1, 'rose-romance.jpg'),
('Sunflower Bliss',  'sunflower-bliss',  'Bright sunflowers to lift any spirits.',    549.00, 30, 1, 'sunflower-bliss.jpg'),
('Pastel Dreams',    'pastel-dreams',    'Soft pastels — perfect for new beginnings.',799.00, 20, 1, 'pastel-dreams.jpg'),
('Birthday Hamper',  'birthday-hamper',  'Roses, chocolates & a birthday card.',     1299.00, 15, 2, 'birthday-hamper.jpg'),
('Love Hamper',      'love-hamper',      'Premium roses with Ferrero Rocher.',       1599.00, 10, 2, 'love-hamper.jpg'),
('Choco Bloom',      'choco-bloom',      'Dark chocolate with dried rose petals.',    499.00, 40, 3, 'choco-bloom.jpg');
