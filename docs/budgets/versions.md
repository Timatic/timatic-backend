# Budget versions

Read this when you change how a budget's title, price or hours are stored, edited or looked up for a date.

## Goal

A budget that had 20 hours a month for a year and 30 hours a month after that must report 20 hours for the first year forever. So the mutable fields of a budget live in versions, each valid from an effective-from date until an effective-to date, and a change creates a new version instead of overwriting the old one. What must stay true: **for every moment in a budget's life there is exactly one version in effect**, with no gaps and no overlaps.

## Rules

- **The first version starts with the budget.** When a budget is created, its first version is effective from the budget's start date, whatever effective-from was posted. Test: `stores a budget`.
- **A one-time budget has one version for its whole duration.** Without a renewal frequency, a new version may only be effective from the budget's start date, so it replaces the previous one entirely. Guards the single period of a one-time budget, which has no boundary a change could align with. No pinning test yet.
- **A renewing budget changes at a period boundary.** The effective-from date of a new version must be the start date of one of the budget's periods. An update without an explicit effective-from date uses the budget's start date. Guards the per-period reporting: a period always has one version. Test: `can update a budget without explicit effective from date`.
- **A partial update keeps what it did not post.** Missing mutable fields are copied from the version active at the moment of the update, so a new version is always complete. Test: `can update a budget with renewal frequencies`.
- **The new version wins over everything from its date onward.** An older version that starts on or after the new effective-from date becomes obsolete and is soft-deleted. An older version that started earlier and ran into the new date gets an effective-to of one minute before it. Backdating is allowed and can retire several versions at once. No pinning test yet.
- **Looking up the version for a moment before the budget started returns the first version.** Entries can be moved onto a budget created after them, and they must still resolve a version. A budget whose versions do not cover the moment falls back to its open-ended version. Test: `always returns an active version`.

## Where in the code

- `app/Services/BudgetVersionService.php` (`createAndReplaceVersion`), `app/Models/BudgetVersion.php` (`isObsoleteComparedTo`, `overlapsWith`), `app/Models/Budget.php` (`activeVersion`)
- Tests: `tests/Feature/Budgets/StoreTest.php`, `tests/Feature/Budgets/BudgetActiveVersionTest.php`
