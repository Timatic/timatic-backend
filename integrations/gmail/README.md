Create a Google Auth Platform client, or reuse the one of the Google Calendar integration.
https://console.cloud.google.com/auth/clients

Enable the Gmail API
https://console.cloud.google.com/apis/library/gmail.googleapis.com

Add the scope https://www.googleapis.com/auth/gmail.metadata under Data Access.
https://console.cloud.google.com/auth/scopes

This scope is restricted: set the audience to Internal, or external users see an unverified-app warning until Google has verified the app.

Add https://<api-domain>/gmail/callback to the authorised redirect URIs of the client.

Set GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET and GMAIL_REDIRECT_URI.
