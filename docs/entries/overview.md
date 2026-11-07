# Entries

Read this when you change how an entry is created, corrected or settled, or what an entry's budget and internal flag mean.

## Goal

An entry is the one record that money follows: it says who worked, for which customer and ticket, from when to when, and on what account. Everything financial (budget consumption, invoicing, reports) reads entries, so **an entry's settlement is always exactly one of three things**, and once a month is closed an entry stops changing.

## Settlement

| budget | internal | settlement |
| --- | --- | --- |
| set | false | consumes minutes spent from the budget's period |
| none | true | internal work, outside any customer budget, never invoiced |
| none | false | **paid per hour** at the customer's rate |

- **Paid per hour exists on a saved entry only.** On an event, activity or suggestion a missing budget or internal flag means *unknown*; the engineer decides the settlement when accepting. See [suggestions](../suggestions/suggestions.md).
- **Internal and budget are exclusive**: making an entry internal drops its budget. Test: `unsets the budget id when entry gets edited to internal`.
- **A rate is snapshotted only on entries without a budget.** An entry on a budget takes the rate of the version active at its date, so a later version change reprices it; a paid-per-hour entry keeps the customer's rate at the time it was saved. Tests: `returns hourly rate with budget`, `returns hourly rate without budget`.

## Minutes spent

- **Minutes spent, not clock time, is what budgets and reports consume.** Overtime raises it: each overtime chunk counts at its percentage (an evening chunk at 135 percent, a night chunk at 150), the rest at 100, rounded to whole minutes. Overtime rules themselves are out of scope here.
- **Moving an entry with overtime requires new overtime times in the same request**, so the chunks stay inside the entry. Test: `does not delete overtime when patched`.

## Corrections

- **A correction is a new locked entry with the minutes negated**, same user, customer, ticket and budget, dated now, and the corrected entry becomes locked too. History stays intact and the pair sums to zero; the budget sees the reversal in the month of the correction. Tests: `stores a correction and makes a correction entry`, `stores a correction and corrects a negative entry to positive`.

## Who may change an entry

- **A locked entry never changes**, for anyone. Entries lock when corrected or when they are a correction.
- **Nothing is created or changed before the locked date.** See [locked dates](locked-dates.md).

## Where in the code

- `app/Models/Entry.php`, `app/Policies/EntryPolicy.php`, `app/Services/EntryEnricher.php`, `app/Services/MinutesSpentCalculator.php`, `app/Services/CorrectEntryService.php`, `app/Listeners/AddEntryData.php` (rate and names)
- Tests: `tests/Feature/Entries/StoreTest.php`, `tests/Feature/Entries/UpdateTest.php`, `tests/Feature/Corrections/StoreTest.php`, `tests/Integration/Entry/HourlyRateTest.php`
