<?php

declare(strict_types=1);

namespace App\Support;

final class Csrf
{
    private const KEY = '__csrf_token';

    public const FIELD = '_token';

    public static function token(): string
    {
        $token = Session::get(self::KEY);

        if (!is_string($token) || strlen($token) !== 64) {
            $token = Str::random(32);
            Session::put(self::KEY, $token);
        }

        return $token;
    }

    public static function verify(?string $candidate): bool
    {
        $token = Session::get(self::KEY);

        if (!is_string($token) || !is_string($candidate)) {
            return false;
        }

        return hash_equals($token, $candidate);
    }

    /** Rotated after login/logout so a fixated token cannot be replayed. */
    public static function rotate(): void
    {
        Session::forget(self::KEY);
        self::token();
    }

    public static function field(): string
    {
        return sprintf('<input type="hidden" name="%s" value="%s">', self::FIELD, htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8'));
    }
}
