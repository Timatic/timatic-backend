# Activities

Read this when you change how a day's events are turned into activities.

## Goal

An activity is a span of time on one customer, and on one ticket when known, that the day's events support. Building them answers one question per minute of the day: *which customer and ticket was the user most likely working on?* What must stay true: **a minute belongs to at most one activity or booked entry**, and **the strongest evidence decides who gets it**. The result depends only on the set of events, not on the order they arrived in. Test: `shuffled input produces the same activities as chronological input`.

## Rules

Events are processed in ascending weight, then by start time. Each event either joins an existing activity or starts a new one.

### Joining

- **Same ticket, same customer, same event type, within 15 minutes joins.** An event within 15 minutes before or after an activity's span joins it and stretches it; further apart it starts a new activity. Events from different sources join as long as the ticket matches. Tests: `same-ticket events within the chain gap merge into one activity across sources`, `same-ticket events further apart than the chain gap become separate activities`.
- **Different customers never join.** Test: `events of different customers never merge`.
- **An event without a customer stands alone.** It becomes an activity of its own and joins nothing, since there is nothing to match it on. Test: `events without customer are never combined`.
- **An event with a customer but no ticket glues onto the nearest activity of the same customer**, whatever that activity's event type; the preceding one wins when both sides are within 15 minutes. One without a neighbour at the time it is processed is held back and tried again after every other event has been placed, so a following activity can still claim it. Tests: `a null-ticket event glues onto the preceding group of the same customer even with another event type`, `a null-ticket event without preceding group is claimed by a following group of the same customer`, `a null-ticket event between two groups goes to the preceding one`, `a null-ticket event of another customer is not glued`.
- **An event with a start and end becomes an activity of that span; one without a start becomes 15 minutes ending at its end.** Tests: `an event with start and end becomes an activity of the same period`, `an event without start becomes an activity starting 15 minutes before its end`.

### Claiming time

- **Booked entries claim their minutes first.** A new activity is cut down to the part of its span no entry of the user covers; an entry inside it splits it in two; an activity entirely inside entries is not created. Tests: `an activity partially overlapping an entry period is trimmed to the unbooked part`, `an entry period inside an activity splits it into two activities`, `an activity fully inside entry periods is not created`.
- **Lower weight claims before higher weight.** A new activity is also cut down to what earlier (stronger) activities left. A strong event inside a weak one splits the weak one around it. Tests: `the higher-weight group keeps its period and the lower one is trimmed`, `a high-weight event inside a low-weight event splits the low-weight into two activities`, `a meeting spanning two commits is split into three activities around the commits`.
- **On equal weight the earlier start wins.** Tests: `on equal weight the earlier-starting group is dominant`, `a subordinate group starts after the latest dominant overlapping group`.
- **Point events of equal weight each keep a slice.** Three commits five minutes apart become three short activities, not one. Test: `fast sequential point events of equal weight each get their own time slice`.
- **An event fully covered by the same customer still counts.** It has no minutes of its own left, so it is attached to the covering activity as extra evidence. Covered by another customer, it stays unattached rather than mixing customers. Tests: `a group fully covered by a dominant group of the same customer hands its events to that group`, `a covered group of another customer stays unattached instead of mixing customers`.
- **Every part of a split keeps the events that overlap it**, so a suggestion can later recognise parts of one event and merge them. See [suggestions](suggestions.md).

## Where in the code

- `app/Services/ActivityProjector.php` (chain gap, overlap subtraction), `app/Models/Activity.php`
- Tests: `tests/Unit/Services/ActivityProjectorTest.php`
