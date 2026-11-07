# Locked dates

Read this when you change how far back a user may create or edit entries, or when finance closes a month.

## Goal

Hours are invoiced per month. Once finance has closed a month, no entry in it may move, and even inside the open month an engineer may only touch recent days, so forgotten hours get booked promptly instead of months later. The **locked date** is the single moment before which the current user may not create or change entries; every entry check compares against it.

## Rules

- **A default user edits 10 days back; a user with the previous-month permission edits back to the first of the previous month.** The short window is deliberate: it forces prompt booking. The permission exists for finance and backoffice fixing last month before invoicing. Tests: `returns entry editable date for default users`, `returns entry editable date for users that can update entries from previous month`.
- **The closed month always wins.** No permission reaches into a closed month. Test: `denies to update a two month old entry when edited with proper permissions`.
- **A month closes on a day of the next month, later for privileged users.** Before the closing day (the 6th by default) the previous month is still open; from that day on it is closed. Users with the previous-month permission get the extended closing day (the 15th by default), so they can finish corrections after everyone else is locked out. Tests: `returns closed month date correctly when today is before month end closing day of month`, `returns closed month date correctly when today is after month end closing day of month`.

## Where in the code

- `app/Services/EntryLockedDateService.php`, `app/Services/ClosedMonthDateService.php`, `app/Policies/EntryPolicy.php`, `config/timatic.php` (`entries_locked_after_days`, `month-end_closing_day_of_month`, `extended_closing_day_of_month`)
- Tests: `tests/Unit/Services/EntryLockedDateServiceTest.php`, `tests/Unit/Services/ClosedMonthDateServiceTest.php`, `tests/Feature/Entries/UpdateTest.php`, `tests/Feature/Permissions/MeTest.php` (locked date in the me response)
