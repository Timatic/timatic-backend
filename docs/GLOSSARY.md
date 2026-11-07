# Glossary

One definition per term. Pages in `docs/` use these words and nothing else for the same thing.

## Customers and budgets

- **Customer**: the organisation work is done for. Owns budgets, tracked domains and an optional **account manager** (the user who receives budget mail for the customer).
- **Budget**: a pot of hours a customer bought, bound to one customer and one budget type. Its type, customer, renewal frequency and start are immutable; everything that may change over time lives in budget versions.
- **Budget type**: a template that says what a budget of this kind needs (start and end date, supervisor, contract id, total price, a ticket on every entry) and which renewal frequencies it allows.
- **Renewal frequency**: `monthly`, `yearly` or none. With a frequency the hours renew every period; without one the budget is a single pot for its whole duration (a **one-time budget**).
- **Budget version**: the mutable part of a budget (title, description, total price, initial minutes) valid from an effective-from date until an effective-to date. The version in effect at a moment is the **active version** for that moment.
- **Period**: one renewal window of a budget, computed on demand from the budget's start, end, renewal frequency and archive date. Never stored. A one-time budget has exactly one period.
- **Credit**: the money value of remaining hours in a period: remaining minutes, as hours, times the hourly rate of the active version.
- **Mutation**: the per-budget, per-month summary of credit movement: start balance, used credit, used out of budget, expired credit, renewed credit, end balance.
- **Supervisor**: the user responsible for a budget on the engineering side. Receives budget mail alongside the account manager.
- **Archived budget**: a budget closed early; its last period ends on the archive date and no later periods exist.

## Entries

- **Entry**: a saved record of work: user, customer, ticket, start and end, description, and either a budget, "internal" or neither.
- **Internal**: an entry (or suggestion) for the company itself, outside any customer budget.
- **Paid per hour**: an **entry** with no budget and not internal. Only a saved entry can be paid per hour; on an event or suggestion a missing budget means *unknown*, see below.
- **Minutes spent**: the number budgets consume. Clock minutes of the entry, raised by overtime percentages where they apply.
- **Correction**: a locked entry with negative minutes that cancels another entry, optionally paired with the entry that replaces it.
- **Locked entry**: an entry that can no longer be changed, either because a correction was made for it or because its date fell behind the locked date.
- **Locked date**: the moment before which the current user may no longer create or change entries.
- **Closed month**: the last month finance has closed. No user can edit entries in or before it.

## Suggestions

- **Source**: where events come from (an integration such as Jira or GitHub, the browser extension, a calendar).
- **Event**: one observed signal that work happened: a commit, an issue transition, a browser focus, a calendar meeting. Has an end, an optional start, an optional customer, budget and ticket, and an event type.
- **Event type**: the kind of event, with a **weight**. Lower weight is stronger evidence of work; higher weight only fills time nothing stronger claimed.
- **Sequence**: the recurring thing an event is an occurrence of, identified by source plus external id (a recurring calendar meeting is the typical case).
- **Activity**: one stretch of work on one customer (and ticket, when known) that one or more events of the same day support, from any source and of any event type. Projected from the day's events; never edited by hand.
- **Suggestion**: a proposed entry for one user and one day, made of one or more activities that chain together. Accepting it creates an entry; dismissing it soft-deletes it.
- **Unknown** (on an event, activity or suggestion): a missing customer, budget or internal flag means the source did not know it. Later activities may fill it. It never means paid per hour.
- **Dismissed suggestion**: a suggestion the user rejected. Its customer, budget, ticket and internal flag form a key; a rebuild projects no new suggestion with that key on that day.
- **Unused suggestion**: an open suggestion of a past week that has no entry for the same ticket on its day. Users get a weekly reminder of these.
- **Rebuild** (of a user-day): throwing away the activities and open suggestions of one user on one day and projecting them again from that day's events.
- **Rebundle**: running the rebuild for every user-day in a date range, for example after the chaining rules changed.
- **Tracked domain**: a website (host plus optional path) mapped to a customer and optional budget, so the browser extension can label its focus events.
