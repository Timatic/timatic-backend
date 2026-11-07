# Suggestion lifecycle

Read this when you change when a day is rebuilt, how long raw signals are kept, or how users are reminded.

## Goal

Activities and open suggestions are derived state. They are rebuilt per **user-day** whenever that day's evidence changes, so a late-arriving event or a changed rule never leaves a stale picture, while the engineer's decisions (entries, dismissals) are the only state that persists.

## Rules

### Rebuilding a user-day

- **Creating an event rebuilds the day it ends on, and the day it starts on when that differs.** The rebuild runs as a queued job that is unique per user and day, so a burst of events causes one rebuild. Tests: `creating an event dispatches a rebuild for its day`, `an event spanning midnight dispatches a rebuild for both days`, `the unique id combines user and date`.
- **A rebuild takes the events ending on that day** (in the preferred timezone), the user's entries overlapping it, and the dismissed suggestions of that day, and projects activities and suggestions from them. See [activities](activities.md) and [suggestions](suggestions.md). Test: `rebuilding a day projects activities and suggestions from its events`.
- **A rebuild replaces the day's activities and open suggestions and keeps everything with an entry.** Open suggestions of the day are removed for good and rebuilt; a suggestion with an entry survives untouched. Tests: `rebuilding replaces the previous activities and force-deletes open suggestions of the day`, `an entry blocks its period and entry-backed suggestions survive the rebuild`.
- **Rebundling rebuilds a date range for every user with events in it**, or for one user, synchronously. Run it after a rule change, so existing suggestions follow the new rules (`php artisan timatic:rebundle-suggestions`, see the README). Tests: `rebundling rebuilds the user-days of open suggestions from their events`, `rebundling respects the user filter`.

### Retention

- **Events and activities older than 30 days are deleted** by the nightly prune at 03:00. They are raw observation of a person's day and are only needed until the day is booked. Suggestions are kept: an accepted one is part of its entry's history and a dismissed one must keep suppressing. Tests: `deletes events that are older than the retention period`, `deletes activities that are older than the retention period`.
- **After pruning, a rebuild of an old day finds no events** and so produces no activities and no open suggestions; rebundle ranges should stay within the retention window.

### Reminding users

- **Every Monday at 10:00 each user with unused suggestions of the previous week gets one mail.** A suggestion counts as unused while it is open and no entry exists for the same user, ticket and day. With ticketed suggestions the mail lists hours per customer and ticket with links into the frontend; with only ticketless ones it gives a count and one link. Tests: `reminds a user of 2 unused suggestions last week`, `reminds a user of 2 unused suggestions without tickets`, `does not remind a user that has no suggestions last week`, `does not remind about suggestions when there are manual entries for the same ticket`.

### Which sources a user can connect

- **The suggestion integrations endpoint lists every source a user can connect or has connected**: company-managed ones (an admin sets them up once) appear once enabled, with their setup state; personal ones (Google Calendar, the browser extension) offer a connect link and show as active once the user connected them. Tests: `lists a company managed integration as active once an admin set it up`, `offers a signed link to connect google calendar`, `shows the chrome extension as active once the user connected it`.

## Where in the code

- `app/Listeners/DispatchActivityRebuild.php`, `app/Jobs/RebuildUserDay.php`, `app/Console/Commands/RebundleSuggestionsCommand.php`, `app/Models/Event.php` and `app/Models/Activity.php` (`RETENTION_DAYS`, `prunable`), `app/Jobs/RemindUsersOfUnusedSuggestions.php`, `app/Queries/UnusedSuggestionsPerWeek.php`, `app/Integrations/Suggestions/`, `routes/console.php` (schedule)
- Tests: `tests/Integration/Activity/DispatchActivityRebuildTest.php`, `tests/Integration/Activity/RebuildUserDayTest.php`, `tests/Integration/RebundleSuggestionsCommandTest.php`, `tests/Feature/Privacy/PruneRawActivityTest.php`, `tests/Integration/RemindUsersOfUnusedSuggestionsTest.php`, `tests/Feature/SuggestionIntegrations/IndexTest.php`
