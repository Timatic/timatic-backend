# Timatic Integration Blueprint

Use this skill when the user wants to add a new third-party integration to the Timatic project. Integrations follow the Jira OAuth package pattern: a path-based composer package under `/integrations/{name}/` with OAuth 2.0, Filament pages, Saloon HTTP connector, and a mapping table that links the external resource (project, repository, board, etc.) to Timatic customers and/or budgets.

## What to ask before starting

Ask the user:
1. **Integration name** — e.g. `bitbucket`, `github`, `gitlab`
2. **OAuth provider endpoints** — authorization URL, token URL, required scopes
3. **Primary resource to map** — e.g. repositories, boards, projects (what Timatic customers/budgets will be linked to)
4. **Map to budgets?** — Jira only maps to customers; some integrations also need budget mapping
5. **Ticket provider?** — Does this integration also supply tickets/issues to the time-entry form? If yes, implement `TicketProviderInterface`

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
Add the three env vars to `.env.example`.

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

### 5. OAuthService — follow JiraOAuthService exactly
Key methods:
- `buildAuthorizationUrl(Integration $integration): string` — stores `oauth_state` in `integration.config`
- `handleCallback(string $code, string $state): Integration` — validates state, exchanges code, fetches workspace/account info, stores in `integration.config`
- `refreshIfExpired(Integration $integration): Integration` — uses cache lock to prevent concurrent refresh
- `disconnect(Integration $integration): void` — nulls out config

**State parameter format:** `{integration_id}|{Str::random(32)}`

**Config keys stored (encrypted:array):**
```php
[
    'access_token'  => '...',
    'refresh_token' => '...',
    'expires_at'    => now()->addSeconds($ttl - 60)->timestamp,
    'oauth_state'   => '...',   // temporary, cleared after callback
    // provider-specific: workspace, cloud_id, cloud_url, etc.
]
```

**Token endpoint auth note:**
- Atlassian/Jira: JSON body with `client_id` + `client_secret`
- Bitbucket: HTTP Basic auth (`client_id:client_secret`)
- GitHub/GitLab: varies — check provider docs

### 6. Controllers

**RedirectController** — `auth` middleware:
```php
return redirect($oauthService->buildAuthorizationUrl($integration));
```

**CallbackController** — `web` middleware only (no auth — user may not be logged in yet):
```php
// 1. validate state
// 2. exchange code for tokens
// 3. fetch provider account/workspace info
// 4. save to integration.config
// 5. redirect to {Name}SettingsPage with success notification
```

### 7. Routes (web.php)
```php
Route::middleware(['web', 'auth'])->group(function () {
    Route::get('integrations/{integration}/{name}/redirect', {Name}RedirectController::class)
        ->name('{name}.oauth.redirect');
});

Route::middleware('web')->group(function () {
    Route::get('integrations/{name}/callback', {Name}CallbackController::class)
        ->name('{name}.oauth.callback');
});
```

### 8. Saloon Connector
- Extend `Connector`
- Base URL: provider API root
- `defaultHeaders()`: `Authorization: Bearer {access_token}` (call `refreshIfExpired` first)

### 9. Filament pages

**SettingsPage:**
- Sub-navigation: Settings | {Resource} Mapping
- Connected callout (workspace/account info)
- Not-connected callout
- "Connect" action → OAuth redirect route
- "Disconnect" action → calls `oauthService->disconnect()`

**MappingPage:**
- `mount()`: sync resources from provider API if connected
- Table columns: identifier, name, customer.name, (budget.title if applicable)
- Bulk action "Assign customer" — always
- Bulk action "Assign budget" — only if budget mapping is enabled (budget select should filter by selected customer via reactive)
- Header action "Refresh"

### 10. ServiceProvider
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

If the integration should supply tickets to the time-entry form, implement `TicketProviderInterface`:
```php
public function fetchTickets(?Customer $customer, ?string $search, ?User $user): Collection;
public static function fromConfig(array $config): static;
```
Register in ServiceProvider boot: `$ticketProviders->register('{name}', {Name}TicketProvider::class)`.

---

## Verification checklist

1. `composer update` — package installs without errors
2. `php artisan migrate` — mapping table created
3. Create an Integration with `type = {name}` in Filament
4. Click Connect → OAuth consent screen opens
5. After authorization → redirect back, status shows Connected
6. Navigate to mapping page → resources load from API
7. Assign customer (+ budget if applicable) to a resource
8. Verify `{name}_{resource}_mappings` table has correct values