# Sync Tempo worklogs

Tempo strips the author and description from the worklogs it mirrors into Jira, so worklogs are
read from Tempo. Add a Tempo integration in the admin with a Tempo API token and the Jira instance
Tempo runs on, then switch on "Sync Tempo worklogs to Timatic entries". Every fifteen minutes the
worklogs changed or deleted since the previous run are copied to entries, with the customer and
budget of the Jira project mapping. Entries that are invoiced or in a locked period are left
untouched and logged. The first run starts from the current moment. To run the sync by hand:

```bash
php artisan tempo:sync-worklogs
```

The command only queues the sync; a queue worker runs it. The settings of the Tempo integration
show when the last sync ran.

Worklogs from before the first sync are imported with "Import worklogs" in the Tempo integration
settings: pick a period and every worklog in it is copied to entries, one queued job per week.
The import is independent of the sync: it does not move the sync position and does not see
worklogs deleted in Tempo.

A worklog is booked on the Timatic user linked to its Jira author under "User mapping" of the
Jira integration. "Refresh" there loads every person of the Jira instance and links the ones with
the same e-mail address; link the others by hand. `php artisan jira:sync-users {integration id}`
does the same from the command line.

A Jira project that holds the work of several customers, such as a support project with an epic
per customer, is mapped per epic: switch on "Customer per epic" for the project under "Project
mapping" of the Jira integration, then give each epic a customer and budget under "Epic mapping".
"Refresh" there loads the epics of those projects. A worklog is booked on the mapping of the epic
of its issue; a sub-task takes the epic of its parent, and time logged on the epic itself counts
for that epic. An issue without an epic, or with an epic that has no customer and budget, falls
back to the mapping of its project.

A worklog is skipped when its author has no Timatic user or neither its epic nor its Jira project
has a customer and budget. The Tempo integration settings then show how many Jira users, epics
and projects that concerns, with links to the user mapping, epic mapping and project mapping that
list exactly those; remove the filter chip there to see every row again.

Issues mirrored from another Jira instance usually start with the original issue key in their
title, e.g. "ABC-12 Fix login". Switch on "Record mirrored issues on the original issue" in the
Tempo integration settings and list the project keys of the originals to record such time on the
original issue and its project mapping instead of the copy.

With "Sync Timatic entries to Tempo" on, every entry saved or deleted on an issue of a project
mapped in that Jira instance is created, updated or deleted in Tempo as a worklog of the
entry's user. Worklog times are sent in the preferred timezone of Timatic, which has to match
the timezone of the Tempo instance.
