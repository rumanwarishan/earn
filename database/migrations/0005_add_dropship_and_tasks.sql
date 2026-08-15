-- Wallet-funded dropship products, order shipping details, and an
-- admin-configurable welcome/daily task (quest) system.

ALTER TABLE products
  ADD COLUMN fulfillment_type ENUM('affiliate','dropship') NOT NULL DEFAULT 'affiliate' AFTER cashback_enabled,
  ADD COLUMN stock_quantity INT UNSIGNED NULL COMMENT 'NULL = unlimited/not tracked' AFTER fulfillment_type;

ALTER TABLE orders
  ADD COLUMN fulfillment_type ENUM('affiliate','dropship') NOT NULL DEFAULT 'affiliate' AFTER marketplace_id,
  ADD COLUMN payment_source ENUM('wallet','external') NOT NULL DEFAULT 'external' AFTER fulfillment_type,
  ADD COLUMN shipping_name VARCHAR(150) NULL,
  ADD COLUMN shipping_phone VARCHAR(40) NULL,
  ADD COLUMN shipping_address_line1 VARCHAR(255) NULL,
  ADD COLUMN shipping_address_line2 VARCHAR(255) NULL,
  ADD COLUMN shipping_city VARCHAR(100) NULL,
  ADD COLUMN shipping_state VARCHAR(100) NULL,
  ADD COLUMN shipping_postal_code VARCHAR(20) NULL,
  ADD COLUMN shipping_country VARCHAR(100) NULL;

ALTER TABLE wallet_ledger MODIFY COLUMN type ENUM(
    'deposit_credit','withdrawal_hold','withdrawal_release','withdrawal_paid',
    'cashback_pending','cashback_release','cashback_reversal',
    'referral_credit','referral_reversal','welcome_bonus',
    'purchase_debit','admin_credit','admin_debit','task_reward'
) NOT NULL;

CREATE TABLE IF NOT EXISTS tasks (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    description VARCHAR(500) NULL,
    type ENUM('welcome','daily','one_time') NOT NULL DEFAULT 'daily',
    reward_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS task_completions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    task_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    -- For welcome/one_time tasks this is just the day it was claimed (dedupe
    -- is "any row exists" in the service layer). For daily tasks this IS the
    -- dedupe key together with task_id+user_id - one claim per calendar day.
    completion_date DATE NOT NULL,
    reward_amount DECIMAL(14,2) NOT NULL,
    completed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_task_completion (task_id, user_id, completion_date),
    KEY idx_tc_user (user_id),
    CONSTRAINT fk_tc_task FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    CONSTRAINT fk_tc_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
