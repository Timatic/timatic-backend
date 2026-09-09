# GitHub integration

Connects Timatic to a GitHub App: commits, pull requests and issue activity become events, and issues are
offered as tickets.

## GitHub App setup

Create a GitHub App (organisation settings → Developer settings → GitHub Apps) and configure:

- **Callback URL**: `https://auth.timatic.app/integrations/github/callback`
- **Expire user authorization tokens**: enabled (recommended — the code also handles it disabled)
- **Webhook URL**: `https://auth.timatic.app/integrations/github/webhook`
- **Webhook secret**: the value of `GITHUB_WEBHOOK_SECRET`, shared with the auth proxy
- **Repository permissions**: Metadata read, Contents read, Issues read, Pull requests read
- **Subscribe to events**: Push, Pull request, Pull request review, Pull request review comment,
  Issue comment, Repository, Create
- Generate a private key and put its PEM contents in `GITHUB_PRIVATE_KEY` (newlines as `\n`)

## Env

```
GITHUB_CLIENT_ID=
GITHUB_CLIENT_SECRET=
GITHUB_APP_ID=
GITHUB_APP_SLUG=
GITHUB_PRIVATE_KEY=
GITHUB_WEBHOOK_SECRET=
```

## Webhook routing

A GitHub App has a single webhook URL, so deliveries for every tenant arrive at the auth proxy. The proxy is
stateless and routes on the payload's `installation.id` using a hand-maintained map. After connecting an
installation, add its entry to `GITHUB_INSTALLATIONS` in the proxy env and deploy it:

```
GITHUB_INSTALLATIONS={installation_id}:{tenant}:{integration_id}
```

The settings page shows the exact line to add.

## Tokens

| Purpose | Credential |
|---|---|
| Identifying the admin at connect time, listing their installations | user access token (`ghu_*`, 8h, refreshable) |
| Reading repositories and issues, everything running in a queue | installation access token (`ghs_*`, 1h, minted on demand and cached) |

Because of that split this package does not use Saloon's `AuthorizationCodeGrant` trait (as
`.claude/docs/integration-oauth.md` prescribes): the trait models a single user-token flow, while nearly every
request here authenticates as the app installation. `OAuthService` handles the user flow and
`InstallationTokenService` mints installation tokens from an RS256 app JWT.
