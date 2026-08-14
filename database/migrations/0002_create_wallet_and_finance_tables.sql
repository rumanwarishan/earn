-- Wallet, immutable ledger, deposits, and withdrawals.
-- Balances are stored (not purely computed) and are only ever mutated
-- through WalletService inside a DB transaction with row locking, always
-- paired with an append-only wallet_ledger row.

CREATE TABLE IF NOT EXISTS wallets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    deposited_balance DECIMAL(18,2) NOT NULL DEFAULT 0,
    cashback_balance DECIMAL(18,2) NOT NULL DEFAULT 0,
    referral_balance DECIMAL(18,2) NOT NULL DEFAULT 0,
    pending_cashback_balance DECIMAL(18,2) NOT NULL DEFAULT 0,
    reserved_balance DECIMAL(18,2) NOT NULL DEFAULT 0,
    total_earned DECIMAL(18,2) NOT NULL DEFAULT 0,
    total_deposited DECIMAL(18,2) NOT NULL DEFAULT 0,
    total_withdrawn DECIMAL(18,2) NOT NULL DEFAULT 0,
    is_frozen TINYINT(1) NOT NULL DEFAULT 0,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_wallet_user (user_id),
    CONSTRAINT fk_wallet_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS wallet_ledger (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    transaction_uuid CHAR(36) NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    type ENUM(
        'deposit_credit','withdrawal_hold','withdrawal_release','withdrawal_paid',
        'cashback_pending','cashback_release','cashback_reversal',
        'referral_credit','referral_reversal','welcome_bonus',
        'purchase_debit','admin_credit','admin_debit'
    ) NOT NULL,
    balance_field ENUM('deposited','cashback','referral','pending_cashback','reserved') NOT NULL,
    amount DECIMAL(18,2) NOT NULL COMMENT 'Signed: positive = credit to balance_field, negative = debit',
    previous_balance DECIMAL(18,2) NOT NULL,
    resulting_balance DECIMAL(18,2) NOT NULL,
    currency VARCHAR(10) NOT NULL DEFAULT 'USD',
    reference_type VARCHAR(50) NULL,
    reference_id INT UNSIGNED NULL,
    status ENUM('posted','reversed') NOT NULL DEFAULT 'posted',
    description VARCHAR(255) NULL,
    created_by_type ENUM('system','admin','user') NOT NULL DEFAULT 'system',
    created_by_id INT UNSIGNED NULL,
    admin_reason VARCHAR(255) NULL,
    ip_address VARCHAR(45) NULL,
    metadata JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_ledger_uuid (transaction_uuid),
    KEY idx_ledger_user (user_id, created_at),
    KEY idx_ledger_reference (reference_type, reference_id),
    KEY idx_ledger_type (type),
    CONSTRAINT fk_ledger_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS deposits (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    deposit_uuid CHAR(36) NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    btc_address_shown VARCHAR(120) NOT NULL,
    btc_amount_claimed DECIMAL(20,8) NOT NULL,
    txid VARCHAR(191) NOT NULL,
    status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    btc_usd_rate DECIMAL(18,2) NULL,
    rate_source VARCHAR(50) NULL,
    rate_captured_at DATETIME NULL,
    usd_amount_credited DECIMAL(18,2) NULL,
    admin_id INT UNSIGNED NULL,
    admin_notes VARCHAR(500) NULL,
    rejection_reason VARCHAR(500) NULL,
    reviewed_at DATETIME NULL,
    ip_address VARCHAR(45) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_deposit_uuid (deposit_uuid),
    UNIQUE KEY uniq_deposit_txid (txid),
    KEY idx_deposit_user (user_id),
    KEY idx_deposit_status (status),
    CONSTRAINT fk_deposit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS deposit_reviews (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    deposit_id INT UNSIGNED NOT NULL,
    admin_id INT UNSIGNED NOT NULL,
    action ENUM('note','approved','rejected') NOT NULL,
    notes VARCHAR(500) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_dr_deposit (deposit_id),
    CONSTRAINT fk_dr_deposit FOREIGN KEY (deposit_id) REFERENCES deposits(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS withdrawals (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    withdrawal_uuid CHAR(36) NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    amount_usd DECIMAL(18,2) NOT NULL,
    destination_btc_address VARCHAR(120) NOT NULL,
    status ENUM('pending','approved','processing','paid','completed','rejected','cancelled') NOT NULL DEFAULT 'pending',
    rejection_reason VARCHAR(500) NULL,
    admin_id INT UNSIGNED NULL,
    btc_amount_paid DECIMAL(20,8) NULL,
    btc_rate_used DECIMAL(18,2) NULL,
    payout_txid VARCHAR(191) NULL,
    admin_notes VARCHAR(500) NULL,
    paid_at DATETIME NULL,
    ip_address VARCHAR(45) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_withdrawal_uuid (withdrawal_uuid),
    KEY idx_withdrawal_user (user_id),
    KEY idx_withdrawal_status (status),
    CONSTRAINT fk_withdrawal_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS withdrawal_reviews (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    withdrawal_id INT UNSIGNED NOT NULL,
    admin_id INT UNSIGNED NOT NULL,
    action ENUM('note','approved','rejected','processing','paid','completed','cancelled') NOT NULL,
    notes VARCHAR(500) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_wr_withdrawal (withdrawal_id),
    CONSTRAINT fk_wr_withdrawal FOREIGN KEY (withdrawal_id) REFERENCES withdrawals(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
