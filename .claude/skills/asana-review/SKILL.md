---
name: asana-review
description: Use when the user asks to review a specific KOZ ticket from the Kozijnr Asana board, e.g. "review KOZ-7", "doe een code review op KOZ-3", or when a ticket is sitting in Ready for review and needs to go through review.
---

# Asana Review

## Overview

Runs a ticket in "Ready for review" through code review, quality, and security checks via subagents, posts the findings as an Asana comment, and routes the ticket forward (User review) or back (In progress) based on the outcome.

**REQUIRED BACKGROUND:** Read `.claude/asana.config.json` for `project.gid` and `workflow` section names/GIDs. Cross-reference the `code-review` and `security-review` skills — this skill orchestrates them, it doesn't replace them.

**Taal:** alle output naar de gebruiker en alle Asana-comments zijn in het Nederlands.

**Handoff van `asana-worker`:** verwacht een comment die begint met "▶ Overgedragen aan review" (zie stap 3). **Handoff naar `asana-user-review`:** bij een geslaagde review plaats je zelf een comment met een vast herkenbaar format (zie stap 5) zodat `asana-user-review` weet wat te testen.

## When to Use

- User names a specific ticket to review, or asks to process what's in "Ready for review"
- Not for the human-facing functional sign-off — see `asana-user-review`
- Not for implementing the ticket — see `asana-worker`

## Workflow

1. **Find the ticket.** `mcp__asana__get_tasks` with `project=config.project.gid`, `opt_fields=name,gid,notes,memberships.section.name`. Match on `"<ticket_prefix>-<n>:"`. If ambiguous or not found, ask the user rather than guessing.
2. **Move to Review.** `mcp__asana__update_tasks` → `add_projects: [{project_id: config.project.gid, section_id: <GID of workflow.in_review>}]`.
3. **Read the trail.** `mcp__asana__get_task_stories` on the ticket to get the "▶ Overgedragen aan review" comment left by `asana-worker` (branch/PR/samenvatting/teststappen).
4. **Run the checks in parallel subagents.** Dispatch separate `Agent` calls for:
   - Code review (correctness, simplification, reuse) — invoke the `code-review` skill's approach
   - Security review — invoke the `security-review` skill's approach
   - Any quality/test checks implied by the ticket (e.g. does the test suite pass)
   Each subagent should report findings, not fix them.
5. **Aggregate, post, en (bij akkoord) draag over.** Combineer de bevindingen tot één comment via `mcp__asana__add_comment`, in het Nederlands:
   - **Geen blokkerende bevindingen** — gebruik dit vaste format zodat `asana-user-review` het herkent als overdracht:
     ```
     ▶ Overgedragen aan user review

     Verdict: goedgekeurd
     Bevindingen: <korte lijst, of "geen">
     Hoe te testen: <korte teststappen voor de mens>
     ```
   - **Blokkerende bevindingen** — plaats de bevindingen (ernstigste eerst) met verdict "wijzigingen nodig", geen overdrachtsformat.
6. **Route the ticket.**
   - No blocking findings → move to `workflow.user_review` (`add_projects` with that `section_id`).
   - Blocking findings → move back to `workflow.in_progress` and tell the user what needs fixing before it can re-enter review.
7. **Rapporteer aan de gebruiker.** Meld in het Nederlands het verdict en waar het ticket geland is.

## Quick Reference

| Outcome | Section (from `config.workflow`) |
|---|---|
| Review passed | `user_review` |
| Changes requested | `in_progress` |

## Common Mistakes

- **Fixing issues instead of reporting them** — this skill's subagents review; `asana-worker` (re-dispatched by the user or automatically re-triggered) fixes.
- **Posting raw subagent transcripts as the comment** — synthesize one readable verdict + findings list, don't paste tool noise into Asana.
- **Moving to User review with unresolved blocking findings** — the gate only opens on a clean or explicitly-accepted result.
