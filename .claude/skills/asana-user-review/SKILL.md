---
name: asana-user-review
description: Use when a KOZ ticket from the Kozijnr Asana board has passed code review and needs the human's functional sign-off before it can be marked Done, or when the user asks to finalize/approve/afronden a ticket.
---

# Asana User Review

## Overview

Handles the final human gate: presents a ticket sitting in "User review" for functional testing, then waits for an explicit go/no-go from the user in chat before touching "Done". For any ticket that reaches this skill, this is a hard stop — no ticket in `user_review` reaches Done without an explicit confirmation in this conversation.

**Elk ticket komt hier langs.** `asana-review` mergt nooit zelfstandig en heeft geen Done-route — elke geslaagde review gaat naar `user_review`. Deze skill is dus de enige plek waar naar `main` wordt gemergd en waar een ticket op Done wordt gezet, en dat gebeurt uitsluitend na een expliciet akkoord van de gebruiker in deze chat.

**REQUIRED BACKGROUND:** Read `.claude/asana.config.json` for `project.gid` and `workflow` section names/GIDs.

**Taal:** alle output naar de gebruiker en alle Asana-comments zijn in het Nederlands.

**Handoff van `asana-review`:** verwacht een comment die begint met "▶ Overgedragen aan user review" (zie stap 2) — dat is het testplan, inclusief het worktree-pad/de branchnaam van het ticket.

**Git worktree + branch:** elk ticket heeft er een (geen uitzonderingen meer — zie `asana-worker`). Blijven bestaan tot dit ticket akkoord krijgt. Bij akkoord ruimt deze skill ze zelf op, ná het mergen naar `main`, als onderdeel van stap 4 (de move naar Done) — zie hieronder.

## When to Use

- A ticket just came out of `asana-review` clean and landed in "User review"
- User asks to finalize, approve, or "afronden" a specific ticket

Not for code/security review — see `asana-review`. Not for implementation — see `asana-worker`.

## Workflow

1. **Find the ticket.** `mcp__asana__get_tasks` with `project=config.project.gid`, matching `"<ticket_prefix>-<n>:"`. If it's not in the `user_review` section yet, say so and stop — don't move it there yourself.
2. **Present a test plan.** Pull the ticket's `notes` and its comment trail (`mcp__asana__get_task_stories`) — specifically the "▶ Overgedragen aan user review" comment from `asana-review` — and summarize in het Nederlands what was built. Give the user a concrete, short checklist of what to verify (steps or URLs), not just "please review".
3. **Wait for explicit confirmation.** Ask the user directly whether it's approved. Only these count as approval: an unambiguous "akkoord", "goedgekeurd", "klopt, done", or equivalent tied to this specific ticket. Anything else (silence, a question, "ziet er ok uit maar...") is NOT approval — ask again or treat as rejection feedback.
4. **On approval.**
   a. **Locate the ticket's worktree/branch.** Check the comment trail for the worktree path/branch name (from `asana-worker`'s and `asana-review`'s handoff comments), and/or run `git worktree list` / `git branch --list koz-<n>`.
   b. **Merge it:** run the normal git workflow — `git checkout main && git pull`, then `git merge --no-ff koz-<n>`.
      - **On a merge conflict:** never resolve it automatically. Stop, post a Nederlandse comment describing the conflict, leave the ticket in `user_review` (do not move to Done), and ask the user how to proceed.
      - **On a clean, conflict-free merge:** proceed to worktree/branch cleanup below.
      - If a parallel `asana-user-review` run might also be merging to `main` right now, treat merges to `main` as sequential/isolated — don't run this merge concurrently with another ticket's merge; if a conflict appears because of a concurrent merge, `git fetch`/`git pull` and retry once, or escalate.
   c. **Remove the worktree and branch — only after a confirmed successful merge.** `git worktree remove <pad>`, then `git branch -d <branch>` as first choice; if that fails (e.g. after a squash-merge, where git doesn't recognize the branch as merged), explicitly verify the branch's content is actually present in `main` (compare the latest commit hash/diff) before falling back to a forced `git branch -D <branch>` — never force-delete without that verification.
   d. `mcp__asana__update_tasks` with `add_projects: [{project_id: config.project.gid, section_id: <GID of workflow.done>}]` and `completed: true`.
   e. Post een afsluitende comment in het Nederlands via `mcp__asana__add_comment` met wie akkoord gaf en wanneer, en dat de worktree/branch zijn opgeruimd.
5. **On rejection.** Move back to `workflow.in_progress` (`add_projects` with that `section_id`), en plaats de feedback van de gebruiker als Nederlandse comment zodat `asana-worker` er de volgende keer met volledige context mee verder kan. **Worktree en branch (indien aanwezig) blijven staan** — pas opruimen bij een latere Done, niet bij afwijzing.

## Quick Reference

| User response | Action |
|---|---|
| Explicit approval | Merge branch → `main` (stop on conflict), remove worktree + branch (verify before force-delete after a squash-merge), move to `done`, mark `completed: true` |
| Merge conflict during approval | Stop, comment, stay in `user_review` — never auto-resolve |
| Explicit rejection / requested changes | Move to `in_progress`, log feedback as comment, leave worktree/branch in place |
| Ambiguous / no response | Do nothing to the ticket — ask again |

## Common Mistakes

- **Treating a code-review pass as user approval** — `asana-review` clearing a ticket into `user_review` is not approval; only the human's own words in this chat move it to `done`.
- **Auto-approving because the change looks small or safe** — the whole point of this skill is that the human decides, not Claude. Every ticket reaches this skill; there is no route that skips it.
- **Moving to Done before posting the closing comment** — do both, but never skip the audit trail.
- **Removing the worktree/branch before approval, or before merging to `main`** — cleanup is the last thing that happens, strictly after an explicit "akkoord" and after a confirmed successful merge, as part of the same Done step.
- **Auto-resolving a merge conflict, or moving to Done on a conflicted/failed merge** — stop and let the user decide.
- **Force-deleting a branch without verifying the merge actually landed in `main`** — especially after a squash-merge, where `git branch -d` correctly refuses.
- **Cleaning up the worktree/branch on rejection** — a rejected ticket goes back to `in_progress` with its worktree/branch intact so `asana-worker` can keep working on it.
