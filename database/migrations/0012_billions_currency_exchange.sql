-- Renames the in-game virtual currency's DISPLAY label from "GP" to "B$"
-- (Billions Store Currency) - purely cosmetic in the database (columns keep
-- their existing names to avoid an unnecessary large-blast-radius rename)
-- and adds a manual, admin-controlled exchange path: a user can convert
-- their B$ balance into real wallet balance at an admin-set rate.
--
-- This is the ONE deliberate, explicit bridge between the game and the
-- financial wallet. Ordinary gameplay (joining a round, winning, losing)
-- still NEVER touches the wallet - only an explicit exchange action does,
-- and it is atomic (one DB transaction debits game_point_ledger and
-- credits wallet_ledger together) and fully audited on both sides.

ALTER TABLE game_settings
  ADD COLUMN exchange_enabled TINYINT(1) NOT NULL DEFAULT 1 AFTER daily_bonus_max_per_day,
  ADD COLUMN exchange_rate DECIMAL(10,6) NOT NULL DEFAULT 0.010000 COMMENT 'USD credited per 1 B$ exchanged' AFTER exchange_enabled,
  ADD COLUMN min_exchange_amount DECIMAL(18,2) NOT NULL DEFAULT 100.00 COMMENT 'Minimum B$ balance a user must exchange at once' AFTER exchange_rate,
  ADD COLUMN max_exchange_per_day DECIMAL(18,2) NOT NULL DEFAULT 2000.00 COMMENT 'Per-user daily cap on B$ exchanged, admin safety lever' AFTER min_exchange_amount;

ALTER TABLE game_point_ledger MODIFY COLUMN type ENUM(
    'game_entry','game_cashout','game_loss','admin_grant','admin_adjustment','daily_bonus','exchange'
) NOT NULL;

ALTER TABLE wallet_ledger MODIFY COLUMN type ENUM(
    'deposit_credit','withdrawal_hold','withdrawal_release','withdrawal_paid',
    'cashback_pending','cashback_release','cashback_reversal',
    'referral_credit','referral_reversal','welcome_bonus',
    'purchase_debit','admin_credit','admin_debit','task_reward','ad_reward','game_exchange'
) NOT NULL;
