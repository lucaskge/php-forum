-- ---------------------------------------------------------------------------
-- 003 — Private messaging and notifications.
-- ---------------------------------------------------------------------------

CREATE TABLE private_messages (
    id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    sender_id           INT UNSIGNED NULL,
    recipient_id        INT UNSIGNED NOT NULL,
    parent_id           INT UNSIGNED NULL,
    subject             VARCHAR(190) NOT NULL,
    body                TEXT NOT NULL,
    is_read             TINYINT(1) NOT NULL DEFAULT 0,
    read_at             DATETIME NULL,
    sender_deleted      TINYINT(1) NOT NULL DEFAULT 0,
    recipient_deleted   TINYINT(1) NOT NULL DEFAULT 0,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_private_messages_inbox (recipient_id, recipient_deleted, created_at),
    KEY idx_private_messages_outbox (sender_id, sender_deleted, created_at),
    KEY idx_private_messages_thread (parent_id),
    CONSTRAINT fk_private_messages_sender FOREIGN KEY (sender_id) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_private_messages_recipient FOREIGN KEY (recipient_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_private_messages_parent FOREIGN KEY (parent_id) REFERENCES private_messages (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notifications (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id         INT UNSIGNED NOT NULL,
    actor_id        INT UNSIGNED NULL,
    type            VARCHAR(32) NOT NULL,
    title           VARCHAR(190) NOT NULL,
    body            VARCHAR(500) NULL,
    url             VARCHAR(255) NOT NULL DEFAULT '/',
    is_read         TINYINT(1) NOT NULL DEFAULT 0,
    read_at         DATETIME NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_notifications_user (user_id, is_read, created_at),
    CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_notifications_actor FOREIGN KEY (actor_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
