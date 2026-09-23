# Running the servers

Two servers, and neither has to be running for the other to work:

| | Address | What it is |
|---|---|---|
| **Board** | <http://127.0.0.1:8080> | The forum itself |
| **Documentation** | <http://127.0.0.1:8100> | These pages, with search and live reload |

## With `make`

The `Makefile` at the project root wraps the compose commands. `make` on its
own prints the list.

```bash
make up         # board          → http://127.0.0.1:8080
make docs       # documentation  → http://127.0.0.1:8100
make stop       # stop both, keep them ready to start again
make start      # start both again, instantly
make status     # what is running, and on which ports
make down       # stop and remove the containers
```

| Want to | Run | Long form |
|---|---|---|
| Start the board | `make up` | `docker compose up -d app` |
| Start the docs | `make docs` | `docker compose --profile docs up -d docs` |
| Stop just the docs | `make docs-stop` | `docker compose stop docs` |
| Stop both, keep them | `make stop` | `docker compose --profile docs stop` |
| Start both again | `make start` | `docker compose --profile docs start` |
| Restart both | `make restart` | `docker compose --profile docs restart` |
| Remove everything | `make down` | `docker compose --profile docs down` |
| Watch the logs | `make logs` | `docker compose --profile docs logs -f` |

Nothing is hidden: every target is one line in the `Makefile` with its
equivalent compose command in a comment beside it.

## `stop` or `down`?

**`make stop`** halts the containers and keeps them, so `make start` brings
both back instantly. Use it at the end of the day.

**`make down`** removes the containers and the network. The next `make up`
recreates them, a few seconds slower. Use it when you have changed
`docker-compose.yml` or the `Dockerfile`.

Neither touches your files, and neither touches the database — that is your own
MySQL, running outside this project. Nothing you have posted is at risk from
either.

!!! warning "`docker compose down` alone is not enough"
    The documentation service sits behind a compose profile, so it never starts
    with a plain `docker compose up`. The cost of that: **`docker compose down`
    on its own leaves the docs container running**, and then fails to remove the
    network with `Resource is still in use`. The profile has to be named —
    `docker compose --profile docs down` — which is exactly what `make down`
    does.

## The commands you run most

Each runs inside the app container, so no PHP on the host is needed:

```bash
make migrate                  # apply pending migrations
make reference                # regenerate docs/reference/ from the live code
make test                     # the suite, against the scratch database
make console CMD="routes"     # any console command
make shell                    # a shell inside the app container
```

## Without Docker

With PHP and Python on the host, the same two servers are:

```bash
php bin/console serve                     # board, 127.0.0.1:8080
mkdocs serve                              # documentation, 127.0.0.1:8000
```

Stop either with Ctrl+C. Both are development servers — for production, serve
`public/` with Apache or nginx as described in
[Installation](installation.md).

## When something is already using a port

`make up` fails with `port is already allocated` if 8080 is taken. Find what
holds it:

```bash
ss -ltnp | grep 8080
```

Then either stop that, or change the published port in `docker-compose.yml`
(`"8081:8080"`) and set `APP_URL` in `.env` to match.
