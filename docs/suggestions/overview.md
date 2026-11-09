# Suggestions

Read this when you touch anything between an integration observing work and an engineer booking an entry for it.

## Goal

Engineers forget to book hours. Timatic therefore collects signals of work from the tools engineers already use and turns them into proposed entries, so a day can be booked by accepting suggestions instead of reconstructing it from memory. What must stay true:

- **A suggestion only ever proposes time nothing else claims.** Booked entries and stronger evidence always win over weaker evidence for the same minutes.
- **A day can always be rebuilt from its events.** Activities and open suggestions are a projection of the day's events, never hand-edited state; a user's decisions (accepting, dismissing) are the only state that survives a rebuild.

```
Source ─▶ Event ─▶ Activity ─▶ Suggestion ─▶ Entry
          one signal   time on one       proposed entry      the engineer's
          of work      customer/ticket   for one user-day    accepted booking
```

| step | page |
| --- | --- |
| a source sends an event for a user | [events](events.md) |
| the user's day is rebuilt: events become activities | [activities](activities.md) |
| activities chain into suggestions | [suggestions](suggestions.md) |
| rebuild triggers, rebundling, retention, reminders | [lifecycle](lifecycle.md) |

## One day, end to end

A commit at 10:10 and another at 11:00 on ticket TIC-1 of customer X, and a two-hour calendar meeting from 10:00 to 12:00 for customer Y.

1. **Events.** Three events for the user on that day. Commits are strong evidence (low weight), a calendar meeting is weak evidence (high weight): the engineer may have left early or coded through it.
2. **Activities.** The commits are processed first and claim 10:10–10:25 and 11:00–11:15 on TIC-1. The meeting is processed next and only gets what is left: 10:00–10:10, 10:25–11:00 and 11:15–12:00, as three activities for customer Y that all point at the same meeting event. Test: `a meeting spanning two commits is split into three activities around the commits`.
3. **Suggestions.** Per customer, activities chain when their ticket, budget and internal flag agree or are unknown. The two commit activities become one suggestion for X on TIC-1. The three meeting parts share one event, so they merge into one suggestion for Y, with 1h35 of minutes spent. Tests: `activities sharing customer, budget, ticket and is_internal bundle into one suggestion`, `customerless parts of one split event bundle into one suggestion`.
4. **Entry.** The engineer accepts the X suggestion, which creates an entry linked to it. The Y suggestion gets dismissed; its key (customer Y, TIC-2, no budget, not internal) suppresses it on every later rebuild of that day.
