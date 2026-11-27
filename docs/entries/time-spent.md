# Time spent totals

Read this when you change the totals per week or month that the dashboards show, for a team, a user or a budget.

## Goal

The team dashboard shows a chart of the time a team spent per week or month, split into internal and billable minutes, and per budget how many minutes remained after each unit. The endpoint exists for that chart. The totals come from a database view over entries that carries the settlement split, so a week or a team can be queried directly and **the totals are always consistent with the entries**.

## Rules

- **Billable is paid-per-hour plus budget minutes**; internal is the rest. The split follows the entry's [settlement](overview.md). Tests: `shows total time spent in weeks`, `serves time spent totals`.
- **Minutes here are minutes spent**, overtime included, like everywhere budgets are consumed. The daily progress indicator is the one place that uses clock time instead, see [daily progress](daily-progress.md).
- **Per-budget totals stop at today**, not at the budget's end date, and report the remaining minutes after each unit. Test: `returns correct time spent totals for budget`.

## Where in the code

- `app/Services/TimeSpentTotalsService.php`, `app/Models/UserCustomerHoursRecord.php` (a database view over entries with the settlement split), `app/Http/Controllers/Reports/GetTimeSpentTotalsController.php`, `app/Http/Controllers/Budgets/GetBudgetTimeSpentTotalsController.php`
- Tests: `tests/Integration/TimeSpentTotalsServiceTest.php`, `tests/Feature/Entries/TimeSpentTotalsEndpointTest.php`, `tests/Feature/Budgets/BudgetTimeSpentTotalsTest.php`
