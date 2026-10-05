# InfraTrack --- Git Rules

## 1. Repository

The repository is the source-control home for InfraTrack.

`main` is the stable branch.

## 2. Main Branch

Do not develop directly on `main`.

`main` should contain working, reviewed code.

AI agents must not push directly to `main`.

## 3. Feature Branches

Create a branch for each focused task.

Naming:

``` text
feature/<feature-name>
fix/<issue-name>
refactor/<area>
docs/<document-name>
test/<feature-name>
```

Examples:

``` text
feature/super-admin
feature/issue-reporting
fix/assignment-access
docs/database
```

## 4. One Task Per Branch

A branch should have one clear purpose.

Do not mix unrelated features.

## 5. Before Starting Work

Run:

``` bash
git status
git branch
git log --oneline -5
```

Never assume uncommitted changes belong to the current task.

## 6. Commits

Make small, meaningful commits.

Format:

``` text
type: short description
```

Examples:

``` text
feat: add roles migration
feat: add super admin command
fix: prevent access to unassigned issues
test: add super admin command tests
docs: update database documentation
```

## 7. Before Committing

1.  Review changed files.
2.  Review the diff.
3.  Run relevant tests.
4.  Confirm no secrets or unrelated changes are included.

Use:

``` bash
git diff
git status
```

## 8. Pull Requests

A feature branch should be reviewed before merging into `main`.

A pull request should state: - what changed; - why it changed; - tests
performed; - known limitations.

## 9. Updating a Branch

Before merging, update the branch with the latest `main`.

Resolve conflicts carefully. Do not blindly accept conflict-resolution
changes.

## 10. AI Agent Rules

AI agents may create branches and commits when explicitly instructed.

An AI agent must not: - push directly to `main`; - overwrite unrelated
work; - reset or discard uncommitted changes without permission; -
rewrite project history without permission; - change architecture
silently; - commit secrets; - commit unnecessary generated files.

The agent must inspect `git status` before starting and before
committing.

## 11. Multiple AI Agents

Different agents may work on different branches.

All agents must use the same repository and documentation.

Agents must not create competing implementations of the same feature
without coordination.

## 12. Merge Rule

Merge only when: - implementation is complete; - relevant tests pass; -
changes have been reviewed; - documentation is updated where
necessary; - no unrelated changes are included.

## 13. History

Do not use force-push or destructive Git operations unless explicitly
required and understood.

Keep Git history understandable.

## 14. Documentation Changes

When an approved project decision changes:

``` text
Decision
  ↓
Update project.md
  ↓
Update affected supporting docs
  ↓
Implement code
  ↓
Test
  ↓
Commit
```
