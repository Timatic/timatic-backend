# Budget notifications

Read this when you change who gets mailed about a budget, when, or what the mail says.

## Goal

The account manager of the customer and the supervisor of the budget must hear about a budget running out before the customer does, and must get a monthly picture of every budget they are responsible for. Two mails serve that: a **usage warning** the moment an entry pushes a period over a threshold, and a **monthly balance overview**.

## Usage warning

Sent when an entry's minutes spent are set and the period the entry falls in crosses a threshold.

- **Thresholds depend on the budget kind.** A one-time budget warns at 70, 90 and 100 percent used. A renewing budget warns at 100 percent only, because its hours come back next period. Tests: `mail for budget usage of one time budget is sent on 70 procent used`, `mail for budget usage of recurring budget is not sent on 70 procent used`, `mail for budget usage of recurring budget is sent on 100 procent used`.
- **Each threshold fires once per crossing.** The mail goes out when the usage before the entry was below a threshold and the usage after it is at or above it. Staying above a threshold sends nothing; an entry over 100 percent on a budget already over 100 sends nothing. Tests: `do not send mail when threshold stay the same`, `mail for budget usage of one time budget is sent on 110 procent used`.
- **Editing a budget can trigger a warning.** Lowering the hours of a version makes the next entry cross a threshold it would otherwise not have reached. Test: `send email when threshold met after editing budget`.
- **A version with zero minutes never warns.** Guards division by zero on placeholder budgets.
- **Recipients are the customer's account manager and the budget's supervisor.** A customer without an account manager falls back to the configured account-management address. Tests: `mail is sent to account manager and supervisor mail addresses`, `mail is sent to default mail address for unknown account manager`.
- **The mail links to the budget in the frontend.** Test: `mail for budget usage has action url`.

## Monthly balance overview

Sent on the first of each month at 08:00 by a scheduled command, one mail per account manager with the budgets of their customers, and one per supervisor with the budgets they supervise. Archived budgets are left out.

- **Each budget shows the remaining hours of its current period and the percentage used**, flagged as a warning above 80 percent for one-time budgets and above 100 percent for renewing ones, plus whether the budget has expired or expires within a month. Test: `send email`.
- **A missing user fails the command without stopping it.** The remaining mails still go out and the command reports failure. Test: `run command successfully`.

## Where in the code

- `app/Listeners/BudgetUsageWarning.php`, `app/Mail/BudgetUsageWarning.php`, `app/Console/Commands/SendBudgetBalanceOverviewCommand.php`, `app/Mail/BudgetMonthlyBalance.php`, `routes/console.php` (schedule), `config/timatic.php` (`account_management_mail_address`)
- Tests: `tests/Feature/Budgets/BudgetUsageWarningTest.php`, `tests/Feature/Reports/BudgetBalanceOverviewTest.php`
