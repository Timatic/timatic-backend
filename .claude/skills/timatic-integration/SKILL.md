# Timatic Integration Blueprint

Use skill when user want add new third-party integration to Timatic. Integrations follow Jira OAuth package pattern: path-based composer package under `/integrations/{name}/` with OAuth 2.0, Filament pages, Saloon HTTP connector, and mapping table linking external resource (project, repository, board, etc.) to Timatic customers and/or budgets.

## What to ask before starting

Ask user:
1. **Integration name** — e.g. `bitbucket`, `github`, `gitlab`
2. **Auth method** — OAuth 2.0 (redirect flow) or simple API auth (basic auth / static API token)?
3. **OAuth provider endpoints** — authorization URL, token URL, required scopes *(OAuth only)*
4. **Primary resource to map** — e.g. repositories, boards, projects (what customers/budgets link to)
5. **Map to budgets?** — Jira maps only to customers; some integrations also need budget mapping
6. **Ticket provider?** — Integration also supply tickets/issues to time-entry form? If yes, implement `TicketProviderInterface`

---

## Package structure

```
/integrations/{name}/
├── composer.json
├── config/
│   └── {name}.php
├── database/migrations/
│   └── {date}_create_{name}_{resource}_mappings_table.php
├── routes/
│   └── web.php
└── src/
    ├── {Name}ServiceProvider.php
    ├── {Name}OAuthService.php
    ├── {Name}Connector.php              # Saloon connector
    ├── Models/
    │   └── {Name}{Resource}Mapping.php
    ├── Requests/
    │   └── Get{Name}{Resource}sRequest.php
    ├── Http/Controllers/
    │   ├── {Name}RedirectController.php
    │   └── {Name}CallbackController.php
    └── Filament/Pages/
        ├── {Name}SettingsPage.php
        └── {Name}{Resource}MappingPage.php
```

---

## Step-by-step checklist

### 1. composer.json (package)
- `name`: `timatic/{name}-integration`
- `autoload.psr-4`: `Timatic\\{Name}\\` → `src/`
- `extra.laravel.providers`: `[{Name}ServiceProvider::class]`

### 2. Register in root composer.json
```json
{
  "repositories": [{ "type": "path", "url": "./integrations/{name}" }],
  "require": { "timatic/{name}-integration": "*" }
}
```

### 3. Config file
```php
// config/{name}.php
return [
    'client_id'     => env('{NAME}_CLIENT_ID'),
    'client_secret' => env('{NAME}_CLIENT_SECRET'),
    'redirect'      => env('{NAME}_REDIRECT_URI'),
];
```
Add three env vars to `.env.example`.

### 4. Migration — mapping table
```php
Schema::create('{name}_{resource}_mappings', function (Blueprint $table) {
    $table->id();
    $table->foreignId('integration_id')->constrained()->cascadeOnDelete();
    // provider-specific identifier fields (slug, key, id, etc.)
    $table->string('{resource}_name');
    $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
    $table->foreignId('budget_id')->nullable()->constrained()->nullOnDelete(); // omit if not needed
    $table->timestamps();
    $table->unique(['integration_id', '...identifier...']);
});
```

### 5. Auth strategy

**OAuth 2.0** — follow steps 5a below (Jira/Bitbucket pattern).

**Simple API auth (basic auth / static token)** — skip steps 5a, 6, 7. Instead:
- Create typed `ApiCredentials` readonly class with required fields (e.g. `baseUrl`, `username`, `apiToken`).
- Bind in `ServiceProvider::register()` reading from `Integration::where('type', '{name}')->first()?->config[...] ?? ''`.
- Connector constructor accepts `ApiCredentials`, sets `Authorization` in `defaultHeaders()`.
- SettingsPage stores credentials in `Integration.config` (encrypted). No redirect/callback flow.
- See TOPdesk integration (`integrations/topdesk/`) as reference implementation.

For OAuth 2.0 implementation details (OAuthService pattern, controllers, routes), see `.claude/docs/integration-oauth.md`.

### 6. Saloon Connector
- Extend `Saloon\Http\Connector`
- **OAuth 2.0**: add `Saloon\Traits\OAuth2\AuthorizationCodeGrant` trait + implement `defaultOauthConfig()`; auth applied via `$connector->authenticate($authenticator)` before sending requests
- **Simple auth**: implement `defaultAuth()` returning the appropriate Saloon authenticator (`TokenAuthenticator` for Bearer, `HeaderAuthenticator` for custom schemes like `Token`, `BasicAuthenticator` for basic auth)
- Base URL: provider API root (use provider-specific identifier, e.g. `cloud_id`, from `integration.config`)

### 7. Filament pages

**SettingsPage:**
- Sub-navigation: Settings | {Resource} Mapping
- `name` field — rename integration.
- Connected callout (workspace/account info)
- Not-connected callout
- "Connect" action → OAuth redirect route
- "Disconnect" action → calls `oauthService->disconnect()`
- "Delete" action —> deletes Integration record
-- For actions, use `$this->redirect(request()->url());` in callback to refresh page.

**MappingPage:**
- `mount()`: sync resources from provider API if connected
- Table columns: identifier, name, customer.name, (budget.title if applicable)
- Bulk action "Assign customer" — always
- Bulk action "Assign budget" — only if budget mapping enabled (budget select filter by selected customer via reactive)
- Header action "Refresh"

### 8. ServiceProvider
```php
// register()
$types->register('{name}', [
    '{name}.settings'    => {Name}SettingsPage::class,
    '{name}.{resources}' => {Name}{Resource}MappingPage::class,
]);

// boot()
$this->loadMigrationsFrom(__DIR__.'/../database/migrations');
$this->loadRoutesFrom(__DIR__.'/../routes/web.php');
$this->mergeConfigFrom(__DIR__.'/../config/{name}.php', '{name}');
// optional: $ticketProviders->register('{name}', {Name}TicketProvider::class);
```

---

## Optional: TicketProvider

Integration should supply tickets to time-entry form? Implement `TicketProviderInterface`:
```php
public function fetchTickets(?Customer $customer, ?string $search, ?User $user): Collection;
public static function fromConfig(array $config): static;
```
Register in ServiceProvider boot: `$ticketProviders->register('{name}', {Name}TicketProvider::class)`.

---

## Verification checklist

1. `composer update` — package installs without errors
2. `php artisan migrate` — mapping table created
3. Create Integration with `type = {name}` in Filament
4. Click Connect → OAuth consent screen opens
5. After authorization → redirect back, status shows Connected
6. Navigate to mapping page → resources load from API
7. Assign customer (+ budget if applicable) to resource
8. Verify `{name}_{resource}_mappings` table has correct values
