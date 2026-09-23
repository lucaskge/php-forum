# Content pipeline

Everything a member types follows the same path. Knowing the two hooks on that
path is most of what you need to customise how text is stored and shown.

```
textarea
   │
   ├─ ContentFilter::clean()        ← SAVE-TIME hook. Runs once, result is stored.
   │
 database (raw markup, never HTML)
   │
   ├─ ContentFilter::display()      ← RENDER-TIME hook. Runs every view, not stored.
   ├─ htmlspecialchars()            ← everything becomes inert text here
   ├─ [code] extracted + highlighted
   ├─ inline tags, quotes, images, links, lists, mentions
   └─ paragraphs
   │
 HTML
```

The order is the security property: **escape first, re-introduce a fixed set of
tags afterwards.** No branch of the pipeline ever interpolates member text into
an HTML attribute or tag name, so there is no arrangement of characters a
member can type that becomes markup.

## The two hooks — `app/Services/ContentFilter.php`

### `clean(string $raw): string` — on the way in

Called by the services before storing a post, a private message or a chat line.
Its result is what lands in the database, so whatever it removes is gone.

Shipped behaviour: normalise line endings, trim, cap runs of blank lines, strip
trailing whitespace per line, and remove invisible characters
(`U+200B`–`U+200F`, the bidirectional overrides, `U+2060`–`U+2064`, `U+FEFF`) —
those are how word filters get evaded and how text gets made unreadable.

Put normalisation here. Do not put censoring here: censoring at save time
destroys the original and cannot be undone or reconfigured later.

### `display(string $raw): string` — on the way out

Called at the very top of `ContentFormatter::render()`, on the **raw source,
before escaping**. That placement is deliberate twice over:

- a replacement can act on markup the member wrote (it sees `[b]`, not `<strong>`);
- a replacement cannot inject HTML, because everything it returns is still
  escaped immediately afterwards.

Shipped behaviour: `censor()`, replacing the words in the `censored_words`
setting with `censor_replacement`. Matching is case- and accent-insensitive and
whole-word only, using `(?<![\p{L}\p{N}])…(?![\p{L}\p{N}])`, so listing `ass`
does not mangle "assembly".

To add a rule — linkifying ticket numbers, expanding shorthand — extend
`display()`:

```php
public function display(string $raw): string
{
    $text = $this->censor($raw);

    // CVE-2024-1234 becomes a link. Note it emits board markup, not HTML.
    return preg_replace('/\bCVE-(\d{4})-(\d{4,7})\b/', '[url=https://cve.org/CVERecord?id=CVE-$1-$2]CVE-$1-$2[/url]', $text) ?? $text;
}
```

Because `display()` is not stored, editing it changes every existing post the
next time it is viewed. That is usually what you want.

## The formatter — `app/Support/ContentFormatter.php`

### The tag registry

Paired inline tags live in one constant, and the editor's help is generated
from it, so a new tag documents itself:

```php
public const INLINE_TAGS = [
    'b' => ['<strong>', '</strong>', 'bold'],
    'i' => ['<em>', '</em>', 'italic'],
    'u' => ['<span class="u">', '</span>', 'underline'],
    's' => ['<del>', '</del>', 'struck through'],
    'mark' => ['<mark class="content-mark">', '</mark>', 'highlighted'],
    'sub' => ['<sub>', '</sub>', 'subscript'],
    'sup' => ['<sup>', '</sup>', 'superscript'],
    'center' => ['<span class="content-center">', '</span>', 'centred'],
    'spoiler' => ['<span class="spoiler" tabindex="0">', '</span>', 'hidden until hovered'],
];
```

Adding `'kbd' => ['<kbd>', '</kbd>', 'a keystroke']` gives you `[kbd]…[/kbd]`
everywhere, listed in the help with its closing tag, with no other edit.

Two rules for anything you add:

1. **The opening HTML is fixed text.** It never interpolates what the member
   wrote. That is the whole reason this is safe.
2. **Style with a class, never a `style` attribute.** The board's
   Content-Security-Policy is `style-src 'self'`, which blocks inline styles
   outright; a tag that needs a colour gets a class here and a rule in the
   theme's CSS.

### Tags that take an argument

`[code]`, `[code=php]`, `[quote]`, `[quote=name]`, `[quote=name;postid]`,
`[url]`, `[url=href]`, `[img]`, `[list]`/`[*]`, `[hr]` and `@mentions` are
handled by their own methods because they parse something. Their help lines are
appended by hand in `reference()` — add yours there if you write another.

### Pipeline order

Two orderings are load-bearing and have each already caused a bug:

- **`[code]` is extracted before anything else**, replaced with a placeholder,
  and restored at the end — so nothing inside a code block is transformed, and
  it is highlighted from the raw source rather than from escaped text.
- **`images()` runs before `links()`.** The bare-URL linkifier would otherwise
  rewrite the URL inside `[img]` into an `<a>`, and no image would ever render.

### Other entry points

| Method | Used for |
|---|---|
| `plain($raw)` | Excerpts, search snippets, meta descriptions. Strips quotes and tags |
| `quoteOf($author, $postId, $content)` | Builds the body of a quoting reply, dropping nested quotes so chains do not snowball |
| `extractMentions($raw)` | The `@name` list the notification service needs |
| `reference()` | The tag help, generated from the registry |

## Syntax highlighting — `app/Support/CodeHighlighter.php`

The highlighter is deliberately **language-agnostic**: one tokeniser, applied to
everything. A per-language grammar set would be a large amount of code to
maintain and would fail on the snippet that is half config and half shell.

It runs one alternation pattern over the source and wraps each match in
`<span class="tok-…">`. Token classes, in the order they are tried:

| Class | |
|---|---|
| `tok-comment` | `//`, `#`, `--`, `/* … */` |
| `tok-string` | single, double and backtick strings, escapes respected |
| `tok-number` | decimal, hex, float, exponent |
| `tok-keyword` | a union word list across the C family, PHP, Python, Ruby, Go, Rust, SQL and shell |
| `tok-literal` | `true`, `false`, `null`, `nil`, `None`, … |
| `tok-variable` | `$name`, `@name`, `%name` |
| `tok-function` | an identifier immediately followed by `(` |
| `tok-type` | `CapitalisedIdentifiers` |
| `tok-tag` | markup tag names |
| `tok-attribute` | attribute and property names |
| `tok-operator` | operators |
| `tok-punctuation` | brackets and separators |

Ordering matters: `comment` and `string` come first so a `#` inside a string, or
a keyword inside a comment, is not re-tokenised.

The twelve colours are theme settings (**Admin → Themes → Customise**), so
changing the palette is a form, not a code edit. Adding a *class* means adding
an entry to `TOKENS`, a branch in `pattern()`, and a `.tok-…` rule in the theme
CSS.

!!! warning "Regex delimiters"
    `pattern()` uses `/` as its delimiter and the patterns contain `//`, `/*`
    and `*/`. Every literal slash in there is escaped. If you edit that method
    and the highlighter starts throwing, check that first.

## Chat transports

In `app/Services/Chat/`. The chat writes through an interface rather than straight to the database, so
that a real-time backend can be added later without touching the controller,
the templates or the moderation tools.

```
ChatTransport (interface)
  name() describe() isRealtime() endpoint()
  publish(ChatMessage): ChatMessage
  history(roomId, limit, includeDeleted): ChatMessage[]
  join(roomId, userId)
  presence(roomId, windowSeconds)
  retract(messageId, moderatorId)

HttpTransport   the shipped one: plain database, meta-refresh polling,
                isRealtime() false, endpoint() null
```

To add one:

```php
// app/Services/Chat/WebsocketTransport.php
final class WebsocketTransport implements ChatTransport { … }

// then, at boot:
TransportFactory::register('websocket', WebsocketTransport::class);
```

and set the `chat_transport` board setting to `websocket`. `TransportFactory`
falls back to `http` for an unknown name, so a mis-set value degrades to a
working chat rather than a broken page.

`isRealtime()` and `endpoint()` exist so a future transport can tell the
template to stop emitting the meta-refresh and where to connect instead — the
one place the no-JavaScript rule would be revisited, and a decision for
whoever adds that transport.
