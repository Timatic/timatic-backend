# Suggestions

Read this when you change how activities become suggestions, how a suggestion fills in its customer, budget or ticket, or what accepting and dismissing do.

## Goal

A suggestion is a proposed entry for one user on one day: one customer, and a ticket, budget and internal flag that are either known or still unknown. Activities of the same customer chain into one suggestion when nothing contradicts, so an engineer sees "2h on TIC-1 for X" instead of five fragments. What must stay true: **a suggestion never combines contradicting facts**, and **a dismissed suggestion stays dismissed** across rebuilds.

## Rules

### Chaining activities

- **Activities of one customer chain in chronological order** onto the current suggestion while they are compatible; the first incompatible one starts a new suggestion. Activities without a customer each get a suggestion of their own and chain with nothing. Tests: `activities sharing customer, budget, ticket and is_internal bundle into one suggestion`, `customerless activities never bundle with each other`.
- **Compatible means equal or unknown**, for ticket and budget. An activity without a ticket chains onto a ticketed one, a budgetless one onto a budgeted one, and the suggestion keeps the known value. Two different known tickets, or two different known budgets, never chain. Tests: `a null-ticket activity chains onto a ticketed one of the same customer on the same day`, `a budgetless activity chains onto a budgeted activity of the same ticket, and the suggestion keeps the budget`, `a ticketed activity and a budgeted activity chain into a suggestion with both ticket and budget`, `activities on different budgets get separate suggestions`, `activities with conflicting budgets do not chain even without ticket numbers`.
- **Internal and external never chain.** Test: `internal and external activities on the same ticket get separate suggestions`.
- **An unknown value becomes known from a later activity, never overwritten.** The suggestion takes ticket id and type, budget and internal flag from the first activity that knows them. A ticketless activity cannot bridge two differently-ticketed ones, because the suggestion already knows its ticket by then. Tests: `a suggestion takes the ticket id and type from a later activity on the same ticket`, `a ticketless activity does not bridge two differently-ticketed activities`, `a ticketless activity bundles with the nearest activity of the same customer`.
- **Parts of one event end up in one suggestion.** An event split into several activities (see [activities](activities.md)) is recognised by the shared event and its parts merge, even when they were customerless. Test: `customerless parts of one split event bundle into one suggestion`.
- **Minutes spent counts overlapping activities once.** Test: `overlapping activities are counted once in the minutes spent`.

### Learning from an earlier booking

- **A suggestion without a customer takes customer, budget and internal flag from the latest entry booked on an earlier occurrence of the same sequence**, for example last week's instance of a recurring meeting. Stated assumption: a sequence belongs to one customer. A recurring meeting that alternates customers has not been seen, so the latest booking of the sequence is the right teacher for every occurrence, including a past day rebuilt after a later booking. Tests: `a customerless activity takes customer and budget from the previous entry of its event sequence`, `a customerless event takes customer and budget from the entry booked on the previous event of its sequence`.
- **A suggestion that knows its customer ignores the sequence.** Test: `an activity with a customer of its own ignores the previous entry of its event sequence`.

### Dismissing and accepting

- **Dismissing soft-deletes the suggestion**, and its key (customer, budget, ticket, internal flag) suppresses any suggestion with the same key on that user-day in every later rebuild. The activities are still built and saved, so the time stays visible. A suggestion with a different ticket is not suppressed. Tests: `no suggestion is projected when a dismissed suggestion matches the group key`, `a dismissed suggestion with a different ticket does not suppress the group`, `a dismissed suggestion suppresses its group but the activity is still saved`, `a dismissed suggestion matching the customer taken from the event sequence stays dismissed`.
- **Accepting creates an entry that points at the suggestion.** An entry-backed suggestion is final: a rebuild leaves it alone and its entry claims its minutes from every later projection. Test: `an entry blocks its period and entry-backed suggestions survive the rebuild`.
- **Unknown is unknown.** A suggestion with no budget and no internal flag is not a paid-per-hour proposal; the engineer decides the settlement when accepting. See [entries](../entries/overview.md).

## Where in the code

- `app/Services/SuggestionProjector.php`, `app/Models/EntrySuggestion.php` (`addActivity`, `minutesSpent`), `app/Queries/PreviousBookingsOfSequences.php`, `app/Http/Controllers/Suggestions/EntrySuggestionController.php`
- Tests: `tests/Unit/Services/SuggestionProjectorTest.php`, `tests/Unit/Models/EntrySuggestionTest.php`, `tests/Integration/Activity/RebuildUserDayTest.php`, `tests/Feature/EntrySuggestions/IndexTest.php`
