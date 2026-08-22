-- Removes the practical friction from converting between wallet USD and B$:
-- minimum amounts drop to 0.00 (any positive amount is accepted - the
-- amount > 0 check already in GamePointService still applies) and daily
-- caps are raised to an effectively unlimited ceiling. The enable/disable
-- toggle and the columns themselves stay in place so an admin can still
-- reintroduce limits later from the Billions Flight settings page.

ALTER TABLE game_settings
  MODIFY COLUMN min_exchange_amount DECIMAL(18,2) NOT NULL DEFAULT 0.00 COMMENT 'Minimum B$ a user must exchange at once (0 = no minimum)',
  MODIFY COLUMN max_exchange_per_day DECIMAL(18,2) NOT NULL DEFAULT 999999999.99 COMMENT 'Per-user daily cap on B$ exchanged (effectively unlimited by default)',
  MODIFY COLUMN min_topup_amount DECIMAL(18,2) NOT NULL DEFAULT 0.00 COMMENT 'Minimum USD a user must convert at once (0 = no minimum)',
  MODIFY COLUMN max_topup_per_day DECIMAL(18,2) NOT NULL DEFAULT 999999999.99 COMMENT 'Per-user daily USD cap converted to B$ (effectively unlimited by default)';

UPDATE game_settings SET
  min_exchange_amount = 0.00,
  max_exchange_per_day = 999999999.99,
  min_topup_amount = 0.00,
  max_topup_per_day = 999999999.99
WHERE id = 1;
