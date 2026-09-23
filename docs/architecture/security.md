# Security

Every measure, what it defends against, and the file it lives in. If you are
reviewing the board, this page is the index.

## Response headers — `config/security.php`, sent by `Response::send()`

```
Content-Security-Policy: default-src 'none'; base-uri 'none'; form-action 'self';
                         frame-ancestors 'none'; img-src 'self' data:;
                         style-src 'self'; font-src 'self'; script-src 'none'
X-Content-Type-Options:  nosniff
X-Frame-Options:         DENY
Referrer-Policy:         strict-origin-when-cross-origin
Permissions-Policy:      geolocation=(), microphone=(), camera=(), interest-cohort=()
```

`script-src 'none'` is the important one: the board ships no JavaScript, and the
CSP makes that a browser-enforced guarantee rather than a promise. Even a
successful injection of a `<script>` tag executes nothing.

`style-src 'self'` has a consequence that trips people up constantly: **inline
`style="…"` attributes do not work.** Anything dynamic — a role colour, a
progress bar width, an avatar size — must be a generated CSS class. That is
what `app/Services/DynamicStyles.php` is for, and
the feature test *"no page relies on an inline style attribute"* fails the
build if any rendered page contains ` style="`.

## SQL injection

Every statement in `app/Repositories/` is prepared with bound parameters, on a
PDO connection with `ATTR_EMULATE_PREPARES => false`, so binding happens in the
server and not by string interpolation.

Where a query needs a fragment that cannot be bound — a sort column, a
direction — the repository maps the request value through a fixed internal
allow-list and falls back to a default. No request value ever reaches SQL as
text.

No SQL exists outside `app/Repositories/`. Auditing the board for injection is
reading one directory.

## Cross-site scripting

Three layers:

1. `ContentFormatter::render()` escapes with
   `htmlspecialchars($raw, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')` **before**
   re-introducing a fixed tag set. No branch interpolates member text into an
   attribute or tag name.
2. `View::e()` escapes everything else a template prints.
3. The CSP blocks execution even if 1 and 2 both failed.

`ENT_SUBSTITUTE` matters: without it, invalid UTF-8 makes `htmlspecialchars()`
return an empty string, and an attacker who can produce that can blank out
escaping in surprising places.

## Cross-site request forgery — `app/Support/Csrf.php`, `app/Middleware/CsrfMiddleware.php`

A per-session token, compared with `hash_equals()`, required on every
`POST`. The middleware is applied globally in `routes/web.php`, so a route
cannot forget it. Templates emit it with `<?= $this->csrfField() ?>`, and a
missing or stale token produces the **419** page, which tells the member to
reload rather than showing a raw error.

`SameSite=Lax` on the session cookie is a second layer; `form-action 'self'`
in the CSP is a third.

## Authentication — `app/Services/AuthService.php`

- `password_hash()` with `PASSWORD_DEFAULT` (bcrypt today, whatever PHP moves
  to tomorrow), verified with `password_verify()`, and re-hashed transparently
  on sign-in when `password_needs_rehash()` says the cost or algorithm changed.
- Minimum length 10, maximum 4096 (`config/security.php`). The maximum exists
  because bcrypt's own input limit makes very long inputs a denial-of-service
  vector, not a security gain.
- Sign-in failures are generic: the board never distinguishes "no such account"
  from "wrong password", so the form cannot be used to enumerate members.
- Every attempt is recorded in `login_attempts` with IP and outcome.
- The session ID is regenerated on sign-in and on sign-out — session fixation.
- Password reset tokens are stored **hashed**, single-use, and expire after an
  hour. A database read does not yield a usable token.

## Sessions — `config/session.php`, `app/Support/Session.php`

Strict mode on, `HttpOnly`, `SameSite=Lax`, `Secure` when `SESSION_SECURE=true`
(set it the moment you are behind HTTPS), idle timeout from
`SESSION_LIFETIME`, and a custom cookie name so the board does not advertise
itself through `PHPSESSID`.

## Authorisation — two layers

**Global permissions.** 46 slugs, grouped, granted to roles, unioned across
every role a member holds. `AccessControl::allows($user, 'topic.create')`.
A `*` grant is the wildcard the Administrator role holds.

**Per-forum access.** The `forum_permissions` matrix gives every role × forum
pair five independent flags: view, read, create topic, reply, moderate. The
union across a member's roles wins, so adding a role can only widen access,
never narrow it.

Policies (`TopicPolicy`, `PostPolicy`, `UserPolicy`) ask `AccessControl` and
are the only thing controllers call. **No code anywhere compares a role name.**
That is what makes a permission invented tomorrow work everywhere at once, and
it is the rule most worth defending in review.

Authorisation is enforced at the route (middleware), in the controller
(policy), and in the repository (listing queries exclude forums the viewer
cannot see) — so a hidden forum is absent from listings, not merely
unclickable, and a direct URL to it 403s.

## Uploads — `app/Services/UploadGuard.php`, `app/Services/AvatarService.php`

Defence in depth, in this order, cheapest first:

1. **Size**, with `clearstatcache()` first so PHP's stat cache cannot hide the
   real size.
2. **Declared extension** — used for exactly one thing: refusing it. A
   dangerous or compound extension (`.php`, `.phtml`, `.jpg.php`) is rejected
   outright.
3. **Signature bytes** — the first 32 bytes must match a known image magic
   number that the policy allows.
4. **`finfo`** — the detected MIME must agree with the signature.
5. **`getimagesize()`** — must parse, and its MIME must agree again. Three
   independent opinions must match.
6. **Dimensions and pixel ceiling** — checked *before* anything decodes, so a
   small file declaring a 60000×60000 canvas cannot exhaust memory (the
   "decompression bomb").
7. **Marker scan** for executable content.
8. **Structural end-of-image check** — parses to the format's real end marker
   (`IEND`, `FFD9`, GIF trailer, RIFF length) and rejects anything appended
   after it. This is the precise check for the classic polyglot.
9. **Mandatory re-encode** through GD or Imagick. The bytes that land on disk
   are bytes the board generated; nothing the member sent is stored.
10. **Generated filename** — `safeFilename()` produces the name and the
    extension. Nothing from the client touches the filesystem.
11. **Execution disabled** in `public/uploads/` by `.htaccess`, and by the
    nginx config in the installation guide.

!!! warning "A lesson about marker scanning"
    An earlier version scanned for markers as short as `<%`. Two bytes occur by
    chance roughly once per 64 KB of compressed data, so real photographs were
    rejected constantly. Every marker is now at least five bytes, and the
    appended-payload case is caught structurally instead. **If you add a marker,
    it must be at least five bytes**, and the reasoning is in a comment beside
    the list.

Steps 9 and 10 are the ones that actually matter. Even if 1–8 were all
defeated, the stored file is a re-encoded image with a generated name in a
directory that cannot execute anything.

## Rate limiting — `app/Support/RateLimiter.php`, `app/Middleware/RateLimitMiddleware.php`

Database-backed fixed windows, applied as `throttle:bucket`:

| Bucket | Limit | Window |
|---|---|---|
| `login` | 6 | 15 min |
| `register` | 5 | 1 hour |
| `password-reset` | 5 | 1 hour |
| `post` | 25 | 10 min |
| `message` | 20 | 1 hour |
| `report` | 10 | 1 hour |
| `chat` | 30 | 5 min |
| `search` | 60 | 5 min |

Exceeding one produces the **429** page. Tune them in `config/security.php`.

## Account states

| State | Can sign in | Effect |
|---|---|---|
| `active` | yes | Normal |
| `pending` | yes | Awaiting email verification, where required |
| `suspended` | yes | Signs in, lands on **Your record**, sees a persistent banner, cannot post |
| `banned` | no | Refused at sign-in with the reason |

Suspension deliberately lets the member in: a suspended member who simply finds
the board broken learns nothing, complains, and repeats the behaviour. The
banner and the record page tell them what happened, by whom, and when it ends.
`RestrictionMiddleware` enforces it on every write route.

## Errors and information disclosure

With `APP_DEBUG=false` — the only correct production setting — an uncaught
exception produces the **500** page with no trace, no file path, no query. The
detail goes to `storage/logs/app.log`.

Error pages never state whether a resource exists: a topic in a forum you
cannot see returns the same 404 as one that was never created. `Logger::security()`
records refused uploads, failed sign-ins and permission denials for review.

The board also does not expose its own configuration to members: versions,
paths, extension availability and database details appear in the admin panel
only, to accounts holding `admin.access`.

## The no-JavaScript guarantee

There is no JavaScript in the source, in any theme, or in any rendered page.
Every interaction is a form submission or a link. This is enforced three ways:
by the CSP, by the feature test *"no page ships any javascript"* which
crawls rendered output for `<script`, `on*=` attributes and `javascript:`
URLs, and by the absence of any build step that could introduce one.

It is also a security property in itself: no XSS sink, no dependency tree, no
supply-chain surface.

## Checklist before going live

- [ ] `APP_DEBUG=false`
- [ ] `APP_URL` matches the real address
- [ ] `SESSION_SECURE=true` behind HTTPS
- [ ] `public/install.php` and `app/Install/` deleted
- [ ] The database user holds privileges on the board's database only — never `ON *.*`
- [ ] `storage/` and `public/uploads/` are writable but not web-executable
- [ ] Only `public/` is reachable from the web
- [ ] The demo accounts are deleted or their passwords changed
