-- ---------------------------------------------------------------------------
-- 001 — Identity: users, roles and the granular permission grid.
-- ---------------------------------------------------------------------------

CREATE TABLE roles (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug            VARCHAR(48) NOT NULL,
    name            VARCHAR(64) NOT NULL,
    description     VARCHAR(255) NULL,
    colour          CHAR(7) NOT NULL DEFAULT '#8fa3b8',
    priority        SMALLINT UNSIGNED NOT NULL DEFAULT 10,
    is_default      TINYINT(1) NOT NULL DEFAULT 0,
    is_guest        TINYINT(1) NOT NULL DEFAULT 0,
    is_staff        TINYINT(1) NOT NULL DEFAULT 0,
    is_system       TINYINT(1) NOT NULL DEFAULT 0,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_roles_slug (slug),
    KEY idx_roles_priority (priority)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE permissions (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug            VARCHAR(64) NOT NULL,
    name            VARCHAR(96) NOT NULL,
    description     VARCHAR(255) NULL,
    group_name      VARCHAR(48) NOT NULL DEFAULT 'general',
    position        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_permissions_slug (slug),
    KEY idx_permissions_group (group_name, position)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE role_permissions (
    role_id         INT UNSIGNED NOT NULL,
    permission_id   INT UNSIGNED NOT NULL,
    granted_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (role_id, permission_id),
    KEY idx_role_permissions_permission (permission_id),
    CONSTRAINT fk_role_permissions_role FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE CASCADE,
    CONSTRAINT fk_role_permissions_permission FOREIGN KEY (permission_id) REFERENCES permissions (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
    id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username            VARCHAR(32) NOT NULL,
    username_canonical  VARCHAR(32) NOT NULL,
    email               VARCHAR(190) NOT NULL,
    password_hash       VARCHAR(255) NOT NULL,
    primary_role_id     INT UNSIGNED NULL,
    title               VARCHAR(64) NULL,
    avatar_path         VARCHAR(255) NULL,
    bio                 TEXT NULL,
    signature           VARCHAR(500) NULL,
    location            VARCHAR(64) NULL,
    website             VARCHAR(190) NULL,
    timezone            VARCHAR(64) NOT NULL DEFAULT 'UTC',
    post_count          INT UNSIGNED NOT NULL DEFAULT 0,
    topic_count         INT UNSIGNED NOT NULL DEFAULT 0,
    reputation          INT NOT NULL DEFAULT 0,
    warning_points      TINYINT UNSIGNED NOT NULL DEFAULT 0,
    status              ENUM('active','pending','suspended','banned') NOT NULL DEFAULT 'active',
    show_online         TINYINT(1) NOT NULL DEFAULT 1,
    notify_replies      TINYINT(1) NOT NULL DEFAULT 1,
    notify_mentions     TINYINT(1) NOT NULL DEFAULT 1,
    notify_quotes       TINYINT(1) NOT NULL DEFAULT 1,
    notify_messages     TINYINT(1) NOT NULL DEFAULT 1,
    posts_per_page      TINYINT UNSIGNED NOT NULL DEFAULT 0,
    email_verified_at   DATETIME NULL,
    last_active_at      DATETIME NULL,
    last_login_at       DATETIME NULL,
    last_ip             VARCHAR(45) NULL,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_users_username (username_canonical),
    UNIQUE KEY uniq_users_email (email),
    KEY idx_users_role (primary_role_id),
    KEY idx_users_status (status),
    KEY idx_users_last_active (last_active_at),
    KEY idx_users_created (created_at),
    CONSTRAINT fk_users_role FOREIGN KEY (primary_role_id) REFERENCES roles (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_roles (
    user_id         INT UNSIGNED NOT NULL,
    role_id         INT UNSIGNED NOT NULL,
    assigned_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, role_id),
    KEY idx_user_roles_role (role_id),
    CONSTRAINT fk_user_roles_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_user_roles_role FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE password_resets (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id         INT UNSIGNED NOT NULL,
    token_hash      CHAR(64) NOT NULL,
    ip_address      VARCHAR(45) NULL,
    expires_at      DATETIME NOT NULL,
    used_at         DATETIME NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_password_resets_token (token_hash),
    KEY idx_password_resets_user (user_id),
    CONSTRAINT fk_password_resets_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE login_attempts (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    identifier      VARCHAR(190) NOT NULL,
    ip_address      VARCHAR(45) NOT NULL,
    successful      TINYINT(1) NOT NULL DEFAULT 0,
    user_agent      VARCHAR(255) NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_login_attempts_identifier (identifier, created_at),
    KEY idx_login_attempts_ip (ip_address, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
