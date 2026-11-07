# Locked dates

Read this when you change how far back a user may create or edit entries, or when finance closes a month.

## Goal

Hours are invoiced per month. Once finance has closed a month, no entry in it may move, and even inside the open month an engineer may only touch recent days, so forgotten hours get booked promptly instead of months later. The **locked date** is the single moment before which the current user may not create or change entries; every entry check compares against it.

## Rules

- **A default user edits up to 10 days back.** The locked date is today minus the configured number of days (`entries_locked_after_days`). Tests: `returns entry editable date for default users`, `denies to update an entry by the owner when 10 days have passed`.
- **A user with the permission to update entries from the previous month edits back to the first of the previous month.** Finance and backoffice fix last month's entries before invoicing. Tests: `returns entry editable date for users that can update entries from previous month`, `updates a month old entry when edited with proper permissions`.
- **The closed month wins.** The locked date never lies before the first day after the closed month, whatever the user's permission. Test: `denies to update a two month old entry when edited with proper permissions`.
- **A month closes on a day of the next month.** Before the closing day (the 6th by default) the previous month is still open and the month before it is closed; from the closing day on, the previous month is closed. Users with the previous-month permission get a later closing day (the 15th by default). Tests: `returns closed month date correctly when today is before month end closing day of month`, `returns closed month date correctly when today is after month end closing day of month`.
- **Clients read the locked date from the API** next to the user's team and permissions, so a frontend can grey out days before fetching anything. Tests: `returns a locked date permission of entries locked after days`, `returns a locked date permission of previous month before month end with permission to update from previous month`, `returns a locked date with extended closing date with permission to edit previous month`.
- **Days are counted in the preferred timezone.** Guards users working around midnight UTC.

## Where in the code

- `app/Services/EntryLockedDateService.php`, `app/Services/ClosedMonthDateService.php`, `app/Policies/EntryPolicy.php`, `config/timatic.php` (`entries_locked_after_days`, `month-end_closing_day_of_month`, `extended_closing_day_of_month`)
- Tests: `tests/Unit/Services/EntryLockedDateServiceTest.php`, `tests/Unit/Services/ClosedMonthDateServiceTest.php`, `tests/Feature/Entries/UpdateTest.php`, `tests/Feature/Permissions/MeTest.php` (locked date in the me response)
