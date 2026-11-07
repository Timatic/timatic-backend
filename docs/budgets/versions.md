# Budget versions

Read this when you change how a budget's title, price or hours are stored, edited or looked up for a date.

## Goal

A budget that had 20 hours a month for a year and 30 hours a month after that must report 20 hours for the first year forever. So the mutable fields of a budget live in versions, each valid from an effective-from date until an effective-to date, and a change creates a new version instead of overwriting the old one. What must stay true: **for every moment in a budget's life there is exactly one version in effect**, with no gaps and no overlaps.

## Rules

- **A version changes at a period boundary, never inside a period.** The effective-from date of a new version must be the start of one of the budget's periods; for a one-time budget that is only the budget's start, so its single version is replaced whole. Guards per-period reporting: a period always has one version. No pinning test yet.
- **An update without an effective-from date applies from the budget's start**, which retires every existing version. A client that means "from now on" must send the start of the current period. Test: `can update a budget without explicit effective from date`.
- **The new version wins from its date onward.** An older version starting on or after the new effective-from date is soft-deleted; one that started earlier gets an effective-to of one minute before. Backdating is allowed and may retire several versions at once. No pinning test yet.
- **A moment before the budget started resolves to the first version.** Entries can be moved onto a budget created after them, and they must still find a rate and hours. Test: `always returns an active version`.

## Where in the code

- `app/Services/BudgetVersionService.php` (`createAndReplaceVersion`), `app/Models/BudgetVersion.php` (`isObsoleteComparedTo`, `overlapsWith`), `app/Models/Budget.php` (`activeVersion`)
- Tests: `tests/Feature/Budgets/StoreTest.php`, `tests/Feature/Budgets/BudgetActiveVersionTest.php`
