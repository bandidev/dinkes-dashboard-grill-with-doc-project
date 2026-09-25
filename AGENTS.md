# Repository Workflow

## Task Completion

- When a task is complete and its relevant checks pass, commit the task's changes and push the current branch to its configured remote.
- Stage only files changed for the completed task. Leave unrelated or pre-existing worktree changes untouched.
- If commit or push cannot complete, report the exact blocker instead of claiming the task is fully complete.

## Reporting Table Work

- Before completing work on a reporting table, run its mapping/import command against the database used by the application and confirm the table is ready there; code changes alone do not update an existing database.
- Keep tests focused on the normal input flow: operators fill every required indicator and enter `0` when its value is not known. Add longer edge-case scenarios only when a stated requirement or reported defect calls for them.
