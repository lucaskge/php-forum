<?php

declare(strict_types=1);

use App\Support\Dates;
use App\Support\Str;
use Tests\Assert;

return [
    'slugs are url-safe' => static function (): void {
        Assert::same('hello-world', Str::slug('Hello World'));
        Assert::same('dns-ttls-what-do-you-set', Str::slug('DNS TTLs: what do you set?'));
        Assert::same('acao-e-reacao', Str::slug('Ação e reação'));
        Assert::same('item', Str::slug('!!!'), 'a slug is never empty');
    },

    'limit keeps whole output short' => static function (): void {
        Assert::same('abc', Str::limit('abc', 10));
        Assert::same('abcde…', Str::limit('abcdefghij', 5));
        Assert::same('one two', Str::limit("one   two", 20), 'whitespace is collapsed');
    },

    'initials and hue are deterministic' => static function (): void {
        Assert::same('GR', Str::initials('grepwire'));
        Assert::same(Str::hue('grepwire'), Str::hue('grepwire'));
        Assert::true(Str::hue('grepwire') >= 0 && Str::hue('grepwire') < 360);
    },

    'e-mail masking hides the local part' => static function (): void {
        Assert::same('gr******@example.org', Str::maskEmail('grepwire@example.org'));
    },

    'random tokens are hex and unique' => static function (): void {
        $a = Str::random(32);

        Assert::same(64, strlen($a));
        Assert::true(ctype_xdigit($a));
        Assert::true($a !== Str::random(32));
    },

    'whitespace normalisation trims and caps blank runs' => static function (): void {
        Assert::same("a\n\n\nb", Str::normaliseWhitespace("  a\n\n\n\n\n\nb  "));
        Assert::same("a\nb", Str::normaliseWhitespace("a\r\nb"));
    },

    'dates format and degrade gracefully' => static function (): void {
        Assert::same('2024-03-05 12:30', Dates::format('2024-03-05 12:30:00'));
        Assert::same('—', Dates::format(null));
        Assert::same('—', Dates::format('0000-00-00 00:00:00'));
    },

    'relative labels describe the distance' => static function (): void {
        Assert::same('never', Dates::relative(null));
        Assert::contains('ago', Dates::relative(Dates::now()->modify('-3 hours')->format('Y-m-d H:i:s')));
        Assert::same('3h ago', Dates::relative(Dates::now()->modify('-3 hours')->format('Y-m-d H:i:s')));
    },

    'timezone conversion is applied' => static function (): void {
        // Lisbon is UTC+1 in July (summer time) and UTC+0 in March.
        Assert::same('2024-07-05 13:30', Dates::format('2024-07-05 12:30:00', 'Y-m-d H:i', 'Europe/Lisbon'));
        Assert::same('2024-03-05 12:30', Dates::format('2024-03-05 12:30:00', 'Y-m-d H:i', 'Europe/Lisbon'));
    },

    'isPast reads the clock correctly' => static function (): void {
        Assert::true(Dates::isPast(Dates::now()->modify('-1 minute')->format('Y-m-d H:i:s')));
        Assert::false(Dates::isPast(Dates::now()->modify('+1 minute')->format('Y-m-d H:i:s')));
        Assert::false(Dates::isPast(null), 'no expiry means never expired');
    },
];
