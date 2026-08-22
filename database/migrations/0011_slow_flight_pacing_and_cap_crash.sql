-- Two gameplay tuning changes requested after first playtest:
-- 1. The plane/multiplier rose too fast - slow the default growth rate so
--    it climbs more gradually (still admin-configurable in Settings).
-- 2. Crash multiplier is now generated bounded to [1.00x, 5.00x] in code
--    (GameRoundService::generateCrashMultiplier) instead of an unbounded
--    long tail - no schema change needed for that (crash_multiplier was
--    already DECIMAL(10,2)), but existing rows' growth_rate default is
--    updated here so already-deployed sites pick up the slower pacing
--    without needing to manually edit Settings.

ALTER TABLE game_settings MODIFY COLUMN growth_rate DECIMAL(6,4) NOT NULL DEFAULT 0.0700
    COMMENT 'Exponential multiplier growth constant: multiplier = e^(growth_rate * seconds_elapsed)';

UPDATE game_settings SET growth_rate = 0.0700 WHERE id = 1 AND growth_rate = 0.1200;
