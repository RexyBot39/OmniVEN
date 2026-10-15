# OmniVEN

The marketing control center for 39DollarGlasses, Ocusafe, Neurolux, Ocusleep, Online Contacts, Lumeo, Lens Replacement and Trailview: every marketing tool, who owns each login, recurring routines, and a team bulletin.

- **Sign-in:** Google only, limited to `@39dollarglasses.com` Workspace accounts (domain, verified email and Google's `hd` claim are all checked server-side).
- **Roles:** everyone signed in can view everything, tick routines and post notes. **Admins** add/edit tools, brands and routines, pin notes, and manage people on the **People** page. Emails in `OMNIVEN_ADMIN_EMAILS` are always admins.
- **Stack:** Laravel 13, PHP 8.4, Postgres on Railway, Blade + plain CSS/JS (no Node build step).

## 1. Create the Google sign-in client (5 minutes)

1. Go to Google Cloud Console → pick or create a project (e.g. "OmniVEN") under the 39dollarglasses.com organization.
2. **APIs & Services → OAuth consent screen**: choose **Internal** (this alone limits sign-in to your Workspace). App name `OmniVEN`, support email your address. Scopes: `openid`, `email`, `profile`.
3. **APIs & Services → Credentials → Create credentials → OAuth client ID** → *Web application*.
   - Authorized redirect URIs:
     - `https://<your-railway-domain>/auth/google/callback`
     - `http://localhost:8000/auth/google/callback` (for local testing)
4. Copy the **Client ID** and **Client secret**.

## 2. Deploy on Railway

1. Push this folder to a new private GitHub repo (e.g. `ballman1/omniven`).
2. In Railway: **New Project → Deploy from GitHub repo** → pick the repo. Railway uses the `Dockerfile` and `railway.json` here.
3. In the same project: **New → Database → PostgreSQL**.
4. On the OmniVEN service → **Variables**, add:

   | Variable | Value |
   |---|---|
   | `APP_NAME` | `OmniVEN` |
   | `APP_ENV` | `production` |
   | `APP_DEBUG` | `false` |
   | `APP_KEY` | output of `php artisan key:generate --show` (any machine with PHP), or `base64:` + 32 random bytes |
   | `APP_URL` | `https://<your-railway-domain>` |
   | `APP_TIMEZONE` | `America/New_York` |
   | `PORT` | `8080` |
   | `DB_CONNECTION` | `pgsql` |
   | `DB_URL` | `${{Postgres.DATABASE_URL}}` |
   | `SESSION_DRIVER` | `database` |
   | `SESSION_SECURE_COOKIE` | `true` |
   | `CACHE_STORE` | `database` |
   | `LOG_CHANNEL` | `stderr` |
   | `GOOGLE_CLIENT_ID` | from step 1 |
   | `GOOGLE_CLIENT_SECRET` | from step 1 |
   | `GOOGLE_REDIRECT_URI` | `https://<your-railway-domain>/auth/google/callback` |
   | `OMNIVEN_ALLOWED_DOMAIN` | `39dollarglasses.com` |
   | `OMNIVEN_ADMIN_EMAILS` | `drmarcweinstein@39dollarglasses.com` (comma-separate more) |

5. **Settings → Networking → Generate domain** (or add a custom one like `omniven.39dollarglasses.com`), then make sure `APP_URL`, `GOOGLE_REDIRECT_URI` and the Google redirect URI all use it.
6. Deploy. Before each release goes live, `bin/predeploy.sh` runs migrations and loads the starter brands, tools and routines **only if the database is empty**.

## 3. Connect Slack (optional)

The **Slack** tab shows recent messages from chosen channels and lets people post into them. Posts appear as "Name (via OmniVEN)".

1. Go to [api.slack.com/apps](https://api.slack.com/apps) → **Create New App** → **From an app manifest** → pick your workspace → paste `slack-app-manifest.yml` from this repo → **Create**.
2. **Install to Workspace** and allow it.
3. Copy the **Bot User OAuth Token** (starts with `xoxb-`) from **OAuth & Permissions**.
4. In Railway, add `SLACK_BOT_TOKEN=xoxb-…` (and optionally `OMNIVEN_SLACK_CHANNELS=reddit-monitor,marketing`, the channels loaded the first time).
5. Open the Slack tab. Public channels are joined automatically. For a private channel, type `/invite @OmniVEN` in it, then add it under **Manage channels**.
6. Access requests: add `OMNIVEN_ACCESS_SLACK_CHANNEL=<channel name>` in Railway to get a short note there whenever someone clicks **Request access** (their name and the tool only, never their reason). Type `/invite @OmniVEN` in that channel so the bot can post. Leave it unset to see requests only on the Access tab.

## 4. Nightly broken-link check (Railway cron)

OmniVEN checks every tool, brand account, brand website, social link and routine link once a night. Admins see the results in **Broken links** on the Access tab and the Access review. A link counts as broken only after failing two nights in a row. Sites that block automated visits (Instagram, Facebook, LinkedIn…) show as "couldn't be checked", not broken. When links newly break, a short note goes to `OMNIVEN_ACCESS_SLACK_CHANNEL`.

Set it up once in Railway:

1. In the OmniVEN project: **New → GitHub Repo** → pick this same repo. Name the new service `omniven-link-check`.
2. On that service → **Settings**:
   - **Config-as-code → Railway config file**: `railway.cron.json`. This runs `php artisan omniven:check-links` on the schedule `0 7 * * *` (07:00 UTC, about 3am Eastern), with no web server or health check.
3. On that service → **Variables**: add the same values as the web service, using references to the web service's variables: `APP_KEY`, `APP_URL`, `APP_TIMEZONE`, `DB_CONNECTION`, `DB_URL`, `CACHE_STORE`, `LOG_CHANNEL`, `SLACK_BOT_TOKEN`, `OMNIVEN_ACCESS_SLACK_CHANNEL`.
4. Deploy it. The run log should end with a line like "Checked 64 links: 58 OK, 2 broken, 4 couldn't check…".

Admins can also press **Check now** on the Access tab at any time.

## Local development

```bash
composer install
cp .env.example .env && php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
php artisan serve          # http://localhost:8000
php artisan test           # feature tests: sign-in, roles, tasks, activity, Slack
```

Run `composer update` once locally and commit the generated `composer.lock` so Railway builds are repeatable.

## Where things live

| What | Where |
|---|---|
| Google sign-in + domain checks | `app/Http/Controllers/Auth/GoogleController.php` |
| Admin rules | `app/Providers/AppServiceProvider.php` (gates), `routes/web.php` |
| Security headers (CSP, HSTS, no framing) | `app/Http/Middleware/SecurityHeaders.php` |
| Categories, statuses, cadences | `config/omniven.php` |
| Starter data | `database/seeders/OmniVenSeeder.php` |
| Dashboard | `resources/views/dashboard.blade.php`, `public/css/omniven.css`, `public/js/omniven.js` |

Never store passwords or 2FA codes in OmniVEN. It records which account a tool lives under and who grants access; credentials belong in the password manager.
