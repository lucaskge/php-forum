-- ---------------------------------------------------------------------------
-- 006 — Settings, themes, session tracking and throttles.
-- ---------------------------------------------------------------------------

CREATE TABLE settings (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    key_name        VARCHAR(64) NOT NULL,
    value           TEXT NULL,
    type            ENUM('string','text','integer','boolean','select') NOT NULL DEFAULT 'string',
    group_name      VARCHAR(48) NOT NULL DEFAULT 'general',
    label           VARCHAR(120) NOT NULL,
    description     VARCHAR(255) NULL,
    options         JSON NULL,
    position        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_settings_key (key_name),
    KEY idx_settings_group (group_name, position)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE themes (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug            VARCHAR(64) NOT NULL,
    name            VARCHAR(96) NOT NULL,
    version         VARCHAR(24) NOT NULL DEFAULT '1.0.0',
    author          VARCHAR(96) NULL,
    description     VARCHAR(500) NULL,
    parent_slug     VARCHAR(64) NULL,
    is_active       TINYINT(1) NOT NULL DEFAULT 0,
    is_enabled      TINYINT(1) NOT NULL DEFAULT 1,
    settings        JSON NULL,
    installed_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_themes_slug (slug),
    KEY idx_themes_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sessions (
    id              VARCHAR(128) NOT NULL,
    user_id         INT UNSIGNED NULL,
    ip_address      VARCHAR(45) NULL,
    user_agent      VARCHAR(255) NULL,
    is_bot          TINYINT(1) NOT NULL DEFAULT 0,
    current_path    VARCHAR(190) NULL,
    last_activity   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_sessions_user (user_id),
    KEY idx_sessions_activity (last_activity),
    CONSTRAINT fk_sessions_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE rate_limits (
    bucket_key      CHAR(64) NOT NULL,
    hits            INT UNSIGNED NOT NULL DEFAULT 0,
    expires_at      DATETIME NOT NULL,
    PRIMARY KEY (bucket_key),
    KEY idx_rate_limits_expiry (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS migrations (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    filename        VARCHAR(190) NOT NULL,
    batch           INT UNSIGNED NOT NULL DEFAULT 1,
    executed_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_migrations_filename (filename)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
