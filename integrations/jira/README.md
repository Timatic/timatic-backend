Create an oAuth 2.0 application
https://developer.atlassian.com/console/myapps/

## Connect with an API token

For a Jira Cloud site the OAuth app cannot be installed on, e.g. the instance of a client.

1. Create an API token with scopes at https://id.atlassian.com/manage-profile/security/api-tokens for the app Jira, with the scopes `read:jira-work` and `read:jira-user`.
2. On the Jira settings page in the admin panel, choose "Connect with API token" and enter the site URL (`https://<site>.atlassian.net`), the account's email address and the token.

Timatic reads Jira with the permissions of that account. The token expires on the date chosen when creating it; reconnect with a new token after that.
