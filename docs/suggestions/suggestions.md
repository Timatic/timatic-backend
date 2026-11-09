# Suggestions

Read this when you change how activities become suggestions, how a suggestion fills in its customer, budget or ticket, or what accepting and dismissing do.

## Goal

A suggestion is a proposed entry for one user on one day: one customer, and a ticket, budget and internal flag that are either known or still unknown. Activities of the same customer chain into one suggestion when nothing contradicts, so an engineer sees "2h on TIC-1 for X" instead of five fragments. What must stay true: **a suggestion never combines contradicting facts**, and **a dismissed suggestion stays dismissed** across rebuilds.

## Rules

### Chaining activities

- **Compatible means equal or unknown.** Activities of one customer chain in chronological order while ticket, budget and internal flag are each equal or unknown on one side; the first contradiction starts a new suggestion. Customerless activities chain with nothing. Tests: `a null-ticket activity chains onto a ticketed one of the same customer on the same day`, `activities with conflicting budgets do not chain even without ticket numbers`, `internal and external activities on the same ticket get separate suggestions`, `customerless activities never bundle with each other`.
- **Unknown becomes known from a later activity and is never overwritten.** Because of that, a ticketless activity cannot bridge two differently-ticketed ones: by the second ticket the suggestion already knows its own. Tests: `a suggestion takes the ticket id and type from a later activity on the same ticket`, `a ticketless activity does not bridge two differently-ticketed activities`.
- **Parts of one event end up in one suggestion**, recognised by the shared event, even when they were customerless. An event split by stronger evidence (see [activities](activities.md)) must not show up as three proposals. Test: `customerless parts of one split event bundle into one suggestion`.
- **Minutes spent counts overlapping activities once.** Test: `overlapping activities are counted once in the minutes spent`.

### Learning from an earlier booking

- **A suggestion without a customer takes customer, budget and internal flag from the latest entry booked on any occurrence of the same sequence**, for example last week's instance of a recurring meeting. Stated assumption: a sequence belongs to one customer. A recurring meeting that alternates customers has not been seen, so the latest booking is the right teacher for every occurrence, including a past day rebuilt after a later booking. A suggestion that knows its customer ignores the sequence. Tests: `a customerless activity takes customer and budget from the previous entry of its event sequence`, `an activity with a customer of its own ignores the previous entry of its event sequence`.

### Dismissing and accepting

- **Dismissing suppresses the key, not the time.** The dismissed suggestion's customer, budget, ticket and internal flag form a key; every later rebuild of that user-day projects no suggestion with that key, while the activities are still built and saved, so the time stays visible. A suggestion with a different ticket is a different key. Tests: `no suggestion is projected when a dismissed suggestion matches the group key`, `a dismissed suggestion with a different ticket does not suppress the group`, `a dismissed suggestion suppresses its group but the activity is still saved`.
- **Accepting makes the suggestion final.** The entry points at it, a rebuild leaves it alone, and the entry claims its minutes from every later projection. Test: `an entry blocks its period and entry-backed suggestions survive the rebuild`.
- **Unknown is unknown.** A suggestion with no budget and no internal flag is not a paid-per-hour proposal; the engineer decides the settlement when accepting. See [entries](../entries/overview.md).

## Where in the code

- `app/Services/SuggestionProjector.php`, `app/Models/EntrySuggestion.php` (`addActivity`, `minutesSpent`), `app/Queries/PreviousBookingsOfSequences.php`, `app/Http/Controllers/Suggestions/EntrySuggestionController.php`
- Tests: `tests/Unit/Services/SuggestionProjectorTest.php`, `tests/Unit/Models/EntrySuggestionTest.php`, `tests/Integration/Activity/RebuildUserDayTest.php`, `tests/Feature/EntrySuggestions/IndexTest.php`
