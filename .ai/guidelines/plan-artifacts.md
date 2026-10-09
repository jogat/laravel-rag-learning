# Plan Artifacts

The `plans/` directory at the repo root stores implementation plans and architectural decision records written by
agents during active work. Each file is named `plan-{topic_slug}-{plan_name}.md` and captures the thinking,
constraints, decisions, rejected alternatives, and approach for a specific piece of work.

## Overrides of other rules

Files matching `plans/plan-*.md` are the sanctioned exception to two Boost rules: "only create documentation files
if explicitly requested" and "don't create new base folders without approval". Write them without asking. The
exception covers only that path.

When any planning or brainstorming skill (`superpowers:writing-plans`, `superpowers:brainstorming`,
`plan-review-loop`, etc.) proposes its own plan or spec location, the plan is still written to
`plans/plan-{topic_slug}-{plan_name}.md` and no copy goes to `docs/`. A plan under `docs/superpowers/` is a
failure of this guideline. Adversarial review of a plan is optional: use it only if a plan-review skill is available.

## When to Read Plan Artifacts

- **Before implementing a task** — check for an existing plan to understand prior analysis, constraints, and decisions.
- **When the history is unclear** — plans explain the *why* behind design choices that are not apparent from the
  code or git log.
- **When picking up abandoned or partially implemented work** — plans document where work was heading and what
  alternatives were rejected.

## How to Find Relevant Plans

No branch name is needed:

```bash
ls -t plans/ | head
ls plans/ | grep -i <keyword>
```

## When to Create or Update Plans

- Create or update a plan before implementation for non-trivial work, feature branches, architecture changes,
  cross-file changes, or work with meaningful constraints, rejected alternatives, or verification steps.
- Update the existing plan instead of creating a duplicate when continuing the same strategy.
- Create a follow-up plan with a distinct `plan_name` only when the new work needs a separate decision record, such
  as `follow_up_cleanup` or `verification_plan`.
- Allowed skip cases are limited to explicitly trivial edits, pure read-only investigation, user-directed no-edit
  work, or emergency fixes where stopping to plan would be harmful.

## How to Name Plans

- Use `plans/plan-{topic_slug}-{plan_name}.md`, all snake_case.
- `topic_slug` is chosen once when the work starts and equals the branch name without the leading `feature/`, with
  hyphens replaced by underscores (`feature/plan-artifacts-adoption` → `plan_artifacts_adoption`). Do not put PR
  numbers in the name. A GitHub issue number may prefix the slug (`123_slug`) only if the issue exists before the plan.
- Keep `plan_name` short and specific, such as `implementation_plan`, `initial_approach`, or `follow_up_cleanup`.

## Plan Template

Write plans in English. Use these headings:

1. `## Summary` — goal and why this approach fits.
2. `## Context` — constraints, existing plans read, relevant prior decisions.
3. `## Implementation` — the planned code, config, or documentation changes.
4. `## Rejected alternatives` — what was considered and why it was dropped.
5. `## Verification` — the focused tests, commands, or inspections needed before handoff.

## Agent Default

When starting non-trivial work, check `plans/` for related artifacts. Read matching plans before writing any code,
then create or update the plan before implementation unless an allowed skip case applies. Final responses for
implementation work must mention the plan artifact path or the explicit skip reason.
