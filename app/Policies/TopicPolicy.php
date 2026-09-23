<?php

declare(strict_types=1);

namespace App\Policies;

use App\Services\AccessControl;
use App\Services\SettingsService;

/**
 * Decides what the current viewer may do with a topic. Controllers ask the
 * policy; the policy asks AccessControl. No controller inspects roles itself.
 */
final class TopicPolicy
{
    private AccessControl $access;

    private SettingsService $settings;

    public function __construct(?AccessControl $access = null, ?SettingsService $settings = null)
    {
        $this->access = $access ?? AccessControl::instance();
        $this->settings = $settings ?? SettingsService::instance();
    }

    /** @param array<string,mixed> $topic */
    public function view(array $topic): bool
    {
        $forumId = (int) $topic['forum_id'];

        if (!$this->access->canReadForum($forumId)) {
            return false;
        }

        $isRestricted = $topic['deleted_at'] !== null || (int) $topic['is_hidden'] === 1;

        return !$isRestricted || $this->moderate($topic);
    }

    /** @param array<string,mixed> $topic */
    public function reply(array $topic): bool
    {
        if ($topic['deleted_at'] !== null) {
            return false;
        }

        $forumId = (int) $topic['forum_id'];
        $locked = (int) $topic['is_locked'] === 1 || (int) $topic['is_archived'] === 1;

        if ($locked && !$this->moderate($topic)) {
            return false;
        }

        return $this->access->canReplyIn($forumId);
    }

    /** @param array<string,mixed> $topic */
    public function edit(array $topic): bool
    {
        if ($this->moderate($topic) || $this->access->can('topic.edit.any')) {
            return true;
        }

        $userId = $this->access->id();

        if ($userId === null || (int) ($topic['user_id'] ?? 0) !== $userId) {
            return false;
        }

        if ((int) $topic['is_locked'] === 1 || (int) $topic['is_archived'] === 1) {
            return false;
        }

        return $this->access->can('topic.edit.own');
    }

    /** @param array<string,mixed> $topic */
    public function delete(array $topic): bool
    {
        if ($this->moderate($topic) || $this->access->can('topic.delete.any')) {
            return true;
        }

        $userId = $this->access->id();

        if ($userId === null || (int) ($topic['user_id'] ?? 0) !== $userId) {
            return false;
        }

        // A member may withdraw their own thread only while nobody has replied.
        return $this->access->can('topic.delete.own') && (int) $topic['post_count'] <= 1;
    }

    /**
     * True when the only reason this viewer may reply is that they moderate.
     *
     * The board lets staff post in a closed thread on purpose — somebody has to
     * be able to leave the note explaining why it was closed. But a normal
     * reply box on a locked topic reads like the lock is not working, so the
     * page says which it is.
     *
     * @param array<string,mixed> $topic
     */
    public function repliesOnlyBecauseModerator(array $topic): bool
    {
        if (!$this->reply($topic)) {
            return false;
        }

        $topicClosed = (int) $topic['is_locked'] === 1 || (int) $topic['is_archived'] === 1;
        $forumClosed = (int) ($topic['forum_locked'] ?? 0) === 1;

        return ($topicClosed || $forumClosed) && $this->moderate($topic);
    }

    /** @param array<string,mixed> $topic */
    public function moderate(array $topic): bool
    {
        return $this->access->canModerateForum((int) $topic['forum_id']);
    }

    /** @param array<string,mixed> $topic */
    public function pin(array $topic): bool
    {
        return $this->moderate($topic) && $this->access->can('topic.pin');
    }

    /** @param array<string,mixed> $topic */
    public function lock(array $topic): bool
    {
        return $this->moderate($topic) && $this->access->can('topic.lock');
    }

    /** @param array<string,mixed> $topic */
    public function move(array $topic): bool
    {
        return $this->moderate($topic) && $this->access->can('topic.move');
    }

    /** @param array<string,mixed> $topic */
    public function merge(array $topic): bool
    {
        return $this->moderate($topic) && $this->access->can('topic.merge');
    }

    /** @param array<string,mixed> $topic */
    public function split(array $topic): bool
    {
        return $this->moderate($topic) && $this->access->can('topic.split');
    }

    /** @param array<string,mixed> $topic */
    public function hide(array $topic): bool
    {
        return $this->moderate($topic) && $this->access->can('topic.hide');
    }

    public function subscribe(): bool
    {
        return $this->access->can('topic.subscribe');
    }

    public function bookmark(): bool
    {
        return $this->access->can('topic.bookmark');
    }
}
