---
name: asana-user-review
description: Use when a KOZ ticket from the Kozijnr Asana board has passed code review and needs the human's functional sign-off before it can be marked Done, or when the user asks to finalize/approve/afronden a ticket.
---

# Asana User Review

## Overview

Handles the final human gate: presents a ticket sitting in "User review" for functional testing, then waits for an explicit go/no-go from the user in chat before touching "Done". For any ticket that reaches this skill, this is a hard stop — no ticket in `user_review` reaches Done without an explicit confirmation in this conversation.

**Elk ticket komt hier langs.** `asana-review` mergt nooit zelfstandig en heeft geen Done-route — elke geslaagde review gaat naar `user_review`. Deze skill is dus de enige plek waar de PR wordt gemerged en waar een ticket op Done wordt gezet, en dat gebeurt uitsluitend na een expliciet akkoord van de gebruiker in deze chat.

**Nooit zelf code wijzigen.** Deze skill test en beoordeelt, maar past nooit zelf implementatiecode aan — ook niet voor een triviale fix. Bij afwijzing gaat het ticket terug naar `todo` met de feedback als comment (zie stap 5), zodat `asana-worker` de daadwerkelijke wijziging doet in een nieuwe pass. Alleen commands die puur *verifiëren* (bv. `make up`, `curl`, tests draaien) horen hier thuis.

**REQUIRED BACKGROUND:** Read `.claude/asana.config.json` for `project.gid` and `workflow` section names/GIDs. `gh` (GitHub CLI) moet geauthenticeerd zijn — deze skill merget de ticket-PR via `gh pr merge`, niet via een lokale `git merge`.

**Taal:** alle output naar de gebruiker en alle Asana-comments zijn in het Nederlands.

**Handoff van `asana-review`:** verwacht een comment die begint met "▶ Overgedragen aan user review" (zie stap 2) — dat is het testplan, inclusief het worktree-pad/de branchnaam van het ticket.

**Git worktree + branch:** elk ticket heeft er een (geen uitzonderingen meer — zie `asana-worker`). Blijven bestaan tot dit ticket akkoord krijgt. Bij akkoord ruimt deze skill ze zelf op, ná het mergen naar `main`, als onderdeel van stap 4 (de move naar Done) — zie hieronder.

## When to Use

- A ticket just came out of `asana-review` clean and landed in "User review"
- User asks to finalize, approve, or "afronden" a specific ticket

Not for code/security review — see `asana-review`. Not for implementation — see `asana-worker`.

## Workflow

1. **Find the ticket.** `mcp__asana__get_tasks` with `project=config.project.gid`, matching `"<ticket_prefix>-<n>:"`. If it's not in the `user_review` section yet, say so and stop — don't move it there yourself.
2. **Present a test plan.** Pull the ticket's `notes` and its comment trail (`mcp__asana__get_task_stories`) — specifically the "▶ Overgedragen aan user review" comment from `asana-review` and, further back in the trail, the "▶ Overgedragen aan review" comment from `asana-worker` that has the PR URL — and summarize in het Nederlands what was built. Give the user a concrete, short checklist of what to verify (steps or URLs), not just "please review". **Always include the PR URL** in this summary, so the user can open the GitHub diff alongside the test plan.
3. **Wait for explicit confirmation.** Ask the user directly whether it's approved. Only these count as approval: an unambiguous "akkoord", "goedgekeurd", "klopt, done", or equivalent tied to this specific ticket. Anything else (silence, a question, "ziet er ok uit maar...") is NOT approval — ask again or treat as rejection feedback.
4. **On approval.**
   a. **Locate the ticket's PR/worktree/branch.** Check the comment trail for the PR URL (from `asana-worker`'s handoff comment) and the worktree path/branch name, and/or run `gh pr list --head koz-<n>` and `git worktree list` / `git branch --list koz-<n>`.
   b. **Merge the PR:** `gh pr merge koz-<n> --merge --delete-branch` (merge commit, not squash — matches this project's convention of keeping each review round's commits visible; `--delete-branch` removes the *remote* branch).
      - **On a merge conflict:** never resolve it automatically. Stop, post a Nederlandse comment describing the conflict, leave the ticket in `user_review` (do not move to Done), and ask the user how to proceed.
      - **On a clean, conflict-free merge:** run `git checkout main && git pull` to bring the merge commit into the local repo, then proceed to worktree/branch cleanup below.
      - If a parallel `asana-user-review` run might also be merging right now, treat merges as sequential/isolated — don't run this merge concurrently with another ticket's merge; if a conflict appears because of a concurrent merge, retry once via `gh pr merge`, or escalate.
   c. **Remove the worktree and local branch — only after a confirmed successful merge.** First stop any containers still running for this ticket's worktree (`docker compose -p koz-<n> down`, or `make down` from inside the worktree) — a running container's bind mount can block the directory removal. Then `git worktree remove <pad>`, then `git branch -d <branch>` as first choice; if that fails (e.g. after a squash-merge, where git doesn't recognize the branch as merged), explicitly verify the branch's content is actually present in `main` (compare the latest commit hash/diff) before falling back to a forced `git branch -D <branch>` — never force-delete without that verification. (`--delete-branch` in step b already removed the *remote* branch; this step is the *local* branch and worktree.)
   d. `mcp__asana__update_tasks` with `add_projects: [{project_id: config.project.gid, section_id: <GID of workflow.done>}]` and `completed: true`.
   e. Post een afsluitende comment in het Nederlands via `mcp__asana__add_comment` met wie akkoord gaf en wanneer, en dat de worktree/branch zijn opgeruimd.
5. **On rejection.** Move back to `workflow.todo` (`add_projects` with that `section_id`, not `in_progress`), en plaats de feedback als een Nederlandse comment in dit vaste format zodat het herkenbaar is als rework en `asana-worker` er de volgende keer met volledige context mee verder kan:
   ```
   Rework nodig

   Feedback van functionele review: <de feedback van de gebruiker, in het Nederlands samengevat of letterlijk>
   ```
   **Worktree, branch en PR (indien aanwezig) blijven staan** — pas opruimen bij een latere Done, niet bij afwijzing. Sluit de PR niet.

## Quick Reference

| User response | Action |
|---|---|
| Explicit approval | `gh pr merge --merge --delete-branch` (stop on conflict), `git pull`, remove local worktree + branch (verify before force-delete after a squash-merge), move to `done`, mark `completed: true` |
| Merge conflict during approval | Stop, comment, stay in `user_review` — never auto-resolve |
| Explicit rejection / requested changes | Move to `todo`, log a "Rework nodig" comment with the feedback, leave worktree/branch/PR in place |
| Ambiguous / no response | Do nothing to the ticket — ask again |

## Common Mistakes

- **Forgetting to surface the PR URL when presenting the test plan** — the user should be able to open the GitHub diff without digging through the comment trail themselves.
- **Treating a code-review pass as user approval** — `asana-review` clearing a ticket into `user_review` is not approval; only the human's own words in this chat move it to `done`.
- **Auto-approving because the change looks small or safe** — the whole point of this skill is that the human decides, not Claude. Every ticket reaches this skill; there is no route that skips it.
- **Fixing the issue yourself instead of routing it back for rework** — this skill only tests/verifies (`make up`, `curl`, running tests); it never edits implementation code, even for a one-line fix. That's `asana-worker`'s job, on the next pass.
- **Moving to Done before posting the closing comment** — do both, but never skip the audit trail.
- **Removing the worktree/branch before approval, or before merging the PR** — cleanup is the last thing that happens, strictly after an explicit "akkoord" and after a confirmed successful merge, as part of the same Done step.
- **Trying to remove the worktree while its containers are still running** — a bind-mounted directory can fail to delete with a permission error; stop the ticket's stack first (`docker compose -p koz-<n> down`).
- **Auto-resolving a merge conflict, or moving to Done on a conflicted/failed merge** — stop and let the user decide.
- **Force-deleting a branch without verifying the merge actually landed in `main`** — especially after a squash-merge, where `git branch -d` correctly refuses.
- **Moving a rejected ticket to `in_progress` instead of `todo`** — rejection is scoped rework, not a silent resume; it goes to `todo` with a "Rework nodig" comment, same convention as `asana-review`'s blocking-findings route.
- **Cleaning up the worktree/branch/PR on rejection** — a rejected ticket keeps its worktree, branch, and open PR intact so `asana-worker` can keep working on it.
