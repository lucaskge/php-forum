<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Generates the reference pages of the documentation from the running code.
 *
 * The four pages under docs/reference/ list every route, permission, setting
 * and console command. Written by hand they would be wrong within a month, so
 * they are generated instead: `php bin/console docs:reference` rewrites them
 * from the router, the database and the command table below.
 *
 * Nothing else reads these files; they exist for people.
 */
final class DocsReference
{
    /**
     * The console's own command table. `bin/console help` prints from here as
     * well, so the tool and its documentation cannot disagree.
     *
     * @var array<int,array{name:string,usage:string,summary:string,detail:string,destructive:bool}>
     */
    public const COMMANDS = [
        [
            'name' => 'migrate',
            'usage' => 'migrate',
            'summary' => 'Apply pending migrations',
            'detail' => 'Runs every migration file that is not yet recorded in the `migrations` table, in filename order, and records each one. Safe to run repeatedly: applying nothing is a normal outcome. Creates the database first if it does not exist and the credentials allow it.',
            'destructive' => false,
        ],
        [
            'name' => 'migrate:fresh',
            'usage' => 'migrate:fresh',
            'summary' => 'Drop every table and migrate from zero',
            'detail' => 'Drops every table in the configured database, then applies all migrations. The schema comes back empty — no accounts, no posts, no settings. Intended for development.',
            'destructive' => true,
        ],
        [
            'name' => 'db:seed',
            'usage' => 'db:seed',
            'summary' => 'Load the demo data',
            'detail' => 'Clears and repopulates the tables it owns with the demo board: roles, permissions, settings, themes, the sample categories and forums, the demo accounts and a set of topics and posts. It does not touch the schema.',
            'destructive' => true,
        ],
        [
            'name' => 'install',
            'usage' => 'install',
            'summary' => 'migrate:fresh followed by db:seed',
            'detail' => 'The one command for a new checkout: drops everything, rebuilds the schema, loads the demo data and prints the demo credentials. Never run it against a board with real content.',
            'destructive' => true,
        ],
        [
            'name' => 'key:generate',
            'usage' => 'key:generate',
            'summary' => 'Write a new APP_KEY into .env',
            'detail' => 'Generates a 32-character random key and writes or replaces the APP_KEY line in .env. Run it once per installation. Changing it later invalidates anything derived from it.',
            'destructive' => false,
        ],
        [
            'name' => 'theme:sync',
            'usage' => 'theme:sync',
            'summary' => 'Register themes found on disk',
            'detail' => 'Scans templates/themes/ for directories holding a theme.json and inserts any that are not yet in the themes table. Run it after copying a theme in by hand; the admin panel does the same thing from its own button.',
            'destructive' => false,
        ],
        [
            'name' => 'maintenance:run',
            'usage' => 'maintenance:run',
            'summary' => 'Purge stale sessions and throttles, expire suspensions',
            'detail' => 'Removes session-tracking rows older than a day, deletes expired rate-limit windows and lifts suspensions whose end date has passed. Harmless to run at any time; a daily cron entry is the intended use.',
            'destructive' => false,
        ],
        [
            'name' => 'user:promote',
            'usage' => 'user:promote <username>',
            'summary' => 'Give an account the administrator role',
            'detail' => 'Assigns the administrator role, makes it the account\'s primary role and sets its status to active. The recovery path when nobody can reach the admin panel any more.',
            'destructive' => false,
        ],
        [
            'name' => 'routes',
            'usage' => 'routes',
            'summary' => 'List every registered route',
            'detail' => 'Prints the verb, URI, route name and middleware of every route, in declaration order — which is also the order the router tries them.',
            'destructive' => false,
        ],
        [
            'name' => 'docs:reference',
            'usage' => 'docs:reference',
            'summary' => 'Regenerate the reference pages of the documentation',
            'detail' => 'Rewrites docs/reference/routes.md, permissions.md, settings.md and console.md from the router, the database and the command table. Run it after adding a route, a permission, a setting or a command.',
            'destructive' => false,
        ],
        [
            'name' => 'serve',
            'usage' => 'serve [host:port]',
            'summary' => 'Run PHP\'s built-in server on /public',
            'detail' => 'Starts PHP\'s development server on 127.0.0.1:8080 unless another address is given, with public/router.php handling static files. For development only — it serves one request at a time.',
            'destructive' => false,
        ],
    ];

    /** Where the source lives. Shown in the documentation footer. */
    public const REPOSITORY = 'https://github.com/lucaskge/php-forum';

    private string $directory;

    public function __construct(?string $directory = null)
    {
        $this->directory = $directory ?? BASE_PATH . '/docs/reference';
    }

    /**
     * Writes all four pages.
     *
     * @return array<int,string> One line per file written, for the console.
     */
    public function generate(): array
    {
        if (!is_dir($this->directory) && !mkdir($this->directory, 0o775, true) && !is_dir($this->directory)) {
            throw new \RuntimeException('Could not create ' . $this->directory);
        }

        $written = [];

        $written[] = $this->stampVersion();

        foreach ([
            'console.md' => $this->console(),
            'routes.md' => $this->routes(),
            'permissions.md' => $this->permissions(),
            'settings.md' => $this->settings(),
        ] as $file => $contents) {
            file_put_contents($this->directory . '/' . $file, $contents);
            $written[] = sprintf('docs/reference/%s (%s lines)', $file, number_format(substr_count($contents, "\n")));
        }

        return $written;
    }

    /** Makes a value safe to drop into a markdown table cell. */
    private function cell(?string $value, int $limit = 0): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', (string) $value) ?? '');
        $text = str_replace('|', '\\|', $text);

        if ($limit > 0 && mb_strlen($text) > $limit) {
            $text = mb_substr($text, 0, $limit - 1) . '…';
        }

        return $text;
    }

    /**
     * Writes the board's version and the page count into the documentation
     * footer, so the site says which version it describes without anyone
     * remembering to edit two places.
     */
    private function stampVersion(): string
    {
        $config = BASE_PATH . '/mkdocs.yml';

        if (!is_file($config) || !is_writable($config)) {
            return 'mkdocs.yml not writable — footer left alone';
        }

        $version = (string) Config::get('app.version', '0.0.0');

        // Version plus the source, which is the pair a reader actually wants
        // in a footer: which release these pages describe, and where the code
        // that produced them lives.
        $line = sprintf(
            'copyright: \'Coldwire %s &middot; <a href="%s">source on GitHub</a>\'',
            $version,
            self::REPOSITORY,
        );

        $contents = (string) file_get_contents($config);

        $contents = preg_match('/^copyright:.*$/m', $contents) === 1
            ? (string) preg_replace('/^copyright:.*$/m', $line, $contents)
            : preg_replace('/^(site_description:.*)$/m', '$1' . "\n" . $line, $contents, 1);

        file_put_contents($config, (string) $contents);

        return sprintf('mkdocs.yml footer (version %s)', $version);
    }

    private function header(string $title, string $intro): string
    {
        return '# ' . $title . "\n\n"
            . $intro . "\n\n"
            . "!!! info \"Generated\"\n"
            . "    This page is written by `php bin/console docs:reference`. Edit the code,\n"
            . "    not this file — the next run overwrites it.\n\n";
    }

    private function console(): string
    {
        $out = $this->header(
            'Console commands',
            "Everything `php bin/console` accepts. In Docker, prefix each with\n"
            . '`docker compose run --rm app`.',
        );

        $out .= "| Command | Does | |\n|---|---|---|\n";

        foreach (self::COMMANDS as $command) {
            $out .= sprintf(
                "| `%s` | %s | %s |\n",
                $command['usage'],
                $command['summary'],
                $command['destructive'] ? '**destroys data**' : '',
            );
        }

        $out .= "\n";

        foreach (self::COMMANDS as $command) {
            $out .= '## `' . $command['usage'] . "`\n\n";

            if ($command['destructive']) {
                $out .= "!!! danger \"Destroys data\"\n    Take a backup first, and never run it against a live board.\n\n";
            }

            $out .= $command['detail'] . "\n\n";
        }

        return $out;
    }

    private function routes(): string
    {
        $kernel = new Kernel();
        $kernel->boot();

        $out = $this->header(
            'Routes',
            "Every registered address, in the order the router tries them. Link to one\n"
            . "with its name — `\$this->route('topic.show', ['slug' => \$slug])` — never by\n"
            . 'writing the path out.',
        );

        $rows = [];

        foreach ($kernel->router()->routes() as $route) {
            $name = $route->routeName();
            $area = $name === '' ? 'unnamed' : (str_contains($name, '.') ? strtok($name, '.') : 'board');
            $rows[(string) $area][] = [
                'methods' => implode(', ', array_values(array_diff($route->methods(), ['HEAD']))),
                'uri' => $route->uri(),
                'name' => $name,
                'middleware' => implode(', ', $route->middlewareStack()),
            ];
        }

        ksort($rows);

        $out .= sprintf("%d routes in %d groups.\n\n", array_sum(array_map('count', $rows)), count($rows));

        foreach ($rows as $area => $routes) {
            $out .= '## ' . ucfirst((string) $area) . "\n\n";
            $out .= "| Method | Address | Name | Middleware |\n|---|---|---|---|\n";

            foreach ($routes as $route) {
                $out .= sprintf(
                    "| %s | `%s` | `%s` | %s |\n",
                    $route['methods'],
                    $route['uri'],
                    $route['name'],
                    $route['middleware'] === '' ? '—' : '`' . str_replace(', ', '`, `', $route['middleware']) . '`',
                );
            }

            $out .= "\n";
        }

        return $out;
    }

    private function permissions(): string
    {
        $db = Database::instance();

        $permissions = $db->select(
            'SELECT p.id, p.slug, p.name, p.description, p.group_name
             FROM permissions p
             ORDER BY p.group_name, p.position, p.slug',
        );

        $roles = $db->select('SELECT id, slug, name, is_staff FROM roles ORDER BY priority, id');

        $granted = [];

        foreach ($db->select('SELECT role_id, permission_id FROM role_permissions') as $row) {
            $granted[(int) $row['permission_id']][(int) $row['role_id']] = true;
        }

        // A role may hold the `*` wildcard instead of individual grants; without
        // this the grid would show the Administrator holding nothing at all.
        $wildcard = [];

        foreach ($permissions as $permission) {
            if ($permission['slug'] !== '*') {
                continue;
            }

            foreach (array_keys($granted[(int) $permission['id']] ?? []) as $roleId) {
                $wildcard[(int) $roleId] = true;
            }
        }

        $out = $this->header(
            'Permissions',
            "The global permission catalogue as this board has it. Grant them on\n"
            . "**Admin → Roles → edit**. A role holding `*` holds everything, present and\n"
            . 'future.',
        );

        $out .= sprintf("%d permissions in %d groups.\n\n", count($permissions), count(array_unique(array_column($permissions, 'group_name'))));

        $out .= "## Who holds what\n\n";
        $out .= "| Permission |";

        foreach ($roles as $role) {
            $out .= ' ' . $role['name'] . ' |';
        }

        $out .= "\n|---|" . str_repeat('---|', count($roles)) . "\n";

        foreach ($permissions as $permission) {
            $out .= '| `' . $permission['slug'] . '` |';

            foreach ($roles as $role) {
                $roleId = (int) $role['id'];

                if (isset($granted[(int) $permission['id']][$roleId])) {
                    $out .= ' ✓ |';
                } elseif (isset($wildcard[$roleId])) {
                    $out .= ' ✳ |';
                } else {
                    $out .= ' · |';
                }
            }

            $out .= "\n";
        }

        $out .= "\n";
        $out .= "✓ granted directly &nbsp;·&nbsp; ✳ held through the `*` wildcard"
            . " &nbsp;·&nbsp; · not held\n\n";

        $current = null;

        foreach ($permissions as $permission) {
            if ($permission['group_name'] !== $current) {
                $out .= $current === null ? '' : "\n";
                $current = (string) $permission['group_name'];
                $out .= '## ' . ucfirst(str_replace('_', ' ', $current)) . "\n\n";
                $out .= "| Slug | Allows |\n|---|---|\n";
            }

            $out .= sprintf(
                "| `%s` | **%s** — %s |\n",
                $permission['slug'],
                $this->cell($permission['name']),
                $this->cell($permission['description'] ?? ''),
            );
        }

        return $out . "\n";
    }

    private function settings(): string
    {
        $db = Database::instance();

        $settings = $db->select(
            'SELECT key_name, value, type, group_name, label, description, options
             FROM settings ORDER BY group_name, position, key_name',
        );

        $out = $this->header(
            'Settings',
            "Every board setting, as stored. Change them on **Admin → Settings**; the\n"
            . "screen builds itself from these rows, so adding one is an insert and not a\n"
            . 'template change.',
        );

        $out .= sprintf("%d settings in %d groups.\n\n", count($settings), count(array_unique(array_column($settings, 'group_name'))));

        $current = null;

        foreach ($settings as $setting) {
            if ($setting['group_name'] !== $current) {
                $out .= $current === null ? '' : "\n";
                $current = (string) $setting['group_name'];
                $out .= '## ' . ucfirst(str_replace('_', ' ', $current)) . "\n\n";
                $out .= "| Key | Type | Default here | Does |\n|---|---|---|---|\n";
            }

            $value = $this->cell($setting['value'] ?? '', 44);

            if ($setting['type'] === 'boolean') {
                $value = $value === '1' ? 'on' : 'off';
            } elseif ($value === '') {
                $value = '*(empty)*';
            } else {
                $value = '`' . $value . '`';
            }

            $choices = '';

            if ($setting['type'] === 'select' && is_string($setting['options'])) {
                $decoded = json_decode($setting['options'], true);

                if (is_array($decoded)) {
                    $keys = array_is_list($decoded) ? $decoded : array_keys($decoded);
                    $choices = ' One of: `' . implode('`, `', array_map('strval', $keys)) . '`.';
                }
            }

            $out .= sprintf(
                "| `%s` | %s | %s | **%s** — %s%s |\n",
                $setting['key_name'],
                $setting['type'],
                $value,
                $this->cell($setting['label']),
                $this->cell($setting['description'] ?? ''),
                $choices,
            );
        }

        return $out . "\n";
    }
}
