# Suggestion lifecycle

Read this when you change when a day is rebuilt, how long raw signals are kept, or how users are reminded.

## Goal

Activities and open suggestions are derived state. They are rebuilt per **user-day** whenever that day's evidence changes, so a late-arriving event or a changed rule never leaves a stale picture, while the user's decisions (entries, dismissals) are the only state that persists.

## Rules

- **A rebuild replaces everything open and keeps everything with an entry.** Open suggestions are removed for good and rebuilt; a suggestion with an entry survives untouched. Tests: `rebuilding replaces the previous activities and force-deletes open suggestions of the day`, `an entry blocks its period and entry-backed suggestions survive the rebuild`.
- **Events and activities live 30 days; suggestions live forever.** Events and activities are raw observation of a person's day and are pruned nightly once the day is booked. An accepted suggestion is part of its entry's history and a dismissed one must keep suppressing. A rebuild of a pruned day therefore finds no events and produces nothing. Tests: `deletes events that are older than the retention period`, `deletes activities that are older than the retention period`.
- **Unused suggestions get one gentle reminder a week.** A suggestion is unused while it is open and no entry exists for the same user, ticket and day. On Monday a user with unused suggestions from the previous week gets one mail saying that time can still be recorded with little effort. It is not a warning about untracked hours: Timatic reduces the strain of recording time rather than adding noise. An entry booked by hand for the ticket silences the reminder. Tests: `reminds a user of 2 unused suggestions last week`, `does not remind about suggestions when there are manual entries for the same ticket`.

## Where in the code

- `app/Listeners/DispatchActivityRebuild.php`, `app/Jobs/RebuildUserDay.php` (a queued job unique per user and day), `app/Console/Commands/RebundleSuggestionsCommand.php` (rebuilds a date range, for testing rule changes locally), `app/Models/Event.php` and `app/Models/Activity.php` (`RETENTION_DAYS`, `prunable`), `app/Jobs/RemindUsersOfUnusedSuggestions.php`, `app/Queries/UnusedSuggestionsPerWeek.php`, `app/Integrations/Suggestions/`, `routes/console.php` (schedule)
- Tests: `tests/Integration/Activity/DispatchActivityRebuildTest.php`, `tests/Integration/Activity/RebuildUserDayTest.php`, `tests/Integration/RebundleSuggestionsCommandTest.php`, `tests/Feature/Privacy/PruneRawActivityTest.php`, `tests/Integration/RemindUsersOfUnusedSuggestionsTest.php`, `tests/Feature/SuggestionIntegrations/IndexTest.php`
