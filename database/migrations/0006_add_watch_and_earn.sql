-- Watch & Earn: admin-configured advertisements that users watch for a
-- required duration to earn a reward credited into the EXISTING wallet
-- ledger (no separate ad balance). One reward per user per advertisement,
-- enforced by a database unique constraint.

CREATE TABLE IF NOT EXISTS advertisements (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    description VARCHAR(500) NULL,
    type ENUM('image','external') NOT NULL DEFAULT 'image',
    image_path VARCHAR(255) NULL,
    destination_url VARCHAR(500) NULL COMMENT 'Advertiser landing page - opened in a new tab while the watch timer runs',
    reward_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    watch_seconds SMALLINT UNSIGNED NOT NULL DEFAULT 15,
    max_completions INT UNSIGNED NULL COMMENT 'NULL = unlimited total completions across all users',
    starts_at DATETIME NULL,
    ends_at DATETIME NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_ads_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- One row per watch attempt. Reward amount and required duration are
-- snapshotted from the advertisement at session-start time, so an in-flight
-- session's terms can never be changed by a later admin edit, and
-- completion never has to trust anything the client reports.
CREATE TABLE IF NOT EXISTS ad_watch_sessions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_uuid CHAR(36) NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    advertisement_id INT UNSIGNED NOT NULL,
    reward_amount DECIMAL(14,2) NOT NULL,
    watch_seconds SMALLINT UNSIGNED NOT NULL,
    started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    completed_at DATETIME NULL,
    status ENUM('active','completed','expired') NOT NULL DEFAULT 'active',
    ip_address VARCHAR(45) NULL,
    UNIQUE KEY uniq_session_uuid (session_uuid),
    KEY idx_aws_user (user_id),
    KEY idx_aws_ad (advertisement_id),
    CONSTRAINT fk_aws_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_aws_ad FOREIGN KEY (advertisement_id) REFERENCES advertisements(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- The authoritative "reward already issued" record. The unique key on
-- (user_id, advertisement_id) is what actually prevents a duplicate reward -
-- everything else is defense in depth on top of this constraint.
CREATE TABLE IF NOT EXISTS ad_completions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    advertisement_id INT UNSIGNED NOT NULL,
    watch_session_id BIGINT UNSIGNED NOT NULL,
    reward_amount DECIMAL(14,2) NOT NULL,
    ledger_uuid CHAR(36) NULL COMMENT 'wallet_ledger.transaction_uuid this reward was posted as',
    completed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_ad_completion (user_id, advertisement_id),
    KEY idx_ac_ad (advertisement_id),
    CONSTRAINT fk_ac_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_ac_ad FOREIGN KEY (advertisement_id) REFERENCES advertisements(id) ON DELETE RESTRICT,
    CONSTRAINT fk_ac_session FOREIGN KEY (watch_session_id) REFERENCES ad_watch_sessions(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE wallet_ledger MODIFY COLUMN type ENUM(
    'deposit_credit','withdrawal_hold','withdrawal_release','withdrawal_paid',
    'cashback_pending','cashback_release','cashback_reversal',
    'referral_credit','referral_reversal','welcome_bonus',
    'purchase_debit','admin_credit','admin_debit','task_reward','ad_reward'
) NOT NULL;
