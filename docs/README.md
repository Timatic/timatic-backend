# Timatic domain docs

Read these when you change behaviour around budgets, entries or suggestions, in this repo or in a repo that consumes its API. Each page opens with the goal of the concept and what must stay true, then lists only the rules the code does not explain by itself: the *why* behind a decision, an invariant a change must preserve, or a behaviour that looks like a bug but is deliberate. Each such rule names the test that pins it. Prose uses the terms from the [glossary](GLOSSARY.md) and stays free of class names; the "Where in the code" footer at the end of each page names the classes and tests.

## Read order

1. [Glossary](GLOSSARY.md)
2. [Budgets overview](budgets/overview.md), then [versions](budgets/versions.md), [periods](budgets/periods.md), [usage](budgets/usage.md), [notifications](budgets/notifications.md)
3. [Entries overview](entries/overview.md), then [locked dates](entries/locked-dates.md), [time spent](entries/time-spent.md), [daily progress](entries/daily-progress.md)
4. [Suggestions overview](suggestions/overview.md), then [events](suggestions/events.md), [activities](suggestions/activities.md), [suggestions](suggestions/suggestions.md), [lifecycle](suggestions/lifecycle.md)

## Other docs

- [Integration OAuth architecture](integration-oauth.md): the callback proxy and the target token-storage pattern for integration packages.
- The JSON:API reference is generated from the code and served at `/docs` of a running instance.

## Keeping these pages true

A bullet earns its place by saying something the code cannot: intent, an invariant, or a deliberate surprise. Obvious behaviour and restatements of the code stay out, however well tested. When you change a rule, change its test and its bullet in the same commit. Full scenarios with dates and minutes live in the tests, which stay the single source for exact numbers.
