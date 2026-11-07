# Budgets

Read this when you touch anything that creates, changes, archives or reads a budget, or that books hours on one.

## Goal

A customer buys a pot of hours. Engineers book entries on that pot, and the business needs to know at any moment, and for any past month, how much was bought, used, overrun and left. Two things must stay true:

- **History never changes.** A price or hours change applies from a date forward; every earlier month still reports what was true then.
- **Nothing about a period is stored.** Periods are derived from the budget's start, end, renewal frequency and archive date, so they can never disagree with those fields.

```
Customer
  └── Budget            immutable: type, customer, renewal frequency, start
        └── Version     mutable: title, description, total price, initial minutes; valid from/to
              └── Period   computed renewal window; carries spent minutes and remaining credit
                    └── Entry   consumes minutes spent from the period it starts in
```

## Rules

- **Four fields are immutable** once a budget exists: budget type, customer, renewal frequency and start date. Updates that send them are ignored rather than rejected. Guards the history of every period already derived. Test: `does not update budget fields`.
- **The budget type decides the shape.** It lists the renewal frequencies a budget of that type may have and whether a start and end date, supervisor, contract id, change ticket, total price and a ticket on every entry are required. Test: `has renewal frequency returns value based on renewal frequencies`.
- **Every change to the mutable fields creates a version**, never an update in place. An update that posts none of the mutable fields creates no version. See [versions](versions.md). Test: `does not create a budget version if none of its fields are posted`.
- **The hourly rate is derived**: total price of the active version divided by its initial minutes as hours. A version with zero minutes has a rate of zero. An entry on a budget carries no rate of its own; an entry without a budget takes the customer's rate. Tests: `returns hourly rate with budget`, `returns hourly rate without budget`.
- **Archiving ends a budget early.** The archive date closes the last period and no later period exists, even when the end date lies further ahead. Tests: `it can archive budgets and set archived at`, `periods should not be created after budget is archived`.
- **A budget may list the users allowed to book on it.** The list is stored and returned with the budget; the clients use it to offer the budget only to those users. Test: `allows a user to be assigned when creating a budget`.
- **A budget may be visible to the customer.** The flag is a filter for client-facing views and changes nothing else. Test: `returns budgets filtered on show to customer`.
- **Budgets are reachable by the customer's external id**, for integrations that only know their own customer id. Test: `returns budgets filtered on customer by external id`.

## Where in the code

- `app/Models/Budget.php` (immutable fields, hourly rate, period generation), `app/Models/BudgetType.php`
- Tests: `tests/Feature/Budgets/StoreTest.php`, `tests/Feature/Budgets/IndexTest.php`, `tests/Feature/Budgets/AssignUsersToBudgetsTest.php`, `tests/Feature/BudgetTypes/RenewalFrequenciesTest.php`, `tests/Integration/Entry/HourlyRateTest.php`, `tests/Feature/Periods/GetFromBudgetTest.php`
