# Periods

Read this when you change how periods are derived, when an entry is counted in a period, or anything that shows remaining minutes or credit.

## Goal

A period is one renewal window of a budget: the hours of the active version are available in it and whatever is left at its end expires. Periods are computed from the budget every time they are needed, so **a budget and its periods can never disagree**, and a change to a start, end or archive date reshapes the periods immediately. A one-time budget is the degenerate case with a single period from start to end.

## Rules

- **Periods stop one renewal step past today.** A long-running budget never produces years of future periods, so "the last period" means the one running now or next, not the one at the end date. Test: `the last period of a budget with a monthly renewal frequency should end when the budget ends`.
- **Monthly periods align to calendar months by default**, with a partial first period for a budget starting mid-month. Legacy alignment (the `align_periods_to_month_start` flag off) keeps the start's day of month for every period, for tenants whose reporting grew around it. Both modes are supported on purpose. Tests: `a mid-month budget has a partial first period and all subsequent periods start on the first of the month`, `a mid-month budget with legacy period alignment keeps periods on the same day of month`.
- **Boundaries are midnight in the preferred timezone**, across daylight-saving transitions, so a period never starts at 23:00 or 01:00 after a clock change. Tests: `a budget starting on the first of the month in summertime starts each period on the first across DST transitions`, `a mid-month budget has a partial first period and subsequent periods start at 00:00 after the winter to summer DST transition`.
- **The first period also counts entries before the budget's start, the last period also counts entries after its end.** Hours booked slightly outside the budget's dates still land on the budget instead of vanishing. Internal and deleted entries count for nothing. Tests: `takes entries before the budget start date and after budget end date into account`, `doesnt count minutes spent on deleted entries`.
- **Remaining minutes are clamped at zero for display and negative for accounting.** The usage report asks for the negative value to measure overrun; everything a user sees stops at zero.
- **A period can be observed as of a date**: spent minutes then only count entries that started before that date. This is how the monthly [usage](usage.md) report sees one period at the first and at the last day of a month.

## Where in the code

- `app/Models/Budget.php` (`periods`, `getPeriodAt`), `app/Models/Period.php` (`setAggregates`, `getRemainingMinutes`, `getRemainingCredit`, `travelTo`), `app/Listeners/InvalidatePeriodCache.php`, `config/timatic.php` (`feature.align_periods_to_month_start`, `preferred_timezone`)
- Tests: `tests/Unit/BudgetPeriodsTest.php`, `tests/Feature/Periods/GetFromBudgetTest.php`, `tests/Feature/Budgets/BudgetPeriodSpentMinutesTest.php`
