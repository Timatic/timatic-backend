# Time spent totals

Read this when you change the totals per week or month that the dashboards show, for a team, a user or a budget.

## Goal

Managers need, per unit of time (week, month), how many minutes were internal and how many were billable, and per budget how many minutes remained after each unit. The totals are computed from entries on request, so **they are always consistent with the entries** and need no bookkeeping of their own.

## Rules

- **Totals per unit split minutes into internal and billable.** Billable is paid-per-hour minutes plus budget minutes; the split follows the entry's settlement (see [entries](overview.md)). Filters narrow to one team or one user. Tests: `shows total time spent in weeks`, `serves time spent totals`.
- **An entry belongs to the unit its start falls in.** Deleted entries count for nothing. Test: `ignores deleted entries`.
- **Totals per budget walk from the budget's start to its end or today**, whichever is earlier, and report the remaining minutes after each unit: the active version's initial minutes minus the minutes spent so far. Test: `returns correct time spent totals for budget`.
- **Minutes here are minutes spent**, so overtime percentages are included, like everywhere budgets are consumed.

## Where in the code

- `app/Services/TimeSpentTotalsService.php`, `app/Models/UserCustomerHoursRecord.php` (a database view over entries with the settlement split), `app/Http/Controllers/Reports/GetTimeSpentTotalsController.php`, `app/Http/Controllers/Budgets/GetBudgetTimeSpentTotalsController.php`
- Tests: `tests/Integration/TimeSpentTotalsServiceTest.php`, `tests/Feature/Entries/TimeSpentTotalsEndpointTest.php`, `tests/Feature/Budgets/BudgetTimeSpentTotalsTest.php`
