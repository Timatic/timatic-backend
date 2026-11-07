# Daily progress

Read this when you change the per-day completeness indicator that tells a user how much of a working day they have booked.

## Goal

An engineer should see at a glance whether a day is fully booked. Daily progress is the booked time of one user on one day as a percentage of an 8-hour working day, so a day under 100 percent invites the user to look at that day's [suggestions](../suggestions/overview.md).

## Rules

- **Progress uses clock time, not minutes spent.** Overtime percentages must not make a day look fuller than it was. No pinning test yet.

## Where in the code

- `app/Services/DailyProgressCalculator.php`, `app/Models/DailyProgress.php`, `app/Http/Controllers/Reports/GetDailyProgressController.php`
