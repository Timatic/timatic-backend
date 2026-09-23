## Timatic API

The [High Level Design documentation](./docs/high-level-design.md) will give you a broader understanding of the application.

The [Business Rules documentation](./docs/business-rules.md) will help you understand the business logic of BudgetVersions, Periods and BudgetUsage calculations.

## Setup local development environment

```bash
cp .env.example .env
composer install
npm install
npm run build
php artisan key:generate
php artisan timatic:install
herd link api.app.timatic --secure
```

`npm run build` compiles the Filament admin theme and the stylesheet for the integration consent pages. Deployments must run it too, otherwise those pages render unstyled.

`timatic:install` runs migrations, creates the first admin user, and optionally generates an API token and seeds dummy data.

### Session cookie

Every environment must set `SESSION_SAME_SITE=lax` and keep `SESSION_SECURE_COOKIE=true`.
The frontend and the API share one registrable domain (`app.timatic.test` and
`api.app.timatic.test` both resolve to `timatic.test`), so the browser still classifies API
requests as same-site and sends the cookie. `SESSION_SAME_SITE=none` would additionally let
any origin's form post ride along on the session, which `tests/Feature/SessionCookieTest.php`
guards against.

### Authentication

A deployment runs exactly one identity provider. Set `SOCIALITE_DRIVER` to `azure`, `google` or
`auth0` and fill that provider's credentials in `.env`; `GET auth/provider` answers 503 until it can,
so a misconfigured deployment fails on the login screen rather than at the provider.

Browser callers authenticate with a bearer token, not the session. `config/api_clients.php` registers
who may ask for one, which redirect uris their codes may travel to and how long their tokens live:

| Client | Redirect uris from | Lifetime |
|---|---|---|
| `web` | `APP_FRONTEND_URL` + `/auth/callback` | `WEB_TOKEN_LIFETIME_DAYS` (30) |
| `extension` | `EXTENSION_IDS` | `EXTENSION_TOKEN_LIFETIME_DAYS` (90) |

The web session remains in use by the Filament admin panel, the integration consent pages and the
`oauth/authorize` consent screen.

### Dummy data

To (re)seed dummy data at any time:

```bash
php artisan db:seed --class=DummySeeder
```

## Commands

### Rebundle entry suggestions

Deletes all open (not accepted, not rejected) entry suggestions and rebundles their
activities chronologically using the current matching rules:

```bash
php artisan timatic:rebundle-suggestions [--user=1] [--from=2026-06-01] [--to=2026-06-30]
```

Pause queue workers before running this against live data, so the `CreateSuggestion`
listener cannot attach new activities while suggestions are being rebundled.

## License

Copyright (c) 2025 Timatic.

You may use this software internally within your organization for any purpose. Selling, sublicensing, or providing the software to third parties for commercial gain is prohibited. See [LICENSE.txt](./LICENSE.txt) for full terms.
