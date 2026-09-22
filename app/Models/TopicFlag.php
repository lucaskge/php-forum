<?php

declare(strict_types=1);

namespace App\Models;

/**
 * The status flags a topic can carry. A topic is "normal" when none are set;
 * the flags are independent, so a topic can be pinned and locked at once.
 */
enum TopicFlag: string
{
    case Pinned = 'is_pinned';
    case Locked = 'is_locked';
    case Hidden = 'is_hidden';
    case Archived = 'is_archived';

    /** @return array<int,string> Column names, for whitelisting a write. */
    public static function columns(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::Pinned => 'Pinned',
            self::Locked => 'Locked',
            self::Hidden => 'Hidden',
            self::Archived => 'Archived',
        };
    }

    /** @param array<string,mixed> $topic */
    public function isSetOn(array $topic): bool
    {
        return (int) ($topic[$this->value] ?? 0) === 1;
    }
}
