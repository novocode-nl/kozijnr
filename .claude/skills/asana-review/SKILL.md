---
name: asana-review
description: Use when the user asks to review a specific KOZ ticket from the Kozijnr Asana board, e.g. "review KOZ-7", "doe een code review op KOZ-3", or when a ticket is sitting in Ready for review and needs to go through review.
---

# Asana Review

## Overview

Runs a ticket in "Ready for review" through code review, quality, and security checks via subagents, posts the findings as an Asana comment, and routes the ticket forward based on the outcome: no blocking findings → always **User review** (a human must weigh in before anything reaches `main`) — there is no AI-autonomous Done route, full stop. On blocking findings it routes back to **Todo** instead (not In progress), with a "Rework nodig" comment so the findings are folded into the next pass.

**Geen auto-merge, ooit.** Deze skill mergt nooit naar `main` en zet nooit zelfstandig een ticket op Done. Elke geslaagde review gaat naar `user_review`; alleen `asana-user-review` mergt naar `main`, en alleen ná een expliciete "akkoord" van de gebruiker in de chat. Dit is een bewuste, door de gebruiker vastgestelde regel (2026-08-18, na een eerdere — inmiddels weer teruggedraaide — poging om een beperkte auto-Done-route toe te staan): geen enkele bestandstypenlijst of tijdelijke versoepeling maakt hier nog een uitzondering op.

**REQUIRED BACKGROUND:** Read `.claude/asana.config.json` for `project.gid` and `workflow` section names/GIDs. Cross-reference the `code-review` en `security-review` skills — this skill orchestrates them, it doesn't replace them.

**Taal:** alle output naar de gebruiker en alle Asana-comments zijn in het Nederlands.

**Handoff van `asana-worker`:** verwacht een comment die begint met "▶ Overgedragen aan review" (zie stap 3), inclusief het worktree-pad/de branchnaam en de GitHub PR-URL van het ticket. **Handoff naar `asana-user-review`:** bij een geslaagde review plaats je zelf een comment met een vast herkenbaar format (zie stap 5) zodat `asana-user-review` weet wat te testen.

**Deze skill raakt de PR nooit aan** — niet mergen, niet sluiten, geen PR-comments plaatsen. De PR (aangemaakt door `asana-worker`) is er puur voor de diff-view en om later gemerged te worden door `asana-user-review`; het volledige review-audit-trail blijft in Asana, de enige bron van waarheid.

**Worktree/branch blijven bestaan:** de git worktree en branch die `asana-worker` voor dit ticket heeft opgezet, blijven staan — deze skill ruimt ze nooit op en mergt ze nooit. Dat gebeurt uitsluitend in `asana-user-review`, na akkoord van de gebruiker.

## When to Use

- User names a specific ticket to review, or asks to process what's in "Ready for review"
- Not for the human-facing functional sign-off — see `asana-user-review`
- Not for implementing the ticket — see `asana-worker`

## Workflow

1. **Find the ticket.** `mcp__asana__get_tasks` with `project=config.project.gid`, `opt_fields=name,gid,notes,memberships.section.name`. Match on `"<ticket_prefix>-<n>:"`. If ambiguous or not found, ask the user rather than guessing.
2. **Move to Review.** `mcp__asana__update_tasks` → `add_projects: [{project_id: config.project.gid, section_id: <GID of workflow.in_review>}]`.
3. **Read the trail.** `mcp__asana__get_task_stories` on the ticket to get the "▶ Overgedragen aan review" comment left by `asana-worker` (branch/PR/samenvatting/teststappen).
3b. **Broad/repo-wide ticket: confirm the branch is still current with `main`.** If the ticket's scope touches many/most files across the codebase (a repo-wide cleanup, a cross-cutting restructuring, or similar — not an ordinary feature ticket), check before starting the actual review whether the branch is up to date with `main` (`git fetch origin && git log <branch>..origin/main --oneline`, or check whether the branch was created/updated recently enough relative to `main`'s current tip). If `main` has moved on since, the diff may already be stale — stop and ask the user to have `asana-worker` rebase/update the branch before review continues, rather than reviewing a diff that silently doesn't reflect the current codebase.
4. **Run the checks in parallel subagents.** Dispatch separate `Agent` calls for:
   - Code review (correctness, simplification, reuse) — invoke the `code-review` skill's approach
   - Security review — invoke the `security-review` skill's approach
   - Any quality/test checks implied by the ticket (e.g. does the test suite pass)
   Each subagent should report findings, not fix them.
5. **Aggregate, post, and route.** Combine the findings into one comment via `mcp__asana__add_comment`, written in het Nederlands.
   - **No blocking findings** — use this fixed format so `asana-user-review` recognizes it as a handoff:
     ```
     ▶ Overgedragen aan user review

     Verdict: goedgekeurd
     Bevindingen: <korte lijst, of "geen">
     Hoe te testen: <korte teststappen voor de mens>
     ```
   - **Blocking findings** — use this fixed format so the findings are carried forward as rework on the next pass:
     ```
     Rework nodig

     Verdict: wijzigingen nodig
     Bevindingen: <lijst, ernstigste eerst, elk met bestand/regio en concreet scenario>
     ```
6. **Route the ticket.**
   - No blocking findings → move to `workflow.user_review` (`add_projects` with that `section_id`). Worktree/branch stay in place — this skill never merges and never touches `main`.
   - Blocking findings → move back to `workflow.todo` (not `in_progress`) and tell the user what needs fixing before it can re-enter review. The "Rework nodig" comment (see step 5) carries the findings forward so they get picked up as part of the ticket's next implementation pass. Worktree/branch stay in place — `asana-worker` continues on it.
7. **Report to the user.** Report in het Nederlands the verdict and where the ticket landed.

## Quick Reference

| Outcome | Section (from `config.workflow`) |
|---|---|
| Review passed | `user_review` (always — never a direct Done route) |
| Changes requested (Rework nodig) | `todo` |

## Common Mistakes

- **Fixing issues instead of reporting them** — this skill's subagents review; `asana-worker` (re-dispatched by the user or automatically re-triggered) fixes.
- **Posting raw subagent transcripts as the comment** — synthesize one readable verdict + findings list, don't paste tool noise into Asana.
- **Moving to User review with unresolved blocking findings** — the gate only opens on a clean or explicitly-accepted result.
- **Merging to `main`, or moving a ticket straight to Done from this skill** — this skill never does either, regardless of how small, safe, or purely backend the change looks. Every clean review goes to `user_review`; only `asana-user-review` merges and marks Done, and only after an explicit human "akkoord".
- **Cleaning up the worktree/branch when moving to `user_review`** — this skill never merges or cleans up a worktree/branch; that only happens in `asana-user-review`, after approval.
- **Moving blocking findings to `in_progress` instead of `todo`** — blocking findings route back to `workflow.todo` with a "Rework nodig" comment, not straight to `in_progress`; the findings need to be picked up as scoped rework, not silently resumed mid-implementation.
- **Reviewing a broad/repo-wide ticket's diff without checking it's still current with `main`** — a codebase-wide change can go stale while other tickets merge in parallel; step 3b's check comes before the actual review, not after.
