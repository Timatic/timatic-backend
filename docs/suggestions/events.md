# Events

Read this when you add a source or event type, change what an integration sends, or change the event endpoint.

## Goal

An event is one observed fact about a user's work: a commit, an issue transition, a pull request comment, a browser tab in focus, a prompt in Claude Code. It carries an end timestamp, or a start timestamp when it marks the beginning of something, such as opening a file or answering a call. Sources differ in what else they know, so **an event may leave anything but its user, source, type and timestamp unknown**, and the pipeline fills the gaps later rather than refusing events.

## Rules

- **An unknown customer is accepted, not rejected.** A customer external id the backend cannot resolve leaves the customer unknown and the event is stored; a source should never lose a signal over a mapping gap. Test: `stores an event and ignores a non existing customer`.
- **An event can be explicitly for a budget or explicitly internal, never explicitly paid per hour.** A missing budget or internal flag means the source did not know; paid per hour is a settlement that exists only on a saved [entry](../entries/overview.md).
- **The end timestamp is the fact, the duration is a guess.** An event without a start gets an estimated duration before its end timestamp, today a flat 15 minutes. A better estimate per source or event type can replace that guess later without touching the stored events.
- **Events are facts, not plans.** Timatic records what someone did, never what someone intends to do, so an event ends in the past. A calendar item only shows intent and can be cancelled during the meeting, which is why it is weak evidence on its own. Test: `refuses an event that ends in the future`.
- **An event spans at most a day.** A longer signal is not work on one thing. Test: `refuses an event that spans more than a day`.
- **The event type's weight says how certain the evidence is.** Some events prove what the user was doing with more certainty than others: a keystroke or a commit is certain, being in a meeting is not, the user may have been answering mail. The lower the weight, the stronger the claim. [Activities](activities.md) are built in ascending weight, so certain evidence keeps its minutes and uncertain evidence only fills what is left. The seeders of the core app and of each integration package hold the weights.
- **A recurring event is suggested the way the user booked it last time.** Occurrences of a recurring meeting share a source and external id, the sequence. Once the user books one occurrence on a customer and budget, every other occurrence is proposed with that customer and budget, as if the user had created a mapping. See [suggestions](suggestions.md).
- **Tracked domains are proposals from admins to users, applied by the browser extension.** An admin records per customer the hosts and paths that belong to it, optionally with a budget, and users can add their own. The extension reads them and sends focus events with the customer already set, so the backend never guesses a customer from a URL.

## Where in the code

- `app/Models/Event.php` (`effectiveStart`, estimated duration), `app/Models/EventType.php`, `app/Models/Source.php`, `app/Http/Requests/EventRequest.php`, `app/Http/Controllers/Suggestions/EventController.php`, `database/seeders/EventTypeSeeder.php` and the seeders under `integrations/*/database/`, `app/Models/TrackedDomain.php`
- Tests: `tests/Feature/Events/StoreTest.php`, `tests/Feature/EventTypes/NamedEventTypesTest.php`, `tests/Feature/TrackedDomains/StoreTest.php`, `tests/Feature/TrackedDomains/IndexTest.php`
