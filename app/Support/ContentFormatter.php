<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Renders the forum's markup dialect to HTML.
 *
 * The input is escaped *first* and only a fixed set of tags is re-introduced
 * afterwards, so no user-supplied markup can ever reach the browser as HTML.
 *
 * Supported: [b] [i] [u] [s] [code] [quote] [quote=author] [quote=author;post]
 *            [url] [url=href] [img] [list]/[*] [spoiler] [hr], bare links,
 *            @mentions.
 */
final class ContentFormatter
{
    public static function render(string $raw): string
    {
        $text = htmlspecialchars($raw, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        // Code blocks are pulled out before anything else so their contents are
        // never touched by the remaining transformations, and are highlighted
        // from the raw source rather than from escaped text.
        $blocks = [];
        $text = preg_replace_callback(
            '/\[code(?:=([^\]]{1,20}))?\](.*?)\[\/code\]/is',
            static function (array $matches) use (&$blocks): string {
                $key = '@@CODEBLOCK' . count($blocks) . '@@';
                $language = CodeHighlighter::normaliseLanguage($matches[1] ?? '');
                $source = html_entity_decode(trim($matches[2], "\n"), ENT_QUOTES, 'UTF-8');

                $header = $language === ''
                    ? ''
                    : '<span class="code-language">' . htmlspecialchars($language, ENT_QUOTES, 'UTF-8') . '</span>';

                $blocks[$key] = '<pre class="code-block">' . $header
                    . '<code>' . CodeHighlighter::highlight($source) . '</code></pre>';

                return $key;
            },
            $text,
        ) ?? $text;

        $text = self::inlineTags($text);
        $text = self::quotes($text);
        // Images resolve before links so the bare-URL linkifier never rewrites
        // the source of an [img] tag out from under it.
        $text = self::images($text);
        $text = self::links($text);
        $text = self::lists($text);
        $text = self::mentions($text);
        $text = str_replace('[hr]', '<hr class="content-rule">', $text);
        $text = self::paragraphs($text);

        foreach ($blocks as $key => $html) {
            $text = str_replace(['<p>' . $key . '</p>', $key], [$html, $html], $text);
        }

        return $text;
    }

    /** Plain-text projection used for excerpts, search snippets and meta tags. */
    public static function plain(string $raw): string
    {
        $text = preg_replace('/\[quote[^\]]*\](.*?)\[\/quote\]/is', '', $raw) ?? $raw;
        $text = preg_replace('/\[\/?[a-z]+(=[^\]]*)?\]/i', '', $text) ?? $text;

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    /**
     * Builds the body of a reply that quotes another post.
     */
    public static function quoteOf(string $author, int $postId, string $content): string
    {
        // Nested quotes are dropped so a long chain does not snowball.
        $content = preg_replace('/\[quote[^\]]*\].*?\[\/quote\]/is', '', $content) ?? $content;

        return sprintf("[quote=%s;%d]%s[/quote]\n\n", str_replace([';', ']'], '', $author), $postId, trim($content));
    }

    /** @return array<int,string> Usernames mentioned with @name. */
    public static function extractMentions(string $raw): array
    {
        preg_match_all('/(?<![\w@])@([a-zA-Z0-9_.-]{3,32})/', $raw, $matches);

        return array_values(array_unique($matches[1] ?? []));
    }

    private static function inlineTags(string $text): string
    {
        $replacements = [
            '/\[b\](.*?)\[\/b\]/is' => '<strong>$1</strong>',
            '/\[i\](.*?)\[\/i\]/is' => '<em>$1</em>',
            '/\[u\](.*?)\[\/u\]/is' => '<span class="u">$1</span>',
            '/\[s\](.*?)\[\/s\]/is' => '<del>$1</del>',
            '/\[spoiler\](.*?)\[\/spoiler\]/is' => '<span class="spoiler" tabindex="0">$1</span>',
        ];

        foreach ($replacements as $pattern => $replacement) {
            $text = preg_replace($pattern, $replacement, $text) ?? $text;
        }

        return $text;
    }

    private static function quotes(string $text): string
    {
        $pattern = '/\[quote(?:=([^\];]*)(?:;(\d+))?)?\]((?:[^[]|\[(?!\/?quote))*?)\[\/quote\]/is';

        // Run repeatedly so nested quotes collapse from the inside out.
        for ($depth = 0; $depth < 4; $depth++) {
            $replaced = preg_replace_callback(
                $pattern,
                static function (array $matches): string {
                    $author = trim($matches[1] ?? '');
                    $postId = $matches[2] ?? '';
                    $body = trim($matches[3]);

                    $header = '';

                    if ($author !== '') {
                        $header = '<div class="quote-head">' . $author . ' wrote';

                        if ($postId !== '') {
                            $header .= ' <a class="quote-link" href="/post/' . (int) $postId . '">#' . (int) $postId . '</a>';
                        }

                        $header .= '</div>';
                    }

                    return '<blockquote class="quote">' . $header . '<div class="quote-body">' . $body . '</div></blockquote>';
                },
                $text,
            );

            if ($replaced === null || $replaced === $text) {
                break;
            }

            $text = $replaced;
        }

        return $text;
    }

    private static function links(string $text): string
    {
        $text = preg_replace_callback(
            '/\[url=([^\]\s]+)\](.*?)\[\/url\]/is',
            static function (array $matches): string {
                $href = self::safeUrl($matches[1]);

                return $href === null ? $matches[2] : '<a href="' . $href . '" rel="nofollow noopener ugc">' . $matches[2] . '</a>';
            },
            $text,
        ) ?? $text;

        $text = preg_replace_callback(
            '/\[url\]([^\[]+)\[\/url\]/is',
            static function (array $matches): string {
                $href = self::safeUrl($matches[1]);

                return $href === null ? $matches[1] : '<a href="' . $href . '" rel="nofollow noopener ugc">' . $matches[1] . '</a>';
            },
            $text,
        ) ?? $text;

        // Bare URLs that are not already inside an anchor.
        return preg_replace_callback(
            '#(?<!["\'=>])\bhttps?://[^\s<\[\]"\']+#i',
            static function (array $matches): string {
                $href = self::safeUrl($matches[0]);

                return $href === null ? $matches[0] : '<a href="' . $href . '" rel="nofollow noopener ugc">' . Str::limit($matches[0], 70) . '</a>';
            },
            $text,
        ) ?? $text;
    }

    private static function images(string $text): string
    {
        return preg_replace_callback(
            '/\[img\]([^\[]+)\[\/img\]/is',
            static function (array $matches): string {
                $src = self::safeUrl($matches[1]);

                if ($src === null) {
                    return '';
                }

                return '<img class="content-image" src="' . $src . '" alt="Image posted by a member" loading="lazy">';
            },
            $text,
        ) ?? $text;
    }

    private static function lists(string $text): string
    {
        return preg_replace_callback(
            '/\[list\](.*?)\[\/list\]/is',
            static function (array $matches): string {
                $items = preg_split('/\[\*\]/', $matches[1]) ?: [];
                $html = '';

                foreach ($items as $item) {
                    $item = trim($item);

                    if ($item !== '') {
                        $html .= '<li>' . $item . '</li>';
                    }
                }

                return $html === '' ? '' : '<ul class="content-list">' . $html . '</ul>';
            },
            $text,
        ) ?? $text;
    }

    private static function mentions(string $text): string
    {
        return preg_replace(
            '/(?<![\w@\/])@([a-zA-Z0-9_.-]{3,32})/',
            '<a class="mention" href="/user/$1">@$1</a>',
            $text,
        ) ?? $text;
    }

    private static function paragraphs(string $text): string
    {
        $blocks = preg_split('/\n{2,}/', trim($text)) ?: [];
        $html = '';

        foreach ($blocks as $block) {
            $block = trim($block);

            if ($block === '') {
                continue;
            }

            if (preg_match('/^<(blockquote|ul|pre|hr|div|h[1-6])/i', $block) === 1) {
                $html .= $block;

                continue;
            }

            $html .= '<p>' . nl2br($block, false) . '</p>';
        }

        return $html;
    }

    private static function safeUrl(string $url): ?string
    {
        $url = trim(html_entity_decode($url, ENT_QUOTES, 'UTF-8'));
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        if (!in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        return htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
