# Formatting

Posts, signatures, private messages and the board rules all use the same
markup. It is escaped first and a fixed set of tags is re-introduced
afterwards, so nothing a member writes can reach the browser as HTML.

## Tags

Every tag except `[*]` and `[hr]` must be closed, or it is shown as plain text.

| You type | You get |
|---|---|
| `[b]bold[/b]` | **bold** |
| `[i]italic[/i]` | *italic* |
| `[u]underline[/u]` | underlined |
| `[s]struck through[/s]` | ~~struck through~~ |
| `[mark]highlighted[/mark]` | highlighted |
| `[sub]subscript[/sub]` | subscript |
| `[sup]superscript[/sup]` | superscript |
| `[center]centred[/center]` | centred on its own line |
| `[spoiler]hidden[/spoiler]` | revealed on hover or keyboard focus |
| `[code]a code block[/code]` | monospaced and syntax-highlighted |
| `[code=php]…[/code]` | the same, labelled with its language |
| `[quote]quoted[/quote]` | an indented quote |
| `[quote=name]quoted[/quote]` | attributed to a member |
| `[url=https://…]label[/url]` | a link |
| `[img]https://…[/img]` | an image |
| `[list][*]one[*]two[/list]` | a bulleted list |
| `[hr]` | a horizontal rule |
| `@username` | a mention, which alerts that member |

Bare `https://` links become links automatically. Only `http` and `https` are
accepted anywhere a URL is taken — a `javascript:` or `data:` address is
dropped and its label is left as plain text.

The editor's own help panel and `/help` are both generated from the tag
registry in the code, so they cannot fall behind it.

## Code highlighting

`[code]` blocks are highlighted **on the server**. There is no client-side
library, because there is no client-side anything.

The highlighter is language-agnostic: rather than a grammar per language it
recognises what nearly all of them share — comments, strings, numbers,
keywords, booleans, variables, function calls, class names, markup tags,
attributes, operators and punctuation. Anything you paste gets useful colour.

`[code=php]` adds a label to the block. It does not change the highlighting.

All fourteen token colours are customisable in
[Admin → Themes → Appearance](../admin/themes.md), with a live preview.

## Mentions

`@name` links to that member's profile and raises an alert, unless they turned
mentions off. An e-mail address is not mistaken for a mention.

One post raises **one** alert per person, even if they follow the topic *and*
were quoted *and* were mentioned. The reason given is the most specific one:
quoted, then mentioned, then replied.

## Word filter

An administrator can configure words to be replaced when a post is displayed.
The stored text is untouched, so the original stays available to moderators and
the list can change at any time. Matching is whole-word and ignores case, so a
short entry cannot mangle a longer legitimate word.

See [Settings → Posting](../admin/settings.md).
