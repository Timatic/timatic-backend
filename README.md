## Timatic API

The [High Level Design documentation](./docs/high-level-design.md) will give you a broader understanding of the application.

The [Business Rules documentation](./docs/business-rules.md) will help you understand the business logic of BudgetVersions, Periods and BudgetUsage calculations.

## Setup local development environment

```bash
cp .env.example .env
composer install
php artisan key:generate
php artisan timatic:install
herd link api.app.timatic --secure
```

`timatic:install` runs migrations, creates the first admin user, and optionally generates an API token and seeds dummy data.

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

## License

Copyright (c) 2025 Timatic.

You may use this software internally within your organization for any purpose. Selling, sublicensing, or providing the software to third parties for commercial gain is prohibited. See [LICENSE.txt](./LICENSE.txt) for full terms.
