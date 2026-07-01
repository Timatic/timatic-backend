# Integration OAuth architecture

## OAuth callback proxy

Third-party OAuth providers require single pre-registered redirect URI. Each tenant runs on own subdomain (`app.{tenant}.timatic.app`), so central proxy handles all callbacks and forwards to correct tenant.

**Proxy:** `auth.timatic.app/integrations/{provider}/callback`  
**Registered redirect URI** (at OAuth provider): `https://auth.timatic.app/integrations/{provider}/callback`

### Flow

1. Integration initiates OAuth flow, encodes tenant slug in `state` as signed JWT: `{ "tenant": "slug", "integration_id": N, "nonce": "..." }`
2. Provider redirects to proxy with `?code=...&state=...`
3. Proxy base64-decodes JWT payload (no verification) to read `tenant` claim
4. Proxy issues `302` to `https://admin.{tenant}.timatic.app/integrations/{provider}/callback` — all query params forwarded unchanged
5. Tenant app verifies `state` JWT and completes token exchange

## Config file

OAuth integrations require a config file for client credentials (these must come from env vars, not `Integration.config`).

```php
// config/{name}.php
return [
    'client_id'     => env('{NAME}_CLIENT_ID'),
    'client_secret' => env('{NAME}_CLIENT_SECRET'),
    'redirect'      => env('{NAME}_REDIRECT_URI'),
];
```

Add three env vars to `.env.example`. Call `$this->mergeConfigFrom(__DIR__.'/../config/{name}.php', '{name}')` in `ServiceProvider::boot()`.

## OAuth implementation pattern

### Connector

- Add `Saloon\Traits\OAuth2\AuthorizationCodeGrant` trait
- Implement `defaultOauthConfig()` with `clientId`, `clientSecret`, `redirectUri`, `authorizeEndpoint`, `tokenEndpoint`, `defaultScopes`
- **Never** set `Authorization` in `defaultHeaders()` — auth applied via `$connector->authenticate($authenticator)`
- Constructor takes only provider-specific identifiers (e.g. `cloud_id`) — not raw token data

### OAuthService

Thin wrapper around Saloon's built-in OAuth methods. **No `Http` facade, no manual token endpoint calls.**

| Method | Purpose |
|---|---|
| `buildAuthorizationUrl(Integration $integration): string` | Store `oauth_nonce` in config; return `$connector->getAuthorizationUrl(state: $jwt)` |
| `handleCallback(string $code, string $state): Integration` | Verify JWT + nonce manually; call `$connector->getAccessToken($code, $state, null)` — pass `null` to skip Saloon's state check (we verify ourselves); fetch provider resources via Saloon request; persist serialized authenticator |
| `refreshIfExpired(Integration $integration): Integration` | Cache lock; call `$connector->refreshAccessToken($authenticator)`; persist serialized authenticator |
| `disconnect(Integration $integration): void` | Clear OAuth keys from config |

### Config keys stored (encrypted:array)

```php
[
    'authenticator'  => serialize($oauthAuthenticator), // Saloon OAuthAuthenticator — never store raw token fields
    'oauth_nonce'    => '...',   // temporary, cleared after callback
    // provider-specific: cloud_id, cloud_url, pending_resources, etc.
]
```

### Authenticating API calls

```php
$integration = $oauthService->refreshIfExpired($integration);
$authenticator = unserialize($integration->config['authenticator']);
$connector = new Connector($integration->config['cloud_id']);
$connector->authenticate($authenticator);
```

### Token endpoint auth note

- Atlassian/Jira: JSON body with `client_id` + `client_secret`
- Bitbucket: HTTP Basic auth (`client_id:client_secret`)
- GitHub/GitLab: varies — check provider docs

If provider requires non-standard token request format, override `createOAuthAuthenticatorFromResponse()` on the connector.

## Controllers

**RedirectController** — `auth` middleware — calls `$oauthService->buildAuthorizationUrl($integration)` and redirects.

**CallbackController** — `web` middleware only (no `auth` — user may not be logged in) — calls `$oauthService->handleCallback($code, $state)` and redirects to SettingsPage.

## Routes (web.php)

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
