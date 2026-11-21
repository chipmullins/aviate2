# Aviate

A private social network for airline crews. Pilots, flight attendants and ground crew at North American airlines sign up with their work email, add the people they fly with, and share posts, photos, comments and direct messages, with live updates.

This is a ground-up rewrite of the original PHP Aviate as a modern, secure single-page app.

| Layer | Tech |
|---|---|
| Front end | Angular 22 (standalone components, signals), Outfit + DM Sans fonts |
| API | Laravel 13 with Sanctum (cookie sessions) |
| Real-time | Laravel Reverb (WebSockets) + Laravel Echo |
| Database | PostgreSQL 17 |
| Runtime | Docker Compose: Caddy (HTTPS), nginx, php-fpm, queue worker, scheduler, Reverb, Postgres |

## Features

- **Airline-only sign-up**: registration and email changes only accept `@united.com`, `@delta.com`, `@aa.com`, `@allegiant.com`, `@suncountry.com`, `@jetblue.com` and `@southwest.com` (exact domain match).
- **News feed** of your posts and your friends', with infinite scroll, photos and YouTube embeds.
- **Profiles** with stats, mutual friends, and posts written on your profile by friends.
- **Likes and comments**, with notifications to the post's author.
- **Friends**: requests, accept/decline, unfriend; sending a request to someone who already requested you accepts it.
- **Direct messages** between friends, delivered live over WebSockets, with unread counts.
- **Notifications** that arrive live, plus a notifications page.
- **Search** by name, with mutual-friend counts.
- **Trending words** from recent posts.
- **Settings**: name and email, password, profile photo with a cropper, and close account (logging back in reopens it).

## Quick start

You need [Docker Desktop](https://www.docker.com/products/docker-desktop/) (or Docker Engine with the Compose plugin).

```bash
./setup.sh                  # creates .env with random secrets (never overwrites an existing one)
docker compose up --build   # first build takes a few minutes
```

Passwords are hashed with Argon2id. If you ever switch `HASH_DRIVER`, reset the database (`docker compose down -v`): existing hashes won't verify under a different algorithm.

Open **http://localhost:8080**.

On Windows, run `./setup.sh` from Git Bash or WSL, or copy `.env.example` to `.env` and fill in the four secrets yourself. `APP_KEY` must be `base64:` followed by 32 random bytes, base64-encoded.

### Demo accounts

The first start fills the database with sample crew (turn this off with `SEED_DEMO_DATA=false` in `.env`). Every demo account's password is **`aviate-demo-1`**.

| Email | Notes |
|---|---|
| `maya.chen@delta.com` | Has friends, posts, a conversation and two pending friend requests. The best one to start with. |
| `marcus.webb@united.com` | Maya's friend. Log in as him in another browser to watch messages arrive live. |
| `priya.raman@aa.com`, `diego.alvarez@southwest.com`, `hannah.brooks@jetblue.com`, `tom.okafor@allegiant.com`, `lena.novak@suncountry.com` | |

You can also register your own account with any made-up address at an allowed domain, e.g. `you@jetblue.com`.

## Running the tests

```bash
# Backend (PHPUnit, 55+ tests). Uses its own in-memory SQLite database, never your real data.
docker compose run --rm api php artisan test

# Front end (Vitest)
cd frontend && npm install && npx ng test --watch=false
```

## Security

Built to be safe to show off:

| Risk | How Aviate handles it |
|---|---|
| SQL injection | Every query goes through Laravel's query builder/Eloquent with bound parameters. Search escapes `%` and `_`. |
| Password storage | Argon2id (memory-hard, OWASP's first choice) via Laravel's `hashed` cast: 64 MB of memory and 4 passes per hash. Minimum 8 characters with letters and numbers. |
| Session hijacking / CSRF | Sanctum SPA auth: HttpOnly, SameSite=Lax, encrypted session cookies, a CSRF token on every write, and a fresh session ID on login. |
| Brute-force login | 5 attempts per email+IP per minute, plus per-IP limits on auth, posting and uploads. On a public server, nginx only takes the visitor's IP from Caddy, which overwrites any forwarded-IP header a visitor sends, so it can't be faked. |
| Account discovery | Login failures give the same message whether or not the email exists. |
| Broken access control | Policies: only the author or profile owner can delete a post; only the commenter or post author can delete a comment; only the sender can delete a message. Messaging requires friendship. Users only ever see their own conversations and notifications. Each tested. |
| Cross-site scripting | Angular escapes all user text; no `innerHTML`. Links are only made for `http(s)` URLs. YouTube embeds are built from a validated 11-character ID only. A strict Content-Security-Policy blocks inline scripts. |
| Malicious uploads | Images are checked by content (not extension), size- and dimension-limited, then decoded and re-encoded by GD (stripping EXIF/GPS data and anything hidden in the file), and saved under random names. Uploads are served with `nosniff` and a sandboxing CSP; nothing in the uploads folder can execute. |
| Data leaks | Emails are never included in other users' data. Errors never expose stack traces (`APP_DEBUG=false`). Closed accounts disappear everywhere. |
| WebSocket snooping | Each user can only join their own private channel (checked server-side). Reverb only accepts connections from your site's host. Its admin HTTP API isn't exposed. |
| Clickjacking & friends | `X-Frame-Options: DENY`, `frame-ancestors 'none'`, `nosniff`, a strict referrer policy, and a Permissions-Policy. |
| Secrets in git | `.env` is git-ignored and generated per machine. The database isn't reachable from outside Docker. |

## Public demo

Set `DEMO_MODE=true` to run Aviate as a public demo:
- The login page shows **Try the demo** buttons that sign visitors in as Maya or Marcus with one click.
- The sample accounts' email, password and photo can't be changed, and they can't be closed, so no visitor can lock out the next one.
- Every night at `DEMO_RESET_AT` (Eastern), a scheduled job wipes all accounts, posts, messages and uploads and restores the sample crew.

## Deploying

[DEPLOY.md](DEPLOY.md) walks through putting the demo online at `https://aviate.glofly.app` on a t3.micro EC2 instance. Caddy gets and renews the HTTPS certificate automatically.

## Developing without Docker

- **Front end with hot reload:** keep the Docker stack running, then `cd frontend && npm install && npx ng serve` and open http://localhost:4200. API, WebSocket and upload requests are proxied to `localhost:8080`.
- **Backend:** see `backend/.env.example`. You'll need PHP 8.3+ with `pdo_pgsql` and `gd`, Composer, and a PostgreSQL database.

## Project layout

```
aviate2/
├── docker-compose.yml
├── setup.sh                     # creates .env with secrets
├── backend/                     # Laravel API
│   ├── app/
│   │   ├── Http/Controllers/Api # one controller per area (auth, posts, friends, messages, ...)
│   │   ├── Http/Requests        # validation rules
│   │   ├── Http/Resources       # exactly what JSON leaves the server
│   │   ├── Policies             # who may delete what
│   │   ├── Rules/AirlineEmail   # the airline-domain check
│   │   ├── Services             # image re-encoding, trending words
│   │   ├── Notifications        # likes, comments, friend requests (saved + pushed live)
│   │   └── Events/MessageSent   # pushed live to the recipient
│   ├── config/aviate.php        # allowed domains, upload limits
│   ├── database/                # migrations, factories, demo seeder
│   ├── routes/api.php, channels.php
│   ├── tests/                   # PHPUnit feature + unit tests
│   └── Dockerfile
└── frontend/                    # Angular app
    ├── src/app/core             # API client, auth, live updates, badge counts
    ├── src/app/shared           # post card, composer, avatar, safe post body
    ├── src/app/pages            # feed, profile, messages, notifications, friends, search, settings
    ├── docker/nginx.conf        # routing + security headers
    └── Dockerfile
```
