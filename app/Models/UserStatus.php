<?php

declare(strict_types=1);

namespace App\Models;

/**
 * The lifecycle states an account can be in. Kept as an enum so the same list
 * drives validation, the admin forms and the status badges instead of the same
 * four strings being retyped in each of them.
 */
enum UserStatus: string
{
    case Active = 'active';
    case Pending = 'pending';
    case Suspended = 'suspended';
    case Banned = 'banned';

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    public static function tryLabel(?string $value): string
    {
        return self::tryFrom((string) $value)?->label() ?? 'unknown';
    }

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Pending => 'Pending approval',
            self::Suspended => 'Suspended',
            self::Banned => 'Banned',
        };
    }

    /** Whether an account in this state may still write to the board. */
    public function canParticipate(): bool
    {
        return $this === self::Active;
    }

    public function isRestricted(): bool
    {
        return $this === self::Suspended || $this === self::Banned;
    }
}
