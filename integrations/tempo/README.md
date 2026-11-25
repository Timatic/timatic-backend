# Sync Tempo worklogs

Tempo strips the author and description from the worklogs it mirrors into Jira, so worklogs are
read from Tempo. Add a Tempo integration in the admin with a Tempo API token and the Jira instance
Tempo runs on, then switch on "Sync Tempo worklogs to Timatic entries". Every fifteen minutes the
worklogs changed or deleted since the previous run are copied to entries, with the customer and
budget of the Jira project mapping. Entries that are invoiced or in a locked period are left
untouched and logged. The first run starts from the current moment; pass a day to import older
worklogs:

```bash
php artisan tempo:sync-worklogs [--since=2026-01-01]
```

The command queues the sync, which runs page by page on the queue worker. "Sync now" in the
Tempo integration settings queues the same sync, optionally from a chosen day, and shows its
progress. The settings also show since which day and until when the worklogs are synced.

A worklog is booked on the Timatic user linked to its Jira author under "User mapping" of the
Jira integration. "Refresh" there loads every person of the Jira instance and links the ones with
the same e-mail address; link the others by hand. Worklogs of unlinked authors are skipped, and
those authors show up in the list as not linked.

Issues mirrored from another Jira instance usually start with the original issue key in their
title, e.g. "ABC-12 Fix login". Switch on "Record mirrored issues on the original issue" in the
Tempo integration settings and list the project keys of the originals to record such time on the
original issue and its project mapping instead of the copy.

With "Sync Timatic entries to Tempo" on, every entry saved or deleted on an issue of a project
mapped in that Jira instance is created, updated or deleted in Tempo as a worklog of the
entry's user. Worklog times are sent in the preferred timezone of Timatic, which has to match
the timezone of the Tempo instance.
