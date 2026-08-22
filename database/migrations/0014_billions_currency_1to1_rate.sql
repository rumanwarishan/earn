-- Corrects the B$<->USD rate to a true 1:1 peg (1 B$ = $1 USD) in both
-- directions. Migrations 0012/0013 shipped with a 100:1 rate (100 B$ per
-- $1, i.e. 1 B$ = $0.01) by default, which is why converting $10 from the
-- wallet produced 1000 B$ instead of the intended 10 B$.

ALTER TABLE game_settings
  MODIFY COLUMN exchange_rate DECIMAL(10,6) NOT NULL DEFAULT 1.000000 COMMENT 'USD credited per 1 B$ exchanged',
  MODIFY COLUMN topup_rate DECIMAL(10,6) NOT NULL DEFAULT 1.000000 COMMENT 'B$ credited per 1 USD converted from wallet';

UPDATE game_settings SET exchange_rate = 1.000000, topup_rate = 1.000000 WHERE id = 1;
