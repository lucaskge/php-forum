-- ---------------------------------------------------------------------------
-- 007 — Private messages stop raising a second alert by default.
--
-- The inbox already carries its own unread counter in the header, so an alert
-- about the same message showed the same number twice. The preference stays,
-- for members who would rather see everything in one list, but it is now
-- something you turn on rather than something you turn off.
-- ---------------------------------------------------------------------------

ALTER TABLE users MODIFY notify_messages TINYINT(1) NOT NULL DEFAULT 0;

UPDATE users SET notify_messages = 0;
