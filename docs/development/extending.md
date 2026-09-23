# Extending the board

The five things people most often want to change, and where each is done.

## Add a formatting tag

One line in `app/Support/ContentFormatter.php`:

```php
public const INLINE_TAGS = [
    …
    'kbd' => ['<kbd>', '</kbd>', 'a keystroke'],
];
```

`[kbd]Ctrl+C[/kbd]` now works everywhere, and the editor help and the help page
list it with its closing tag, because both are generated from this constant.

Style it in `templates/themes/default/assets/css/board.css`. **Never a `style`
attribute** — the CSP blocks them.

A tag that takes an argument (`[colour=red]`) needs its own method following
the pattern of `quotes()` or `links()`, plus a line in `reference()`. The
opening HTML must never interpolate what the member typed; map their value
through a fixed list to a class name instead:

```php
// Safe: the member's word only ever selects a class from a list we wrote.
$class = match (strtolower($matches[1])) {
    'red' => 'tint-red',
    'green' => 'tint-green',
    default => 'tint-none',
};
```

## Filter or rewrite content

`app/Services/ContentFilter.php`, two methods:

- `clean()` — runs once, before storing. Normalisation only; what it removes is
  gone.
- `display()` — runs on every render, before escaping. Censoring, linkifying,
  shorthand expansion. Not stored, so changing it changes every existing post.

Full detail, including why `display()` cannot introduce HTML, is in
[Content pipeline](../architecture/content.md).

## Add a permission

1. Insert it in a migration:

```sql
INSERT INTO permissions (slug, name, description, group_name, position)
VALUES ('topic.tag', 'Tag topics', 'Attach tags to a topic.', 'topics', 60);
```

2. Grant it to roles, either in the same migration or from
   **Admin → Roles → edit** — which lists it automatically, because that screen
   renders from the `permissions` table.

3. Enforce it. On the route:

```php
->middleware(['auth', 'can:topic.tag'])
```

and in the template, so no dead button appears:

```php
<?php if ($this->can('topic.tag')): ?> … <?php endif; ?>
```

For something per-forum rather than board-wide, use the `forum_permissions`
matrix instead: `$this->access->canReplyIn($forumId)` and its siblings.

Never compare a role name. A policy asks `AccessControl`; that is what makes a
new permission work everywhere at once.

## Add a board setting

Insert a row — the settings screen builds itself from the table:

```sql
INSERT INTO settings (key_name, value, type, group_name, label, description, position)
VALUES ('topic_tags_enabled', '1', 'boolean', 'features', 'Topic tags',
        'Let members attach tags to their topics.', 40);
```

`type` may be `string`, `text`, `integer`, `boolean` or `select`; a `select`
carries its choices in the `options` JSON column.

Read it: `$this->settings->bool('topic_tags_enabled', true)`, or `string()`,
`int()`.

**Enforce it at the route**, not only in the template:

```php
->middleware(['setting:topic_tags_enabled'])
```

Hiding a link is presentation. The route refusing is the feature being off.

## Add or customise a theme

A theme is a directory under `templates/themes/` with a `theme.json` manifest.
Copy `default`, change the slug, and it appears in **Admin → Themes**.

### Inheriting

Set `"parent": "default"` and ship only the files you change. `ThemeManager`
resolves each template through the child first, then the parent, so a theme can
be three files.

### Declaring settings

Each entry in the manifest's `settings` array becomes a field on
**Admin → Themes → Customise** and a CSS custom property in the generated
`/theme/{slug}/settings.css`:

```json
{ "key": "accent", "group": "Palette", "label": "Accent",
  "type": "colour", "default": "#6d93b8", "css": "--accent",
  "hint": "Buttons, current page markers, highlights." }
```

| Field | |
|---|---|
| `key` | Stored name |
| `group` | Fieldset on the customise screen |
| `type` | `colour`, `integer` or `select` |
| `css` | The custom property it sets |
| `unit` | Appended to an integer, e.g. `px` |
| `min`, `max` | Clamped server-side for integers |
| `options` / `values` | Labels shown, and the CSS each writes, for a `select` |

Values are sanitised on save: colours must match `#rrggbb`, integers are
clamped, and a `select` value must be one of the declared keys. A value that
fails falls back to the default, so a hand-edited database row cannot inject
CSS.

The stylesheet URL carries a version derived from the stored settings, so a
change takes effect immediately without a stale cache.

The default theme declares 29 settings: five palette colours, five text
colours, the twelve syntax-highlighting colours, and five layout values
including the interface typeface.

### Writing CSS for a theme

Use the custom properties rather than literal colours, or the customise screen
will not affect your rule:

```css
.topic-tag {
    background: var(--surface-2);
    color: var(--ink-dim);
    border: 1px solid var(--line);
    border-radius: var(--radius);
}
```

## Add a chat transport

Implement `App\Services\Chat\ChatTransport`, register it with
`TransportFactory::register('websocket', WebsocketTransport::class)`, and set
the `chat_transport` setting. See
[Content pipeline → Chat transports](../architecture/content.md#chat-transports).

## Add a middleware

```php
// app/Middleware/OfficeHoursMiddleware.php
final class OfficeHoursMiddleware implements Middleware
{
    public function handle(Request $request, Closure $next, ?string $parameter = null): Response
    {
        if ((int) date('G') < 8) {
            throw new HttpException(403, 'The board is closed until 08:00.');
        }

        return $next($request);
    }
}
```

Register the alias in `Kernel::boot()` and apply it with
`->middleware(['office-hours'])`. The `$parameter` is whatever follows the
colon, as in `can:topic.tag`.
