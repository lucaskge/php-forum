<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\RoleRepository;
use App\Support\Str;

/**
 * Generates the stylesheet for values that depend on database content.
 *
 * The board's Content-Security-Policy is `style-src 'self'`, which blocks inline
 * `style="…"` attributes as well as `<style>` blocks. That is a policy worth
 * keeping — it is the same rule that stops injected markup from carrying its own
 * presentation — so anything that varies per row is emitted here as a class in a
 * real stylesheet instead.
 *
 * Three families of class are produced:
 *
 *   .role-colour-<id>   the colour an administrator chose for a role
 *   .hue-<n>            the generated avatar monogram's hue, in 15° buckets
 *   .bar-w-<n>          bar-chart widths, in 5% steps
 *   .avatar-s<n>        the avatar sizes the templates actually ask for
 */
final class DynamicStyles
{
    /** Avatar sizes used across the theme. Anything else snaps to the nearest. */
    public const AVATAR_SIZES = [16, 20, 22, 24, 26, 28, 32, 40, 48, 56, 72, 88, 96];

    private const HUE_BUCKETS = 24;

    private RoleRepository $roles;

    public function __construct(?RoleRepository $roles = null)
    {
        $this->roles = $roles ?? new RoleRepository();
    }

    public function css(): string
    {
        $lines = [
            '/* Generated from the database: role colours, avatar hues and bar widths.',
            '   Emitted as classes so the Content-Security-Policy can keep refusing',
            '   inline styles. Regenerated whenever a role changes. */',
            '',
        ];

        foreach ($this->roles->all() as $role) {
            $colour = $this->colour((string) $role['colour']);
            $lines[] = sprintf('.role-colour-%d { --role-colour: %s; }', (int) $role['id'], $colour);
        }

        $lines[] = '';

        for ($bucket = 0; $bucket < self::HUE_BUCKETS; $bucket++) {
            $lines[] = sprintf(
                '.hue-%d { --monogram-hue: %d; }',
                $bucket,
                (int) round($bucket * (360 / self::HUE_BUCKETS)),
            );
        }

        $lines[] = '';

        for ($percent = 0; $percent <= 100; $percent += 5) {
            $lines[] = sprintf('.bar-w-%d { --bar-width: %d%%; }', $percent, $percent);
        }

        $lines[] = '';

        foreach (self::AVATAR_SIZES as $size) {
            $lines[] = sprintf(
                '.avatar-s%d { width: %dpx; height: %dpx; font-size: %dpx; }',
                $size,
                $size,
                $size,
                max(9, (int) round($size * 0.38)),
            );
        }

        return implode("\n", $lines) . "\n";
    }

    /** Changes whenever a role does, so browsers fetch the new file. */
    public function version(): string
    {
        $latest = 0;

        foreach ($this->roles->all() as $role) {
            $latest = max($latest, (int) strtotime((string) $role['updated_at']));
        }

        return (string) $latest;
    }

    /** The class carrying a role's colour, or an empty string when unknown. */
    public static function roleClass(mixed $roleId): string
    {
        $roleId = is_numeric($roleId) ? (int) $roleId : 0;

        return $roleId > 0 ? 'role-colour-' . $roleId : '';
    }

    /** The bucketed hue class for a generated monogram. */
    public static function hueClass(string $name): string
    {
        $bucket = (int) floor(Str::hue($name) / (360 / self::HUE_BUCKETS));

        return 'hue-' . max(0, min(self::HUE_BUCKETS - 1, $bucket));
    }

    /** A bar width, rounded to the nearest 5%. */
    public static function barClass(float $ratio): string
    {
        $percent = (int) round(max(0.0, min(1.0, $ratio)) * 20) * 5;

        return 'bar-w-' . $percent;
    }

    /** The nearest declared avatar size class. */
    public static function avatarClass(int $size): string
    {
        $closest = self::AVATAR_SIZES[0];

        foreach (self::AVATAR_SIZES as $candidate) {
            if (abs($candidate - $size) < abs($closest - $size)) {
                $closest = $candidate;
            }
        }

        return 'avatar-s' . $closest;
    }

    private function colour(string $value): string
    {
        return preg_match('/^#[0-9a-fA-F]{6}$/', $value) === 1 ? strtolower($value) : '#8fa3b8';
    }
}
