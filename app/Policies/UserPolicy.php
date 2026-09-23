<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\UserStatus;
use App\Services\AccessControl;

final class UserPolicy
{
    private AccessControl $access;

    public function __construct(?AccessControl $access = null)
    {
        $this->access = $access ?? AccessControl::instance();
    }

    /** @param array<string,mixed> $target */
    public function viewProfile(array $target): bool
    {
        if (UserStatus::tryFrom((string) $target['status']) === UserStatus::Banned && !$this->access->isStaff()) {
            return false;
        }

        return true;
    }

    /** @param array<string,mixed> $target */
    public function message(array $target): bool
    {
        $viewerId = $this->access->id();

        return $viewerId !== null
            && $this->access->can('message.send')
            && (int) $target['id'] !== $viewerId
            && UserStatus::tryFrom((string) $target['status'])?->canParticipate() === true;
    }

    /**
     * Reporting a member, rather than one of their posts.
     *
     * For conduct that is not in a single post: a pattern across several, a
     * name, an avatar, messages sent privately. Staff are reportable too — the
     * report goes to the queue every moderator sees, and an administrator can
     * act on it.
     *
     * @param array<string,mixed> $target
     */
    public function report(array $target): bool
    {
        $viewerId = $this->access->id();

        return $viewerId !== null
            && $this->access->can('report.create')
            && (int) $target['id'] !== $viewerId;
    }

    /** @param array<string,mixed> $target */
    public function warn(array $target): bool
    {
        return $this->access->can('user.warn') && !$this->isProtected($target);
    }

    /** @param array<string,mixed> $target */
    public function suspend(array $target): bool
    {
        return $this->access->can('user.suspend') && !$this->isProtected($target);
    }

    /** @param array<string,mixed> $target */
    public function ban(array $target): bool
    {
        return $this->access->can('user.ban') && !$this->isProtected($target);
    }

    /** @param array<string,mixed> $target */
    public function manageRoles(array $target): bool
    {
        return $this->access->can('user.role.manage') && (int) $target['id'] !== $this->access->id();
    }

    /** @param array<string,mixed> $target */
    public function editProfileOf(array $target): bool
    {
        return (int) $target['id'] === $this->access->id() || $this->access->can('admin.users');
    }

    /**
     * Staff cannot action themselves, and only an administrator may action
     * another staff member.
     *
     * @param array<string,mixed> $target
     */
    public function isProtected(array $target): bool
    {
        if ((int) $target['id'] === $this->access->id()) {
            return true;
        }

        $targetIsStaff = (int) ($target['role_is_staff'] ?? 0) === 1;

        return $targetIsStaff && !$this->access->isAdministrator();
    }
}
