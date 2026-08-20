-- Milestone tasks (invite N friends, watch N ads, first purchase, first
-- deposit) need real server-verified completion, not just a claim button -
-- the existing tasks system was a pure honor-system claim ("not yet claimed
-- today/ever"), which would let anyone claim a cash reward for something
-- they never actually did. criteria_key/criteria_target let TaskService
-- check the user's real referral/ad/order/deposit data before allowing a
-- claim; NULL criteria_key keeps the old honor-system behavior for tasks
-- with no verifiable signal (daily check-in, social share).

ALTER TABLE tasks
  ADD COLUMN criteria_key VARCHAR(40) NULL AFTER type,
  ADD COLUMN criteria_target INT UNSIGNED NULL AFTER criteria_key;

INSERT INTO tasks (title, description, type, criteria_key, criteria_target, reward_amount, is_active, sort_order) VALUES
  ('Invite 5 friends', 'Invite 5 friends to join Billions Earn using your referral link.', 'one_time', 'referral_count', 5, 5.00, 1, 10),
  ('Purchase your 1st product', 'Buy any product from the shop to complete this task.', 'one_time', 'first_purchase', 1, 10.00, 1, 11),
  ('Watch 5 ads', 'Watch 5 advertisements in Watch & Earn.', 'one_time', 'ad_watch_count', 5, 5.00, 1, 12),
  ('Deposit your first amount', 'Make your first approved deposit into your wallet.', 'one_time', 'first_deposit', 1, 10.00, 1, 13),
  ('Share on social media', 'Share Billions Earn with your friends on social media.', 'one_time', NULL, NULL, 5.00, 1, 14);
