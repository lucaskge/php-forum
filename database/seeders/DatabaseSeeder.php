<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Repositories\ForumRepository;
use App\Repositories\TopicRepository;
use App\Services\TopicService;
use App\Support\Database;
use App\Support\Dates;
use App\Support\Str;

/**
 * Development seed data.
 *
 * Creates the permission grid, the roles, the board structure, demo accounts
 * and a realistic amount of discussion so every screen has something to show.
 * Safe to re-run: it clears the tables it owns first.
 */
final class DatabaseSeeder
{
    private Database $db;

    /** @var array<string,int> */
    private array $roleIds = [];

    /** @var array<string,int> */
    private array $permissionIds = [];

    /** @var array<string,int> */
    private array $userIds = [];

    /** @var array<string,int> */
    private array $forumIds = [];

    /** @var array<int,string> */
    private array $log = [];

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::instance();
    }

    /** @return array<int,string> Progress lines for the console. */
    public function run(): array
    {
        $this->truncate();

        // The board's configuration is defined once, in CoreSeeder, and shared
        // with the web installer. Only the demo content below is specific here.
        $core = new CoreSeeder($this->db);

        foreach ($core->run(withStarterBoard: false) as $line) {
            $this->note($line);
        }

        $this->roleIds = $core->roleIds();
        $this->permissionIds = $core->permissionIds();

        $this->seedUsers();
        $this->seedBoard();
        $this->seedForumPermissions();
        $this->seedDiscussion();
        $this->seedChat();
        $this->seedMessagingAndModeration();
        $this->refreshCounters();

        return $this->log;
    }

    private function note(string $message): void
    {
        $this->log[] = $message;
    }

    private function truncate(): void
    {
        $tables = [
            'chat_presence', 'chat_bans', 'chat_messages', 'chat_rooms',
            'moderation_actions', 'user_notes', 'warnings', 'bans', 'reports',
            'notifications', 'private_messages',
            'bookmarks', 'topic_subscriptions', 'post_edits', 'posts', 'topics',
            'forum_permissions', 'forums', 'categories',
            'password_resets', 'login_attempts', 'sessions', 'rate_limits',
            'user_roles', 'role_permissions', 'users', 'permissions', 'roles',
            'settings', 'themes',
        ];

        $this->db->execute('SET FOREIGN_KEY_CHECKS = 0');

        foreach ($tables as $table) {
            $this->db->execute('TRUNCATE TABLE `' . $table . '`');
        }

        $this->db->execute('SET FOREIGN_KEY_CHECKS = 1');
        $this->note('Cleared existing data.');
    }





    // ------------------------------------------------------------------
    // Accounts
    // ------------------------------------------------------------------

    private function seedUsers(): void
    {
        $users = [
            [
                'username' => 'admin', 'email' => 'admin@coldwire.local', 'password' => 'coldwire-admin-1',
                'role' => 'administrator', 'title' => 'Board operator', 'days' => 900,
                'bio' => "Runs the machine. Reachable by private message for anything the moderators cannot settle.\n\nUptime is a feature.",
                'signature' => 'operator — [i]if it is not logged, it did not happen[/i]',
                'location' => 'behind the rack',
            ],
            [
                'username' => 'nullroute', 'email' => 'nullroute@coldwire.local', 'password' => 'coldwire-mod-1',
                'role' => 'moderator', 'title' => 'Moderator', 'days' => 760,
                'bio' => 'Network engineer. Moderates the technology and networking boards. Reports get read, eventually all of them.',
                'signature' => '[i]route add default via patience[/i]',
                'location' => 'AS64512',
            ],
            [
                'username' => 'hexdump', 'email' => 'hexdump@coldwire.local', 'password' => 'coldwire-mod-2',
                'role' => 'moderator', 'title' => 'Moderator', 'days' => 610,
                'bio' => 'Reverse engineering, firmware, and whatever is on the bench this week.',
                'signature' => null,
                'location' => 'lab',
            ],
            [
                'username' => 'grepwire', 'email' => 'grepwire@coldwire.local', 'password' => 'coldwire-user-1',
                'role' => 'trusted', 'title' => null, 'days' => 480,
                'bio' => 'Sysadmin by trade. I like boring software that keeps running.',
                'signature' => '[code]tail -f /var/log/everything[/code]',
                'location' => 'Lisbon',
            ],
            [
                'username' => 'pale_socket', 'email' => 'pale.socket@coldwire.local', 'password' => 'coldwire-user-2',
                'role' => 'member', 'title' => null, 'days' => 300,
                'bio' => 'Backend developer, mostly PHP and Go. Here for the networking board.',
                'signature' => null,
                'location' => null,
            ],
            [
                'username' => 'stratum', 'email' => 'stratum@coldwire.local', 'password' => 'coldwire-user-3',
                'role' => 'member', 'title' => null, 'days' => 210,
                'bio' => 'Storage, filesystems, and arguing about backups.',
                'signature' => '[i]RAID is not a backup[/i]',
                'location' => 'Utrecht',
            ],
            [
                'username' => 'quiet_fan', 'email' => 'quiet.fan@coldwire.local', 'password' => 'coldwire-user-4',
                'role' => 'member', 'title' => null, 'days' => 120,
                'bio' => 'Hardware, cooling, and the pursuit of silence.',
                'signature' => null,
                'location' => null,
            ],
            [
                'username' => 'dust_index', 'email' => 'dust.index@coldwire.local', 'password' => 'coldwire-user-5',
                'role' => 'member', 'title' => null, 'days' => 45,
                'bio' => 'New here. Mostly reading.',
                'signature' => null,
                'location' => null,
            ],
            [
                'username' => 'loudpacket', 'email' => 'loudpacket@coldwire.local', 'password' => 'coldwire-user-6',
                'role' => 'member', 'title' => null, 'days' => 30,
                'bio' => 'Account kept as a demonstration of a suspended member.',
                'signature' => null,
                'location' => null,
            ],
        ];

        foreach ($users as $user) {
            $created = Dates::now()->modify('-' . $user['days'] . ' days')->format('Y-m-d H:i:s');
            $lastActive = Dates::now()->modify('-' . random_int(0, 240) . ' minutes')->format('Y-m-d H:i:s');

            $userId = $this->db->insert('users', [
                'username' => $user['username'],
                'username_canonical' => mb_strtolower($user['username']),
                'email' => $user['email'],
                'password_hash' => password_hash($user['password'], PASSWORD_DEFAULT),
                'primary_role_id' => $this->roleIds[$user['role']],
                'title' => $user['title'],
                'bio' => $user['bio'],
                'signature' => $user['signature'],
                'location' => $user['location'],
                'timezone' => 'UTC',
                'status' => 'active',
                'email_verified_at' => $created,
                'last_active_at' => $lastActive,
                'last_login_at' => $lastActive,
                'last_ip' => '127.0.0.1',
                'created_at' => $created,
                'updated_at' => $created,
            ]);

            $this->userIds[$user['username']] = $userId;

            $this->db->insert('user_roles', [
                'user_id' => $userId,
                'role_id' => $this->roleIds[$user['role']],
                'assigned_at' => $created,
            ]);

            // Moderators also hold the member role, so permission union is exercised.
            if (in_array($user['role'], ['moderator', 'administrator'], true)) {
                $this->db->insert('user_roles', [
                    'user_id' => $userId,
                    'role_id' => $this->roleIds['member'],
                    'assigned_at' => $created,
                ]);
            }
        }

        $this->note(sprintf('Created %d demo accounts.', count($this->userIds)));
    }

    // ------------------------------------------------------------------
    // Board structure
    // ------------------------------------------------------------------

    private function seedBoard(): void
    {
        $structure = [
            [
                'name' => 'General Discussion',
                'description' => 'Start here.',
                'forums' => [
                    ['name' => 'General', 'icon' => '#', 'description' => 'Anything that belongs on the board but nowhere else.'],
                    ['name' => 'Introductions', 'icon' => '>_', 'description' => 'Say who you are and what you work on.'],
                    ['name' => 'Announcements', 'icon' => '!!', 'description' => 'Board news from the staff.', 'locked' => true],
                ],
            ],
            [
                'name' => 'Technology',
                'description' => 'The machines and the wires between them.',
                'forums' => [
                    ['name' => 'Hardware', 'icon' => '::', 'description' => 'Builds, components, benchmarks and failures.', 'children' => [
                        ['name' => 'Storage', 'icon' => '[]', 'description' => 'Disks, arrays, filesystems and the backups you keep meaning to test.'],
                    ]],
                    ['name' => 'Software', 'icon' => '&&', 'description' => 'Operating systems, tooling and the things that run on top.'],
                    ['name' => 'Networking', 'icon' => '<>', 'description' => 'Routing, DNS, VPNs, packets that go missing.'],
                ],
            ],
            [
                'name' => 'Programming',
                'description' => 'Writing it, reading it, regretting it.',
                'forums' => [
                    ['name' => 'PHP', 'icon' => '<?', 'description' => 'The language this board is written in.'],
                    ['name' => 'Web Development', 'icon' => '{}', 'description' => 'HTTP, HTML, CSS and the server side of the browser.'],
                    ['name' => 'Linux', 'icon' => '$', 'description' => 'Userland, kernel, packaging and init arguments.'],
                    ['name' => 'Other Programming', 'icon' => '..', 'description' => 'Every other language and paradigm.'],
                ],
            ],
            [
                'name' => 'Off Topic',
                'description' => 'Everything else.',
                'forums' => [
                    ['name' => 'Random', 'icon' => '~', 'description' => 'Unstructured conversation.'],
                    ['name' => 'Media', 'icon' => '()', 'description' => 'Books, film, music and long reads.'],
                ],
            ],
        ];

        $categoryPosition = 0;

        foreach ($structure as $category) {
            $categoryId = $this->db->insert('categories', [
                'name' => $category['name'],
                'slug' => Str::slug($category['name']),
                'description' => $category['description'],
                'position' => $categoryPosition,
                'is_visible' => 1,
                'created_at' => Dates::nowString(),
                'updated_at' => Dates::nowString(),
            ]);

            $categoryPosition += 10;
            $forumPosition = 0;

            foreach ($category['forums'] as $forum) {
                $forumId = $this->insertForum($forum, $categoryId, null, $forumPosition);
                $forumPosition += 10;
                $childPosition = 0;

                foreach ($forum['children'] ?? [] as $child) {
                    $this->insertForum($child, $categoryId, $forumId, $childPosition);
                    $childPosition += 10;
                }
            }
        }

        $this->note(sprintf('Created %d categories and %d forums.', count($structure), count($this->forumIds)));
    }

    /** @param array<string,mixed> $forum */
    private function insertForum(array $forum, int $categoryId, ?int $parentId, int $position): int
    {
        $slug = Str::slug((string) $forum['name']);

        $forumId = $this->db->insert('forums', [
            'category_id' => $categoryId,
            'parent_id' => $parentId,
            'name' => $forum['name'],
            'slug' => $slug,
            'description' => $forum['description'],
            'icon' => $forum['icon'],
            'position' => $position,
            'is_visible' => 1,
            'is_locked' => ($forum['locked'] ?? false) ? 1 : 0,
            'created_at' => Dates::nowString(),
            'updated_at' => Dates::nowString(),
        ]);

        $this->forumIds[$slug] = $forumId;

        return $forumId;
    }

    private function seedForumPermissions(): void
    {
        foreach ($this->forumIds as $slug => $forumId) {
            $announcements = $slug === 'announcements';

            $matrix = [
                'guest' => [1, 1, 0, 0, 0],
                'member' => [1, 1, $announcements ? 0 : 1, $announcements ? 0 : 1, 0],
                'trusted' => [1, 1, $announcements ? 0 : 1, 1, 0],
                'moderator' => [1, 1, 1, 1, 1],
                'administrator' => [1, 1, 1, 1, 1],
            ];

            foreach ($matrix as $role => $flags) {
                $this->db->insert('forum_permissions', [
                    'forum_id' => $forumId,
                    'role_id' => $this->roleIds[$role],
                    'can_view' => $flags[0],
                    'can_read' => $flags[1],
                    'can_create_topic' => $flags[2],
                    'can_reply' => $flags[3],
                    'can_moderate' => $flags[4],
                ]);
            }
        }

        $this->note('Applied the per-forum permission matrix.');
    }

    // ------------------------------------------------------------------
    // Discussion
    // ------------------------------------------------------------------

    private function seedDiscussion(): void
    {
        $topics = [
            [
                'forum' => 'announcements', 'author' => 'admin', 'days' => 240, 'pinned' => true, 'locked' => true,
                'title' => 'Board rules and how moderation works here',
                'posts' => [
                    ['admin', "Welcome to the board.\n\nThe rules are short and they are enforced:\n\n[list][*]Stay on topic.[*]No spam, no advertising.[*]Attack arguments, not people.[*]Nothing illegal.[*]One account per person.[/list]\n\nEvery moderator action is written to a log with the moderator, the target and the reason. If you think a decision was wrong, send a private message — do not start a thread about it.\n\nThe full text lives on the [url=/rules]rules page[/url]."],
                ],
            ],
            [
                'forum' => 'announcements', 'author' => 'admin', 'days' => 30, 'pinned' => true,
                'title' => 'Scheduled maintenance windows',
                'posts' => [
                    ['admin', "Maintenance happens on the first Sunday of the month, 02:00–04:00 UTC.\n\nDuring the window the board goes into maintenance mode: everyone except administrators sees the offline notice, and no writes are accepted. Nothing is lost — the forms simply refuse until it is over."],
                    ['nullroute', "Worth adding: the chat transcript is unaffected, it just stops accepting new messages for the duration."],
                ],
            ],
            [
                'forum' => 'introductions', 'author' => 'grepwire', 'days' => 200,
                'title' => 'grepwire — sysadmin, mostly Debian',
                'posts' => [
                    ['grepwire', "Fifteen years of keeping other people's software running. Currently responsible for about two hundred machines that nobody wants to think about.\n\nHere for the networking and storage boards. I like boring software."],
                    ['nullroute', "Welcome. The networking board is quiet but the people in it are worth the wait."],
                    ['pale_socket', "Two hundred machines and you still have time to post? Impressive."],
                    ['grepwire', "[quote=pale_socket;0]still have time to post[/quote]\n\nAutomation. The machines post more than I do."],
                ],
            ],
            [
                'forum' => 'introductions', 'author' => 'dust_index', 'days' => 40,
                'title' => 'Lurker finally registering',
                'posts' => [
                    ['dust_index', "Been reading for months. Work in embedded, mostly ARM, mostly firmware that ships once and is never touched again.\n\nNo questions yet. Just saying hello."],
                    ['hexdump', "Firmware that ships once and is never touched again is the most honest description of the industry I have read this year. Welcome."],
                ],
            ],
            [
                'forum' => 'networking', 'author' => 'nullroute', 'days' => 150,
                'title' => 'Debugging asymmetric routing without losing your mind',
                'posts' => [
                    ['nullroute', "A pattern I keep running into: traffic leaves via one path and comes back via another, the stateful firewall in the middle drops the return packets, and the application reports a timeout with no other symptom.\n\nThe method that works for me:\n\n[list][*]Capture on both directions at the same time. One capture proves nothing.[*]Check the routing table on the return path, not the outbound one — that is where the surprise usually is.[*]Only then look at the firewall.[/list]\n\nWhat do you check first?"],
                    ['grepwire', "Conntrack table before anything else. If the entry is missing on the return path the argument is over."],
                    ['pale_socket', "I lost two days to this exact thing last year. The part nobody writes down is that the asymmetry was introduced by a route the provider added on their side without telling us."],
                    ['nullroute', "[quote=pale_socket;0]a route the provider added on their side[/quote]\n\nThat is the usual ending. Which is why the first capture should always be on their handoff."],
                    ['stratum', "Adding one: if you have ECMP anywhere in the path, capture long enough to see more than one flow. A single flow will happily take a single path and tell you everything is fine."],
                ],
            ],
            [
                'forum' => 'networking', 'author' => 'pale_socket', 'days' => 90,
                'title' => 'DNS TTLs: what do you actually set in production?',
                'posts' => [
                    ['pale_socket', "The advice ranges from 60 seconds to a day depending on who wrote the article. What are people running in practice, and what broke when you changed it?"],
                    ['nullroute', "300 for anything that might need to move, 3600 for the rest, 86400 for records that have not changed in years.\n\nThe failure mode nobody mentions: resolvers that ignore your TTL entirely. Plan for a tail of traffic hitting the old address for hours after a cut-over."],
                    ['grepwire', "Same numbers here. Also: drop the TTL a day *before* the migration, not on the day. A low TTL that has not propagated yet is worth nothing."],
                    ['dust_index', "This is the kind of thing that never appears in documentation. Noted."],
                ],
            ],
            [
                'forum' => 'hardware', 'author' => 'quiet_fan', 'days' => 70,
                'title' => 'Undervolting for silence, not for temperature',
                'posts' => [
                    ['quiet_fan', "Most undervolting threads chase lower temperatures. I am chasing a fan curve that never leaves the bottom step.\n\nCurrent setup holds a sustained workload at 62°C with the case fans at 600 rpm, which is inaudible at a metre. The part that mattered was not the voltage offset but the power limit — the fans react to the transients, not the average."],
                    ['stratum', "The transients point is the whole thing. A 10-second power limit averaging window turns a spiky workload into a flat one and the fans never spin up."],
                    ['hexdump', "How are you measuring inaudible? At some point the difference is the room, not the machine."],
                    ['quiet_fan', "Phone app at a metre, which is not a real measurement, and my own ears at 2am, which is. The room floor is about 26 dBA."],
                ],
            ],
            [
                'forum' => 'storage', 'author' => 'stratum', 'days' => 55,
                'title' => 'Your backups are not backups until you have restored one',
                'posts' => [
                    ['stratum', "Yearly reminder.\n\nA backup job that reports success is a job that wrote bytes somewhere. It is not evidence that you can get your data back. The only evidence is a restore.\n\nMy schedule:\n\n[list][*]Monthly: restore one random file, verify checksum.[*]Quarterly: restore one full dataset to scratch space.[*]Yearly: restore to different hardware, from cold storage, with the runbook only.[/list]\n\nThe yearly one has failed twice. Both times the backups were fine and the runbook was wrong."],
                    ['grepwire', "[quote=stratum;0]the backups were fine and the runbook was wrong[/quote]\n\nThis is the real finding and it is why the exercise is worth the day it costs."],
                    ['admin', "Pinning this in spirit if not in software. Everyone running anything should read it once a year."],
                    ['pale_socket', "Third category worth testing: can you restore when the person who set it up is unavailable? That is what the runbook-only rule is really measuring."],
                ],
            ],
            [
                'forum' => 'php', 'author' => 'pale_socket', 'days' => 60,
                'title' => 'Writing a forum without a framework — worth it?',
                'posts' => [
                    ['pale_socket', "Genuine question rather than a provocation. Everything I build professionally sits on a framework. When I write something small for myself I keep reaching for plain PHP and I am not sure whether that is craft or stubbornness.\n\nWhere is the line for you?"],
                    ['grepwire', "The line for me is dependencies I would have to audit. A router, a template loader and a PDO wrapper are a few hundred lines I can read in an afternoon. Anything involving authentication or serialisation, I take the well-reviewed one."],
                    ['admin', "This board is the answer I settled on: plain PHP, a small router, plain templates, PDO everywhere. Roughly four thousand lines of application code and no supply chain to worry about.\n\nIt is not the right choice for a team of twenty. It is the right choice for something one person maintains for a decade."],
                    ['stratum', "The part that usually bites is not writing it, it is the second year, when you need something the framework would have given you and you now own the problem."],
                    ['pale_socket', "[quote=admin;0]the right choice for something one person maintains for a decade[/quote]\n\nThat is a clearer criterion than anything I had. Thanks."],
                ],
            ],
            [
                'forum' => 'php', 'author' => 'grepwire', 'days' => 25,
                'title' => 'Prepared statements: the one rule that never bends',
                'posts' => [
                    ['grepwire', "Every SQL injection I have ever traced came down to somebody deciding this one query was safe enough to interpolate.\n\n[code]\$sql = \"SELECT * FROM users WHERE id = \" . \$id;   // no\n\$sql = 'SELECT * FROM users WHERE id = :id';      // yes\n[/code]\n\nEven when the value is an integer you cast yourself. Even in an admin tool. Even in a migration script. The rule only works if it has no exceptions."],
                    ['pale_socket', "The exception people reach for is ORDER BY, which cannot be a bound parameter. The answer is an allow-list of column names, not string building."],
                    ['admin', "Correct. In this codebase every dynamic fragment comes from a fixed internal list and the values themselves are always bound. Grep for a concatenated query and you will not find one."],
                ],
            ],
            [
                'forum' => 'web-development', 'author' => 'admin', 'days' => 20,
                'title' => 'Building for no JavaScript: what you actually give up',
                'posts' => [
                    ['admin', "Having just built a whole board this way, the honest accounting.\n\n[b]What you give up:[/b] live updates, inline validation, anything resembling a modal dialog, drag-to-reorder, autocomplete.\n\n[b]What you get:[/b] every interaction is a URL, which means every interaction is bookmarkable, linkable, testable with curl and usable in any browser. Nothing breaks when a request fails halfway.\n\n[b]The substitutions that worked:[/b] [code]<details>[/code] for the mobile menu, real confirmation pages instead of dialogs, server-side quoting instead of a rich editor, and page reloads instead of polling."],
                    ['quiet_fan', "The confirmation page point is underrated. A dialog that appears over the thing you are deleting shows you less than a page that renders what you are about to lose."],
                    ['dust_index', "Does search work well enough without suggestions?"],
                    ['admin', "It works differently. Instead of guessing as you type, the search page gives you author, forum and date filters on one screen. Fewer round trips, more precision."],
                    ['hexdump', "The tell for me is the back button. On a board like this it always does the obvious thing."],
                ],
            ],
            [
                'forum' => 'linux', 'author' => 'hexdump', 'days' => 35,
                'title' => 'systemd units I wish I had written five years earlier',
                'posts' => [
                    ['hexdump', "Three that removed entire categories of problem:\n\n[list][*][code]OnFailure=[/code] pointing at a unit that pages me. No more silent failures.[*][code]RuntimeMaxSec=[/code] on anything that could hang. A restart beats a stuck process nobody notices.[*][code]StateDirectory=[/code] instead of hand-made directories with hand-set permissions.[/list]\n\nWhat is in your standard template?"],
                    ['grepwire', "[code]ProtectSystem=strict[/code] and [code]PrivateTmp=yes[/code] on everything, then loosen only where the service complains. Starting locked and opening up finds things that starting open never does."],
                    ['stratum', "Timers over cron for anything with state. Persistent=true has saved several jobs after reboots."],
                ],
            ],
            [
                'forum' => 'software', 'author' => 'stratum', 'days' => 15,
                'title' => 'Software you have run unchanged for over a decade',
                'posts' => [
                    ['stratum', "Not nostalgia — genuinely still in production, still doing the job, no rewrite planned.\n\nMine: one Postfix instance configured in 2011, one rsync script from roughly the same era, and a Makefile that has outlived three CI systems."],
                    ['grepwire', "An OpenLDAP directory from 2009. I no longer know how it works. It has never failed."],
                    ['nullroute', "A pair of BGP configs written by somebody who left the company in 2013. We have touched the prefixes and nothing else."],
                    ['quiet_fan', "There is a pattern in this thread: everything that survives is text files and one process."],
                ],
            ],
            [
                'forum' => 'general', 'author' => 'nullroute', 'days' => 10,
                'title' => 'What are you working on this week?',
                'posts' => [
                    ['nullroute', "The recurring thread. Short updates, no pressure."],
                    ['stratum', "Migrating four arrays onto new shelves without downtime. Currently at the stage where it is going suspiciously well."],
                    ['pale_socket', "Rewriting a report generator that has grown three ways to do the same thing. Deleting more than I am adding, which is the best kind of week."],
                    ['dust_index', "Reading the board and trying to fix a bootloader that will not respect its own configuration."],
                    ['quiet_fan', "Repasting an old workstation. The fans are already quieter and I have not even closed the case."],
                    ['hexdump', "Chasing a checksum mismatch in a firmware image. It is byte 4096, it is always byte 4096."],
                ],
            ],
            [
                'forum' => 'random', 'author' => 'quiet_fan', 'days' => 8,
                'title' => 'Desk setups: post the boring ones',
                'posts' => [
                    ['quiet_fan', "Not the ones with lighting. The ones you actually work at, with the mug and the cable you keep meaning to route properly."],
                    ['grepwire', "Two monitors, one of them permanently showing logs, and a keyboard from 2016 that I will replace when it dies and not before."],
                    ['dust_index', "Laptop on a stack of books. It works and I have stopped apologising for it."],
                ],
            ],
            [
                'forum' => 'media', 'author' => 'grepwire', 'days' => 5,
                'title' => 'Long-form technical writing worth the evening',
                'posts' => [
                    ['grepwire', "Looking for the kind of article that takes an hour and changes how you think about something, rather than the kind that takes four minutes and changes nothing.\n\nPost what you have."],
                    ['admin', "Anything that walks through a real outage from first symptom to root cause. The genre is small and it is the best teaching material there is."],
                    ['stratum', "Seconded, with a preference for the ones that admit what the team believed at each step and why it was wrong."],
                ],
            ],
            [
                'forum' => 'other-programming', 'author' => 'dust_index', 'days' => 3,
                'title' => 'Learning a second language properly, not superficially',
                'posts' => [
                    ['dust_index', "I can read four languages and think in one. Has anyone deliberately worked at getting to the point where a second language feels native, and what did that take?"],
                    ['pale_socket', "Writing something non-trivial in it, alone, with no deadline. Tutorials teach syntax; a project you care about teaches the idioms."],
                    ['hexdump', "Reading the standard library source. Not the docs — the source. That is where the idioms actually live."],
                ],
            ],
        ];

        $topicService = new TopicService();
        $topicRepository = new TopicRepository();
        $created = 0;

        foreach ($topics as $definition) {
            $forumId = $this->forumIds[$definition['forum']] ?? null;

            if ($forumId === null) {
                continue;
            }

            $startedAt = Dates::now()->modify('-' . $definition['days'] . ' days');
            $slug = $topicRepository->uniqueSlug(Str::slug(Str::limit((string) $definition['title'], 80, '')));

            $topicId = $this->db->insert('topics', [
                'forum_id' => $forumId,
                'user_id' => $this->userIds[$definition['author']],
                'title' => $definition['title'],
                'slug' => $slug,
                'is_pinned' => ($definition['pinned'] ?? false) ? 1 : 0,
                'is_locked' => ($definition['locked'] ?? false) ? 1 : 0,
                'view_count' => random_int(40, 2400),
                'post_count' => 0,
                'created_at' => $startedAt->format('Y-m-d H:i:s'),
                'updated_at' => $startedAt->format('Y-m-d H:i:s'),
            ]);

            $offsetMinutes = 0;
            $first = true;

            foreach ($definition['posts'] as $post) {
                [$author, $content] = $post;
                $postedAt = $startedAt->modify('+' . $offsetMinutes . ' minutes');

                $this->db->insert('posts', [
                    'topic_id' => $topicId,
                    'forum_id' => $forumId,
                    'user_id' => $this->userIds[$author],
                    'content' => $content,
                    'is_first_post' => $first ? 1 : 0,
                    'ip_address' => '127.0.0.1',
                    'created_at' => $postedAt->format('Y-m-d H:i:s'),
                    'updated_at' => $postedAt->format('Y-m-d H:i:s'),
                ]);

                $offsetMinutes += random_int(35, 900);
                $first = false;
            }

            // A couple of members follow each topic so notifications have a path.
            foreach (array_slice(array_keys($this->userIds), 0, 3) as $follower) {
                $this->db->execute(
                    'INSERT IGNORE INTO topic_subscriptions (user_id, topic_id, created_at) VALUES (:user, :topic, :at)',
                    ['user' => $this->userIds[$follower], 'topic' => $topicId, 'at' => Dates::nowString()],
                );
            }

            $created++;
        }

        $this->seedLongTopic();
        $this->seedEditedPost();

        $this->note(sprintf('Created %d demo topics with their posts.', $created + 1));
    }

    /**
     * One deliberately long topic so pagination has something real to page
     * through on a fresh installation.
     */
    private function seedLongTopic(): void
    {
        $forumId = $this->forumIds['general'];
        $authors = array_values(array_diff(array_keys($this->userIds), ['loudpacket']));
        $startedAt = Dates::now()->modify('-120 days');

        $topicId = $this->db->insert('topics', [
            'forum_id' => $forumId,
            'user_id' => $this->userIds['admin'],
            'title' => 'The long thread: one question, answered badly, for months',
            'slug' => 'the-long-thread',
            'is_pinned' => 0,
            'is_locked' => 0,
            'view_count' => 8421,
            'post_count' => 0,
            'created_at' => $startedAt->format('Y-m-d H:i:s'),
            'updated_at' => $startedAt->format('Y-m-d H:i:s'),
        ]);

        $lines = [
            'Still no closer to an answer, but the question is sharper than it was.',
            'Tried the obvious thing. The obvious thing was wrong, as usual.',
            'Reproduced it on a second machine, which rules out the first machine.',
            'Reading the source now. The comment says it cannot happen.',
            'It happens.',
            'New theory: the timeout is not the problem, it is the symptom.',
            'Captured 40 minutes of traffic. Nothing at the moment of failure.',
            'Someone suggested checking the clocks. The clocks were fine. Checking them was still worth it.',
            'Rolled back one version. Same behaviour, so it is not the upgrade.',
            'Rolled back two versions. Different behaviour, which is worse.',
            'Narrowed it to a single code path. That path has no tests, naturally.',
            'Wrote the test. The test passes. The system still fails.',
            'The test was wrong.',
            'Fixed the test. It now fails, which is progress.',
            'Root cause found: an assumption about ordering that has been false since 2019.',
            'Fix is three lines. Writing it up took the rest of the day.',
            'Post-mortem in the technology board when I have slept.',
        ];

        $offset = 0;
        $first = true;

        for ($index = 0; $index < 58; $index++) {
            $author = $authors[$index % count($authors)];
            $postedAt = $startedAt->modify('+' . $offset . ' minutes');

            $content = $first
                ? "This is the thread where a single question went unanswered for four months.\n\nIt is kept because the process is more useful than the answer, and because a board with no long threads is a board nobody uses.\n\nStart of the log below."
                : sprintf("[b]Day %d.[/b] %s", $index * 2, $lines[$index % count($lines)]);

            $this->db->insert('posts', [
                'topic_id' => $topicId,
                'forum_id' => $forumId,
                'user_id' => $this->userIds[$author],
                'content' => $content,
                'is_first_post' => $first ? 1 : 0,
                'ip_address' => '127.0.0.1',
                'created_at' => $postedAt->format('Y-m-d H:i:s'),
                'updated_at' => $postedAt->format('Y-m-d H:i:s'),
            ]);

            $offset += random_int(600, 2400);
            $first = false;
        }
    }

    /** Gives one post a revision history so that screen is not empty. */
    private function seedEditedPost(): void
    {
        $post = $this->db->selectOne(
            'SELECT id, content, user_id FROM posts WHERE is_first_post = 0 ORDER BY id ASC LIMIT 1',
        );

        if ($post === null) {
            return;
        }

        $before = "Conntrack table before anything else.";
        $after = (string) $post['content'];
        $editedAt = Dates::now()->modify('-100 days')->format('Y-m-d H:i:s');

        $this->db->insert('post_edits', [
            'post_id' => (int) $post['id'],
            'editor_id' => (int) $post['user_id'],
            'content_before' => $before,
            'content_after' => $after,
            'reason' => 'Added the reasoning, not just the conclusion.',
            'ip_address' => '127.0.0.1',
            'created_at' => $editedAt,
        ]);

        $this->db->update('posts', [
            'edit_count' => 1,
            'edited_at' => $editedAt,
            'edited_by' => (int) $post['user_id'],
        ], 'id = :id', ['id' => (int) $post['id']]);
    }

    // ------------------------------------------------------------------
    // Chat
    // ------------------------------------------------------------------

    private function seedChat(): void
    {
        $rooms = [
            ['slug' => 'lobby', 'name' => 'Lobby', 'description' => 'The general room. Everything else has a forum.', 'topic_line' => 'no support questions — use the forums', 'position' => 0],
            ['slug' => 'operations', 'name' => 'Operations', 'description' => 'For the people holding the pager.', 'topic_line' => 'incidents, maintenance, and the aftermath', 'position' => 10],
        ];

        $roomIds = [];

        foreach ($rooms as $room) {
            $roomIds[$room['slug']] = $this->db->insert('chat_rooms', [
                'slug' => $room['slug'],
                'name' => $room['name'],
                'description' => $room['description'],
                'topic_line' => $room['topic_line'],
                'is_active' => 1,
                'is_readonly' => 0,
                'slow_mode' => 0,
                'position' => $room['position'],
                'created_at' => Dates::nowString(),
                'updated_at' => Dates::nowString(),
            ]);
        }

        $transcript = [
            ['nullroute', 'morning. anyone else seeing packet loss on the transit link?'],
            ['grepwire', 'nothing here, but I am not on that path'],
            ['nullroute', 'about 2% and only on the return. classic'],
            ['stratum', 'array migration is done, no downtime, I am surprised too'],
            ['quiet_fan', 'congratulations, now do it again with an audience'],
            ['pale_socket', 'is the search page meant to be this fast without any javascript?'],
            ['admin', 'that is the entire point, yes'],
            ['pale_socket', 'fair'],
            ['dust_index', 'lurking, as usual'],
            ['hexdump', 'byte 4096 again'],
            ['grepwire', 'it is always byte 4096'],
            ['nullroute', 'loss cleared, provider pushed a config. no notification of course'],
        ];

        $offset = count($transcript) * 7;

        foreach ($transcript as [$author, $content]) {
            $this->db->insert('chat_messages', [
                'room_id' => $roomIds['lobby'],
                'user_id' => $this->userIds[$author],
                'type' => 'message',
                'content' => $content,
                'ip_address' => '127.0.0.1',
                'created_at' => Dates::now()->modify('-' . $offset . ' minutes')->format('Y-m-d H:i:s'),
            ]);

            $offset -= 7;
        }

        $this->db->insert('chat_messages', [
            'room_id' => $roomIds['operations'],
            'user_id' => null,
            'type' => 'system',
            'content' => 'Room created. Incident chatter belongs here; the transcript is retained.',
            'created_at' => Dates::now()->modify('-2 days')->format('Y-m-d H:i:s'),
        ]);

        foreach (['nullroute', 'grepwire', 'admin'] as $present) {
            $this->db->execute(
                'INSERT INTO chat_presence (room_id, user_id, last_seen_at) VALUES (:room, :user, :seen)
                 ON DUPLICATE KEY UPDATE last_seen_at = :seen2',
                [
                    'room' => $roomIds['lobby'],
                    'user' => $this->userIds[$present],
                    'seen' => Dates::now()->modify('-' . random_int(1, 4) . ' minutes')->format('Y-m-d H:i:s'),
                    'seen2' => Dates::nowString(),
                ],
            );
        }

        $this->note('Seeded two chat rooms with a transcript.');
    }

    // ------------------------------------------------------------------
    // Messages, notifications, reports, moderation history
    // ------------------------------------------------------------------

    private function seedMessagingAndModeration(): void
    {
        // Private messages
        $messageId = $this->db->insert('private_messages', [
            'sender_id' => $this->userIds['nullroute'],
            'recipient_id' => $this->userIds['admin'],
            'subject' => 'Report queue is backing up',
            'body' => "Three reports open on the same account. I can handle two of them but the third needs an administrator — it is a ban evasion case and I only have suspend rights.\n\nDetails are in the moderation record.",
            'is_read' => 0,
            'created_at' => Dates::now()->modify('-2 days')->format('Y-m-d H:i:s'),
        ]);

        $this->db->insert('private_messages', [
            'sender_id' => $this->userIds['admin'],
            'recipient_id' => $this->userIds['nullroute'],
            'parent_id' => $messageId,
            'subject' => 'Re: Report queue is backing up',
            'body' => "Looked at it. Handling the third one myself this evening.\n\nIf this pattern repeats we should give the moderator role the ban permission and stop routing these through me.",
            'is_read' => 1,
            'read_at' => Dates::now()->modify('-1 day')->format('Y-m-d H:i:s'),
            'created_at' => Dates::now()->modify('-1 day')->format('Y-m-d H:i:s'),
        ]);

        $this->db->insert('private_messages', [
            'sender_id' => $this->userIds['grepwire'],
            'recipient_id' => $this->userIds['pale_socket'],
            'subject' => 'That routing thread',
            'body' => "Sent you the capture I mentioned. Filter on the return path and you will see the same pattern we were talking about.",
            'is_read' => 0,
            'created_at' => Dates::now()->modify('-6 hours')->format('Y-m-d H:i:s'),
        ]);

        // Notifications
        $notifications = [
            [$this->userIds['admin'], $this->userIds['nullroute'], 'message.received', 'New message from nullroute', 'Report queue is backing up', '/messages/' . $messageId],
            [$this->userIds['pale_socket'], $this->userIds['grepwire'], 'topic.reply', 'grepwire replied in “DNS TTLs: what do you actually set in production?”', '300 for anything that might need to move…', '/topic/dns-ttls-what-do-you-actually-set-in-production'],
            [$this->userIds['grepwire'], $this->userIds['stratum'], 'post.quote', 'stratum quoted you in “Your backups are not backups until you have restored one”', 'Your post was quoted in a reply.', '/topic/your-backups-are-not-backups-until-you-have-restored-one'],
        ];

        foreach ($notifications as [$userId, $actorId, $type, $title, $body, $url]) {
            $this->db->insert('notifications', [
                'user_id' => $userId,
                'actor_id' => $actorId,
                'type' => $type,
                'title' => $title,
                'body' => $body,
                'url' => $url,
                'is_read' => 0,
                'created_at' => Dates::now()->modify('-' . random_int(2, 40) . ' hours')->format('Y-m-d H:i:s'),
            ]);
        }

        // A pending report pointing at a real post, plus one already handled.
        $post = $this->db->selectOne('SELECT id, user_id FROM posts WHERE is_first_post = 0 ORDER BY id DESC LIMIT 1');

        if ($post !== null) {
            $this->db->insert('reports', [
                'reporter_id' => $this->userIds['dust_index'],
                'reported_user_id' => (int) $post['user_id'],
                'content_type' => 'post',
                'content_id' => (int) $post['id'],
                'reason' => 'offtopic',
                'details' => 'This reply is about a different subject entirely. Might belong in its own thread rather than here.',
                'status' => 'pending',
                'ip_address' => '127.0.0.1',
                'created_at' => Dates::now()->modify('-5 hours')->format('Y-m-d H:i:s'),
            ]);
        }

        $this->db->insert('reports', [
            'reporter_id' => $this->userIds['quiet_fan'],
            'reported_user_id' => $this->userIds['loudpacket'],
            'content_type' => 'user',
            'content_id' => $this->userIds['loudpacket'],
            'reason' => 'spam',
            'details' => 'Posted the same link in four threads within a few minutes.',
            'status' => 'resolved',
            'handled_by' => $this->userIds['nullroute'],
            'handled_at' => Dates::now()->modify('-3 days')->format('Y-m-d H:i:s'),
            'action_taken' => 'post deleted',
            'moderator_notes' => 'Removed the posts and suspended the account for seven days. First offence.',
            'ip_address' => '127.0.0.1',
            'created_at' => Dates::now()->modify('-3 days')->format('Y-m-d H:i:s'),
        ]);

        // A suspended account, so the restriction screens have real data.
        $suspendedUntil = Dates::now()->modify('+4 days')->format('Y-m-d H:i:s');

        $banId = $this->db->insert('bans', [
            'user_id' => $this->userIds['loudpacket'],
            'created_by' => $this->userIds['nullroute'],
            'type' => 'suspension',
            'reason' => 'Repeated advertising across several boards.',
            'internal_note' => 'Watch for a second account from the same address.',
            'expires_at' => $suspendedUntil,
            'is_active' => 1,
            'created_at' => Dates::now()->modify('-3 days')->format('Y-m-d H:i:s'),
        ]);

        $this->db->update('users', ['status' => 'suspended'], 'id = :id', ['id' => $this->userIds['loudpacket']]);

        $this->db->insert('warnings', [
            'user_id' => $this->userIds['loudpacket'],
            'moderator_id' => $this->userIds['nullroute'],
            'reason' => 'Advertising in the introductions board.',
            'details' => 'First warning; explained the rule and removed the post.',
            'points' => 2,
            'expires_at' => Dates::now()->modify('+87 days')->format('Y-m-d H:i:s'),
            'created_at' => Dates::now()->modify('-4 days')->format('Y-m-d H:i:s'),
        ]);

        $this->db->update('users', ['warning_points' => 2], 'id = :id', ['id' => $this->userIds['loudpacket']]);

        $this->db->insert('user_notes', [
            'user_id' => $this->userIds['loudpacket'],
            'author_id' => $this->userIds['nullroute'],
            'note' => "Registered from the same subnet as an account banned last year. Not conclusive on its own — noting it for whoever handles the next report.",
            'created_at' => Dates::now()->modify('-3 days')->format('Y-m-d H:i:s'),
        ]);

        // Moderation log entries
        $entries = [
            ['nullroute', 'user.warn', 'user', $this->userIds['loudpacket'], 'Warned loudpacket (2 point(s))', 'Advertising in the introductions board.', 4],
            ['nullroute', 'post.delete', 'post', null, 'Deleted post #0 by loudpacket', 'Spam', 3],
            ['nullroute', 'user.suspend', 'user', $this->userIds['loudpacket'], 'Suspended loudpacket for 7 day(s)', 'Repeated advertising across several boards.', 3],
            ['nullroute', 'report.resolve', 'report', null, 'Resolved report #2 (post deleted)', null, 3],
            ['admin', 'topic.pin', 'topic', null, 'Pinned topic “Board rules and how moderation works here”', null, 240],
            ['admin', 'topic.lock', 'topic', null, 'Locked topic “Board rules and how moderation works here”', 'Reference thread', 240],
            ['hexdump', 'topic.move', 'topic', null, 'Moved “Undervolting for silence, not for temperature” from General to Hardware', 'Wrong board', 69],
        ];

        foreach ($entries as [$moderator, $action, $targetType, $targetUser, $summary, $reason, $daysAgo]) {
            $this->db->insert('moderation_actions', [
                'moderator_id' => $this->userIds[$moderator],
                'action' => $action,
                'target_type' => $targetType,
                'target_id' => null,
                'target_user_id' => $targetUser,
                'summary' => $summary,
                'reason' => $reason,
                'metadata' => json_encode(['seeded' => true], JSON_UNESCAPED_SLASHES),
                'ip_address' => '127.0.0.1',
                'created_at' => Dates::now()->modify('-' . $daysAgo . ' days')->format('Y-m-d H:i:s'),
            ]);
        }

        $this->note('Seeded messages, notifications, reports and moderation history.');
    }

    private function refreshCounters(): void
    {
        $topics = new TopicRepository();
        $forums = new ForumRepository();

        foreach ($this->db->select('SELECT id FROM topics') as $row) {
            $topics->refreshCounters((int) $row['id']);
        }

        foreach ($this->db->select('SELECT id FROM forums ORDER BY parent_id IS NULL, id') as $row) {
            $forums->refreshCounters((int) $row['id']);
        }

        // User post and topic counts derive from the same rows.
        $this->db->execute(
            'UPDATE users u SET
                post_count = (SELECT COUNT(*) FROM posts p WHERE p.user_id = u.id AND p.deleted_at IS NULL),
                topic_count = (SELECT COUNT(*) FROM topics t WHERE t.user_id = u.id AND t.deleted_at IS NULL)',
        );

        $this->db->execute('UPDATE users SET reputation = FLOOR(post_count * 1.5)');

        $this->note('Recalculated every counter.');
    }
}
