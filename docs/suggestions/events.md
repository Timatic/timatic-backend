# Events

Read this when you add a source or event type, change what an integration sends, or change the event endpoint.

## Goal

An event is one observed signal that a user did work: a commit, an issue transition, a pull request comment, a browser tab in focus, a calendar meeting, a prompt in Claude Code. Sources differ in what they know, so **an event may leave anything but its user, source, type and end unknown**, and the pipeline fills gaps later rather than refusing events.

## Rules

- **An unknown customer is accepted, not rejected.** A customer external id the backend cannot resolve leaves the customer unknown and the event is stored; a source should never lose a signal over a mapping gap. Test: `stores an event and ignores a non existing customer`.
- **Unknown is unknown.** A missing budget or internal flag means the source did not know; it never means paid per hour, which exists only on a saved [entry](../entries/overview.md).
- **An event without a start is 15 minutes ending at its end.** A commit says nothing about when the work began; 15 minutes is the estimate the whole pipeline uses.
- **An event spans at most a day and ends in the past.** A longer signal is not work on one thing. Tests: `refuses an event that spans more than a day`, `refuses an event that ends in the future`.
- **The event type's weight ranks evidence**: the lower, the stronger the claim that the user worked on exactly this at exactly that time. [Activities](activities.md) are built in ascending weight, so a strong event keeps its minutes and a weak one only fills what is left. Representative weights (the seeders of the core app and each integration package are the source of truth): branch created 10, Claude Code prompt 20, pull request comment 40, pull request opened 50, pull request merged 70, calendar meeting 75, Jira issue done 93, Jira worklog 95, browser focus 100.
- **Source plus external id identifies a sequence.** Occurrences of a recurring meeting share one external id, which lets a suggestion learn from the booking of an earlier occurrence. See [suggestions](suggestions.md).
- **Tracked domains are applied by the browser extension, not by the backend.** The backend stores, per customer, the hosts and paths that belong to it (optionally with a budget, optionally private to one user); the extension reads them and sends focus events with the customer already set. Issue #80 plans to move this mapping into the extension's integration.

## Where in the code

- `app/Models/Event.php` (`effectiveStart`, estimated duration), `app/Models/EventType.php`, `app/Models/Source.php`, `app/Http/Requests/EventRequest.php`, `app/Http/Controllers/Suggestions/EventController.php`, `database/seeders/EventTypeSeeder.php` and the seeders under `integrations/*/database/`, `app/Models/TrackedDomain.php`
- Tests: `tests/Feature/Events/StoreTest.php`, `tests/Feature/EventTypes/NamedEventTypesTest.php`, `tests/Feature/TrackedDomains/StoreTest.php`, `tests/Feature/TrackedDomains/IndexTest.php`
