<?php

declare(strict_types=1);

namespace App\Models;

/**
 * What a report can point at. Reports outlive the content they reference, so
 * the type is stored alongside the id rather than resolved at write time.
 */
enum ContentType: string
{
    case Post = 'post';
    case Topic = 'topic';
    case Message = 'message';
    case ChatMessage = 'chat_message';
    case User = 'user';

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::Post => 'Post',
            self::Topic => 'Topic',
            self::Message => 'Private message',
            self::ChatMessage => 'Chat message',
            self::User => 'Member',
        };
    }
}
