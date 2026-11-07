# Events

Read this when you add a source or event type, change what an integration sends, or change the event endpoint.

## Goal

An event is one observed signal that a user did work: a commit, an issue transition, a pull request comment, a browser tab in focus, a calendar meeting, a prompt in Claude Code. Sources differ in what they know, so **an event may leave anything but its user, source, type and end unknown**, and the pipeline fills gaps later rather than refusing events.

## Rules

- **A source sends events over the API or from its own integration package.** Each event names its source, event type, user (by id or external id) and end. Customer and user may be given by external id, which the backend resolves; an unknown external customer leaves the customer unknown instead of failing. Tests: `stores an event`, `stores an event and finds a customer by its external id`, `stores an event and ignores a non existing customer`, `stores an event and finds a user by its external id`.
- **An event without a start is a point in time**: it is treated as 15 minutes ending at its end. A commit says nothing about when the work began.
- **An event ends in the past, after it started, and spans at most a day.** Longer signals are not work on one thing. Tests: `refuses an event that ends in the future`, `refuses an event that ends before it starts`, `refuses an event that spans more than a day`.
- **Unknown is unknown.** A missing budget or internal flag on an event means the source did not know; it never means paid per hour, which exists only on a saved [entry](../entries/overview.md).
- **The event type carries a weight**: the lower, the stronger the evidence that the user worked on exactly this at exactly that time. Activities are built in ascending weight, so a strong event keeps its minutes and a weak one only fills what is left. See [activities](activities.md). Representative weights (the seeders of the core app and each integration package are the source of truth): branch created 10, Claude Code prompt 20, pull request comment 40, pull request opened 50, pull request merged 70, calendar meeting 75, Jira issue done 93, Jira worklog 95, browser focus 100.
- **Every event type has a name** for the interface, so an activity can say what it was built from. Tests: `gives every seeded event type a name to show in the interface`, `includes the name of the event type an activity was built from`.
- **An external id makes an event part of a sequence** (source plus external id). Occurrences of a recurring meeting share one external id, which is what lets a suggestion learn from the booking of an earlier occurrence. See [suggestions](suggestions.md).
- **Tracked domains label browser events.** The backend stores, per customer, the hosts and paths that belong to it, optionally with a budget and optionally private to one user; the browser extension reads them and sends focus events with the customer already set. Issue #80 plans to move this mapping into the extension's integration. Tests: `stores a tracked domain`, `shows a user their own private mapping`, `hides a private mapping of another user`.
- **Creating an event schedules a rebuild** of the user's day, and of both days when the event spans midnight. See [lifecycle](lifecycle.md).

## Where in the code

- `app/Models/Event.php` (`effectiveStart`, estimated duration), `app/Models/EventType.php`, `app/Models/Source.php`, `app/Http/Requests/EventRequest.php`, `app/Http/Controllers/Suggestions/EventController.php`, `database/seeders/EventTypeSeeder.php` and the seeders under `integrations/*/database/`, `app/Models/TrackedDomain.php`
- Tests: `tests/Feature/Events/StoreTest.php`, `tests/Feature/EventTypes/NamedEventTypesTest.php`, `tests/Feature/EntrySuggestions/IncludeEventTypeTest.php`, `tests/Feature/TrackedDomains/StoreTest.php`, `tests/Feature/TrackedDomains/IndexTest.php`
