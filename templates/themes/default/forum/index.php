<?php
/**
 * Board index.
 *
 * @var App\Support\View $this
 * @var array<int,array{category:array<string,mixed>,forums:array<int,array<string,mixed>>}> $tree
 */
?>
<?php if (($announcement ?? '') !== ''): ?>
<section class="announcement" aria-label="Announcement">
    <span class="announcement-tag">notice</span>
    <div class="announcement-body"><?= $this->content((string) $announcement) ?></div>
</section>
<?php endif; ?>

<div class="index-grid">
    <div class="index-main">
<?php if ($tree === []): ?>
        <?= $this->partial('partials/empty', ['message' => 'No forums are visible to your account yet.']) ?>
<?php endif; ?>

<?php foreach ($tree as $node): ?>
        <section class="panel category-panel">
            <header class="panel-head">
                <h2 class="panel-title">
                    <a href="#category-<?= (int) $node['category']['id'] ?>" id="category-<?= (int) $node['category']['id'] ?>">
                        <?= $this->e((string) $node['category']['name']) ?>
                    </a>
                </h2>
<?php if (($node['category']['description'] ?? '') !== ''): ?>
                <p class="panel-subtitle"><?= $this->e((string) $node['category']['description']) ?></p>
<?php endif; ?>
            </header>
            <table class="board-table forum-table">
                <caption class="visually-hidden">Forums in <?= $this->e((string) $node['category']['name']) ?></caption>
                <thead>
                    <tr>
                        <th scope="col" class="col-icon"><span class="visually-hidden">Status</span></th>
                        <th scope="col" class="col-forum">Forum</th>
                        <th scope="col" class="col-num">Topics</th>
                        <th scope="col" class="col-num">Posts</th>
                        <th scope="col" class="col-last">Last post</th>
                    </tr>
                </thead>
                <tbody>
<?php foreach ($node['forums'] as $forum): ?>
                    <?= $this->partial('partials/forum-row', ['forum' => $forum]) ?>
<?php endforeach; ?>
                </tbody>
            </table>
        </section>
<?php endforeach; ?>

        <div class="split-panels">
            <section class="panel">
                <header class="panel-head"><h2 class="panel-title">Latest topics</h2></header>
                <ul class="feed-list">
<?php foreach ($latest_topics as $topic): ?>
                    <li class="feed-item">
                        <a class="feed-title" href="<?= $this->e($this->route('topic.show', ['slug' => (string) $topic['slug']])) ?>">
                            <?= $this->e(App\Support\Str::limit((string) $topic['title'], 64)) ?>
                        </a>
                        <span class="feed-meta">
                            <a href="<?= $this->e($this->route('forum.show', ['slug' => (string) $topic['forum_slug']])) ?>"><?= $this->e((string) $topic['forum_name']) ?></a>
                            · <?= $this->e($this->relative((string) ($topic['last_post_at'] ?? $topic['created_at']))) ?>
                        </span>
                    </li>
<?php endforeach; ?>
<?php if ($latest_topics === []): ?>
                    <li class="feed-item"><span class="muted">Nothing posted yet.</span></li>
<?php endif; ?>
                </ul>
            </section>

            <section class="panel">
                <header class="panel-head"><h2 class="panel-title">Latest posts</h2></header>
                <ul class="feed-list">
<?php foreach ($latest_posts as $post): ?>
                    <li class="feed-item">
                        <a class="feed-title" href="<?= $this->e($this->route('post.permalink', ['id' => (int) $post['id']])) ?>">
                            <?= $this->e(App\Support\Str::limit((string) $post['topic_title'], 52)) ?>
                        </a>
                        <span class="feed-meta">
                            <?= $this->username($post) ?>
                            · <?= $this->e($this->relative((string) $post['created_at'])) ?>
                        </span>
                        <p class="feed-excerpt"><?= $this->e($this->excerpt((string) $post['content'], 130)) ?></p>
                    </li>
<?php endforeach; ?>
<?php if ($latest_posts === []): ?>
                    <li class="feed-item"><span class="muted">Nothing posted yet.</span></li>
<?php endif; ?>
                </ul>
            </section>
        </div>
    </div>

    <aside class="index-side" aria-label="Board information">
        <section class="panel">
            <header class="panel-head"><h2 class="panel-title">Board statistics</h2></header>
            <dl class="stat-list">
                <div><dt>Topics</dt><dd><?= $this->number($statistics['topics']) ?></dd></div>
                <div><dt>Posts</dt><dd><?= $this->number($statistics['posts']) ?></dd></div>
                <div><dt>Members</dt><dd><?= $this->number($statistics['members']) ?></dd></div>
                <div><dt>Forums</dt><dd><?= $this->number($statistics['forums']) ?></dd></div>
            </dl>
<?php if ($statistics['newest_member'] !== null): ?>
            <p class="panel-note">
                Newest member:
                <?= $this->username(['username' => (string) $statistics['newest_member'], 'primary_role_id' => $statistics['newest_member_role_id'] ?? null]) ?>
            </p>
<?php endif; ?>
        </section>

        <section class="panel">
            <header class="panel-head">
                <h2 class="panel-title">Online now</h2>
                <a class="panel-action" href="<?= $this->e($this->route('online')) ?>">details</a>
            </header>
            <p class="panel-note mono">
                <?= $this->number(count($online_users)) ?> member(s) ·
                <?= $this->number($guest_count) ?> guest(s) ·
                <?= $this->number($bot_count) ?> crawler(s)
                <span class="muted">/ last <?= (int) $online_window_minutes ?> min</span>
            </p>
            <ul class="user-list">
<?php foreach (array_slice($online_users, 0, 24) as $member): ?>
<?php if ((int) ($member['show_online'] ?? 1) === 0 && !$this->shared('is_staff')) { continue; } ?>
                <?= $this->partial('partials/user-card', ['member' => $member, 'meta' => null]) ?>
<?php endforeach; ?>
<?php if ($online_users === []): ?>
                <li class="muted">No members online.</li>
<?php endif; ?>
            </ul>
        </section>

<?php if ($staff !== []): ?>
        <section class="panel">
            <header class="panel-head"><h2 class="panel-title">Staff</h2></header>
            <ul class="user-list">
<?php foreach ($staff as $member): ?>
                <?= $this->partial('partials/user-card', ['member' => $member, 'meta' => (string) $member['role_name']]) ?>
<?php endforeach; ?>
            </ul>
        </section>
<?php endif; ?>

        <section class="panel">
            <header class="panel-head"><h2 class="panel-title">Newest members</h2></header>
            <ul class="user-list">
<?php foreach ($newest_members as $member): ?>
                <?= $this->partial('partials/user-card', ['member' => $member, 'meta' => $this->date((string) $member['created_at'], 'Y-m-d')]) ?>
<?php endforeach; ?>
            </ul>
        </section>

<?php if ($this->shared('current_user') === null): ?>
        <section class="panel panel-cta">
            <header class="panel-head"><h2 class="panel-title">Join the board</h2></header>
            <p class="panel-note">Reading is open to everyone. Posting, messages and chat require an account.</p>
            <p><a class="btn btn-accent btn-block" href="<?= $this->e($this->route('auth.register.show')) ?>">Create an account</a></p>
            <p><a class="btn btn-quiet btn-block" href="<?= $this->e($this->route('auth.login.show')) ?>">Sign in</a></p>
        </section>
<?php else: ?>
        <section class="panel">
            <header class="panel-head"><h2 class="panel-title">Shortcuts</h2></header>
            <ul class="link-list">
                <li><a href="<?= $this->e($this->route('settings.subscriptions')) ?>">Subscribed topics</a></li>
                <li><a href="<?= $this->e($this->route('settings.bookmarks')) ?>">Bookmarks</a></li>
                <li><a href="<?= $this->e($this->route('user.posts', ['username' => (string) $this->shared('current_user')['username']])) ?>">Your posts</a></li>
                <li>
                    <form class="inline-form" action="<?= $this->e($this->route('forums.mark-read')) ?>" method="post">
                        <?= $this->csrf() ?>
                        <button type="submit" class="linklike">Mark forums read</button>
                    </form>
                </li>
            </ul>
        </section>
<?php endif; ?>
    </aside>
</div>
