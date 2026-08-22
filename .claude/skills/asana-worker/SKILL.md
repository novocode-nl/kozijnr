---
name: asana-worker
description: Use when the user asks to pick up, start, or implement one or more KOZ tickets from the Kozijnr Asana board, e.g. "pak KOZ-7 op", "werk KOZ-3 uit", "implementeer dit ticket", "pak alle openstaande tickets op".
---

# Asana Worker

## Overview

Takes every named (or all "Todo") KOZ ticket through implementation to "Ready for review": looks each one up, moves it to "In progress", and dispatches the actual build work to **one dedicated subagent per ticket**, run in parallel. Each subagent's work is isolated — one ticket's implementation never shares context or state with another's — then each ticket gets its own handoff comment and move to "Ready for review" as its subagent finishes.

**REQUIRED BACKGROUND:** Read `.claude/asana.config.json` for `project.gid` and `workflow` section names/GIDs before doing anything else. Cross-reference `superpowers:dispatching-parallel-agents` for how to fan out independent subagents in one batch.

**Taal:** alle output naar de gebruiker en alle Asana-comments zijn in het Nederlands.

**Handoff naar `asana-review`:** de laatste comment die deze skill per ticket plaatst is het overdrachtssignaal. `asana-review` leest de comment-trail (`get_task_stories`) en verwacht die comment om te weten wat er gebouwd is — zie stap 5/6.

**Git worktree + branch per ticket, altijd — geen uitzondering.** Elke subagent werkt in zijn eigen git worktree op een eigen branch (zie stap 4), ook voor meta-werk aan skill-/configbestanden onder `.claude/`. Er is geen uitzondering meer die direct op `main` mag committen: alles gaat via een branch en een GitHub PR, en die wordt pas gemergd nadat de gebruiker het ticket in `asana-user-review` heeft goedgekeurd. Die worktree/branch/PR blijft bestaan tot en met de Done-afhandeling — opruimen gebeurt pas ná het mergen, uitsluitend door `asana-user-review` na akkoord van de gebruiker. Zie `asana-user-review` voor die opruimstap.

**Elk ticket krijgt een PR.** Na de commit pusht elke subagent zijn branch en maakt een GitHub PR aan (`gh pr create`, zie stap 4) — dat is waar `asana-user-review` straks tegen mergt. `gh` moet geauthenticeerd zijn; als `gh` niet beschikbaar/geauthenticeerd is, stop en meld dat aan de gebruiker in plaats van zonder PR verder te gaan.

## When to Use

- User names one or more specific tickets (`KOZ-N`) and asks to start/implement/pick them up
- User asks broadly to pick up whatever is ready (e.g. "pak alles op wat klaarstaat in Todo")
- Not for reviewing finished work — see `asana-review`
- Not for creating tickets — see `asana-create`

## Workflow

1. **Find the ticket(s).** Call `mcp__asana__get_tasks` with `project=config.project.gid` and `opt_fields=name,gid,completed,notes,memberships.section.name,dependencies.name,dependencies.completed`.
   - Named ticket(s): filter client-side for tasks whose `name` starts with `"<ticket_prefix>-<n>:"`. If a named ticket isn't found, stop and ask — never guess which ticket.
   - Broad request ("pak alles op"): take every task currently in the `workflow.todo` section.
   - If nothing matches, tell the user there's nothing to pick up and stop.
1b. **Check each ticket's Asana dependencies for an open (not-yet-merged) blocker PR.** A dependency being marked `completed: true` in Asana is not the same as its PR actually being merged to `main` — `asana-user-review` can leave a ticket functionally approved but still in `user_review` (PR still open) when the user has chosen to stack a dependent ticket's PR on top of it instead of merging right away (see `asana-user-review`'s "Stacked PR's" section). For every ticket about to be dispatched, check whether any of its dependencies has an open PR not yet merged into `main` (`gh pr list --head koz-<blocker-n>` / `gh pr view <n> --json state`).
    - **Found an unmerged blocker PR:** don't silently pick a strategy. Ask the user, per ticket, in one sentence: wachten tot de blocker-PR gemerged is naar `main`, of dit ticket als gestapelde PR bovenop de blocker's branch beginnen (`gh stack`, zie stap 4)? Only proceed with that ticket once answered.
    - **No unmerged blocker PR found** (dependency is Done and actually merged, or there is no dependency): proceed as normal, base the branch on `main` as always.
2. **Move each to In progress.** For every ticket found, call `mcp__asana__update_tasks` (batched, one call for all of them) with `add_projects: [{project_id: config.project.gid, section_id: <GID of workflow.in_progress>}]` per task. Re-adding a task to the project it's already in, with a different `section_id`, is how Asana moves it between board columns.
3. **Dispatch one subagent per ticket, in parallel.** In a single message, launch one `Agent` call per ticket — never one agent handling multiple tickets, and never tickets processed sequentially one Agent call at a time. Each subagent gets only its own ticket's context: title, the full "Doel / Verwacht eindproduct / Out of scope / Definition of Done / Kernpunten" notes, and its ticket ID.
4. **Each subagent's own job** (this is what you put in that subagent's prompt, not something you do here):
   - **Set up an isolated git worktree + branch before touching any code — always, no exceptions.** Invoke `superpowers:using-git-worktrees` (native tool if available, otherwise the `git worktree add`/`.worktrees/` fallback it describes). Name the branch after the ticket, e.g. `koz-<n>` (lowercase ticket prefix + number, matching `"<ticket_prefix>-<n>"` from the ticket title). One worktree/branch per ticket — never share one across tickets, and never implement directly on `main` or on whatever branch the top-level session happens to be on. This applies even to meta-work on skill/config/documentation files under `.claude/`: there is no `.claude/`-only exception anymore, since it let work land on `main` before any review or human approval — every ticket, regardless of what it touches, goes through a branch and only reaches `main` via `asana-user-review`'s post-approval merge.
     - **Branch base is normally `main`.** The one exception: if step 1b's check found an unmerged blocker PR and the user chose to stack, base this ticket's branch on the blocker's branch instead (`git worktree add ... -b koz-<n> koz-<blocker-n>`, not `main`), and use `gh stack` (`gh extension install github/gh-stack` if not already installed) so the resulting PR targets the blocker's branch, forming a chain — not `gh pr create --base main`.
   - Read the ticket's "Verwacht eindproduct" and "Kernpunten" to judge scope: backend, frontend, or both.
   - Load `kozijnr-backend` and/or `kozijnr-frontend` accordingly (both, if the ticket spans the stack), plus whatever further skills/subagents those pull in (TDD, systematic-debugging, `shadcn`, etc.).
   - Treat the ticket's "Definition of Done" as the acceptance test for its own work, not a suggestion.
   - **Commit the work before handing off.** Once implementation is complete, commit it on the ticket's own branch with a clear, descriptive commit message referencing the ticket (e.g. `KOZ-<n>: <short summary>`). Do this *before* pushing/opening the PR: an uncommitted working tree has nothing to push.
   - **Broad/repo-wide ticket: rebase onto current `main` right before pushing.** If the ticket's scope touches many/most files across the codebase (a repo-wide cleanup, a cross-cutting restructuring, or similar — not an ordinary feature ticket that touches a handful of files), check right before pushing whether new commits have landed on `main` since the branch was created (`git fetch origin && git log HEAD..origin/main --oneline`). If there are any, merge/rebase `origin/main` into the branch first (same approach as the branch already used, e.g. `git merge origin/main`) and resolve any conflicts before continuing — a repo-wide change silently goes stale the moment another ticket merges in parallel, so this check is not optional for that kind of ticket.
   - **Push the branch and open a PR.** `git push -u origin koz-<n>`, then `gh pr create --base main --head koz-<n> --title "KOZ-<n>: <ticket title>" --body "<Wat is gebouwd + Hoe te verifiëren, same content as the Asana handoff comment below>"` — or, if this ticket is stacked per step 1b/4, `gh stack`'s own PR-creation flow with `--base koz-<blocker-n>` instead of `main`. This is the PR `asana-user-review` will later merge (or, for a stacked PR, merge as part of the stack) — do this *before* posting the progress/handoff comment in step 5, since that comment includes the PR URL.
   - **Leave the worktree, branch, and PR in place when done** — do not remove or close them after implementation. They stay alive through `asana-review` and `asana-user-review`; cleanup only happens once the ticket reaches Done (see `asana-user-review`).
   - Report back: what was built, which files/branch/worktree path/commit/PR URL, and concrete steps to verify it.
5. **Per ticket, as its subagent finishes: log progress + handoff.** Call `mcp__asana__add_comment` on that specific ticket in het Nederlands, met dit vaste format zodat `asana-review` het als overdracht herkent:

   ```
   ▶ Overgedragen aan review

   Wat is gebouwd: <korte samenvatting>
   Bestanden/branch: <worktree-pad + branchnaam (bv. koz-7) + commit-hash>
   PR: <GitHub PR-URL>
   Hoe te verifiëren: <korte teststappen>
   ```
6. **Move that ticket to Ready for review.** Call `mcp__asana__update_tasks` with `add_projects: [{project_id: config.project.gid, section_id: <GID of workflow.ready_for_review>}]`. Do this per ticket as soon as its subagent and comment are done — don't wait for the slowest ticket in the batch to move the fast ones.
7. **Rapporteer aan de gebruiker.** Meld in het Nederlands, per ticket, dat het in "Ready for review" staat, met de samenvatting van stap 5.

## Quick Reference

| Step | Section (from `config.workflow`) |
|---|---|
| Start work | `in_progress` |
| Finish work | `ready_for_review` |

Moving section = `update_tasks` → `add_projects: [{project_id, section_id}]`, not a separate "move" call.

One `Agent` call per ticket, all dispatched together — never combine tickets into one subagent, never dispatch them one at a time.

## Common Mistakes

- **Matching the wrong ticket** — `KOZ-1` is a text prefix of `KOZ-10`; always match on `"KOZ-N:"` including the colon, not a bare substring.
- **Doing the implementation inline** instead of dispatching a subagent — loses the isolation the user asked for and pollutes this session's context.
- **One subagent handling several tickets** — defeats the isolation; a bug or bad assumption in one ticket's work shouldn't be able to bleed into another's context.
- **Waiting for all tickets before moving any to Ready for review** — route each ticket the moment its own subagent and handoff comment are done.
- **Skipping the progress comment** — `asana-review` and the human need to know what changed and why without re-reading the whole diff from scratch.
- **Implementing directly on `main` / the current working tree** instead of a dedicated worktree+branch — breaks the per-ticket isolation and risks one ticket's half-finished work bleeding into another's.
- **Removing the worktree/branch/PR right after implementation** — they must survive `asana-review` and `asana-user-review`; only the Done step in `asana-user-review` cleans them up, and only after merging.
- **Posting the handoff comment before committing and opening the PR** — `asana-user-review`'s merge step assumes a PR exists with the committed work; always commit, push, and `gh pr create` first, then post the handoff comment with the PR URL.
- **Skipping the worktree for `.claude/`-only or "meta" tickets** — there is no such exception anymore; every ticket, including skill/config/doc-only work, gets its own worktree/branch/PR and only reaches `main` through `asana-user-review`'s post-approval merge.
- **Skipping the PR because `gh` isn't set up** — don't silently fall back to "no PR, just a branch"; stop and tell the user `gh` needs to be installed/authenticated.
- **Assuming a dependency's `completed: true` means its PR is merged** — `asana-user-review` can leave a functionally-approved ticket in `user_review` with its PR still open if the user chose to stack a dependent ticket on top of it (step 1b). Check the actual PR state (`gh pr view`), not just the Asana dependency's completion flag, before basing a new branch on `main`.
- **Silently deciding to stack (or not) without asking** — step 1b is not a judgment call to make alone; an unmerged blocker PR always means asking the user wait-vs-stack per ticket, every time.
- **Pushing a broad/repo-wide ticket without checking for new commits on `main` first** — a codebase-wide cleanup or restructuring can silently go stale while other tickets merge in parallel; always check `git log HEAD..origin/main` and rebase/merge before the final push for that kind of ticket.
