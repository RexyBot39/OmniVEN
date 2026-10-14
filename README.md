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

## Local development

```bash
composer install
cp .env.example .env && php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
php artisan serve          # http://localhost:8000
php artisan test           # 17 feature tests: domain lock, roles, routines, notes
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
