<?php

declare(strict_types=1);

namespace App\Policies;

use App\Services\AccessControl;
use App\Services\SettingsService;
use App\Support\Dates;

final class PostPolicy
{
    private AccessControl $access;

    private SettingsService $settings;

    public function __construct(?AccessControl $access = null, ?SettingsService $settings = null)
    {
        $this->access = $access ?? AccessControl::instance();
        $this->settings = $settings ?? SettingsService::instance();
    }

    /** @param array<string,mixed> $post */
    public function view(array $post): bool
    {
        if (!$this->access->canReadForum((int) $post['forum_id'])) {
            return false;
        }

        $restricted = $post['deleted_at'] !== null || (int) $post['is_hidden'] === 1;

        return !$restricted || $this->moderate($post);
    }

    /** @param array<string,mixed> $post */
    public function edit(array $post): bool
    {
        if ($this->access->can('post.edit.any') && $this->moderate($post)) {
            return true;
        }

        if ($this->access->isAdministrator()) {
            return true;
        }

        $userId = $this->access->id();

        if ($userId === null || (int) ($post['user_id'] ?? 0) !== $userId) {
            return false;
        }

        if (!$this->access->can('post.edit.own')) {
            return false;
        }

        if ($post['deleted_at'] !== null) {
            return false;
        }

        // The topic being locked freezes its posts for ordinary members.
        if ((int) ($post['topic_locked'] ?? 0) === 1) {
            return false;
        }

        return $this->withinEditWindow($post);
    }

    /** @param array<string,mixed> $post */
    public function delete(array $post): bool
    {
        if ($this->access->isAdministrator()) {
            return true;
        }

        if ($this->access->can('post.delete.any') && $this->moderate($post)) {
            return true;
        }

        $userId = $this->access->id();

        if ($userId === null || (int) ($post['user_id'] ?? 0) !== $userId) {
            return false;
        }

        if ((int) ($post['is_first_post'] ?? 0) === 1) {
            // Removing the opening post means removing the topic.
            return false;
        }

        if ((int) ($post['topic_locked'] ?? 0) === 1 || $post['deleted_at'] !== null) {
            return false;
        }

        return $this->access->can('post.delete.own') && $this->withinEditWindow($post);
    }

    /** @param array<string,mixed> $post */
    public function restore(array $post): bool
    {
        return $this->moderate($post) && $this->access->can('post.delete.any');
    }

    /** @param array<string,mixed> $post */
    public function hide(array $post): bool
    {
        return $this->moderate($post) && $this->access->can('post.hide');
    }

    /** @param array<string,mixed> $post */
    public function viewHistory(array $post): bool
    {
        if ($this->access->can('post.history.view') || $this->moderate($post)) {
            return true;
        }

        $userId = $this->access->id();

        return $userId !== null && (int) ($post['user_id'] ?? 0) === $userId;
    }

    /** @param array<string,mixed> $post */
    public function report(array $post): bool
    {
        $userId = $this->access->id();

        return $userId !== null
            && $this->access->can('report.create')
            && (int) ($post['user_id'] ?? 0) !== $userId;
    }

    /** @param array<string,mixed> $post */
    public function moderate(array $post): bool
    {
        return $this->access->canModerateForum((int) $post['forum_id']);
    }

    /** @param array<string,mixed> $post */
    private function withinEditWindow(array $post): bool
    {
        $minutes = $this->settings->int('edit_window_minutes', 0);

        if ($minutes <= 0) {
            return true;
        }

        $created = Dates::parse((string) $post['created_at']);

        if ($created === null) {
            return true;
        }

        return (Dates::now()->getTimestamp() - $created->getTimestamp()) <= ($minutes * 60);
    }
}
