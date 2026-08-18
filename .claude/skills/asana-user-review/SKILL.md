---
name: asana-user-review
description: Use when a KOZ ticket from the Kozijnr Asana board has passed code review and needs the human's functional sign-off before it can be marked Done, or when the user asks to finalize/approve/afronden a ticket.
---

# Asana User Review

## Overview

Handles the final human gate: presents a ticket sitting in "User review" for functional testing, then waits for an explicit go/no-go from the user in chat before touching "Done". This is a hard stop — no ticket reaches Done without an explicit confirmation in this conversation.

**REQUIRED BACKGROUND:** Read `.claude/asana.config.json` for `project.gid` and `workflow` section names/GIDs.

**Taal:** alle output naar de gebruiker en alle Asana-comments zijn in het Nederlands.

**Handoff van `asana-review`:** verwacht een comment die begint met "▶ Overgedragen aan user review" (zie stap 2) — dat is het testplan.

## When to Use

- A ticket just came out of `asana-review` clean and landed in "User review"
- User asks to finalize, approve, or "afronden" a specific ticket

Not for code/security review — see `asana-review`. Not for implementation — see `asana-worker`.

## Workflow

1. **Find the ticket.** `mcp__asana__get_tasks` with `project=config.project.gid`, matching `"<ticket_prefix>-<n>:"`. If it's not in the `user_review` section yet, say so and stop — don't move it there yourself.
2. **Present a test plan.** Pull the ticket's `notes` and its comment trail (`mcp__asana__get_task_stories`) — met name de "▶ Overgedragen aan user review"-comment van `asana-review` — en vat in het Nederlands samen wat er is gebouwd. Geef de gebruiker een concrete, korte lijst van wat te checken (stappen of URLs), niet alleen "graag reviewen".
3. **Wait for explicit confirmation.** Ask the user directly whether it's approved. Only these count as approval: an unambiguous "akkoord", "goedgekeurd", "klopt, done", or equivalent tied to this specific ticket. Anything else (silence, a question, "ziet er ok uit maar...") is NOT approval — ask again or treat as rejection feedback.
4. **On approval.** `mcp__asana__update_tasks` with `add_projects: [{project_id: config.project.gid, section_id: <GID of workflow.done>}]` and `completed: true`. Post een afsluitende comment in het Nederlands via `mcp__asana__add_comment` met wie akkoord gaf en wanneer.
5. **On rejection.** Move back to `workflow.in_progress` (`add_projects` with that `section_id`), en plaats de feedback van de gebruiker als Nederlandse comment zodat `asana-worker` er de volgende keer met volledige context mee verder kan.

## Quick Reference

| User response | Action |
|---|---|
| Explicit approval | Move to `done`, mark `completed: true` |
| Explicit rejection / requested changes | Move to `in_progress`, log feedback as comment |
| Ambiguous / no response | Do nothing to the ticket — ask again |

## Common Mistakes

- **Treating a code-review pass as user approval** — `asana-review` clearing a ticket only gets it into `user_review`; only the human's own words in this chat move it to `done`.
- **Auto-approving because the change looks small or safe** — the whole point of this skill is that the human decides, not Claude.
- **Moving to Done before posting the closing comment** — do both, but never skip the audit trail.
