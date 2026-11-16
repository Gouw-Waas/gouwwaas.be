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
- `/districten` – the four districts with their groups, each linking to its website
  (`resources/views/districten.blade.php`)
- `/wie-is-wie` – gouwbureau, adjuncten and districtsploegen; admins edit them
  (`resources/views/wie-is-wie/`)
- `/activiteiten` – upcoming and past activities; anyone can open one and register
  until the registration deadline (`resources/views/activiteiten/`)
- `/login` – login; once logged in the header shows "Welkom {naam}!"
- `/wachtwoord-vergeten` – request a password reset e-mail
- `/profiel` – logged-in users: see name and e-mail, change password
- `/gebruikers` – admins only: add, edit and delete users, force a password reset

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
see its registrations, which can be downloaded as a CSV for Excel. Answers keep a
copy of the question label, so changing or removing a question later does not
lose earlier answers. Payment is not handled online: the price is informational.

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

**Districts and groups** are listed in `config/gouw.php` (source: Scoutnet) as
`[name, municipality, group number, website]`. Edit that file to add, remove or
rename a group or change its website (`null` = no link); the counts update
automatically.

Styling lives in `public/css/app.css` (no build step required). Colours come from
the Gouw Waas emblem (`public/images/logo-*.webp`) and the Scouts en Gidsen
Vlaanderen house style, with Zilla Slab and Nunito Sans (self-hosted in
`public/fonts`, SIL OFL) standing in for the paid house fonts.

## Tests

`php artisan test` (uses an in-memory SQLite database).

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
