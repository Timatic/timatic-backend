# Budget notifications

Read this when you change who gets mailed about a budget, when, or what the mail says.

## Goal

The account manager of the customer and the supervisor of the budget must hear about a budget running out before the customer does, and must get a monthly picture of every budget they are responsible for. Two mails serve that: a **usage warning** the moment an entry pushes a period over a threshold, and a **monthly balance overview** on the first of each month. A warning can also follow a budget edit, because lowering a version's hours changes the percentage of entries already booked.

## Rules

- **One-time budgets warn at 70, 90 and 100 percent; renewing budgets only at 100.** Renewing hours come back next period, so an early warning would only be noise. Tests: `mail for budget usage of one time budget is sent on 70 procent used`, `mail for budget usage of recurring budget is not sent on 70 procent used`, `mail for budget usage of recurring budget is sent on 100 procent used`.
- **A warning is never lost for want of a recipient.** A customer without an account manager falls back to the configured account-management address. Test: `mail is sent to default mail address for unknown account manager`.
- **The monthly overview flags a budget above 80 percent (one-time) or above 100 percent (renewing).** These differ from the warning thresholds on purpose: the overview is a heads-up, the warning an alarm. Test: `send email`.
- **A missing user never stops the monthly run.** The remaining mails go out and the command reports failure afterwards. Test: `run command successfully`.

## Where in the code

- `app/Listeners/BudgetUsageWarning.php`, `app/Mail/BudgetUsageWarning.php`, `app/Console/Commands/SendBudgetBalanceOverviewCommand.php`, `app/Mail/BudgetMonthlyBalance.php`, `routes/console.php` (schedule), `config/timatic.php` (`account_management_mail_address`)
- Tests: `tests/Feature/Budgets/BudgetUsageWarningTest.php`, `tests/Feature/Reports/BudgetBalanceOverviewTest.php`
