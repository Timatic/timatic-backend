# Periods

Read this when you change how periods are derived, when an entry is counted in a period, or anything that shows remaining minutes or credit.

## Goal

A period is one renewal window of a budget: the hours of the active version are available in it and whatever is left at its end expires. Periods are computed from the budget every time they are needed, so **a budget and its periods can never disagree**, and a change to a start, end or archive date reshapes the periods immediately.

## Rules

- **A one-time budget has exactly one period**, from the budget's start to its end, or to the archive date when that is earlier. It is both the first and the last period. Test: `get periods from budget`.
- **A renewing budget has one period per frequency step** (`monthly` or `yearly`) from its start, each ending one second before the next starts. Periods are generated up to one frequency step past today, so a long-running budget does not produce years of future periods. Test: `the last period of a budget with a monthly renewal frequency should end when the budget ends`.
- **Monthly periods align to calendar months** (the default, behind the `align_periods_to_month_start` feature flag). A budget starting on the first starts every period on the first. A budget starting mid-month has a partial first period up to the end of that month and starts every later period on the first. Tests: `a budget starting on the first of the month starts each period on the first across the winter to summer DST transition`, `a mid-month budget has a partial first period and all subsequent periods start on the first of the month`.
- **Legacy alignment keeps the day of month.** With the flag off, a budget starting on the 15th starts every period on the 15th. Kept for tenants whose existing reporting depends on it. Test: `a mid-month budget with legacy period alignment keeps periods on the same day of month`.
- **Period boundaries are midnight in the preferred timezone**, across daylight-saving transitions. Guards budgets whose start is in summer time and whose later periods fall in winter time, or the reverse. Tests: `a budget starting on the first of the month in summertime starts each period on the first across DST transitions`, `a mid-month budget has a partial first period and subsequent periods start at 00:00 after the winter to summer DST transition`.
- **The last period ends with the budget.** It ends on the budget's end date, or on the archive date when the budget was archived. An archived budget has no periods after its archive date. Tests: `the last period of a budget with a monthly renewal frequency should end when the budget ends`, `periods should not be created after budget is archived`.
- **Start and end dates are always set.** Period derivation assumes both exist; the database and the API require them. Decided in issue #76.
- **An entry belongs to the period its start falls in.** The first period also counts entries that start before the budget, the last period also counts entries that start after it, so hours booked slightly outside the budget's dates still land on the budget. Deleted and internal entries count for nothing. Tests: `takes entries before the budget start date and after budget end date into account`, `doesnt count minutes spent on deleted entries`.
- **Remaining minutes are the initial minutes of the active version minus the spent minutes of the period.** Views clamp at zero; the usage calculation asks for the negative value to measure overrun. Remaining credit is the remaining hours times the hourly rate of the version active at the period's start.
- **A period can be observed as of a date.** Spent minutes then only count entries that started before that date, which is how the monthly usage report sees a period at the first and at the last day of a month. See [usage](usage.md).
- **Period figures are cached per budget** and flushed whenever the budget or an entry on it is saved.

## Where in the code

- `app/Models/Budget.php` (`periods`, `getPeriodAt`), `app/Models/Period.php` (`setAggregates`, `getRemainingMinutes`, `getRemainingCredit`, `travelTo`), `app/Listeners/InvalidatePeriodCache.php`, `config/timatic.php` (`feature.align_periods_to_month_start`, `preferred_timezone`)
- Tests: `tests/Unit/BudgetPeriodsTest.php`, `tests/Feature/Periods/GetFromBudgetTest.php`, `tests/Feature/Budgets/BudgetPeriodSpentMinutesTest.php`
