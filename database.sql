-- ============================================================
-- Petal Moments Flowers & Events - Database Schema
-- Target: MySQL / MariaDB (XAMPP)
-- ============================================================

CREATE DATABASE IF NOT EXISTS petal_moments
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE petal_moments;

-- ------------------------------------------------------------
-- USERS (customers and admins)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS users;
CREATE TABLE users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  first_name    VARCHAR(60)  NOT NULL,
  last_name     VARCHAR(60)  NOT NULL,
  email         VARCHAR(120) NOT NULL UNIQUE,
  password      VARCHAR(255) NOT NULL,
  phone         VARCHAR(20)  NULL,
  address       VARCHAR(255) NULL,
  role          ENUM('customer','admin') NOT NULL DEFAULT 'customer',
  is_active     TINYINT(1)   NOT NULL DEFAULT 1,
  created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- CATEGORIES (occasion groupings such as Birthday, Wedding...)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS categories;
CREATE TABLE categories (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(80)  NOT NULL UNIQUE,
  slug        VARCHAR(100) NOT NULL UNIQUE,
  description TEXT         NULL,
  image       VARCHAR(255) NULL,
  is_active   TINYINT(1)   NOT NULL DEFAULT 1
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- PRODUCTS (floral arrangements)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS products;
CREATE TABLE products (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category_id  INT UNSIGNED   NULL,
  name         VARCHAR(120)   NOT NULL,
  slug         VARCHAR(140)   NOT NULL UNIQUE,
  description  TEXT           NULL,
  price        DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
  image        VARCHAR(255)   NULL,
  stock        INT            NOT NULL DEFAULT 0,
  is_featured  TINYINT(1)     NOT NULL DEFAULT 0,
  is_active    TINYINT(1)     NOT NULL DEFAULT 1,
  created_at   TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_products_category
    FOREIGN KEY (category_id) REFERENCES categories(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- ORDERS (customer orders/transactions)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS orders;
CREATE TABLE orders (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id         INT UNSIGNED   NOT NULL,
  total_amount    DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
  delivery_address VARCHAR(255)  NOT NULL,
  delivery_date   DATE           NULL,
  delivery_notes  TEXT           NULL,
  payment_method  ENUM('cash_on_delivery','gcash') NOT NULL DEFAULT 'cash_on_delivery',
  status          ENUM('pending','processing','delivered','cancelled')
                                 NOT NULL DEFAULT 'pending',
  created_at      TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_orders_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- ORDER ITEMS (lines composing an order)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS order_items;
CREATE TABLE order_items (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id    INT UNSIGNED   NOT NULL,
  product_id  INT UNSIGNED   NOT NULL,
  quantity    INT            NOT NULL DEFAULT 1,
  unit_price  DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
  subtotal    DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
  CONSTRAINT fk_items_order
    FOREIGN KEY (order_id) REFERENCES orders(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_items_product
    FOREIGN KEY (product_id) REFERENCES products(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- ORDER STATUS HISTORY (audit trail of status changes)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS order_status_history;
CREATE TABLE order_status_history (
  id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id  INT UNSIGNED NOT NULL,
  status    VARCHAR(30)  NOT NULL,
  note      TEXT         NULL,
  changed_at TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_history_order
    FOREIGN KEY (order_id) REFERENCES orders(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- WISHLIST (saved favorite products)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS wishlist;
CREATE TABLE wishlist (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NOT NULL,
  product_id  INT UNSIGNED NOT NULL,
  created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_wishlist_user_product (user_id, product_id),
  CONSTRAINT fk_wishlist_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_wishlist_product
    FOREIGN KEY (product_id) REFERENCES products(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- EVENT INQUIRIES (event styling requests)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS event_inquiries;
CREATE TABLE event_inquiries (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED   NULL,
  name        VARCHAR(120)   NULL,
  email       VARCHAR(120)   NULL,
  phone       VARCHAR(30)    NULL,
  event_type  VARCHAR(80)    NOT NULL,
  event_date  DATE           NULL,
  guest_count INT            NULL,
  budget      DECIMAL(10,2)  NULL,
  message     TEXT           NULL,
  status      ENUM('pending','contacted','completed') NOT NULL DEFAULT 'pending',
  created_at  TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_inquiry_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- NEWSLETTER SUBSCRIBERS
-- ------------------------------------------------------------
DROP TABLE IF EXISTS newsletter_subscribers;
CREATE TABLE newsletter_subscribers (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email        VARCHAR(120) NOT NULL UNIQUE,
  is_subscribed TINYINT(1)  NOT NULL DEFAULT 1,
  created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- SEED DATA
-- ============================================================

-- Admin account (password: admin123)
INSERT INTO users (first_name, last_name, email, password, role)
VALUES ('Ashlei', 'Burdeos', 'admin@petalmoments.com',
        '$2y$10$XAIDZ0KnNxAbb0jeQFexHu4FNaG4OocJLQZpkSze0cuz6e4hGbJQO', 'admin');

-- customer account (password: customer123)
INSERT INTO users (first_name, last_name, email, password, phone, address, role)
VALUES ('Maria', 'Santos', 'customer@petalmoments.com',
        '$2y$10$hVrgugJib6/fp7/TlULkvOcJ0kLsCuFSeDVCU5OjjsS4aj5AsHfbK', '09171234567', '123 Rizal St., Montalban', 'customer');

-- Categories
INSERT INTO categories (name, slug, description) VALUES
('Birthday',   'birthday',  'Bright and joyful arrangements for birthdays.'),
('Weddings',   'weddings',  'Sweet and timeless blooms for your big day.'),
('Bouquet',    'bouquet',   'Classic hand-tied bouquets for any occasion.'),
('Funeral',    'funeral',   'Gentle and meaningful arrangements of sympathy.');

-- Products
INSERT INTO products (category_id, name, slug, description, price, image, stock, is_featured) VALUES
(3, 'Blush Garden',     'blush-garden',     'Roses, Carnations and seasonal fillers in soft blush tones.', 899.00,  'https://images.unsplash.com/photo-1518895949257-7621c3c786d7?auto=format&fit=crop&w=800&q=80', 0, 1),
(3, 'Pure Grace',       'pure-grace',       'White roses, baby\'s breath and fresh greens.',                1099.00, 'https://images.unsplash.com/photo-1527061011665-3652c757a4d4?auto=format&fit=crop&w=800&q=80', 0, 1),
(3, 'Sunshine Hello',   'sunshine-hello',   'Sunflowers, chrysanthemums and bright greens.',                799.00,  'https://images.unsplash.com/photo-1533616688419-b7a585564566?auto=format&fit=crop&w=800&q=80', 0, 1),
(3, 'Classic Romance',  'classic-romance',  'Premium red roses and eucalyptus.',                           1299.00, 'https://images.unsplash.com/photo-1561181286-d3fee7d55364?auto=format&fit=crop&w=800&q=80', 0, 1),
(1, 'Birthday Joy',     'birthday-joy',     'A cheerful medley perfect for celebrations.',                 999.00,  'https://images.unsplash.com/photo-1490750967868-88aa4486c946?auto=format&fit=crop&w=1200&q=85', 0, 0),
(2, 'Wedding Radiance', 'wedding-radiance', 'An elegant white arrangement for wedding ceremonies.',        1599.00, 'https://images.unsplash.com/photo-1523438885200-e635ba2c371e?auto=format&fit=crop&w=700&q=80', 0, 0),
(4, 'Sympathy Tribute', 'sympathy-tribute', 'A gentle, calming tribute for moments of loss.',              1199.00, 'https://images.unsplash.com/photo-1487070183336-b863922373d4?auto=format&fit=crop&w=700&q=80', 0, 0);

-- Newsletter sample subscribers
INSERT INTO newsletter_subscribers (email) VALUES
('sample1@example.com'),
('sample2@example.com');
