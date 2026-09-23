# First steps

What to do once the board is installed.

## Demo accounts

Created only by `php bin/console install` or `db:seed`. The web installer
creates **no** demo content — only the administrator you named.

| Username | Password | Role |
|---|---|---|
| `admin` | `coldwire-admin-1` | Administrator |
| `nullroute` | `coldwire-mod-1` | Moderator |
| `hexdump` | `coldwire-mod-2` | Moderator |
| `grepwire` | `coldwire-user-1` | Trusted member |
| `pale_socket` | `coldwire-user-2` | Member |
| `stratum` | `coldwire-user-3` | Member |
| `quiet_fan` | `coldwire-user-4` | Member |
| `dust_index` | `coldwire-user-5` | Member |
| `loudpacket` | `coldwire-user-6` | Member, suspended |

!!! danger "These are development credentials"
    Change or delete them before the board is reachable by anyone else.
    `admin` especially.

## The first ten minutes

1. **Sign in** as your administrator.
2. **Name the board** — *Admin → Site settings → General*: name, tagline, and
   the meta description search engines will show.
3. **Write the rules** — same screen. They appear on `/rules` and during
   registration, and members must accept them to sign up.
4. **Build the board** — *Admin → Categories & forums*. Create categories
   first; a forum always lives inside one. See [Forums](../admin/forums.md).
5. **Check who can do what** — *Admin → Permissions* shows every permission
   against every role on one screen.
6. **Decide about registration** — *Admin → Site settings → Registration*. It
   is open by default.
7. **Delete `public/install.php`** if it is still there.

## Before going live

| | |
|---|---|
| `APP_DEBUG=false` and `APP_ENV=production` in `.env` | Stack traces stay out of the browser |
| `SESSION_SECURE=true` behind HTTPS | The session cookie stops travelling in the clear |
| Demo accounts changed or deleted | |
| `public/install.php` gone | |
| Database user limited to its own database | Not `CREATE`/`DROP` on everything |
| Document root at `public/` | Nothing above it should be reachable |
| `MAIL_TRANSPORT=mail` | Otherwise password resets only land in a log file |

## Where things are

| | |
|---|---|
| The board | `/` |
| Moderation | `/moderation` |
| Administration | `/admin` |
| Your account | `/settings/profile` |
| Your moderation record | `/settings/record` |
| Logs | `/admin/logs`, or `storage/logs/` |
