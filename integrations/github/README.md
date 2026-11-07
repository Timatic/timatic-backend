# GitHub integration

Connects Timatic to a GitHub App: commits, pull requests and issue activity become events, and issues are
offered as tickets.

## GitHub App setup

Create a GitHub App (organisation settings → Developer settings → GitHub Apps) and configure:

- **Callback URL**: `https://{your-domain}/integrations/github/callback`. When tenants share one app on
  subdomains, register the parent domain (`https://timatic.app/integrations/github/callback`) and enable
  wildcard matching, so every `admin.{tenant}.timatic.app` receives the callback directly.
- **Expire user authorization tokens**: enabled (recommended — the code also handles it disabled)
- **Webhook**: inactive. Every mapped repository gets its own webhook instead, see below
- **Repository permissions**: Metadata read, Contents read, Issues read, Pull requests read, Webhooks read & write
- Generate a private key and put its PEM contents in `GITHUB_PRIVATE_KEY` (newlines as `\n`)

## Env

```
GITHUB_CLIENT_ID=
GITHUB_CLIENT_SECRET=
GITHUB_APP_ID=
GITHUB_APP_SLUG=
GITHUB_PRIVATE_KEY=
```

## Installations

Connecting adopts **every installation the connecting user can reach** (`GET /user/installations`) — there is
nothing to pick. "Refresh installations" on the settings page re-reads that list after the app is installed
on another organisation; the delegate page refreshes it on every visit.

The list is stored in `Integration.config`, so repositories and issues keep syncing when the user token
lapses. Only discovering new installations needs a valid user token.

Every organisation the connecting user can see is adopted, including their personal account. Those
repositories appear as unmapped rows until a customer is linked, and produce no events while unmapped.

## Webhooks

Linking a repository to a customer creates a webhook on that repository, pointing at this instance's
`/integrations/github/webhook/{integration}`. Unlinking it deletes the webhook again, so unmapped repositories
send nothing. Every integration signs its webhooks with its own secret, generated on first use and stored in
`Integration.config`.

Creating the webhook fails until the organisation accepts the app's **Webhooks** permission; the mapping page
then names the repositories whose webhook could not be created. Assign them again once the permission is
accepted.

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
| `push` (per commit) | `commit_created`, plus `rebase` for replayed commits on a forced push |
| `pull_request` opened / reopened | `pr_opened` |
| `pull_request` closed, merged | `pr_merged` |
| `pull_request` closed, not merged | `pr_declined` |
| `pull_request_review` submitted | `pr_approved`, `pr_changes_requested` or `pr_commented` |
| `pull_request_review_comment` created | `pr_commented` |
| `issue_comment` created on a pull request | `pr_commented` |
| `issue_comment` created on an issue | `issue_commented` |
| `create` branch / tag | `branch_created` / `tag_created` |
| `repository` created, deleted, archived, unarchived, publicized, privatized, edited, renamed, transferred | `repository_{action}` |

## People

`UserMatcher` links GitHub identities to Timatic users in the package's own tables: one login per user
(`github_user_logins`) and any number of commit emails (`github_commit_emails`). A commit's author is
found by, in order:

1. a linked commit email,
2. a linked login (`commits[].author.username`),
3. the Timatic account email.

A match links the commit's email and, if the user has none yet, its login. Every other delivery matches on
`sender.login` only, so a user without a known login produces no events until their first push. Admins
correct or complete the links on the integration's People page.

Note: `repository` with action `created` rarely arrives. Unless the app is installed on "All repositories",
no installation covers a brand-new repository yet, so GitHub sends nothing. `deleted` behaves the same once
access is gone.

## Issues as tickets

`TicketProvider` offers GitHub issues in the time entry form, keyed as `owner/repo#123`:

- With a customer selected, open issues from that customer's mapped repositories.
- Without a customer, open issues the user is involved in (`involves:{login}`), so a user without a
  known login sees none.
- With a search term, `/search/issues` scoped to the mapped repositories. A term shaped like
  `owner/repo#123` is looked up directly.

Searches run once per installation, since an installation token only covers its own repositories. A key for
an unmapped repository resolves through the installation whose account owns it.

Pull requests are filtered out, since GitHub's issue endpoints return them as issues.

Two limits worth knowing: at most 20 `repo:` qualifiers are sent (GitHub caps the search query length), so
searches cover the 20 most recently updated mappings; and GitHub exposes no email address for issue
commenters, so ticket actions carry the login as display name and no email.
