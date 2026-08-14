-- Marketplace, products, orders, cashback and click tracking.

CREATE TABLE IF NOT EXISTS marketplaces (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL,
    slug VARCHAR(80) NOT NULL,
    logo_path VARCHAR(255) NULL,
    website_url VARCHAR(255) NULL,
    affiliate_base_url VARCHAR(255) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_marketplace_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS product_categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL,
    parent_id INT UNSIGNED NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    UNIQUE KEY uniq_category_slug (slug),
    CONSTRAINT fk_category_parent FOREIGN KEY (parent_id) REFERENCES product_categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS products (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    slug VARCHAR(220) NOT NULL,
    description TEXT NULL,
    short_description VARCHAR(500) NULL,
    image_path VARCHAR(255) NULL,
    marketplace_id INT UNSIGNED NOT NULL,
    category_id INT UNSIGNED NULL,
    marketplace_product_id VARCHAR(120) NULL,
    external_url VARCHAR(500) NOT NULL,
    affiliate_url VARCHAR(500) NULL,
    original_price DECIMAL(14,2) NULL,
    display_price DECIMAL(14,2) NOT NULL,
    cashback_type ENUM('percentage','fixed') NOT NULL DEFAULT 'percentage',
    cashback_value DECIMAL(8,3) NOT NULL DEFAULT 0,
    cashback_enabled TINYINT(1) NOT NULL DEFAULT 1,
    tags VARCHAR(500) NULL,
    stock_status ENUM('in_stock','out_of_stock','unknown') NOT NULL DEFAULT 'unknown',
    is_featured TINYINT(1) NOT NULL DEFAULT 0,
    is_published TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    starts_at DATETIME NULL,
    ends_at DATETIME NULL,
    metadata JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_product_slug (slug),
    KEY idx_product_marketplace (marketplace_id),
    KEY idx_product_category (category_id),
    KEY idx_product_published (is_published, is_featured),
    CONSTRAINT fk_product_marketplace FOREIGN KEY (marketplace_id) REFERENCES marketplaces(id) ON DELETE RESTRICT,
    CONSTRAINT fk_product_category FOREIGN KEY (category_id) REFERENCES product_categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS product_images (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    KEY idx_pi_product (product_id),
    CONSTRAINT fk_pi_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS product_clicks (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    click_uuid CHAR(36) NOT NULL,
    user_id INT UNSIGNED NULL,
    product_id INT UNSIGNED NOT NULL,
    marketplace_id INT UNSIGNED NOT NULL,
    tracking_url VARCHAR(500) NOT NULL,
    campaign_source VARCHAR(100) NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_click_uuid (click_uuid),
    KEY idx_click_user (user_id),
    KEY idx_click_product (product_id),
    CONSTRAINT fk_click_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    CONSTRAINT fk_click_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS orders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_uuid CHAR(36) NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    product_click_id BIGINT UNSIGNED NULL,
    marketplace_id INT UNSIGNED NOT NULL,
    external_order_reference VARCHAR(150) NULL,
    product_price DECIMAL(14,2) NOT NULL,
    cashback_percentage DECIMAL(8,3) NOT NULL DEFAULT 0,
    cashback_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    status ENUM('pending','tracking','confirmed','completed','cancelled','refunded') NOT NULL DEFAULT 'pending',
    cashback_status ENUM('not_applicable','pending','credited','reversed') NOT NULL DEFAULT 'pending',
    admin_id INT UNSIGNED NULL,
    admin_notes VARCHAR(500) NULL,
    ordered_at DATETIME NULL,
    confirmed_at DATETIME NULL,
    completed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_order_uuid (order_uuid),
    KEY idx_order_user (user_id),
    KEY idx_order_status (status),
    CONSTRAINT fk_order_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_order_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT,
    CONSTRAINT fk_order_marketplace FOREIGN KEY (marketplace_id) REFERENCES marketplaces(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cashback_transactions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    amount DECIMAL(14,2) NOT NULL,
    status ENUM('pending','released','reversed') NOT NULL DEFAULT 'pending',
    admin_id INT UNSIGNED NULL,
    reason VARCHAR(500) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_cashback_order (order_id),
    KEY idx_cashback_user (user_id),
    CONSTRAINT fk_cashback_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_cashback_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
