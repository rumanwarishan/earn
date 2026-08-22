-- "Billions Flight" - a crash-style multiplier game paid out purely in
-- non-redeemable virtual Game Points (GP), completely separate from the
-- real financial wallet (wallets/wallet_ledger). No table here has any
-- foreign key or code path back into the financial wallet - GP cannot be
-- deposited, withdrawn, or converted to/from USD/BTC/USDT by design.

CREATE TABLE IF NOT EXISTS game_point_wallets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    balance DECIMAL(18,2) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_gpw_user (user_id),
    CONSTRAINT fk_gpw_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Append-only, mirrors wallet_ledger's discipline: every balance change is a
-- row here with a before/after snapshot, written in the same transaction
-- that updates game_point_wallets.balance under a row lock.
CREATE TABLE IF NOT EXISTS game_point_ledger (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    wallet_id INT UNSIGNED NOT NULL,
    type ENUM('game_entry','game_cashout','game_loss','admin_grant','admin_adjustment','daily_bonus') NOT NULL,
    amount DECIMAL(18,2) NOT NULL COMMENT 'Signed: positive credit, negative debit',
    balance_before DECIMAL(18,2) NOT NULL,
    balance_after DECIMAL(18,2) NOT NULL,
    reference_type VARCHAR(50) NULL,
    reference_id BIGINT UNSIGNED NULL,
    description VARCHAR(255) NULL,
    admin_id INT UNSIGNED NULL COMMENT 'Set only for admin_grant/admin_adjustment rows',
    admin_reason VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_gpl_user (user_id),
    KEY idx_gpl_type (type),
    CONSTRAINT fk_gpl_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_gpl_wallet FOREIGN KEY (wallet_id) REFERENCES game_point_wallets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- A single global round at a time. The crash_multiplier is generated with
-- random_int() the instant the row is created (WAITING) and is never sent
-- to the client until the round has actually crashed - see GameRoundService.
CREATE TABLE IF NOT EXISTS game_rounds (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    round_uuid CHAR(36) NOT NULL,
    status ENUM('waiting','running','crashed','completed') NOT NULL DEFAULT 'waiting',
    crash_multiplier DECIMAL(10,2) NOT NULL,
    starts_at DATETIME NOT NULL COMMENT 'When the countdown ends and the round starts running',
    started_at DATETIME NULL,
    crashed_at DATETIME NULL,
    completed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_gr_uuid (round_uuid),
    KEY idx_gr_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS game_bets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    round_id BIGINT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    stake DECIMAL(18,2) NOT NULL,
    status ENUM('active','cashed_out','lost') NOT NULL DEFAULT 'active',
    cashout_multiplier DECIMAL(10,2) NULL,
    payout DECIMAL(18,2) NULL,
    placed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    cashed_out_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_gb_round_user (round_id, user_id) COMMENT 'One bet per user per round - the real duplicate-bet guard',
    KEY idx_gb_user (user_id),
    CONSTRAINT fk_gb_round FOREIGN KEY (round_id) REFERENCES game_rounds(id) ON DELETE CASCADE,
    CONSTRAINT fk_gb_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS game_settings (
    id TINYINT UNSIGNED PRIMARY KEY DEFAULT 1,
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    maintenance_mode TINYINT(1) NOT NULL DEFAULT 0,
    minimum_entry DECIMAL(18,2) NOT NULL DEFAULT 5.00,
    maximum_entry DECIMAL(18,2) NOT NULL DEFAULT 80.00,
    countdown_seconds SMALLINT UNSIGNED NOT NULL DEFAULT 5,
    round_grace_seconds SMALLINT UNSIGNED NOT NULL DEFAULT 3 COMMENT 'How long the CRASHED state is shown before the next round starts',
    growth_rate DECIMAL(6,4) NOT NULL DEFAULT 0.1200 COMMENT 'Exponential multiplier growth constant: multiplier = e^(growth_rate * seconds_elapsed)',
    starting_balance DECIMAL(18,2) NOT NULL DEFAULT 1000.00 COMMENT 'Game Points granted automatically the first time a user opens the game',
    daily_bonus_enabled TINYINT(1) NOT NULL DEFAULT 1,
    daily_bonus_amount DECIMAL(18,2) NOT NULL DEFAULT 100.00,
    daily_bonus_max_per_day TINYINT UNSIGNED NOT NULL DEFAULT 1,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO game_settings (id) VALUES (1);
