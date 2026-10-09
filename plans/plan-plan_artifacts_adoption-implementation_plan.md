# Plan artifacts adoption in the RAG repo

## Summary

Adopt the *plan artifacts* methodology: a guideline (`.ai/guidelines/plan-artifacts.md`) that Boost composes into
`CLAUDE.md`/`AGENTS.md`, plus a versioned `plans/` directory where agents leave the plan before implementing
non-trivial work. Only the methodology is adopted, not any external team's tooling.

## Context

- The methodology is a single ~45-line guideline with committed plans in `plans/`. Scaffold scripts and board/story-point
  rules from the source project are tied to its own infrastructure (`gh`, Sail, a project board) and are not needed here.
- This repo had no `.ai/guidelines` or `plans/`; `boost.json` has `guidelines: true`; `composer.json:65` runs
  `boost:update` after every `composer update`, so the Boost block is never edited by hand.
- Boost includes "only create documentation files if explicitly requested" and "don't create new base folders without
  approval"; the guideline exempts `plans/plan-*.md` from both.
- The superpowers plugin saves plans to `docs/superpowers/plans/`; the guideline states that `plans/` takes precedence.
- Reviewed adversarially in 3 iterations (Opus, Fable, Opus); findings F1–F7 resolved (F7 incorporated after the gate).

## Implementation

0. Branch `feature/plan-artifacts-adoption` from a clean master; all commits on the branch.
1. Create `.ai/guidelines/plan-artifacts.md`: file name `plans/plan-{topic_slug}-{plan_name}.md` (slug = branch without
   `feature/`, hyphens to underscores, no PR numbers), lookup without a branch (`ls -t plans/ | head`, `grep -i`),
   exemption from the two Boost rules, precedence over planning skills, five-section template.
2. Create this file as the first plan.
3. Run `php artisan boost:update --no-interaction` as the only writer of the Boost block. Expected: a new
   `=== .ai/plan-artifacts rules ===` block in both files. If it does not pick up `.ai/guidelines/` or touches the
   hand-written section, stop and report. Unrelated drift goes in a separate commit or is restored wholesale.
4. Add one line pointing to `plans/` in the hand-written section of `CLAUDE.md`.
5. Commit on the branch; open a PR only with approval.

## Rejected alternatives

- Porting a plan-scaffold script: coupled to infrastructure this repo does not have.
- Board/story-point rules: there is no board here.
- PR/issue numbers in file names: they do not exist before the plan, so they are not a stable lookup key.
- Pasting the block by hand into `CLAUDE.md`/`AGENTS.md` if Boost fails: `composer update` would erase it.
- Storing plans in `docs/`: conflicts with superpowers and with the rule against creating documentation unasked.

## Verification

- `git status` lists only the guideline, this plan, `CLAUDE.md` and `AGENTS.md`; changes under `.agents/skills`,
  `.claude/skills`, `.ai/mcp` or `boost.json` are flagged and handled per step 3.
- `grep -c 'plan-artifacts rules' CLAUDE.md AGENTS.md` returns 1 for each; this plan has the five sections.
- Idempotence: a second `boost:update` leaves `git diff --exit-code` clean.
- `php artisan test --compact` is green; Pint makes no changes.
- Behavior in a disposable worktree from the branch HEAD, after the commit: Claude Code with and without superpowers,
  and Codex. (a) non-trivial task → plan in `plans/`; (b) trivial edit → skip reason cited, no plan; (c) read-only
  question → no plan. Any failure, especially a plan in `docs/superpowers/`, blocks the PR until the wording is revised
  and the runs repeated, or the user accepts the limitation in writing.
