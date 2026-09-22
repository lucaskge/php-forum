<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Services\ThemeManager;
use App\Support\Database;
use App\Support\Dates;
use App\Support\Str;

/**
 * Everything a working board needs and nothing more: the permission grid, the
 * roles, the board settings, the theme record, a starter forum and a chat room.
 *
 * This is what the web installer runs. The demo seeder (DatabaseSeeder) builds
 * on it and adds accounts and discussion on top, so both paths configure the
 * board identically and there is only one copy of that configuration.
 */
final class CoreSeeder
{
    private Database $db;

    /** @var array<string,int> */
    private array $roleIds = [];

    /** @var array<string,int> */
    private array $permissionIds = [];

    /** @var array<int,string> */
    private array $log = [];

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::instance();
    }

    /**
     * Seeds the configuration. With $withStarterBoard the board also gets one
     * category, two forums and a chat room, so a fresh installation is usable
     * the moment it finishes.
     *
     * @return array<int,string>
     */
    public function run(bool $withStarterBoard = true): array
    {
        $this->seedPermissions();
        $this->seedRoles();
        $this->seedSettings();
        $this->seedThemes();

        if ($withStarterBoard) {
            $this->seedStarterBoard();
            $this->seedChatRoom();
        }

        return $this->log;
    }

    /** @return array<string,int> */
    public function roleIds(): array
    {
        return $this->roleIds;
    }

    /** @return array<string,int> */
    public function permissionIds(): array
    {
        return $this->permissionIds;
    }

    /** @return array<int,string> */
    public function log(): array
    {
        return $this->log;
    }

    private function note(string $message): void
    {
        $this->log[] = $message;
    }

    public function seedPermissions(): void
    {
        $permissions = [
            ['*', 'Full access', 'Bypasses every other check. Reserve it for administrators.', 'system', 0],

            ['search.use', 'Use search', 'Run searches over readable forums.', 'general', 10],

            ['topic.create', 'Start topics', null, 'topics', 10],
            ['topic.reply', 'Reply to topics', null, 'topics', 20],
            ['topic.edit.own', 'Edit own topics', 'Change the subject of a topic they started.', 'topics', 30],
            ['topic.delete.own', 'Delete own topics', 'Only while nobody has replied.', 'topics', 40],
            ['topic.edit.any', 'Edit any topic', null, 'topics', 50],
            ['topic.delete.any', 'Delete any topic', null, 'topics', 60],
            ['topic.subscribe', 'Subscribe to topics', null, 'topics', 70],
            ['topic.bookmark', 'Bookmark topics', null, 'topics', 80],

            ['post.edit.own', 'Edit own posts', 'Subject to the edit window setting.', 'posts', 10],
            ['post.delete.own', 'Delete own posts', null, 'posts', 20],
            ['post.edit.any', 'Edit any post', 'Requires moderation rights on the forum.', 'posts', 30],
            ['post.delete.any', 'Delete any post', 'Requires moderation rights on the forum.', 'posts', 40],
            ['post.hide', 'Hide posts', 'Keep a post in place but out of sight of members.', 'posts', 50],
            ['post.history.view', 'View edit history', 'See every revision of any post.', 'posts', 60],

            ['message.send', 'Use private messages', null, 'messaging', 10],
            ['report.create', 'Report content', null, 'messaging', 20],

            ['chat.post', 'Post in chat', null, 'chat', 10],
            ['chat.moderate', 'Moderate chat', 'Delete messages, mute and ban from chat.', 'chat', 20],

            ['profile.edit', 'Edit own profile', null, 'profile', 10],
            ['avatar.upload', 'Upload an avatar', null, 'profile', 20],

            ['moderation.access', 'Open the moderation area', null, 'moderation', 10],
            ['moderation.forums.all', 'Moderate every readable forum', 'Grants moderation on any forum the role can read.', 'moderation', 20],
            ['moderation.log.view', 'Read the moderation log', null, 'moderation', 30],
            ['report.view', 'View reports', null, 'moderation', 40],
            ['report.handle', 'Resolve reports', null, 'moderation', 50],
            ['topic.pin', 'Pin topics', null, 'moderation', 60],
            ['topic.lock', 'Lock and archive topics', null, 'moderation', 70],
            ['topic.move', 'Move topics', null, 'moderation', 80],
            ['topic.merge', 'Merge topics', null, 'moderation', 90],
            ['topic.split', 'Split topics', null, 'moderation', 100],
            ['topic.hide', 'Hide topics', null, 'moderation', 110],
            ['user.warn', 'Warn members', null, 'moderation', 120],
            ['user.suspend', 'Suspend members', null, 'moderation', 130],
            ['user.ban', 'Ban members', null, 'moderation', 140],

            ['admin.access', 'Open the administration area', null, 'administration', 10],
            ['admin.users', 'Manage user accounts', null, 'administration', 20],
            ['admin.roles', 'Manage roles and permissions', null, 'administration', 30],
            ['user.role.manage', 'Assign roles to members', null, 'administration', 40],
            ['admin.forums', 'Manage categories and forums', null, 'administration', 50],
            ['admin.content', 'Manage topics and posts', null, 'administration', 60],
            ['admin.settings', 'Change board settings', null, 'administration', 70],
            ['admin.themes', 'Manage themes', null, 'administration', 80],
            ['admin.chat', 'Manage chat rooms and transport', null, 'administration', 90],
            ['admin.system', 'System information, logs and maintenance', null, 'administration', 100],
        ];

        foreach ($permissions as [$slug, $name, $description, $group, $position]) {
            $this->permissionIds[$slug] = $this->db->insert('permissions', [
                'slug' => $slug,
                'name' => $name,
                'description' => $description,
                'group_name' => $group,
                'position' => $position,
            ]);
        }

        $this->note(sprintf('Seeded %d permissions.', count($this->permissionIds)));
    }

    public function seedRoles(): void
    {
        $memberPermissions = [
            'search.use', 'topic.create', 'topic.reply', 'topic.edit.own', 'topic.delete.own',
            'topic.subscribe', 'topic.bookmark', 'post.edit.own', 'post.delete.own',
            'message.send', 'report.create', 'chat.post', 'profile.edit', 'avatar.upload',
        ];

        $moderatorPermissions = array_merge($memberPermissions, [
            'moderation.access', 'moderation.forums.all', 'moderation.log.view',
            'report.view', 'report.handle',
            'topic.pin', 'topic.lock', 'topic.move', 'topic.merge', 'topic.split', 'topic.hide',
            'topic.edit.any', 'topic.delete.any',
            'post.edit.any', 'post.delete.any', 'post.hide', 'post.history.view',
            'user.warn', 'user.suspend', 'chat.moderate',
        ]);

        $roles = [
            [
                'slug' => 'guest', 'name' => 'Guest', 'colour' => '#6d7986', 'priority' => 0,
                'description' => 'Everyone who is not signed in.',
                'is_default' => 0, 'is_guest' => 1, 'is_staff' => 0, 'is_system' => 1,
                'permissions' => ['search.use'],
            ],
            [
                'slug' => 'member', 'name' => 'Member', 'colour' => '#8fa3b8', 'priority' => 10,
                'description' => 'Registered accounts. The default role on registration.',
                'is_default' => 1, 'is_guest' => 0, 'is_staff' => 0, 'is_system' => 1,
                'permissions' => $memberPermissions,
            ],
            [
                'slug' => 'trusted', 'name' => 'Trusted member', 'colour' => '#7fae9b', 'priority' => 20,
                'description' => 'Long-standing members. Demonstrates granular grants on top of the member role.',
                'is_default' => 0, 'is_guest' => 0, 'is_staff' => 0, 'is_system' => 0,
                'permissions' => array_merge($memberPermissions, ['post.history.view']),
            ],
            [
                'slug' => 'moderator', 'name' => 'Moderator', 'colour' => '#c2a55f', 'priority' => 50,
                'description' => 'Handles reports, topics and member conduct.',
                'is_default' => 0, 'is_guest' => 0, 'is_staff' => 1, 'is_system' => 1,
                'permissions' => $moderatorPermissions,
            ],
            [
                'slug' => 'administrator', 'name' => 'Administrator', 'colour' => '#c97f76', 'priority' => 100,
                'description' => 'Full access to the board and its configuration.',
                'is_default' => 0, 'is_guest' => 0, 'is_staff' => 1, 'is_system' => 1,
                'permissions' => ['*'],
            ],
        ];

        foreach ($roles as $role) {
            $permissions = $role['permissions'];
            unset($role['permissions']);

            $role['created_at'] = Dates::nowString();
            $role['updated_at'] = Dates::nowString();

            $roleId = $this->db->insert('roles', $role);
            $this->roleIds[(string) $role['slug']] = $roleId;

            foreach ($permissions as $slug) {
                $this->db->insert('role_permissions', [
                    'role_id' => $roleId,
                    'permission_id' => $this->permissionIds[$slug],
                    'granted_at' => Dates::nowString(),
                ]);
            }
        }

        $this->note(sprintf('Seeded %d roles with their grants.', count($this->roleIds)));
    }

    public function seedSettings(): void
    {
        $settings = [
            ['site_name', 'Coldwire', 'string', 'general', 'Board name', 'Shown in the masthead, page titles and e-mail.', null, 10],
            ['site_tagline', 'technical discussion, quietly', 'string', 'general', 'Tagline', 'Short line under the board name.', null, 20],
            ['site_description', 'A technical discussion board for systems, networking and code.', 'string', 'general', 'Meta description', 'Used by search engines on the index page.', null, 30],
            ['announcement', '', 'text', 'general', 'Announcement', 'Shown at the top of the board index. Board formatting works. Leave empty to hide it.', null, 40],
            ['board_rules', "[b]1. Stay on topic.[/b]\nThreads drift; posts should not. Start a new topic rather than derailing one.\n\n[b]2. No spam, no advertising.[/b]\nLinks are welcome when they carry the discussion.\n\n[b]3. Attack arguments, not people.[/b]\nDisagreement is the point of a discussion board. Abuse is not.\n\n[b]4. Nothing illegal.[/b]\nNo warez, no stolen credentials, no personal data belonging to other people.\n\n[b]5. One account per person.[/b]\nBan evasion with a second account is a permanent ban for both.", 'text', 'general', 'Board rules', 'Shown on /rules and during registration.', null, 50],
            ['contact_email', 'staff@localhost', 'string', 'general', 'Contact address', 'Where members should write about account problems.', null, 60],
            ['maintenance_mode', '0', 'boolean', 'general', 'Maintenance mode', 'When on, only administrators can reach the board.', null, 70],
            ['maintenance_message', 'The board is offline for scheduled maintenance. It will be back shortly.', 'text', 'general', 'Maintenance message', 'Shown while maintenance mode is on.', null, 80],

            ['registration_enabled', '1', 'boolean', 'registration', 'Registration open', 'Turn off to close new sign-ups.', null, 10],
            ['registration_closed_message', 'Registration is closed at the moment. Watch the announcements for the next opening.', 'text', 'registration', 'Closed message', 'Shown when registration is off.', null, 20],
            ['avatars_enabled', '1', 'boolean', 'registration', 'Allow avatar uploads', 'When off, everyone uses the generated monogram.', null, 30],
            ['signature_max_length', '400', 'integer', 'registration', 'Signature length limit', 'Characters allowed in a member signature.', null, 40],

            ['topics_per_page', '25', 'integer', 'forums', 'Topics per page', 'Rows in a forum listing.', null, 10],
            ['posts_per_page', '15', 'integer', 'forums', 'Posts per page', 'Posts shown per page in a topic; members may override this.', null, 20],
            ['items_per_page', '20', 'integer', 'forums', 'Rows per page elsewhere', 'Search results, member lists, messages and admin tables.', null, 30],
            ['online_window_minutes', '15', 'integer', 'forums', 'Online window (minutes)', 'How long after their last request a member counts as online.', null, 40],

            ['min_post_length', '5', 'integer', 'posting', 'Minimum post length', 'Characters required in a post or reply.', null, 10],
            ['edit_window_minutes', '0', 'integer', 'posting', 'Self-edit window (minutes)', 'How long members may edit their own posts. 0 means no limit.', null, 20],
            ['search_min_length', '3', 'integer', 'posting', 'Minimum search term length', null, null, 30],

            ['chat_enabled', '1', 'boolean', 'chat', 'Chat enabled', 'Turn the chat area on or off for everyone.', null, 10],
            ['chat_rules', "Keep it civil and keep it readable.\nNo flooding, no walls of text — that is what the forums are for.\nModerators can mute or remove anyone from chat without warning.", 'text', 'chat', 'Chat rules', 'Shown in the sidebar of the chat page.', null, 20],
            ['chat_max_length', '500', 'integer', 'chat', 'Message length limit', null, null, 30],
            ['chat_slow_mode', '0', 'integer', 'chat', 'Global slow mode (seconds)', 'Minimum delay between messages from the same member. Rooms can set a higher value.', null, 40],
            ['chat_history_limit', '60', 'integer', 'chat', 'Messages shown', 'How many recent messages the transcript renders.', null, 50],
            ['chat_presence_window', '300', 'integer', 'chat', 'Presence window (seconds)', 'How long a member stays listed in the room after loading it.', null, 60],
            ['chat_transport', 'http', 'select', 'chat', 'Chat transport', 'Implementation used to deliver messages. Additional transports register themselves in TransportFactory.', '{"http":"HTTP (server-rendered, no JavaScript)"}', 70],
        ];

        foreach ($settings as [$key, $value, $type, $group, $label, $description, $options, $position]) {
            $this->db->insert('settings', [
                'key_name' => $key,
                'value' => $value,
                'type' => $type,
                'group_name' => $group,
                'label' => $label,
                'description' => $description,
                'options' => $options,
                'position' => $position,
                'updated_at' => Dates::nowString(),
            ]);
        }

        $this->note(sprintf('Seeded %d board settings.', count($settings)));
    }

    public function seedThemes(): void
    {
        $manifest = (new ThemeManager())->manifest('default');

        $this->db->insert('themes', [
            'slug' => 'default',
            'name' => (string) ($manifest['name'] ?? 'Coldwire Default'),
            'version' => (string) ($manifest['version'] ?? '1.0.0'),
            'author' => (string) ($manifest['author'] ?? 'Coldwire'),
            'description' => mb_substr((string) ($manifest['description'] ?? ''), 0, 500),
            'parent_slug' => $manifest['parent'] ?? null,
            'is_active' => 1,
            'is_enabled' => 1,
            // Chosen values, not the schema: an untouched theme has none, and
            // every setting falls back to the default in its manifest.
            'settings' => json_encode([], JSON_UNESCAPED_SLASHES),
            'installed_at' => Dates::nowString(),
            'updated_at' => Dates::nowString(),
        ]);

        $this->note('Registered the default theme.');
    }

    /**
     * A category with two forums, so the board is not an empty page on the
     * first visit. Everything here is editable in the administration area.
     */
    public function seedStarterBoard(): void
    {
        $categoryId = $this->db->insert('categories', [
            'name' => 'General Discussion',
            'slug' => 'general-discussion',
            'description' => 'Start here.',
            'position' => 0,
            'is_visible' => 1,
            'created_at' => Dates::nowString(),
            'updated_at' => Dates::nowString(),
        ]);

        $forums = [
            ['name' => 'General', 'icon' => '#', 'description' => 'Anything that belongs on the board but nowhere else.'],
            ['name' => 'Introductions', 'icon' => '>_', 'description' => 'Say who you are and what you work on.'],
        ];

        $position = 0;

        foreach ($forums as $forum) {
            $forumId = $this->db->insert('forums', [
                'category_id' => $categoryId,
                'parent_id' => null,
                'name' => $forum['name'],
                'slug' => Str::slug((string) $forum['name']),
                'description' => $forum['description'],
                'icon' => $forum['icon'],
                'position' => $position,
                'is_visible' => 1,
                'is_locked' => 0,
                'created_at' => Dates::nowString(),
                'updated_at' => Dates::nowString(),
            ]);

            $this->applyDefaultForumPermissions($forumId);
            $position += 10;
        }

        $this->note('Created a starter category with two forums.');
    }

    /**
     * Guests read, members take part, staff moderate. Adjustable per forum in
     * the administration area afterwards.
     */
    public function applyDefaultForumPermissions(int $forumId): void
    {
        $matrix = [
            'guest' => [1, 1, 0, 0, 0],
            'member' => [1, 1, 1, 1, 0],
            'trusted' => [1, 1, 1, 1, 0],
            'moderator' => [1, 1, 1, 1, 1],
            'administrator' => [1, 1, 1, 1, 1],
        ];

        foreach ($matrix as $role => $flags) {
            if (!isset($this->roleIds[$role])) {
                continue;
            }

            $this->db->insert('forum_permissions', [
                'forum_id' => $forumId,
                'role_id' => $this->roleIds[$role],
                'can_view' => $flags[0],
                'can_read' => $flags[1],
                'can_create_topic' => $flags[2],
                'can_reply' => $flags[3],
                'can_moderate' => $flags[4],
            ]);
        }
    }

    public function seedChatRoom(): void
    {
        $this->db->insert('chat_rooms', [
            'slug' => 'lobby',
            'name' => 'Lobby',
            'description' => 'The general room.',
            'topic_line' => null,
            'is_active' => 1,
            'is_readonly' => 0,
            'slow_mode' => 0,
            'position' => 0,
            'created_at' => Dates::nowString(),
            'updated_at' => Dates::nowString(),
        ]);

        $this->note('Created the default chat room.');
    }

    /**
     * The first account. It holds the administrator role, which carries the
     * `*` permission, so it can reach everything from the first sign-in.
     */
    public function createAdministrator(string $username, string $email, string $password): int
    {
        if ($this->roleIds === []) {
            foreach ($this->db->select('SELECT id, slug FROM roles') as $role) {
                $this->roleIds[(string) $role['slug']] = (int) $role['id'];
            }
        }

        $roleId = $this->roleIds['administrator'] ?? null;

        if ($roleId === null) {
            throw new \RuntimeException('The administrator role is missing; seed the configuration first.');
        }

        $userId = $this->db->insert('users', [
            'username' => $username,
            'username_canonical' => mb_strtolower($username, 'UTF-8'),
            'email' => mb_strtolower($email, 'UTF-8'),
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'primary_role_id' => $roleId,
            'timezone' => 'UTC',
            'status' => 'active',
            'email_verified_at' => Dates::nowString(),
            'last_active_at' => Dates::nowString(),
            'created_at' => Dates::nowString(),
            'updated_at' => Dates::nowString(),
        ]);

        $this->db->insert('user_roles', [
            'user_id' => $userId,
            'role_id' => $roleId,
            'assigned_at' => Dates::nowString(),
        ]);

        // Also a plain member, so the union of roles is exercised from day one.
        if (isset($this->roleIds['member'])) {
            $this->db->insert('user_roles', [
                'user_id' => $userId,
                'role_id' => $this->roleIds['member'],
                'assigned_at' => Dates::nowString(),
            ]);
        }

        $this->note(sprintf('Created the administrator account "%s".', $username));

        return $userId;
    }
}
