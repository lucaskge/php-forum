<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\Str;

/**
 * The single place where post content is processed.
 *
 * Two hooks, deliberately separate, because they answer different questions:
 *
 *   clean()   runs once, on the way in. What it changes is what gets stored,
 *             so it is for normalisation — trimming, collapsing runaway blank
 *             lines, stripping invisible characters. Anything destructive here
 *             is destructive forever.
 *
 *   display() runs on the way out, every time. What it changes is only what
 *             this reader sees, so it is for anything you might want to undo,
 *             change your mind about, or apply retroactively — a word filter
 *             above all. The original stays in the database, which is what a
 *             moderator needs when somebody disputes a filtered post.
 *
 * Both are called from one place each, so adding a rule means editing this
 * class and nothing else:
 *
 *   clean()   — App\Services\TopicService, on create, reply and edit
 *   display() — App\Support\ContentFormatter::render()
 */
final class ContentFilter
{
    private SettingsService $settings;

    /** @var array<int,string>|null */
    private ?array $words = null;

    public function __construct(?SettingsService $settings = null)
    {
        $this->settings = $settings ?? SettingsService::instance();
    }

    /**
     * Normalises content on its way into the database.
     *
     * Keep this conservative: it is applied before storing, so whatever it
     * removes cannot be recovered.
     */
    public function clean(string $raw): string
    {
        // Normalises line endings, trims, and caps runs of blank lines.
        $content = Str::normaliseWhitespace($raw);

        // Characters with no visible glyph are stripped: zero-width joiners and
        // friends are how filters get evaded and how text gets made unreadable.
        $content = preg_replace('/[\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2060}-\x{2064}\x{FEFF}]/u', '', $content) ?? $content;

        // Trailing spaces on a line are noise in a plain-text format.
        $content = preg_replace('/[ \t]+$/m', '', $content) ?? $content;

        return trim($content);
    }

    /**
     * Applied every time content is rendered, never stored.
     *
     * Runs on the raw source before any markup is produced, so a replacement
     * cannot introduce HTML — whatever this returns is still escaped by the
     * formatter afterwards.
     */
    public function display(string $raw): string
    {
        return $this->censor($raw);
    }

    /**
     * Replaces the words listed in the `censored_words` board setting.
     *
     * Matching ignores case and accents, and only whole words are replaced, so
     * a short entry cannot quietly mangle a longer legitimate word.
     */
    public function censor(string $text): string
    {
        $words = $this->words();

        if ($words === []) {
            return $text;
        }

        $replacement = $this->settings->string('censor_replacement', '***');

        foreach ($words as $word) {
            $pattern = '/(?<![\p{L}\p{N}])' . preg_quote($word, '/') . '(?![\p{L}\p{N}])/iu';
            $text = preg_replace($pattern, $replacement, $text) ?? $text;
        }

        return $text;
    }

    /**
     * The configured word list, one entry per line or separated by commas.
     *
     * @return array<int,string>
     */
    public function words(): array
    {
        if ($this->words !== null) {
            return $this->words;
        }

        $raw = $this->settings->string('censored_words', '');
        $entries = preg_split('/[\r\n,]+/', $raw) ?: [];
        $words = [];

        foreach ($entries as $entry) {
            $entry = trim($entry);

            // A one-character entry would match far too much to be useful.
            if (mb_strlen($entry, 'UTF-8') >= 2) {
                $words[] = $entry;
            }
        }

        return $this->words = $words;
    }

    public function enabled(): bool
    {
        return $this->words() !== [];
    }
}
