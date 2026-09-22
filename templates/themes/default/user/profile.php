<?php
/**
 * @var App\Support\View $this
 * @var array<string,mixed> $profile
 */
?>
<header class="profile-head">
    <div class="profile-identity">
        <?= $this->avatar($profile, 88) ?>
        <div>
            <h1 class="profile-name <?= $this->e($this->roleClass($profile['primary_role_id'] ?? null)) ?>">
                <?= $this->e((string) $profile['username']) ?>
            </h1>
            <p class="profile-rank">
                <?= $this->e((string) ($profile['title'] ?? $profile['role_name'] ?? 'Member')) ?>
<?php if ($is_online): ?>
                · <span class="status status-online">online</span>
<?php else: ?>
                · <span class="status status-offline">last seen <?= $this->e($this->relative((string) $profile['last_active_at'])) ?></span>
<?php endif; ?>
<?php if ((string) $profile['status'] !== 'active'): ?>
                · <span class="tag tag-danger"><?= $this->e((string) $profile['status']) ?></span>
<?php endif; ?>
            </p>
        </div>
    </div>
    <div class="page-actions">
<?php if ($can_message): ?>
        <a class="btn btn-accent" href="<?= $this->e($this->route('messages.compose', [], ['to' => (string) $profile['username']])) ?>">Send message</a>
<?php endif; ?>
<?php if ($can_moderate): ?>
        <a class="btn btn-mod" href="<?= $this->e($this->route('moderation.user', ['username' => (string) $profile['username']])) ?>">Staff view</a>
<?php endif; ?>
    </div>
</header>

<div class="profile-grid">
    <div class="profile-main">
<?php if (($profile['bio'] ?? '') !== ''): ?>
        <section class="panel">
            <header class="panel-head"><h2 class="panel-title">About</h2></header>
            <div class="panel-inset post-content"><?= $this->content((string) $profile['bio']) ?></div>
        </section>
<?php endif; ?>

        <section class="panel">
            <header class="panel-head">
                <h2 class="panel-title">Recent topics</h2>
                <a class="panel-action" href="<?= $this->e($this->route('user.topics', ['username' => (string) $profile['username']])) ?>">all topics</a>
            </header>
<?php if ($recent_topics === []): ?>
            <div class="panel-inset muted">No topics started yet.</div>
<?php else: ?>
            <ul class="feed-list">
<?php foreach ($recent_topics as $topic): ?>
                <li class="feed-item">
                    <a class="feed-title" href="<?= $this->e($this->route('topic.show', ['slug' => (string) $topic['slug']])) ?>"><?= $this->e((string) $topic['title']) ?></a>
                    <span class="feed-meta">
                        <a href="<?= $this->e($this->route('forum.show', ['slug' => (string) $topic['forum_slug']])) ?>"><?= $this->e((string) $topic['forum_name']) ?></a>
                        · <?= $this->e($this->date((string) $topic['created_at'], 'Y-m-d')) ?>
                        · <?= $this->number(max(0, (int) $topic['post_count'] - 1)) ?> replies
                    </span>
                </li>
<?php endforeach; ?>
            </ul>
<?php endif; ?>
        </section>

        <section class="panel">
            <header class="panel-head">
                <h2 class="panel-title">Recent posts</h2>
                <a class="panel-action" href="<?= $this->e($this->route('user.posts', ['username' => (string) $profile['username']])) ?>">all posts</a>
            </header>
<?php if ($recent_posts === []): ?>
            <div class="panel-inset muted">No posts yet.</div>
<?php else: ?>
            <ul class="feed-list">
<?php foreach ($recent_posts as $post): ?>
                <li class="feed-item">
                    <a class="feed-title" href="<?= $this->e($this->route('post.permalink', ['id' => (int) $post['id']])) ?>"><?= $this->e((string) $post['topic_title']) ?></a>
                    <span class="feed-meta"><?= $this->e((string) $post['forum_name']) ?> · <?= $this->e($this->relative((string) $post['created_at'])) ?></span>
                    <p class="feed-excerpt"><?= $this->e($this->excerpt((string) $post['content'], 200)) ?></p>
                </li>
<?php endforeach; ?>
            </ul>
<?php endif; ?>
        </section>
    </div>

    <aside class="profile-side">
        <section class="panel">
            <header class="panel-head"><h2 class="panel-title">Statistics</h2></header>
            <dl class="stat-list">
                <div><dt>Posts</dt><dd><?= $this->number($profile['post_count']) ?></dd></div>
                <div><dt>Topics</dt><dd><?= $this->number($profile['topic_count']) ?></dd></div>
                <div><dt>Reputation</dt><dd><?= $this->number($profile['reputation']) ?></dd></div>
                <div><dt>Joined</dt><dd><?= $this->e($this->date((string) $profile['created_at'], 'Y-m-d')) ?></dd></div>
                <div><dt>Last active</dt><dd><?= $this->e($this->relative((string) $profile['last_active_at'])) ?></dd></div>
            </dl>
        </section>

        <section class="panel">
            <header class="panel-head"><h2 class="panel-title">Details</h2></header>
            <dl class="stat-list stat-list-wide">
                <div><dt>Roles</dt><dd><?= $this->e(implode(', ', array_map(static fn (array $r): string => (string) $r['name'], $roles)) ?: 'Member') ?></dd></div>
<?php if (($profile['location'] ?? '') !== ''): ?>
                <div><dt>Location</dt><dd><?= $this->e((string) $profile['location']) ?></dd></div>
<?php endif; ?>
<?php if (($profile['website'] ?? '') !== ''): ?>
                <div><dt>Website</dt><dd><a href="<?= $this->e((string) $profile['website']) ?>" rel="nofollow noopener ugc"><?= $this->e(App\Support\Str::limit((string) $profile['website'], 32)) ?></a></dd></div>
<?php endif; ?>
                <div><dt>Timezone</dt><dd class="mono"><?= $this->e((string) $profile['timezone']) ?></dd></div>
            </dl>
        </section>

<?php if ($can_moderate): ?>
        <section class="panel panel-mod">
            <header class="panel-head"><h2 class="panel-title">Staff notes</h2></header>
            <dl class="stat-list stat-list-wide">
                <div><dt>Warnings</dt><dd><?= $this->number($warning_count) ?></dd></div>
                <div><dt>Warning points</dt><dd><?= $this->number($profile['warning_points']) ?></dd></div>
                <div><dt>Status</dt><dd><?= $this->e((string) $profile['status']) ?></dd></div>
<?php if ($active_ban !== null): ?>
                <div><dt>Restriction</dt><dd><?= $this->e((string) $active_ban['type']) ?> — <?= $this->e((string) $active_ban['reason']) ?></dd></div>
<?php endif; ?>
            </dl>
            <p class="panel-note"><a href="<?= $this->e($this->route('moderation.user', ['username' => (string) $profile['username']])) ?>">Open the moderation record &rarr;</a></p>
        </section>
<?php endif; ?>
    </aside>
</div>
