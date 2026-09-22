-- ---------------------------------------------------------------------------
-- 004 — Reports, moderation log, bans and warnings.
-- ---------------------------------------------------------------------------

CREATE TABLE reports (
    id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    reporter_id         INT UNSIGNED NULL,
    reported_user_id    INT UNSIGNED NULL,
    content_type        ENUM('post','topic','message','chat_message','user') NOT NULL,
    content_id          INT UNSIGNED NOT NULL,
    reason              VARCHAR(64) NOT NULL,
    details             TEXT NULL,
    status              ENUM('pending','resolved','dismissed') NOT NULL DEFAULT 'pending',
    handled_by          INT UNSIGNED NULL,
    handled_at          DATETIME NULL,
    action_taken        VARCHAR(64) NULL,
    moderator_notes     TEXT NULL,
    ip_address          VARCHAR(45) NULL,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_reports_status (status, created_at),
    KEY idx_reports_content (content_type, content_id),
    KEY idx_reports_reported_user (reported_user_id),
    CONSTRAINT fk_reports_reporter FOREIGN KEY (reporter_id) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_reports_reported_user FOREIGN KEY (reported_user_id) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_reports_handler FOREIGN KEY (handled_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE moderation_actions (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    moderator_id    INT UNSIGNED NULL,
    action          VARCHAR(48) NOT NULL,
    target_type     VARCHAR(32) NOT NULL,
    target_id       INT UNSIGNED NULL,
    target_user_id  INT UNSIGNED NULL,
    summary         VARCHAR(255) NOT NULL,
    reason          VARCHAR(255) NULL,
    metadata        JSON NULL,
    ip_address      VARCHAR(45) NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_moderation_actions_created (created_at),
    KEY idx_moderation_actions_moderator (moderator_id, created_at),
    KEY idx_moderation_actions_target (target_type, target_id),
    KEY idx_moderation_actions_target_user (target_user_id, created_at),
    CONSTRAINT fk_moderation_actions_moderator FOREIGN KEY (moderator_id) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_moderation_actions_target_user FOREIGN KEY (target_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE bans (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id         INT UNSIGNED NOT NULL,
    created_by      INT UNSIGNED NULL,
    type            ENUM('ban','suspension') NOT NULL DEFAULT 'ban',
    reason          VARCHAR(255) NOT NULL,
    internal_note   TEXT NULL,
    expires_at      DATETIME NULL,
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    lifted_by       INT UNSIGNED NULL,
    lifted_at       DATETIME NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_bans_user (user_id, is_active),
    KEY idx_bans_expiry (is_active, expires_at),
    CONSTRAINT fk_bans_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_bans_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_bans_lifter FOREIGN KEY (lifted_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE warnings (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id         INT UNSIGNED NOT NULL,
    moderator_id    INT UNSIGNED NULL,
    reason          VARCHAR(255) NOT NULL,
    details         TEXT NULL,
    points          TINYINT UNSIGNED NOT NULL DEFAULT 1,
    expires_at      DATETIME NULL,
    acknowledged_at DATETIME NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_warnings_user (user_id, created_at),
    CONSTRAINT fk_warnings_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_warnings_moderator FOREIGN KEY (moderator_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_notes (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id         INT UNSIGNED NOT NULL,
    author_id       INT UNSIGNED NULL,
    note            TEXT NOT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_user_notes_user (user_id, created_at),
    CONSTRAINT fk_user_notes_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_user_notes_author FOREIGN KEY (author_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
