# Activities

Read this when you change how a day's events are turned into activities.

## Goal

An activity is one stretch of work on one customer, and on one ticket when known, that the day's events support, whatever their sources and event types. Reviewing a pull request in the browser, replying in a comment thread and committing a fix are one activity when they belong to one ticket and run on without a gap. The event type and source on an activity are the label of its strongest event, not a boundary; the full list of sources and types lives on its events. Building them answers one question per minute of the day: *which customer and ticket was the user most likely working on?* What must stay true: **a minute belongs to at most one activity or booked entry**, and **the strongest evidence decides who gets it**. The result depends only on the set of events, not on the order they arrived in. Test: `shuffled input produces the same activities as chronological input`.

## Rules

Events are processed in ascending weight, then by start time. Each event either joins an existing activity or starts a new one, and a new activity only gets the minutes nothing stronger has claimed.

- **Booked entries claim first, then stronger activities.** A new activity is cut to what the user's entries and the earlier (lower-weight) activities left; a stronger span inside it splits it in two; one with nothing left is not created. Tests: `an entry period inside an activity splits it into two activities`, `an activity fully inside entry periods is not created`, `a high-weight event inside a low-weight event splits the low-weight into two activities`.
- **On equal weight the earlier start wins, and point events each keep a slice.** Three commits five minutes apart become three short activities, not one, so later chaining can still tell them apart. Tests: `on equal weight the earlier-starting group is dominant`, `fast sequential point events of equal weight each get their own time slice`.
- **Same ticket within 15 minutes joins, across sources and event types.** The gap is the pipeline's estimate of "still the same stretch of work"; the type of evidence does not split a stretch, only a gap or another ticket does. Issue #41 made this explicit for review events: a pull request comment, an approval and a change request on one ticket were three activities and are now one. Tests: `same-ticket events within the chain gap merge into one activity across sources`, `same-ticket events of different types within the chain gap merge into one activity`, `same-ticket events further apart than the chain gap become separate activities`.
- **A ticketless event with a customer glues onto the nearest activity of that customer**, whatever that activity's event type, the preceding one winning a tie. One without a neighbour yet is held back and placed after every other event, so a following activity can still claim it. Tests: `a null-ticket event glues onto the preceding group of the same customer even with another event type`, `a null-ticket event without preceding group is claimed by a following group of the same customer`.
- **A customerless event stands alone.** There is nothing to match it on, so it joins nothing and becomes its own activity. Test: `events without customer are never combined`.
- **An event fully covered by the same customer is kept as evidence; covered by another customer it stays unattached.** Its minutes are gone, but it still underlines the covering activity. Mixing customers would be worse than losing the signal. Tests: `a group fully covered by a dominant group of the same customer hands its events to that group`, `a covered group of another customer stays unattached instead of mixing customers`.
- **Every part of a split keeps the events that overlap it**, so a [suggestion](suggestions.md) can recognise parts of one event and merge them again.

## Where in the code

- `app/Services/ActivityProjector.php` (chain gap, overlap subtraction), `app/Models/Activity.php`
- Tests: `tests/Unit/Services/ActivityProjectorTest.php`
