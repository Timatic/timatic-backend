# Entries

Read this when you change how an entry is created, corrected or settled, or what an entry's budget and internal flag mean.

## Goal

An entry is the one record that money follows: it says who worked, for which customer and ticket, from when to when, and on what account. Everything financial (budget consumption, invoicing, reports) reads entries, so **an entry's settlement is always one of exactly three things**, and once a month is closed an entry stops changing.

## Settlement

| budget | internal | settlement |
| --- | --- | --- |
| set | false | consumes minutes spent from the budget's period |
| none | true | internal work, outside any customer budget, never invoiced |
| none | false | **paid per hour** at the customer's rate |

- **Paid per hour is a property of a saved entry only.** On an event, activity or suggestion a missing budget or internal flag means *unknown*; a suggestion fills them from later activities or from an earlier booking of the same sequence. See [suggestions](../suggestions/suggestions.md).
- **Making an entry internal drops its budget.** Test: `unsets the budget id when entry gets edited to internal`.
- **An entry on a budget carries no rate.** The budget's active version decides the rate; an entry without a budget stores the customer's rate at the time it was saved. Tests: `returns hourly rate with budget`, `returns hourly rate without budget`.

## Minutes spent

- **Minutes spent is the number budgets and reports consume**, set every time an entry is saved. Without overtime it equals the clock minutes between start and end.
- **Overtime raises it.** Customer overtime splits the entry into chunks, each with a percentage (an evening chunk at 135 percent, a night chunk at 150 percent); minutes spent is the sum of chunk minutes times their percentage plus the remaining clock minutes, rounded to whole minutes. The overtime rules themselves are out of scope here.
- **Changing an entry's time with overtime requires new overtime times.** A partial update that moves the start or end of an entry with personal overtime must also send the overtime start and end, so the overtime chunks stay inside the entry. Test: `does not delete overtime when patched`.

## Corrections

A correction cancels an entry whose month is closed or that must not be edited, without touching history.

- **A correction is a new locked entry with the minutes negated**, on the same user, customer, ticket and budget as the corrected entry, dated now. The corrected entry becomes locked too. Correcting a negative entry produces a positive one. Tests: `stores a correction and makes a correction entry`, `stores a correction and corrects a negative entry to positive`.
- **A correction may point at the entry that replaces it**, so a report can show the three together.
- **Corrections reverse budget usage in the bucket it came from.** See [budget usage](../budgets/usage.md).

## Who may change an entry

- **Nobody changes a locked entry.** An entry is locked when it was corrected or is itself a correction.
- **Nobody creates or changes an entry that starts before the locked date.** See [locked dates](locked-dates.md). Test: `denies to update an entry by the owner when 10 days have passed`.
- **The owner and the creator may change their entry; others need the permission to update entries of others.** Test: `denies to update an entry by someone other than the owner`.
- **An API token creates entries for others only with that permission.**

## Where in the code

- `app/Models/Entry.php`, `app/Policies/EntryPolicy.php`, `app/Services/EntryEnricher.php`, `app/Services/MinutesSpentCalculator.php`, `app/Services/CorrectEntryService.php`, `app/Listeners/AddEntryData.php` (rate and names)
- Tests: `tests/Feature/Entries/StoreTest.php`, `tests/Feature/Entries/UpdateTest.php`, `tests/Feature/Corrections/StoreTest.php`, `tests/Integration/Entry/HourlyRateTest.php`
