# Suggestions

Read this when you touch anything between an integration observing work and a user booking an entry for it.

## Goal

People forget to book hours. Timatic therefore collects signals of work from the tools people already use and turns them into proposed entries, so a day can be booked by accepting suggestions instead of reconstructing it from memory. What must stay true:

- **One customer is never charged twice for a minute.** A booked entry settles its minutes for its customer, and between activities of one customer the strongest evidence decides. Two customers may both be charged when the user was multitasking.
- **A day can always be rebuilt from its events.** Activities and open suggestions are a projection of the day's events, never hand-edited state; a user's decisions (accepting, dismissing) are the only state that survives a rebuild.

```
Source       where signals come from: an integration, the extension, a calendar
  │
  ▼
Event        one fact about work: a commit, a focused tab, a meeting
  │
  ▼
Activity     one stretch of work on one customer and ticket
  │
  ▼
Suggestion   a proposed entry for one user-day
  │
  ▼
Entry        the booking the user accepted
```

| step | page |
| --- | --- |
| a source sends an event for a user | [events](events.md) |
| the user's day is rebuilt: events become activities | [activities](activities.md) |
| activities chain into suggestions | [suggestions](suggestions.md) |
| rebuild triggers, rebundling, retention, reminders | [lifecycle](lifecycle.md) |

## One day, end to end

A commit on ticket TIC-1 of customer X from 09:00 to 09:30, a calendar meeting of customer X without a ticket from 09:35 to 09:50, and another commit on TIC-1 at 11:00.

1. **Events.** Three events for the user on that day. The second commit has no start, so it gets the estimated duration of 15 minutes.
2. **Activities.** The first commit starts an activity on TIC-1. The meeting has the same customer, no ticket and follows within the 15-minute gap, so it joins that activity, which now runs 09:00–09:50. The second commit is more than 15 minutes away and starts a second activity, 10:45–11:00. Tests: `a null-ticket event glues onto the preceding group of the same customer even with another event type`, `same-ticket events further apart than the chain gap become separate activities`.
3. **Suggestions.** Both activities are customer X on TIC-1 and neither contradicts the other on budget or internal flag, so they chain into one suggestion of 65 minutes. Test: `activities sharing customer, budget, ticket and is_internal bundle into one suggestion`.
4. **Entry.** The user accepts, which creates an entry linked to the suggestion. Had they dismissed it, its key (customer X, TIC-1, no budget, not internal) would suppress it on every later rebuild of that day.
