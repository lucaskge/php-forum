<?php

declare(strict_types=1);

use App\Support\ContentFormatter;
use Tests\Assert;

return [
    'html in user input is escaped' => static function (): void {
        $html = ContentFormatter::render('<script>alert(1)</script>');

        Assert::notContains('<script>', $html, 'a script tag must never survive');
        Assert::contains('&lt;script&gt;', $html);
    },

    'attribute injection through markup is escaped' => static function (): void {
        $html = ContentFormatter::render('<img src=x onerror=alert(1)>');

        Assert::notContains('<img src=x', $html);
        Assert::contains('&lt;img', $html);
    },

    'inline tags render' => static function (): void {
        $html = ContentFormatter::render('[b]bold[/b] and [i]italic[/i]');

        Assert::contains('<strong>bold</strong>', $html);
        Assert::contains('<em>italic</em>', $html);
    },

    'code blocks are left untouched inside' => static function (): void {
        $html = ContentFormatter::render("[code]\$a = [b]not bold[/b];[/code]");

        Assert::contains('<pre class="code-block">', $html);
        Assert::notContains('<strong>not bold</strong>', $html, 'markup inside code is inert');
    },

    'quotes render with their attribution' => static function (): void {
        $html = ContentFormatter::render('[quote=grepwire;12]quoted text[/quote]');

        Assert::contains('<blockquote class="quote">', $html);
        Assert::contains('grepwire wrote', $html);
        Assert::contains('/post/12', $html);
    },

    'http links are linkified, other schemes are not' => static function (): void {
        $safe = ContentFormatter::render('[url=https://example.org]label[/url]');
        Assert::contains('href="https://example.org"', $safe);
        Assert::contains('rel="nofollow noopener ugc"', $safe);

        $unsafe = ContentFormatter::render('[url=javascript:alert(1)]click[/url]');
        Assert::notContains('javascript:', $unsafe, 'javascript: URLs must be dropped');
        Assert::contains('click', $unsafe, 'the label survives as plain text');
    },

    'bare urls become links' => static function (): void {
        Assert::contains('<a href="https://example.org/x"', ContentFormatter::render('see https://example.org/x now'));
    },

    'images only accept http(s) sources' => static function (): void {
        Assert::contains('<img class="content-image"', ContentFormatter::render('[img]https://example.org/a.png[/img]'));
        Assert::notContains('<img', ContentFormatter::render('[img]data:text/html;base64,AAAA[/img]'));
    },

    'mentions link to the profile' => static function (): void {
        Assert::contains('href="/user/nullroute"', ContentFormatter::render('ping @nullroute please'));
    },

    'e-mail addresses are not mistaken for mentions' => static function (): void {
        Assert::notContains('class="mention"', ContentFormatter::render('write to name@example.org'));
    },

    'lists render' => static function (): void {
        $html = ContentFormatter::render('[list][*]one[*]two[/list]');

        Assert::contains('<ul class="content-list">', $html);
        Assert::contains('<li>one</li>', $html);
    },

    'paragraphs are separated on blank lines' => static function (): void {
        $html = ContentFormatter::render("first\n\nsecond");

        Assert::contains('<p>first</p>', $html);
        Assert::contains('<p>second</p>', $html);
    },

    'plain projection strips markup and quotes' => static function (): void {
        $plain = ContentFormatter::plain("[quote=a]old[/quote]\n[b]new[/b] text");

        Assert::notContains('old', $plain, 'quoted text is excluded from excerpts');
        Assert::contains('new text', $plain);
    },

    'quoting a post drops nested quotes' => static function (): void {
        $quote = ContentFormatter::quoteOf('grepwire', 9, "[quote=x]older[/quote]\nthe point");

        Assert::contains('[quote=grepwire;9]', $quote);
        Assert::contains('the point', $quote);
        Assert::notContains('older', $quote, 'quote chains do not snowball');
    },

    'mentions are extracted for notification fan-out' => static function (): void {
        Assert::same(['nullroute', 'grepwire'], ContentFormatter::extractMentions('@nullroute and @grepwire and @nullroute'));
    },
];
