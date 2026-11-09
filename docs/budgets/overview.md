# Budgets

Read this when you touch anything that creates, changes, archives or reads a budget, or that books hours on one.

## Goal

A customer buys a pot of hours. Engineers book entries on that pot, and the business needs to know at any moment, and for any past month, how much was bought, used, overrun and left. Two things must stay true:

- **History never changes.** A price or hours change applies from a date forward; every earlier month still reports what was true then. That is why the mutable fields live in [versions](versions.md).
- **Nothing about a period is stored.** [Periods](periods.md) are derived from the budget's start, end, renewal frequency and archive date, so they can never disagree with those fields.

```
Customer
  └── Budget            immutable: type, customer, renewal frequency, start
        └── Version     mutable: title, description, total price, initial minutes; valid from/to
              └── Period   computed renewal window; carries spent minutes and remaining credit
                    └── Entry   consumes minutes spent from the period it starts in
```

The budget type is the template: it lists the renewal frequencies a budget may have and which fields and rules apply (end date, supervisor, contract id, total price, a ticket on every entry).

## Rules

- **The four immutable fields are silently ignored on update**, not rejected: budget type, customer, renewal frequency and start date. A client that sends them gets a success response and an unchanged budget. Test: `does not update budget fields`.
- **The hourly rate is derived, never entered**: total price of the active version divided by its initial minutes as hours, so a change of price or hours changes the rate from that version on. An entry on a budget carries no rate of its own; an entry without a budget snapshots the customer's rate when saved. Tests: `returns hourly rate with budget`, `returns hourly rate without budget`.
- **Archiving ends a budget early.** The archive date closes the last period, whatever the end date says, so credit stops expiring and renewing from that day. Tests: `it can archive budgets and set archived at`, `periods should not be created after budget is archived`.
- **An empty allowed-users list means everyone.** Only a non-empty list restricts who may create entries on the budget. Entries before the budget's start are refused for everyone. Tests: `stores an entry if the budget has no allowed users`, `does not store an entry if the current user is not an allowed user on the budget`, `does not store an entry when the budget has not started`.

## Where in the code

- `app/Models/Budget.php` (immutable fields, hourly rate, period generation), `app/Models/BudgetType.php`, `app/Policies/EntryPolicy.php` (allowed users)
- Tests: `tests/Feature/Budgets/StoreTest.php`, `tests/Feature/Budgets/IndexTest.php`, `tests/Feature/Budgets/AssignUsersToBudgetsTest.php`, `tests/Feature/Entries/StoreTest.php`, `tests/Integration/Entry/HourlyRateTest.php`, `tests/Feature/Periods/GetFromBudgetTest.php`
