<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ForumRepository;
use App\Repositories\RoleRepository;

/**
 * Central authorisation service.
 *
 * Two layers work together:
 *   1. Global permissions — granular slugs granted to roles (`topic.create`,
 *      `moderation.reports.handle`, `admin.settings`, …).
 *   2. Per-forum access — a role/forum matrix (view, read, create, reply,
 *      moderate) so a single role can behave differently per board.
 *
 * Nothing in the application checks a role name directly; everything asks this
 * service, which is what makes the permission system genuinely granular.
 */
final class AccessControl
{
    private static ?AccessControl $instance = null;

    private AuthService $auth;

    private RoleRepository $roles;

    private ForumRepository $forums;

    /** @var array<int,string>|null */
    private ?array $permissions = null;

    /** @var array<int,int>|null */
    private ?array $roleIds = null;

    /** @var array<int,array<string,bool>>|null */
    private ?array $forumAccess = null;

    /** @var array<int,array<string,mixed>>|null */
    private ?array $forumIndex = null;

    public function __construct(?AuthService $auth = null, ?RoleRepository $roles = null, ?ForumRepository $forums = null)
    {
        $this->auth = $auth ?? AuthService::instance();
        $this->roles = $roles ?? new RoleRepository();
        $this->forums = $forums ?? new ForumRepository();
    }

    public static function instance(): AccessControl
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /** Drops every cached decision — call after changing roles at runtime. */
    public function flush(): void
    {
        $this->permissions = null;
        $this->roleIds = null;
        $this->forumAccess = null;
        $this->forumIndex = null;
    }

    /** @return array<string,mixed>|null */
    public function user(): ?array
    {
        return $this->auth->user();
    }

    public function id(): ?int
    {
        return $this->auth->id();
    }

    public function isGuest(): bool
    {
        return $this->auth->guest();
    }

    /** @return array<int,int> */
    public function roleIds(): array
    {
        if ($this->roleIds !== null) {
            return $this->roleIds;
        }

        $user = $this->auth->user();

        if ($user === null) {
            $guest = $this->roles->guestRole();

            return $this->roleIds = $guest === null ? [] : [(int) $guest['id']];
        }

        $ids = $this->roles->roleIdsForUser((int) $user['id']);

        if ($ids === [] && $user['primary_role_id'] !== null) {
            $ids = [(int) $user['primary_role_id']];
        }

        return $this->roleIds = $ids;
    }

    /** @return array<int,string> */
    public function permissions(): array
    {
        if ($this->permissions !== null) {
            return $this->permissions;
        }

        return $this->permissions = $this->roles->permissionSlugsForRoles($this->roleIds());
    }

    public function can(string $permission): bool
    {
        $permissions = $this->permissions();

        // `*` is the administrator wildcard; it is granted to no other role.
        return in_array('*', $permissions, true) || in_array($permission, $permissions, true);
    }

    /** @param array<int,string> $permissions */
    public function canAny(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->can($permission)) {
                return true;
            }
        }

        return false;
    }

    public function isStaff(): bool
    {
        return $this->canAny(['moderation.access', 'admin.access']);
    }

    public function isAdministrator(): bool
    {
        return $this->can('admin.access');
    }

    // ------------------------------------------------------------------
    // Forum-scoped access
    // ------------------------------------------------------------------

    /** @return array<int,array<string,bool>> */
    private function accessMap(): array
    {
        if ($this->forumAccess !== null) {
            return $this->forumAccess;
        }

        return $this->forumAccess = $this->forums->accessMapForRoles($this->roleIds());
    }

    /** @return array<int,array<string,mixed>> */
    private function forumIndex(): array
    {
        if ($this->forumIndex !== null) {
            return $this->forumIndex;
        }

        $index = [];

        foreach ($this->forums->allForums(false) as $forum) {
            $index[(int) $forum['id']] = $forum;
        }

        return $this->forumIndex = $index;
    }

    /**
     * @return array{can_view:bool,can_read:bool,can_create_topic:bool,can_reply:bool,can_moderate:bool}
     */
    public function forumAccess(int $forumId): array
    {
        $map = $this->accessMap();

        $access = $map[$forumId] ?? [
            'can_view' => false,
            'can_read' => false,
            'can_create_topic' => false,
            'can_reply' => false,
            'can_moderate' => false,
        ];

        // Administrators are never locked out of a board by the matrix.
        if ($this->isAdministrator()) {
            return [
                'can_view' => true,
                'can_read' => true,
                'can_create_topic' => true,
                'can_reply' => true,
                'can_moderate' => true,
            ];
        }

        // A global moderation permission implies moderating every board the
        // user can already read.
        if ($access['can_read'] && $this->can('moderation.forums.all')) {
            $access['can_moderate'] = true;
        }

        $forum = $this->forumIndex()[$forumId] ?? null;

        if ($forum !== null && (int) $forum['is_visible'] === 0 && !$access['can_moderate']) {
            $access['can_view'] = false;
            $access['can_read'] = false;
        }

        // A forum only counts as reachable when every ancestor is reachable.
        if ($forum !== null && $forum['parent_id'] !== null && $access['can_view']) {
            $parent = $this->forumAccess((int) $forum['parent_id']);

            if (!$parent['can_view']) {
                $access['can_view'] = false;
                $access['can_read'] = false;
            }
        }

        return $access;
    }

    public function canViewForum(int $forumId): bool
    {
        return $this->forumAccess($forumId)['can_view'];
    }

    public function canReadForum(int $forumId): bool
    {
        return $this->forumAccess($forumId)['can_read'];
    }

    public function canCreateTopicIn(int $forumId): bool
    {
        $forum = $this->forumIndex()[$forumId] ?? null;

        if ($forum !== null && (int) $forum['is_locked'] === 1 && !$this->canModerateForum($forumId)) {
            return false;
        }

        return $this->can('topic.create') && $this->forumAccess($forumId)['can_create_topic'];
    }

    public function canReplyIn(int $forumId): bool
    {
        $forum = $this->forumIndex()[$forumId] ?? null;

        if ($forum !== null && (int) $forum['is_locked'] === 1 && !$this->canModerateForum($forumId)) {
            return false;
        }

        return $this->can('topic.reply') && $this->forumAccess($forumId)['can_reply'];
    }

    public function canModerateForum(int $forumId): bool
    {
        return $this->forumAccess($forumId)['can_moderate'];
    }

    /**
     * Forum ids the viewer may read — the scope applied to listings, search,
     * profiles and "latest activity" panels.
     *
     * @return array<int,int>
     */
    public function readableForumIds(): array
    {
        $ids = [];

        foreach (array_keys($this->forumIndex()) as $forumId) {
            if ($this->canReadForum($forumId)) {
                $ids[] = $forumId;
            }
        }

        return $ids;
    }

    /** @return array<int,int> */
    public function moderatableForumIds(): array
    {
        $ids = [];

        foreach (array_keys($this->forumIndex()) as $forumId) {
            if ($this->canModerateForum($forumId)) {
                $ids[] = $forumId;
            }
        }

        return $ids;
    }

    public function canModerateAnything(): bool
    {
        return $this->isStaff() || $this->moderatableForumIds() !== [];
    }
}
