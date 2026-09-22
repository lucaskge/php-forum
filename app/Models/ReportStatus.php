<?php

declare(strict_types=1);

namespace App\Models;

enum ReportStatus: string
{
    case Pending = 'pending';
    case Resolved = 'resolved';
    case Dismissed = 'dismissed';

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    /** The filter values the moderation queue accepts, including "all". */
    public static function filters(): array
    {
        return array_merge(self::values(), ['all']);
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Resolved => 'Resolved',
            self::Dismissed => 'Dismissed',
        };
    }
}
