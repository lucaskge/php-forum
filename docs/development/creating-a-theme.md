# Creating a theme

A theme is a directory with a manifest. Nothing about the forum's behaviour
lives in one: templates receive data and print it, so a theme can change every
pixel without touching a controller, a query or a permission.

There are three levels of effort, and most people need only the first.

| | Effort | Use when |
|---|---|---|
| **Customise** | A form | You want different colours, spacing or fonts |
| **Inherit** | A few files | You want to change some markup, keep the rest |
| **Build** | Every template | You want a different board entirely |

## 1. Customise, without writing anything

**Admin → Themes → Customise** lists everything the active theme declares as
adjustable. The default theme declares 29 settings: five palette colours, five
text colours, the twelve syntax-highlighting colours and five layout values
including the interface typeface.

Saving writes the values to the `themes.settings` JSON column, and the board
serves them as `/theme/{slug}/settings.css`:

```css
/* Theme settings for "default". Generated from the database. */
:root {
    --bg: #0a0c0e;
    --accent: #6d93b8;
    --base-font-size: 14px;
}
```

The URL carries a version derived from `themes.updated_at`, so a change takes
effect on the next page load with no cache to clear. **Reset to defaults** on
the same screen drops the stored values.

## 2. Inherit from the default theme

The fastest way to a real theme. Create the directory, declare a parent, and
ship only the files you change:

```
templates/themes/glacier/
    theme.json
    assets/css/board.css
```

```json
{
    "name": "Glacier",
    "slug": "glacier",
    "version": "1.0.0",
    "author": "You",
    "description": "The default board, colder.",
    "parent": "default",
    "supports": { "customisable": true },
    "settings": []
}
```

`ThemeManager::lookupPaths()` walks the chain — child, then parent, then the
`default` theme as a final fallback — and takes the first file it finds. So a
theme of two files works: everything not overridden comes from the parent.

The loop tracks which slugs it has seen, so a manifest that names itself as its
own parent, or two themes that name each other, stop instead of hanging.

Register it:

```bash
make console CMD="theme:sync"
```

or press **Scan for new themes** on **Admin → Themes**. Then activate it there.

### Overriding one template

Copy it from the parent, keeping the same path:

```
templates/themes/glacier/partials/post.php      overrides the default's post.php
templates/themes/glacier/topic/show.php         overrides the topic page
```

Nothing registers an override. The file existing *is* the override.

## 3. Build one from nothing

Leave `parent` as `null` and provide everything. The full structure:

```
templates/themes/yours/
    theme.json
    assets/
        css/board.css
        img/favicon.svg
    layouts/
        main.php          the board
        narrow.php        forms
        minimal.php       sign-in, errors
        admin.php         the admin panel
        moderation.php    the moderation panel
    partials/             header, footer, nav-links, post, topic-row, …
    forum/  topic/  user/  auth/  messages/  notifications/
    search/  chat/  admin/  moderation/  static/  errors/
```

115 templates in total. Copy `default` and work through it — building from an
empty directory means finding out which templates exist by hitting 500s.

## The manifest

```json
{
    "name": "Glacier",
    "slug": "glacier",
    "version": "1.0.0",
    "author": "You",
    "description": "Shown on the themes screen.",
    "parent": "default",
    "screenshot": null,
    "supports": {
        "layouts": ["main", "narrow", "minimal", "admin", "moderation"],
        "customisable": true
    },
    "settings": []
}
```

`slug` must match the directory name — that is how the database row and the
files find each other.

### Declaring settings

Each entry becomes a field on the customise screen **and** a CSS custom
property in the generated stylesheet:

```json
{ "key": "accent", "group": "Palette", "label": "Accent",
  "type": "colour", "default": "#6d93b8", "css": "--accent",
  "hint": "Buttons, current page markers, highlights." }
```

| Field | |
|---|---|
| `key` | Stored name, unique within the theme |
| `group` | The fieldset it appears under |
| `label`, `hint` | What the administrator reads |
| `type` | `colour`, `integer` or `select` |
| `css` | The custom property it writes |
| `default` | Used until somebody saves something else |
| `unit` | Appended to an integer, e.g. `px` |
| `min`, `max` | Clamped server-side |
| `options` / `values` | For a `select`: the labels shown, and the CSS each writes |

A `select` maps a key onto a real CSS value, which is how the typeface picker
offers four words and writes a full font stack:

```json
{ "key": "body_font", "type": "select", "css": "--font-sans", "default": "system",
  "options": { "system": "System sans-serif", "mono": "Monospace throughout" },
  "values": {
      "system": "-apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, sans-serif",
      "mono": "ui-monospace, SFMono-Regular, Menlo, Consolas, monospace"
  } }
```

Values are sanitised on save: a colour must match `#rrggbb`, an integer is
clamped to `min`/`max`, and a `select` value must be one of the declared keys.
Anything else falls back to the default — so a hand-edited database row cannot
inject CSS through this.

Declare no settings and the customise screen says so. `"customisable": false`
hides it entirely.

## Writing the CSS

Assets live under `assets/` and are served from `/theme/{slug}/{path}`, with a
cache-busting `?v=` from the file's mtime. Reference them with the helper, never
by hand:

```php
<link rel="stylesheet" href="<?= $this->e($this->assetUrl('css/board.css')) ?>">
```

If the file is missing from your theme, `assetUrl()` falls back to the default
theme's copy — so an inherited theme that ships no CSS still renders.

**Use the custom properties, not literal colours**, or the customise screen
will not reach your rules:

```css
.topic-tag {
    background: var(--surface-2);
    color: var(--ink-dim);
    border: 1px solid var(--line);
    border-radius: var(--radius);
}
```

The properties the default theme defines:

| Group | |
|---|---|
| Surfaces | `--bg` `--bg-grid` `--surface` `--surface-2` `--surface-hover` |
| Lines | `--line` `--line-soft` `--line-strong` |
| Text | `--ink` `--ink-strong` `--ink-dim` `--ink-faint` |
| Accent | `--accent` `--accent-soft` `--accent-faint` |
| States | `--ok` `--warn` `--danger` `--danger-soft` |
| Links | `--link` `--link-hover` |
| Code | `--code-bg` `--code-text`, and `--tok-*` for the twelve token classes |
| Layout | `--font-sans` `--font-mono` `--radius` `--gap` `--shell-width` `--base-font-size` |

## The three rules a theme must not break

**1. No JavaScript.** Not a file, not a tag, not an `onclick`. The board's
Content-Security-Policy is `script-src 'none'`, so a script would not run
anyway, and a feature test crawls every rendered page for one. Interactions are
forms, links and `<details>`.

**2. No inline `style` attributes.** `style-src 'self'` blocks them silently —
the rule simply does nothing, which is a miserable thing to debug. Anything
dynamic is a class. For values that cannot be known in advance, the board
generates classes in `DynamicStyles` and serves them at `/board-styles.css`.

**3. Every username goes through `partials/username.php`.** That partial is
what applies the role colour and the staff badge. A name printed any other way
loses both, and that is a bug report waiting to happen.

## Templates

A template is plain PHP. `$this` is the `View`, and the data is whatever the
controller passed, already extracted into variables:

```php
<?php /** @var App\Support\View $this */ ?>
<h1 class="page-title"><?= $this->e($topic['title']) ?></h1>

<?php foreach ($posts as $post): ?>
    <?= $this->partial('partials/post', ['post' => $post]) ?>
<?php endforeach; ?>
```

The helpers you will use constantly:

| | |
|---|---|
| `$this->e($v)` | Escape. Everything, always |
| `$this->content($raw)` | Board markup → HTML. The one thing not escaped after |
| `$this->url($path)`, `$this->route($name, $params)` | Links. Never write a path by hand |
| `$this->formAction($name)`, `$this->routeField($name)` | GET forms — both are required |
| `$this->csrfField()` | Every POST form |
| `$this->partial($template, $data)` | Include a partial |
| `$this->can($permission)` | Guard a link, so no dead buttons appear |
| `$this->active($prefix)`, `$this->shared($key)` | Current page, shared data |
| `$this->assetUrl($path)` | Your theme's files |

No template opens a database connection. If a template needs something it was
not given, that is a controller to fix, not a query to add.

## Testing it

Activate the theme and walk the board — index, a forum, a topic, sign-in, the
admin panel, an error page, and all of it at phone width. Then:

```bash
make test
```

The suite renders pages through the **active** theme, so a theme that breaks a
form, drops a CSRF field or introduces an inline style fails the run. That is
the quickest check that an override did not quietly break something.

## Packaging

A theme is a directory. Zip it, commit it, copy it to another board's
`templates/themes/` and run `theme:sync`. There is no build step, no
compilation and no dependency to install — which is the point.
