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

## Events

| GitHub delivery | Timatic event type |
|---|---|
| `push` (per commit) | `commit_pushed`, plus `rebase` for replayed commits on a forced push |
| `pull_request` opened / reopened | `pr_opened` |
| `pull_request` closed, merged | `pr_merged` |
| `pull_request` closed, not merged | `pr_declined` |
| `pull_request_review` submitted | `pr_approved`, `pr_changes_requested` or `pr_commented` |
| `pull_request_review_comment` created | `pr_commented` |
| `issue_comment` created on a pull request | `pr_commented` |
| `issue_comment` created on an issue | `issue_commented` |
| `create` branch / tag | `branch_created` / `tag_created` |
| `repository` created, deleted, archived, unarchived, publicized, privatized, edited, renamed, transferred | `repository_{action}` |

Commit events match the Timatic user by commit author email and learn `users.github_login` from
`commits[].author.username`. Every other delivery matches on `sender.login`, so a user without a known login
produces no events until their first push.

Note: `repository` with action `created` rarely arrives. Unless the app is installed on "All repositories",
no installation covers a brand-new repository yet, so GitHub sends nothing. `deleted` behaves the same once
access is gone.

## Issues as tickets

`TicketProvider` offers GitHub issues in the time entry form, keyed as `owner/repo#123`:

- With a customer selected, open issues from that customer's mapped repositories.
- Without a customer, open issues the user is involved in (`involves:{github_login}`), so a user without a
  known login sees none.
- With a search term, `/search/issues` scoped to the mapped repositories. A term shaped like
  `owner/repo#123` is looked up directly.

Pull requests are filtered out, since GitHub's issue endpoints return them as issues.

Two limits worth knowing: at most 20 `repo:` qualifiers are sent (GitHub caps the search query length), so
searches cover the 20 most recently updated mappings; and GitHub exposes no email address for issue
commenters, so ticket actions carry the login as display name and no email.
