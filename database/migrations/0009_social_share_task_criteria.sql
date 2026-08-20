-- Marks the "Share on social media" task with a distinct criteria_key so
-- the Tasks page can gate its Claim button behind actually clicking a share
-- action first, instead of showing a bare Claim button immediately. This key
-- is deliberately NOT in TaskService's verifiable-criteria list (a share
-- can't be confirmed server-side) - it stays functionally honor-system, the
-- key only changes which UI the Tasks page renders for this one task.

UPDATE tasks SET criteria_key = 'social_share' WHERE title = 'Share on social media' AND criteria_key IS NULL;
