# Daily progress

Read this when you change the per-day completeness indicator that tells a user how much of a working day they have booked.

## Goal

An engineer should see at a glance whether a day is fully booked. Daily progress is the booked time of one user on one day as a percentage of a working day, so a day under 100 percent invites the user to look at that day's [suggestions](../suggestions/overview.md).

## Rules

- **Progress is booked hours divided by the working day, as a whole percentage.** The working day is 8 hours. A day with more than 8 hours booked goes above 100.
- **Booked time is the clock time of the user's entries starting on that day**, in the preferred timezone, not minutes spent: overtime percentages do not make a day look fuller.
- **The report serves a single day or a date range**, one progress value per day. The endpoint is for signed-in users only.
- No pinning test yet.

## Where in the code

- `app/Services/DailyProgressCalculator.php`, `app/Models/DailyProgress.php`, `app/Http/Controllers/Reports/GetDailyProgressController.php`
