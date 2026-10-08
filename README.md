# iRIMS-V Ticketing System

The help desk for LRMIS users: tickets for bugs, problems and feature requests, a news feed for announcements and maintenance notices, and a community forum. It is a Laravel 13 application with an Inertia 3 and Vue 3 front end, styled with Tailwind 4.

The app lives **inside the LRMIS database**. It authenticates against the LRMIS `users` table and never changes LRMIS data beyond an account's password or an administrator's own details. Anyone whose LRMIS user type is *Administrator* (level 0) administers the help desk; everyone else is a member.

## Requirements

- PHP 8.4 with the `pdo_pgsql` extension, Composer
- Node 20+ and npm
- PostgreSQL with the LRMIS database (the `lrmis` schema)

## Setting up

```sh
composer install
cp .env.example .env
php artisan key:generate
npm install
```

Then edit `.env`:

| Setting | Why |
| --- | --- |
| `DB_*` | The LRMIS PostgreSQL database. `DB_SEARCH_PATH=lrmis` is required: the help desk tables are created beside the LRMIS tables, and the `users` table is read from there. |
| `LRMIS_URL` | Where LRMIS serves profile photos from. Leave empty to use this app's own host. |
| `HELPDESK_ADMIN_*` | The administrator account `php artisan db:seed` creates when LRMIS has no account with that username yet. |
| `HELPDESK_SSO_SECRET` | Shared with iRIMS-V so its *Support Center* link signs people in. Leave empty to turn single sign-on off. |
| `REVERB_*` and `VITE_REVERB_*` | The WebSocket server for live updates. Without them the pages fall back to polling. |
| `HELPDESK_EMAIL_NOTIFICATIONS` and `MAIL_*` | Off by default: people are only told in the app. Once you have a mail account, point `MAIL_*` at it, set `HELPDESK_EMAIL_NOTIFICATIONS=true` and run a queue worker, since emails go through the queue. |

Create the tables and the seeded administrator, then build the front end:

```sh
php artisan migrate
php artisan db:seed
npm run build
```

Migrations never drop the LRMIS tables; the `users` and `usertypes` tables are only created when they are absent, as on the test database.

## Running it

For development, this starts the PHP server, the queue worker, the log tail and Vite together:

```sh
composer run dev
```

In production, run `php artisan optimize`, build the assets with `npm run build`, and keep these processes alive beside the web server:

| Process | What it does |
| --- | --- |
| `php artisan queue:work` | Sends the ticket emails. Only needed once email notifications are turned on. |
| `php artisan reverb:start` | The WebSocket server for live updates. The web server must proxy `/app` to it. |
| `php artisan schedule:run` every minute (cron) | Closes tickets nobody reopened, prunes old notifications, and removes tickets deleted more than 30 days ago. |

## Administration

- `php artisan helpdesk:grant-admin {username}` moves an LRMIS account onto the Administrator user type so that it administers the help desk. `--revoke="Teacher"` moves it back. This changes the account's LRMIS user type too.
- `php artisan tickets:close-resolved` closes the tickets that have stayed resolved for `HELPDESK_AUTO_CLOSE_DAYS` days (7 by default).
- `php artisan notifications:prune` removes notifications read more than `HELPDESK_PRUNE_NOTIFICATIONS_AFTER_DAYS` days ago (90 by default).

Other knobs in `config/helpdesk.php`: how long a requester may reopen a resolved ticket (`HELPDESK_REOPEN_WINDOW_DAYS`), how long a maintenance notice stays on the dashboard, the Content Security Policy mode (`HELPDESK_CSP=enforce|report|off`), and the longest single sign-on token accepted.

## How tickets work

- A ticket is private to the person who raised it and the administrators unless the requester shares it with everyone. Shared tickets can be followed, and other people can say they have the same problem (or, for a feature request, that they want it too).
- Bugs and problems move through *Open*, *In progress*, *Resolved* and *Closed*. Feature requests move through *Open*, *Under review*, *Planned*, *In progress*, *Shipped* and *Closed*, and can be linked to the news post about the release that delivered them.
- The requester chooses a priority when raising a ticket; afterwards only the help desk changes it.
- Every ticket shows whose turn it is: it waits on the help desk until an administrator replies, then on the requester. Resolved tickets wait for the requester to confirm the fix or reopen the ticket; after a week they close on their own.
- Administrators can leave internal notes the requester never sees, insert saved replies, change several tickets at once from the list, export the list as CSV, and restore a deleted ticket for 30 days.
- The requester, the followers and the help desk are told in the app, and by email if they want, when a ticket is raised, replied to, or moved to another status.

## Tests

```sh
php artisan test --compact
```

The suite runs on an in-memory SQLite database and needs no LRMIS data.
