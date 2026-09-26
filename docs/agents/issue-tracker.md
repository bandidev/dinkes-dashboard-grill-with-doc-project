# Issue tracker: GitHub

Issues and specs live in this repository's GitHub Issues. Use `gh` for issue operations and infer the repository from `git remote -v`.

## Conventions

- Create: `gh issue create --title "..." --body-file <file>`.
- Read: `gh issue view <number> --comments`.
- List: `gh issue list --state open --json number,title,body,labels,comments`, with appropriate filters.
- Comment: `gh issue comment <number> --body "..."`.
- Label: `gh issue edit <number> --add-label "..."` or `--remove-label "..."`.
- Close: `gh issue close <number> --comment "..."`.

When a skill says “publish to the issue tracker”, create a GitHub issue. When it says “fetch the relevant ticket”, read the issue and its comments.

## Pull requests as a triage surface

**PRs as a request surface: no.** Set to `yes` here if external PRs should enter the triage queue.

## Wayfinding

A map is an issue labelled `wayfinder:map`; tickets are child issues. Use GitHub sub-issues and native issue dependencies when available; otherwise link children in the map and record `Blocked by: #<number>` on tickets. A ticket is unblocked when all blockers are closed. Claim with `gh issue edit <number> --add-assignee @me`; resolve by commenting, closing, and linking the decision from the map.
