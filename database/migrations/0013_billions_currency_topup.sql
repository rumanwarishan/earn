-- Adds the reverse bridge to migration 0012's B$-to-wallet exchange: a user
-- can also convert real wallet USD into B$ balance to fund play, at an
-- admin-configured rate with its own minimum and daily-cap safety limits
-- (mirrors exchange_*'s design exactly, just in the opposite direction).
--
-- Also relaunches the B$ starting economy at a much smaller 10.00 (was
-- 1000.00): the new default applies to all future signups, and this
-- migration does a one-time reset of every EXISTING player's current B$
-- balance down to 10.00 too, each with an audited game_point_ledger entry
-- so the change is fully traceable. This does not touch wallet_ledger/
-- wallets at all - only the isolated B$ balance.

ALTER TABLE game_settings
  ADD COLUMN topup_enabled TINYINT(1) NOT NULL DEFAULT 1 AFTER max_exchange_per_day,
  ADD COLUMN topup_rate DECIMAL(10,6) NOT NULL DEFAULT 100.000000 COMMENT 'B$ credited per 1 USD converted from wallet' AFTER topup_enabled,
  ADD COLUMN min_topup_amount DECIMAL(18,2) NOT NULL DEFAULT 1.00 COMMENT 'Minimum USD a user must convert at once' AFTER topup_rate,
  ADD COLUMN max_topup_per_day DECIMAL(18,2) NOT NULL DEFAULT 50.00 COMMENT 'Per-user daily USD cap converted to B$, admin safety lever' AFTER min_topup_amount;

ALTER TABLE game_point_ledger MODIFY COLUMN type ENUM(
    'game_entry','game_cashout','game_loss','admin_grant','admin_adjustment','daily_bonus','exchange','topup'
) NOT NULL;

ALTER TABLE wallet_ledger MODIFY COLUMN type ENUM(
    'deposit_credit','withdrawal_hold','withdrawal_release','withdrawal_paid',
    'cashback_pending','cashback_release','cashback_reversal',
    'referral_credit','referral_reversal','welcome_bonus',
    'purchase_debit','admin_credit','admin_debit','task_reward','ad_reward','game_exchange','game_topup'
) NOT NULL;

UPDATE game_settings SET starting_balance = 10.00 WHERE id = 1;

INSERT INTO game_point_ledger (user_id, wallet_id, type, amount, balance_before, balance_after, description)
SELECT user_id, id, 'admin_adjustment', CAST(10.00 - balance AS DECIMAL(18,2)), balance, 10.00,
       'Admin reset: B$ economy relaunch - balance set to 10.00'
FROM game_point_wallets
WHERE balance <> 10.00;

UPDATE game_point_wallets SET balance = 10.00 WHERE balance <> 10.00;
