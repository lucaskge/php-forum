-- ---------------------------------------------------------------------------
-- 005 — Chat. The transport is abstracted behind App\Services\Chat\*; this
-- schema is what the polling transport persists and what a future WebSocket or
-- SSE transport would read from and write to.
-- ---------------------------------------------------------------------------

CREATE TABLE chat_rooms (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug            VARCHAR(48) NOT NULL,
    name            VARCHAR(96) NOT NULL,
    description     VARCHAR(255) NULL,
    topic_line      VARCHAR(255) NULL,
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    is_readonly     TINYINT(1) NOT NULL DEFAULT 0,
    min_role_id     INT UNSIGNED NULL,
    slow_mode       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    position        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_chat_rooms_slug (slug),
    CONSTRAINT fk_chat_rooms_min_role FOREIGN KEY (min_role_id) REFERENCES roles (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE chat_messages (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    room_id         INT UNSIGNED NOT NULL,
    user_id         INT UNSIGNED NULL,
    type            ENUM('message','system') NOT NULL DEFAULT 'message',
    content         VARCHAR(1000) NOT NULL,
    is_deleted      TINYINT(1) NOT NULL DEFAULT 0,
    deleted_by      INT UNSIGNED NULL,
    deleted_at      DATETIME NULL,
    ip_address      VARCHAR(45) NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_chat_messages_room (room_id, id),
    KEY idx_chat_messages_user (user_id, created_at),
    CONSTRAINT fk_chat_messages_room FOREIGN KEY (room_id) REFERENCES chat_rooms (id) ON DELETE CASCADE,
    CONSTRAINT fk_chat_messages_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_chat_messages_deleter FOREIGN KEY (deleted_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE chat_bans (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id         INT UNSIGNED NOT NULL,
    moderator_id    INT UNSIGNED NULL,
    room_id         INT UNSIGNED NULL,
    type            ENUM('mute','ban') NOT NULL DEFAULT 'mute',
    reason          VARCHAR(255) NOT NULL,
    expires_at      DATETIME NULL,
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_chat_bans_user (user_id, is_active),
    CONSTRAINT fk_chat_bans_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_chat_bans_moderator FOREIGN KEY (moderator_id) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_chat_bans_room FOREIGN KEY (room_id) REFERENCES chat_rooms (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE chat_presence (
    room_id         INT UNSIGNED NOT NULL,
    user_id         INT UNSIGNED NOT NULL,
    last_seen_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (room_id, user_id),
    KEY idx_chat_presence_seen (last_seen_at),
    CONSTRAINT fk_chat_presence_room FOREIGN KEY (room_id) REFERENCES chat_rooms (id) ON DELETE CASCADE,
    CONSTRAINT fk_chat_presence_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
