-- ---------------------------------------------------------------------------
-- 002 — Board structure: categories, forums, topics, posts and edit history.
-- ---------------------------------------------------------------------------

CREATE TABLE categories (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name            VARCHAR(96) NOT NULL,
    slug            VARCHAR(120) NOT NULL,
    description     VARCHAR(255) NULL,
    position        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    is_visible      TINYINT(1) NOT NULL DEFAULT 1,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_categories_slug (slug),
    KEY idx_categories_position (position)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE forums (
    id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    category_id         INT UNSIGNED NOT NULL,
    parent_id           INT UNSIGNED NULL,
    name                VARCHAR(96) NOT NULL,
    slug                VARCHAR(120) NOT NULL,
    description         VARCHAR(500) NULL,
    icon                VARCHAR(16) NOT NULL DEFAULT '#',
    position            SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    is_visible          TINYINT(1) NOT NULL DEFAULT 1,
    is_locked           TINYINT(1) NOT NULL DEFAULT 0,
    topic_count         INT UNSIGNED NOT NULL DEFAULT 0,
    post_count          INT UNSIGNED NOT NULL DEFAULT 0,
    last_topic_id       INT UNSIGNED NULL,
    last_post_id        INT UNSIGNED NULL,
    last_post_user_id   INT UNSIGNED NULL,
    last_post_at        DATETIME NULL,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_forums_slug (slug),
    KEY idx_forums_category (category_id, position),
    KEY idx_forums_parent (parent_id, position),
    KEY idx_forums_last_post (last_post_at),
    CONSTRAINT fk_forums_category FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE CASCADE,
    CONSTRAINT fk_forums_parent FOREIGN KEY (parent_id) REFERENCES forums (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE forum_permissions (
    id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    forum_id            INT UNSIGNED NOT NULL,
    role_id             INT UNSIGNED NOT NULL,
    can_view            TINYINT(1) NOT NULL DEFAULT 1,
    can_read            TINYINT(1) NOT NULL DEFAULT 1,
    can_create_topic    TINYINT(1) NOT NULL DEFAULT 0,
    can_reply           TINYINT(1) NOT NULL DEFAULT 0,
    can_moderate        TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_forum_permissions (forum_id, role_id),
    KEY idx_forum_permissions_role (role_id),
    CONSTRAINT fk_forum_permissions_forum FOREIGN KEY (forum_id) REFERENCES forums (id) ON DELETE CASCADE,
    CONSTRAINT fk_forum_permissions_role FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE topics (
    id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    forum_id            INT UNSIGNED NOT NULL,
    user_id             INT UNSIGNED NULL,
    title               VARCHAR(190) NOT NULL,
    slug                VARCHAR(220) NOT NULL,
    is_pinned           TINYINT(1) NOT NULL DEFAULT 0,
    is_locked           TINYINT(1) NOT NULL DEFAULT 0,
    is_hidden           TINYINT(1) NOT NULL DEFAULT 0,
    is_archived         TINYINT(1) NOT NULL DEFAULT 0,
    view_count          INT UNSIGNED NOT NULL DEFAULT 0,
    post_count          INT UNSIGNED NOT NULL DEFAULT 0,
    first_post_id       INT UNSIGNED NULL,
    last_post_id        INT UNSIGNED NULL,
    last_post_user_id   INT UNSIGNED NULL,
    last_post_at        DATETIME NULL,
    deleted_at          DATETIME NULL,
    deleted_by          INT UNSIGNED NULL,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_topics_slug (slug),
    KEY idx_topics_forum_listing (forum_id, deleted_at, is_pinned, last_post_at),
    KEY idx_topics_user (user_id, created_at),
    KEY idx_topics_last_post (last_post_at),
    KEY idx_topics_title (title),
    CONSTRAINT fk_topics_forum FOREIGN KEY (forum_id) REFERENCES forums (id) ON DELETE CASCADE,
    CONSTRAINT fk_topics_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE posts (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    topic_id        INT UNSIGNED NOT NULL,
    forum_id        INT UNSIGNED NOT NULL,
    user_id         INT UNSIGNED NULL,
    content         MEDIUMTEXT NOT NULL,
    is_hidden       TINYINT(1) NOT NULL DEFAULT 0,
    is_first_post   TINYINT(1) NOT NULL DEFAULT 0,
    edit_count      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    edited_at       DATETIME NULL,
    edited_by       INT UNSIGNED NULL,
    ip_address      VARCHAR(45) NULL,
    deleted_at      DATETIME NULL,
    deleted_by      INT UNSIGNED NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_posts_topic (topic_id, deleted_at, created_at),
    KEY idx_posts_user (user_id, created_at),
    KEY idx_posts_forum (forum_id, created_at),
    CONSTRAINT fk_posts_topic FOREIGN KEY (topic_id) REFERENCES topics (id) ON DELETE CASCADE,
    CONSTRAINT fk_posts_forum FOREIGN KEY (forum_id) REFERENCES forums (id) ON DELETE CASCADE,
    CONSTRAINT fk_posts_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE post_edits (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    post_id         INT UNSIGNED NOT NULL,
    editor_id       INT UNSIGNED NULL,
    content_before  MEDIUMTEXT NOT NULL,
    content_after   MEDIUMTEXT NOT NULL,
    reason          VARCHAR(190) NULL,
    ip_address      VARCHAR(45) NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_post_edits_post (post_id, created_at),
    CONSTRAINT fk_post_edits_post FOREIGN KEY (post_id) REFERENCES posts (id) ON DELETE CASCADE,
    CONSTRAINT fk_post_edits_editor FOREIGN KEY (editor_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE topic_subscriptions (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id         INT UNSIGNED NOT NULL,
    topic_id        INT UNSIGNED NOT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_topic_subscriptions (user_id, topic_id),
    KEY idx_topic_subscriptions_topic (topic_id),
    CONSTRAINT fk_topic_subscriptions_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_topic_subscriptions_topic FOREIGN KEY (topic_id) REFERENCES topics (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE bookmarks (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id         INT UNSIGNED NOT NULL,
    topic_id        INT UNSIGNED NOT NULL,
    note            VARCHAR(190) NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_bookmarks (user_id, topic_id),
    KEY idx_bookmarks_topic (topic_id),
    CONSTRAINT fk_bookmarks_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_bookmarks_topic FOREIGN KEY (topic_id) REFERENCES topics (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE topics
    ADD CONSTRAINT fk_topics_first_post FOREIGN KEY (first_post_id) REFERENCES posts (id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_topics_last_post FOREIGN KEY (last_post_id) REFERENCES posts (id) ON DELETE SET NULL;
