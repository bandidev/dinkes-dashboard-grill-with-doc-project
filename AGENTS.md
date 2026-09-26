# Repository Workflow

## Task Completion

- After all relevant automated tests and required manual verification pass, commit and push the completed task without waiting for separate approval.
- Stage only files changed for the completed task. Leave unrelated or pre-existing worktree changes untouched.
- If commit or push cannot complete, report the exact blocker instead of claiming the task is fully complete.

## Testing

- Give the user the exact test commands and manual verification steps to run; do not run tests or browser checks yourself. Continue independent work and use the user's reported results before declaring verification complete.

## Reporting Table Work

- Before completing work on a reporting table, run its mapping/import command against the database used by the application and confirm the table is ready there; code changes alone do not update an existing database.
- Keep tests focused on the normal input flow: operators fill every required indicator and enter `0` when its value is not known. Add longer edge-case scenarios only when a stated requirement or reported defect calls for them.

## Agent skills

### Issue tracker

Issues and specs live in GitHub Issues for this repository. See `docs/agents/issue-tracker.md`.

### Triage labels

Use the five canonical triage labels. See `docs/agents/triage-labels.md`.

### Domain docs

Single-context: read the root `CONTEXT.md` and relevant ADRs under `docs/adr/`. See `docs/agents/domain.md`.
