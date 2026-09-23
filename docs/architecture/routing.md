# Routing and URLs

## The three URL modes

The board must run on hosting where you do not control the web server, so it
never assumes URL rewriting is available. `APP_URL_MODE` in `.env` picks the
shape of every link the board emits:

| Mode | A topic looks like | Needs |
|---|---|---|
| `query` | `/index.php?r=/topic/hello-world` | Nothing. The default |
| `pathinfo` | `/index.php/topic/hello-world` | `PATH_INFO`, on by default nearly everywhere |
| `path` | `/topic/hello-world` | mod_rewrite, or the nginx `try_files` block |

All three run the same routes and the same controllers. Switching is a one-line
change in `.env` and takes effect immediately; nothing is stored in the
database and no links are rewritten, because links are generated per request.

The `r` parameter is built by hand rather than with `http_build_query`, so the
slashes in a path stay readable instead of becoming `%2F`:

```php
$url = $entrypoint . '?' . self::ROUTE_PARAMETER . '=' . self::encodePath($path);
```

`encodePath()` encodes each segment and rejoins them with real slashes.

## Building links

Never write a URL by hand in a template or controller. Templates that hardcode
`/topic/...` break in query mode; templates that hardcode `index.php?r=...`
break in path mode. Two helpers cover everything:

```php
$this->url('/forum/general')                  // a path
$this->route('topic.show', ['slug' => $slug]) // a named route
```

Both take optional query parameters and a fragment:

```php
$this->route('topic.show', ['slug' => $slug], ['page' => 3], '#post-42');
```

In PHP outside a template, the same lives on `Url::to()` and `Url::route()`.

Route names are registered by `->name()` at declaration time, so
`Url::route()` knows the URI template for every named route.

## The GET form rule

This is the single easiest way to break the board, and it caused a real bug: in
query mode a form's action carries the route in its query string, and **an HTML
GET form discards the action's query string** before appending its own fields.
So this silently posts to the board index:

```php
<form method="get" action="<?= $this->route('search') ?>">   <!-- WRONG -->
```

Every GET form must use the two helpers made for it:

```php
<form method="get" action="<?= $this->formAction('search') ?>">
    <?= $this->routeField('search') ?>
    …
</form>
```

- `formAction()` returns the bare entrypoint in query mode, the full URL otherwise.
- `routeField()` emits the hidden `r` input in query mode, nothing otherwise.

POST forms are unaffected — a POST action keeps its query string — but using
the helpers there too costs nothing.

The feature test *"every GET form survives the rewrite-free url mode"*
walks every template, finds each `<form method="get">`, and fails if it does
not carry a route field. That test is why the rule cannot quietly rot.

## Declaring routes

Every route lives in `routes/web.php`. Nothing else registers routes.

```php
$router->get('/topic/{slug}', [TopicController::class, 'show'])
    ->name('topic.show');

$router->post('/topic/{slug}/reply', [TopicController::class, 'reply'])
    ->middleware(['auth', 'restricted', 'throttle:post'])
    ->name('topic.reply');
```

Placeholders are `{name}` with an optional constraint, `{id:\d+}`. The
compiler swaps each placeholder for an inert token, quotes the rest of the URI,
then substitutes the capture groups — so a literal dot or dash in a path can
never be read as a pattern.

Groups apply a prefix and middleware to everything inside:

```php
$router->group(['prefix' => 'admin', 'middleware' => ['auth', 'can:admin.access']], function ($router) {
    $router->get('/settings', [SettingsController::class, 'index'])->name('admin.settings');
});
```

## Matching

`Router::dispatch()` walks the routes in declaration order, takes the first
whose regex matches the path, and:

- if the verb is accepted, runs the middleware pipeline;
- if the path matched but no route accepts the verb, throws **405**;
- if nothing matched, throws **404**.

That 405 matters: a `POST` to a `GET`-only address tells you the route exists
and the form's method is wrong, which is much easier to debug than a 404.

## Middleware

Middleware is a pipeline built with `array_reduce` over the reversed stack, so
the first alias listed is the outermost wrapper and the controller is the
innermost. Each one may inspect the request, short-circuit with its own
response, or call `$next`.

| Alias | Does | Parameter |
|---|---|---|
| `auth` | Requires a signed-in member; redirects to sign-in with a return address | — |
| `guest` | Requires *not* being signed in (sign-in, register) | — |
| `can` | Requires a global permission | the slug: `can:admin.access` |
| `csrf` | Verifies the token on every unsafe verb | — |
| `throttle` | Fixed-window rate limit | the bucket: `throttle:login` |
| `restricted` | Blocks members whose account is posting-restricted | — |
| `maintenance` | Serves the maintenance page to non-administrators | — |
| `setting` | Requires a board setting to be on | the key: `setting:chat_enabled` |

`setting` is what makes "disable the chat" actually disable it: hiding the menu
link is presentation, and the route itself must refuse. Every optional feature
carries its own `setting:` guard.

Register a new alias in `Kernel::boot()`; the class implements
`App\Middleware\Middleware` — one `handle(Request $request, Closure $next, ?string $parameter): Response`.

## Controllers

A controller method receives the `Request` and returns either a `Response` or a
string of HTML. Route parameters come from `$request->route('slug')`, input
from `$request->input('title')`, both already decoded.

```php
public function show(Request $request): Response
{
    $topic = (new TopicRepository())->findBySlug((string) $request->route('slug'));

    if ($topic === null) {
        throw HttpException::notFound();
    }

    (new TopicPolicy())->authoriseView(Auth::user(), $topic);

    return Response::html($this->view->render('topic/show', ['topic' => $topic]));
}
```

Throwing `HttpException` is how a controller returns an error page; the kernel
catches it and renders the matching template.
