-- Adds a 'video' advertisement type for admin-provided YouTube links.
-- destination_url stores the extracted 11-character YouTube video ID
-- (validated/extracted server-side, never the raw pasted URL) so the
-- player can be embedded directly without re-parsing untrusted input.

ALTER TABLE advertisements
  MODIFY COLUMN type ENUM('image','external','video') NOT NULL DEFAULT 'image';
