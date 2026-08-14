-- Core auth, referral, wallet, and configuration schema.
-- All monetary columns use DECIMAL to avoid floating point rounding errors.

CREATE TABLE IF NOT EXISTS site_settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `key` VARCHAR(100) NOT NULL,
    `value` TEXT NULL,
    `type` VARCHAR(20) NOT NULL DEFAULT 'string',
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_settings_key (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS membership_levels (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    slug VARCHAR(50) NOT NULL,
    icon VARCHAR(50) NOT NULL DEFAULT 'bronze',
    color VARCHAR(20) NOT NULL DEFAULT '#cd7f32',
    description VARCHAR(255) NULL,
    min_total_deposited DECIMAL(18,2) NOT NULL DEFAULT 0,
    min_total_purchased DECIMAL(18,2) NOT NULL DEFAULT 0,
    cashback_multiplier DECIMAL(6,3) NOT NULL DEFAULT 1.000,
    referral_bonus_multiplier DECIMAL(6,3) NOT NULL DEFAULT 1.000,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_level_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(190) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    referral_code VARCHAR(20) NOT NULL,
    referred_by_user_id INT UNSIGNED NULL,
    membership_level_id INT UNSIGNED NULL,
    status ENUM('active','suspended','banned') NOT NULL DEFAULT 'active',
    email_verified_at DATETIME NULL,
    last_login_at DATETIME NULL,
    last_login_ip VARCHAR(45) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_users_uuid (uuid),
    UNIQUE KEY uniq_users_email (email),
    UNIQUE KEY uniq_users_referral_code (referral_code),
    KEY idx_users_referred_by (referred_by_user_id),
    KEY idx_users_status (status),
    CONSTRAINT fk_users_referred_by FOREIGN KEY (referred_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_users_membership FOREIGN KEY (membership_level_id) REFERENCES membership_levels(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS email_verifications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    consumed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_ev_user (user_id),
    UNIQUE KEY uniq_ev_token (token_hash),
    CONSTRAINT fk_ev_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS password_resets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    consumed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_pr_user (user_id),
    UNIQUE KEY uniq_pr_token (token_hash),
    CONSTRAINT fk_pr_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS login_attempts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    identifier VARCHAR(190) NOT NULL,
    ip VARCHAR(45) NOT NULL,
    success TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_la_identifier (identifier, created_at),
    KEY idx_la_ip (ip, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS referral_settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    level INT UNSIGNED NOT NULL,
    bonus_type ENUM('fixed','percentage') NOT NULL DEFAULT 'fixed',
    bonus_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
    min_qualifying_deposit DECIMAL(18,2) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_referral_level (level)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS referral_relationships (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    referrer_user_id INT UNSIGNED NOT NULL,
    referred_user_id INT UNSIGNED NOT NULL,
    level INT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_referrer_referred_level (referrer_user_id, referred_user_id, level),
    KEY idx_referred (referred_user_id),
    CONSTRAINT fk_rr_referrer FOREIGN KEY (referrer_user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_rr_referred FOREIGN KEY (referred_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS referral_rewards (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reward_uuid CHAR(36) NOT NULL,
    referrer_user_id INT UNSIGNED NOT NULL,
    referred_user_id INT UNSIGNED NOT NULL,
    level INT UNSIGNED NOT NULL DEFAULT 1,
    reward_type ENUM('welcome_bonus','deposit_bonus') NOT NULL,
    amount DECIMAL(18,2) NOT NULL,
    status ENUM('credited','reversed') NOT NULL DEFAULT 'credited',
    trigger_reference_type VARCHAR(50) NULL,
    trigger_reference_id INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_reward_uuid (reward_uuid),
    -- Dedupe guard: for welcome_bonus, referrer_user_id = referred_user_id and level = 0
    -- (one welcome bonus per account ever). For deposit_bonus, one bonus per
    -- referrer/referred/level ever (multi-level trees don't double-pay a level).
    UNIQUE KEY uniq_reward_dedupe (referrer_user_id, referred_user_id, level, reward_type),
    KEY idx_rr_referrer (referrer_user_id),
    CONSTRAINT fk_rw_referrer FOREIGN KEY (referrer_user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_rw_referred FOREIGN KEY (referred_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
