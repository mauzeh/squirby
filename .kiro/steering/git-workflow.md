---
inclusion: always
---

# Git Workflow

## Commits

You may commit at your own discretion — after completing a logical unit of work, fixing a bug, or finishing a task. Use clear, concise commit messages that describe what changed.

## Hard Rules

1. **NEVER push.** Do not run `git push` under any circumstances. The user will push when ready.
2. **NEVER merge into main.** Do not run `git merge main`, `git checkout main`, or any operation that touches the main branch. All work happens on the current feature branch.
3. **NEVER force-push.** No `git push --force` or `git push -f`.
4. **NEVER rebase onto main.** No `git rebase main`. The user manages branch integration.

## Branch Assumptions

- You are always working on a feature branch (never directly on main).
- If you need to create a branch, name it descriptively: `feature/sync-api`, `fix/cardio-distance-migration`, etc.
- If you're unsure which branch you're on, run `git branch --show-current` before committing.

## Commit Message Style

- Imperative mood: "Add migration for lift_sets columns" not "Added migration"
- No prefix conventions required (no "feat:", "fix:", etc. unless the user asks)
- Keep the first line under 72 characters
