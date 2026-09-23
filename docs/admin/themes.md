# Themes

*Admin → Themes*. Lists every theme found on disk and every theme registered in
the database, with its version, author, template count and state.

| Action | |
|---|---|
| **Activate** | Makes it the board's theme |
| **Enable / Disable** | A disabled theme cannot be activated. The active one cannot be disabled |
| **Scan directory** | Registers themes added to `templates/themes/` since the last scan |
| **Details** | Its manifest and metadata |
| **Appearance** | Its customisable settings — see below |

## Appearance

*Admin → Themes → (theme) → Appearance*, or **Colours and type** in the
sidebar.

A theme declares which of its values may be changed, in the `settings` array of
its `theme.json`. The screen is generated from that declaration, so a custom
theme exposes whatever it wants with no PHP change.

The default theme exposes **29 settings** in four groups:

| Group | |
|---|---|
| **Palette** | Page background, panel background, raised panel, borders, accent |
| **Text** | Body text, headings, secondary text, link, link on hover |
| **Code** | Code background, plain code, and one colour for each of the twelve token classes the highlighter emits |
| **Layout** | Base text size, corner radius, panel spacing, maximum page width, interface typeface |

Colours use a native `<input type="color">` — a real picker, no scripting. The
page shows a **live code preview** rendered with the saved values, so you can
see the highlighting palette as it will look.

**Reset** discards every override and returns to what the theme ships with.

### How it is delivered

Values are written to the theme's database record and served as a real
stylesheet at `/theme/{slug}/settings.css`, linked after the theme's own CSS:

```css
:root {
    --bg: #0a0c0e;
    --accent: #6d93b8;
    --tok-keyword: #7fa7cc;
}
```

A real file rather than an inline `<style>` block is what lets the
Content-Security-Policy keep refusing inline styles outright. The URL carries a
version that changes when the settings do, so browsers pick up a change
immediately.

### Values are validated

Every value is checked against its declared type before it can reach a
stylesheet: a colour must match `#rrggbb`, an integer is clamped to its
declared range, a select must be one of its own options. Anything else falls
back to the default. A stored setting can never inject arbitrary CSS.

## Installing a theme

1. Put the directory in `templates/themes/`.
2. **Scan directory**.
3. **Activate**.

## Writing one

See [Extending the board](../development/extending.md#add-or-customise-a-theme) for the manifest
format, template lookup order and the conventions a theme must follow.

## Making your own

Building or modifying a theme is a developer task, covered step by step in
[Creating a theme](../development/creating-a-theme.md) — customising, inheriting
from the default theme, and building one from nothing.
