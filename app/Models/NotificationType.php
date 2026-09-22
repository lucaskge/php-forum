<?php

declare(strict_types=1);

namespace App\Models;

enum NotificationType: string
{
    case Reply = 'topic.reply';
    case Quote = 'post.quote';
    case Mention = 'post.mention';
    case Message = 'message.received';
    case Moderation = 'moderation.action';
    case Warning = 'moderation.warning';

    /** Short marker shown beside the entry in the notification list. */
    public function marker(): string
    {
        return match ($this) {
            self::Reply => 'reply',
            self::Quote => 'quote',
            self::Mention => 'mention',
            self::Message => 'message',
            self::Moderation => 'mod',
            self::Warning => 'warn',
        };
    }

    public static function markerFor(?string $value): string
    {
        return self::tryFrom((string) $value)?->marker() ?? 'info';
    }
}
