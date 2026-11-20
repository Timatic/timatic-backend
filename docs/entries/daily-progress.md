# Daily progress

Read this when you change the per-day completeness indicator that tells a user how much of a working day they have booked.

## Goal

Daily progress tells a user whether the working hours of a day are recorded. It is the booked clock time of one user on one day as a percentage of an 8-hour working day. Overlapping entries count once, because the question is how much of the working day still needs an entry, not how many hours were charged. A day under 100 percent invites the user to look at that day's [suggestions](../suggestions/overview.md).

## Rules

- **Progress uses clock time, not minutes spent.** Overtime percentages must not make a day look fuller than it was. Test: `measures progress in clock time, not minutes spent`.

## Where in the code

- `app/Services/DailyProgressCalculator.php`, `app/Models/DailyProgress.php`, `app/Http/Controllers/Reports/GetDailyProgressController.php`
- Tests: `tests/Feature/Reports/DailyProgressTest.php`
