# gouwwaas.be

Laravel 13 website (PHP 8.4+) for Gouw Waas.

## Local development

Two ways to run the site locally, each with its own env file:

| | Env file | Database | URL |
| --- | --- | --- | --- |
| **Local** (default) | `.env.local` (committed) | MariaDB in Docker, with an anonymised copy of the production data | http://localhost:8000 |
| **Production data** | `.env.prod` (never committed) | the real production database | http://localhost:8001 |

There is no `.env` locally: without one, Laravel uses `.env.local`
(`bootstrap/app.php`), and `APP_ENV=prod` switches to `.env.prod`. Servers keep using
their own `.env`.

### First time

1. Install PHP 8.4+, Composer and Docker Desktop
2. `composer install`
3. `cp .env.prod.example .env.prod` and fill in the production database credentials
   (needed to copy production data; skip it and use `composer setup` for an empty site)

### Run

PhpStorm has two run configurations for this (`.run/`), or use Composer:

- `composer dev:local` – starts the Docker database (MariaDB 10.6, the production
  version, on port 3307), replaces the local database with an anonymised copy of the
  production data, and serves on :8000. Log in as **admin@gouwwaas.be** / **password**.
- `composer dev:prod` – serves on :8001 against the **production database**. Everything
  you change there is live. Sessions and cache stay on your machine and e-mail only goes
  to the log, but data you save is real.

Both can run at the same time (separate ports and session cookies).

**E-mail locally** goes to [Mailpit](https://mailpit.axllent.org) (started by
`docker compose up -d`): read everything the site sends at http://localhost:8025;
nothing reaches real addresses.

**Secrets for `.env.local`** (such as the Mollie test key, `MOLLIE_API_KEY=test_…`) go in
`.env.local.secrets` next to it. That file is git-ignored and its values override
`.env.local` (`bootstrap/app.php`), so `.env.local` itself can stay committed. Copy
`.env.local.secrets.example` to start. With a `test_…` key, payments are test payments:
Mollie's checkout lets you choose the outcome (paid, failed, canceled, …).

After changing an env file, restart `php artisan serve`: a running server keeps the old
values.

Without production access: `composer setup` starts Docker, creates the tables and the
admin account, after which `php artisan serve` works.

`docker compose down` stops the database (the data is kept); `docker compose down -v`
deletes it.

### What the production copy contains

`php artisan db:pull-production` (part of `composer dev:local`) rebuilds the local
schema and copies the production data, reading production only (credentials from
`.env.prod`). It refuses to run when the local database is the production database or
when `APP_ENV=production`. On the way:

- production accounts are **not** copied: the only local user is admin@gouwwaas.be
  (password `password`), who takes over every activity;
- registrants get fake names, e-mail addresses and answers (dropdown, checkbox and
  number answers are kept);
- sessions, cache, jobs and password reset tokens are not copied;
- uploaded photos are not copied: people whose photo isn't on your disk show their
  initials.

## Pages

The site is in Dutch (`APP_LOCALE=nl`).

- `/` – home: introduction and how to join (`resources/views/home.blade.php`)
- `/districten` – map of the four districts and their scouting groups, with a sidebar
  per district and group; admins manage the groups (`resources/views/districten/`)
- `/wie-is-wie` – gouwbureau, adjuncten and districtsploegen; admins edit them
  (`resources/views/wie-is-wie/`)
- `/activiteiten` – upcoming and past activities; anyone can open one and register
  until the registration deadline (`resources/views/activiteiten/`)
- `/privacy` – privacy statement (`resources/views/privacy.blade.php`), linked from the
  footer and the registration form. Update it (and its "laatst aangepast" date) when the
  site starts collecting or sharing other data
- `/login` – login; once logged in the header shows "Welkom {naam}!"
- `/wachtwoord-vergeten` – request a password reset e-mail
- `/profiel` – logged-in users: see name and e-mail, change password
- `/organisatie` – admins only ("Organisatie" in the header), three tabs: **Gegevens**
  (the organisation's name, address and e-mail), **Gebruikers** (`/organisatie/gebruikers`:
  add, edit and delete users, force a password reset) and **Attest**
  (`/organisatie/attest`: certificate stamp, signers and preview)

**Users and admins.** Admins (`users.is_admin`) manage the other users. The flag
cannot be set through the website: the account that existed when the
`add_is_admin_to_users_table` migration ran became the admin. New users get an
e-mail to choose their own password; "Wachtwoord resetten" makes the current
password unusable, logs the user out everywhere and e-mails a reset link. Reset
links are valid for 24 hours.

**Activities.** Every logged-in user can create activities (`/activiteiten/nieuw`)
with a start (and optional end), location, description, registration deadline,
price (empty = free) and their own registration questions (short/long text,
number, e-mail, phone, date, dropdown, checkbox). Registrants always give their
name and e-mail. Only the creator (or an admin) can edit or delete an activity and
see its registrations, which can be downloaded as an Excel file (.xlsx). Answers keep a
copy of the question label, so changing or removing a question later does not
lose earlier answers.

**Background image and link previews.** An activity can have a background image
(optional, on the activity form). It is shown behind the title on the activity page,
under a dark gradient so the title and details stay readable on any photo. In the
browser, large photos are made at most 1920 pixels wide (JPEG) before uploading; the
server accepts jpg, png and webp up to 5 MB. The file is stored on the `public` disk
(`activities/`) and deleted with the activity (also when cancelled or archived).
Every page has Open Graph tags for link previews (WhatsApp, Facebook, LinkedIn…): title,
description, URL and image. An activity with a background image uses it as preview
image; other pages use the logo (`public/images/logo-600.png`).

**Payments and confirmation e-mails.** After registering, everyone lands on their
registration's own page (`/inschrijvingen/{token}`, a secret 40-character token, never
the id). For a free activity that page confirms the registration and a confirmation
e-mail is sent straight away. For a paid activity it is the checkout: an overview and a
"Betalen" button that starts a payment at [Mollie](https://www.mollie.com) and sends the
payer to Mollie's checkout. Mollie sends them back to the same page, which asks Mollie
for the status and shows the result: paid (registered, e-mail sent), still being
processed by the bank, or failed / canceled / expired (not registered, "Opnieuw
proberen"). Mollie also reports status changes to `/mollie/webhook`, so the
registration is confirmed and mailed even if the payer never comes back; on a local
address (`localhost`) no webhook is sent, because Mollie can't reach it. The e-mail is
sent exactly once, and straight away (production has no queue worker). The price is
stored with the registration, so a later price change doesn't change what was agreed.
Organisers see the payment status per registration (overview and Excel); only paid and
free registrations (and those added by an organiser) count. Online registrations that aren't
paid yet (payment in progress, never clicked "Betalen", or the payment failed / was canceled
/ expired) are left out of the overview and the Excel; the ones that will never be paid are
deleted by the nightly clean-up a day later (see
**Automatic archiving**). The code is in `app/Payments` (Mollie behind a
`PaymentGateway` interface, faked in the tests).

**Cancelling and refunds.** Participants can't cancel themselves: the confirmation e-mail
and page say to reply or write to the sender address. Organisers cancel a registration
with "Annuleren" in the overview. For a paid registration the dialog offers a full refund
or a refund minus administrative costs (€ 1,00 by default, adjustable); the refund is
requested through Mollie's refund API, the participant gets an e-mail that the money is
coming back within a few business days, and only then is the registration removed (if
Mollie refuses the refund, nothing is removed). Free registrations get an "are you sure"
and a cancellation e-mail without refund; registrations that were never paid are removed
without e-mail. Deleting an activity refunds every paid registration in full and e-mails
everyone; if a refund fails, the activity stays with just those registrations, so it can
be retried. The code is in `app/Payments/RegistrationCancellation.php`.

**Maximum number of participants.** An activity can have a maximum (empty = no limit).
A place is taken by every participant (free, paid or added by an organiser) and by an
online payment still in progress at Mollie, so two people can't pay for the last place at
the same time; expired or failed payments free the place again. The activity list and
page show the places left ("Nog 3 plaatsen", "Volzet"); a full activity hides the
registration form, and a payment is refused (nothing charged) when someone else took the
last place in the meantime. There is no waiting list.

**Manual registrations.** Organisers and admins add people themselves with "Inschrijving
toevoegen" (signed up by phone, on paper…): name and e-mail are required, the questions
are optional. For a paid activity they choose "Betaald" (cash, transfer…) or "Nog niet
betaald"; either way the person counts as a participant, optionally with a confirmation
e-mail. They may go over the maximum (the form warns). "Op betaald zetten" in the
overview marks a registration paid when the money came in outside the website, also for
an online registration whose payment failed or expired (not while a Mollie payment is
still running). Payments outside the website are never refunded through Mollie: when
cancelling, the dialog says to settle the refund yourself.

**Financial summary.** For paid activities the registrations overview ends with the
revenue: paid online (Mollie, before Mollie's transaction costs), paid manually (only
shown when there are manual payments), the total, and what manually added participants
still have to pay. It counts the price each registration paid; cancelled registrations
are removed, so refunds (and administrative costs kept back) are not in it.

**Attendance.** The registrations overview has an "Aanwezig" checkbox per registrant
(saved straight away, without reloading). To mark many people at once, select them
(or "select all") and use Aanwezig in the selection bar. The Excel export has an
"Aanwezig" column. Stored as `registrations.present_at`.

**Filtering, sorting and selecting.** Above the table: search by name or e-mail
(accents don't matter), filters for Aanwezig, Betaling and every question (a dropdown for
choice fields and checkboxes, "bevat" text for the rest; more than 3 questions sit behind
"Meer filters"), and sortable column headers. A checkbox per row (and "select all" for the
rows shown) opens a bar with Mailen (the mail page addressed to just those people),
Aanwezig, Niet aanwezig and Attesten versturen for the selection. Rows a filter hides are
deselected. The server only acts on ids of this activity and still checks who can get a
certificate. Download (Excel) is always everyone. All of it is in `public/js/registrations.js`;
without JavaScript the page works as before. The Betaling column and the financial summary
only show when some registration involves money (not the activity's current price).

**Participation certificates (attesten).** An activity with *vormingsuren* (optional
field on the activity form, whole or half hours) gives attendees a one-page PDF
certificate: organisation, activity, dates, hours, the participant's name, the
Gouwcommissaris(sen) and the stamp. "Attesten versturen (n)" on the overview e-mails
every registrant who is present and confirmed and hasn't had one yet, one mail per
registration (siblings on one address each get their own); it shows once the activity
has started. `registrations.certificate_sent_at` makes sure nobody gets it twice, so the
button can be used again for latecomers. Each row also has an "Attest" download. The PDF
is made with dompdf (needs PHP's GD extension); the code is in `app/Certificates/`, the
layout in `resources/views/attesten/attest.blade.php`.

**Certificate stamp and signers.** Admins upload the stamp (PNG) on Organisatie → Attest
(`/organisatie/attest`); it is stored on the private disk at
`storage/app/private/attesten/stempel.png`. The page also has a preview. Certificates are
signed by everyone with the Gouwcommissaris function on Wie is wie. Without a stamp or a
Gouwcommissaris, no certificates can
be made and the overview says why.

**Organisation details.** The name, building, address and e-mail of the
organisation are edited by admins on Organisatie → Gegevens (`/organisatie`) and stored
in the one-row `organisation` table (`App\Models\Organisation::current()`). They are
used on certificates, as reply-to address of certificate mails, in the footer, on the
privacy page and as the location placeholder. Until an admin saves that page, the
defaults from `organisation` in `config/gouw.php` are used. The logo on certificates is
uploaded on the same tab (PNG, private disk: `storage/app/private/organisatie/logo.png`);
without an upload it's `logo` in that config file. The website's own logos (header,
footer, link previews) are the optimised files in `public/images/`.

**Mailing participants.** Select people in the overview (or "select all") and choose
Mailen in the selection bar: the message goes to exactly those people (paid or not), one
e-mail per address so nobody sees the others' addresses. The activity's date, place and link are
added below the message, replies go to the organiser who sent it, and they can get a
copy. Sent straight away, like all mail (no queue worker).

**Calendar.** The confirmation e-mail has the activity attached as a calendar file
(`.ics`) and links for Google Calendar and Outlook.com; the registration's confirmation
page has the same links. `/activiteiten/{slug}/agenda.ics` serves the file (public; an
activity without an end lasts one day, as everywhere else). The code is in `app/Support/ActivityCalendar.php`.

**Home page texts.** The text blocks on the home page ("Gouw watte?", "En wat doen
jullie dan?"…) are stored in `page_blocks`; the migration creates the first two. Admins
add blocks with "+ Blok toevoegen" (new blocks go at the end), and change or remove one
with its "Bewerken" button. The text is
light Markdown (blank line = new paragraph, `**bold**`, `*italic*`, `[link](https://…)`,
`- ` lists); HTML is shown as text and `javascript:` links are dropped. `{aantal groepen}`
is replaced by the number of groups on the map.

**Wie is wie.** Every function (`roles` table) has a category: `bureau`, `adjunct`
or `district`. Admins see a "Bewerken" button on each function to change its
description, contact details (e-mail, Instagram, Facebook, website; only what is
filled in is shown) and the people filling it (`role_members`: first and last name,
optional totem, function title and photo). A function without people shows an empty
person with "Gezocht!" (`public/images/gezocht.png`). Only adjuncten can be added, renamed or deleted; the three gouwbureau
functions (`config/gouw.php`, `bureau`) and one function per district are fixed and
take their title and order from the config. Admins click a person's picture to choose a photo and crop it to a circle in the
browser; only the cropped 400×400 image (WebP, or PNG on older Safari) is uploaded.
Long descriptions are shortened on the page with a "Lees meer" button. Photos are
stored on the `public` disk,
so run `php artisan storage:link` once locally (deploys do this automatically).

**Automatic archiving.** Activities are archived automatically `archive_after_months`
(`config/gouw.php`, 24) after they ended: the activity and its registrations are deleted,
without refunds or e-mails (`php artisan activities:archive`; `--dry-run` previews, `-v`
shows what it does). The same command also cleans up unpaid online registrations: payments
still in progress after a day are checked with Mollie again (in case a webhook was missed),
and registrations without a payment, or whose payment failed, are deleted once nobody has
touched them for a day (`Registration::ABANDONED_AFTER_HOURS`; until then the payer can still
"Opnieuw proberen" on their page). If Mollie can't be reached the command fails, so cron
mails it; those payments are tried again the next night. It runs once a day from a cron
job set in the hosting control panel (the host doesn't allow `crontab` over SSH):

| Field | Value |
| --- | --- |
| Minute / hour | `0` / `3` (every night at 03:00); day, month and weekday `*` |
| Command | `/usr/local/bin/php /home/u211343p458668/gouwwaas/current/artisan activities:archive` |
| E-mail for cron output | the address that should hear about failures |

It must be a single command (the host's cron check refuses `cd … &&` and redirects) and
must **not** use `--quiet`: that would also hide errors. The command prints nothing when
all goes well (what it archived goes to the log), so the cron e-mail only arrives when
something fails. The deploy path is `~/gouwwaas` (`public_html` points to
`~/gouwwaas/current/public`).

**Districts and groups.** The four districts (name, map colour, outline) are in
`config/gouw.php`. The scouting groups are in the database (`scout_groups`): name, group
number (e.g. O3101G), type (gemengd, scouts, gidsen, zeescouts), meeting place address,
location, website and logo. Admins add, edit and remove them on `/districten`, and set a
logo per district there too ("Logo wijzigen" in the district panel; stored in the
`districts` table, keyed like `config/gouw.php`). Logos are jpg, png or webp (no SVG, which
can contain scripts) and appear at the top right of the sidebar panel. In the form,
the location is set by clicking or dragging on a small map, or with "Zoek adres"
(OpenStreetMap). The initial list (November 2026) comes from the Scouts en Gidsen
Vlaanderen groepenzoeker.

The map uses [Leaflet](https://leafletjs.com) 1.9.4 (in `public/vendor/leaflet`, BSD
licence) with the grey base map of Digitaal Vlaanderen (GRB), and `public/js/districten.js`.
Clicking a group or district opens it in the sidebar; links such as
`/districten#groep-o3101g` or `/districten#district-klaas` open one directly. A district
panel shows its districtsploeg from `/wie-is-wie`. District outlines appear as soon as
`boundary` is filled in for a district in `config/gouw.php` (a list of
`[latitude, longitude]` points). Groups without a location are listed but not on the map.

Styling lives in `public/css/app.css` (no build step required). Colours come from
the Gouw Waas emblem (`public/images/logo-*.webp`) and the Scouts en Gidsen
Vlaanderen house style, with Zilla Slab and Nunito Sans (self-hosted in
`public/fonts`, SIL OFL) standing in for the paid house fonts.

**Error pages** (`resources/views/errors/`: 403, 404, 419, 429, 500, 503) use their own
layout without login state, session or database, so they still render when those are
the problem.

## Tests and code quality

- `composer check` runs everything CI runs before a deploy: code style
  ([Pint](https://laravel.com/docs/pint)), static analysis
  ([Larastan](https://github.com/larastan/larastan), level 6, `phpstan.neon`) and the tests
  (`php artisan test`, in-memory SQLite).
- `composer fix` fixes the code style.

**Security headers** (`app/Http/Middleware/SecurityHeaders.php`): every response has a
Content-Security-Policy and the usual hardening headers (HSTS over https). Inline scripts
only run with the request's nonce, so write them as `<script nonce="{{ $cspNonce }}">`;
inline event handlers (`onclick="…"`) are blocked, so put behaviour in a script file
(`public/js/site.js`). A test fails when a view breaks either rule. New external hosts
(images, API calls) have to be added to the policy.

## Deployment

Every push to `main` runs the tests in GitHub Actions and, when they pass, deploys
to production with [Deployer](https://deployer.org) (`deploy.php`,
`.github/workflows/deploy.yml`).

- The code and the production `vendor/` folder are built in CI and uploaded over
  SSH with rsync. The server needs PHP and rsync, but not git or composer.
- Each deploy goes to a new folder in `releases/`. The `current` symlink only
  switches when everything succeeded (zero downtime). The last 5 releases are kept.
- `storage/` and `.env` are shared between releases, in `shared/`.
- The production `.env` is generated from GitHub secrets on every deploy.
- Migrations run automatically (`migrate --force`).
- After the deploy, the workflow checks `APP_URL/up` (the app starts and reaches the
  database) and the home page. If either doesn't answer 200 within a minute, the run
  fails (GitHub e-mails you) and the previous release is put back automatically
  (`dep rollback`). Only the code goes back: migrations that already ran stay, so keep
  them backward compatible.

### GitHub secrets

Add these under *Settings → Secrets and variables → Actions*:

| Secret | Example / notes |
| --- | --- |
| `SSH_PRIVATE_KEY` | Private key of a key pair whose public key is in the server's `~/.ssh/authorized_keys` |
| `SSH_KNOWN_HOSTS` | Output of `ssh-keyscan -p <port> <host>` |
| `DEPLOY_HOST` | SSH hostname of the server |
| `DEPLOY_USER` | SSH user |
| `DEPLOY_PORT` | Optional, defaults to `22` |
| `DEPLOY_PATH` | Folder on the server to deploy into, e.g. `~/domains/gouwwaas.be` |
| `DEPLOY_PHP_BIN` | Optional, path to PHP on the server if `php` is not the right version |
| `APP_KEY` | Generate with `php artisan key:generate --show` |
| `APP_URL` | `https://gouwwaas.be` |
| `DB_CONNECTION` | Optional, defaults to `mysql` |
| `DB_HOST` | Usually `localhost` when the database is on the same server |
| `DB_PORT` | Optional, defaults to `3306` |
| `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | Database credentials |
| `MAIL_HOST` | SMTP server for password e-mails, e.g. the host's mail server. Without it, e-mails only go to the log |
| `MAIL_PORT` | Optional, defaults to `587` (use `465` for SSL) |
| `MAIL_USERNAME`, `MAIL_PASSWORD` | SMTP login, usually the mailbox address and its password |
| `MAIL_FROM_ADDRESS` | Optional, defaults to `gouwbureau@gouwwaas.be` |
| `MOLLIE_API_KEY` | Mollie API key for activity payments (`live_…` in production, from the Mollie dashboard under *Developers → API keys*). Without it, paid activities show an error at checkout |

### One-time server setup

The web server's document root must point to `<DEPLOY_PATH>/current/public`. On
hosting where the document root is a fixed `public_html` folder, replace it with a
symlink after the first deploy:

```sh
cd <DEPLOY_PATH>
mv public_html public_html.bak
ln -s current/public public_html
```

After the first deploy, create a login on the server:

```sh
cd <DEPLOY_PATH>/current && php artisan user:create "Your Name" you@example.com
```

To roll back, re-run the workflow run of an earlier commit in the GitHub *Actions*
tab, or on the server point the `current` symlink at a previous folder in `releases/`.
