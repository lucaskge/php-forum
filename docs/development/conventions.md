# Conventions

Rules the codebase actually follows. Following them keeps a change consistent
with everything around it; breaking one usually breaks something that is not
obviously related.

## The five rules

**1. No JavaScript.** Not in a template, not in a theme, not inline, not in an
attribute. Every interaction is a form or a link. The CSP enforces it and a
test crawls for it. If a feature seems to need it, it needs rethinking as a
form submission or a server-rendered state.

**2. No inline `style` attributes.** `style-src 'self'` blocks them, so they
silently do nothing. Anything dynamic becomes a generated class — see
`DynamicStyles`.

**3. All SQL in `app/Repositories/`.** Controllers and services never open a
connection. Templates certainly never do.

**4. No role-name comparisons.** `$user['role'] === 'admin'` is forbidden.
Ask a policy, which asks `AccessControl`.

**5. Never build a URL by hand.** `$this->url()`, `$this->route()`,
`Url::to()`, `Url::route()`. GET forms use `formAction()` + `routeField()`.

## PHP style

- `declare(strict_types=1);` at the top of every file.
- One class per file, PSR-4, `final` unless it is designed to be extended.
- Type every parameter, property and return. Use `?Type` rather than a
  doc-comment for nullability, and array shapes in doc-comments for arrays.
- Four spaces, no tabs. Braces on their own line for classes and methods.
- Constructor property promotion where it reads well.
- No static state except deliberate caches (`Config`, `Env`, `SettingsService`,
  `Url`'s route table), all resettable so tests are isolated.

## Naming

| Thing | Shape | Example |
|---|---|---|
| Class | `StudlyCase` | `TopicRepository` |
| Method, variable | `camelCase` | `findBySlug`, `$topicId` |
| Constant | `UPPER_SNAKE` | `INLINE_TAGS` |
| Table, column | `snake_case`, tables plural | `post_edits`, `last_post_at` |
| Route name | dotted, area first | `admin.settings`, `topic.show` |
| Permission slug | dotted, area first | `topic.create`, `moderation.access` |
| Setting key | `snake_case` | `chat_enabled` |
| Template | `area/name.php` | `topic/show.php` |
| CSS class | kebab, no abbreviations | `.topic-row`, `.quote-head` |

British spelling in prose, identifiers and comments — `colour`, `authorise`,
`normalise`. The database columns use it too (`roles.colour`), so matching it
avoids a mixture.

## Comments

Comment the **why**, never the what. `// increment the counter` above an
increment is noise; a note explaining that images must resolve before links
because the linkifier would otherwise eat the image source is the reason the
next person does not reintroduce a bug.

Every non-obvious ordering, every security-relevant decision and every
workaround for a MySQL/MariaDB difference carries a comment saying why. Those
are load-bearing. Do not tidy them away.

## Controllers

Thin. Validate, delegate, render.

```php
public function store(Request $request): Response
{
    $forum = $this->forums->findBySlug((string) $request->route('forum'));

    if ($forum === null) {
        throw HttpException::notFound('There is no forum at this address.');
    }

    if (!$this->access->canCreateTopicIn((int) $forum['id'])) {
        throw HttpException::forbidden('You cannot start a topic in this forum.');
    }

    $validator = Validator::make(['title' => $title, 'content' => $content])
        ->label('title', 'Subject')
        ->required('title')->between('title', 5, 190)
        ->required('content')->between('content', 5, 60000);

    if ($validator->fails()) {
        return $this->withErrors($validator->errors(), ['title' => $title], $formUrl);
    }

    $result = $this->service->createTopic(…);

    Flash::success('Your topic was posted.');

    return $this->redirect(Url::route('topic.show', ['slug' => $result['slug']]));
}
```

The shape is always the same: **resolve, authorise, validate, delegate,
flash, redirect.** `withErrors()` puts the messages and the member's own input
back in the session and returns them to the form, so nothing they typed is
lost.

No SQL. No business rule that another entry point would also need. No HTML.

## Services

Own the write paths and the transactions. A service method that touches three
tables wraps them in one `transaction()`. If two controllers would duplicate
logic, it belongs in a service — that is the entire test for whether something
should move.

## Repositories

One per aggregate. Every method returns arrays, never objects: the board has no
ORM and pretending otherwise adds a layer without adding a guarantee. Listing
methods take the viewer so that visibility filtering happens in SQL, not after.

## Templates

`templates/themes/<theme>/`. They receive data and print it. A template that
needs something it was not given is a controller bug, not a reason to fetch it
in the view.

Escape everything: `<?= $this->e($value) ?>`. The only exception is content
already through `ContentFormatter::render()`, which is HTML by construction.

Repeated fragments become partials in `partials/`. Every username on the board
goes through `partials/username.php` — that is why role colours appear
everywhere at once, and a username printed any other way is a bug.

## Commits

Present tense, one concern each, explaining the why in the body where it is not
obvious from the subject. `Fix avatar upload rejecting valid photographs`
followed by a paragraph on the two-byte-marker false-positive rate is worth ten
times `fix bug`.
