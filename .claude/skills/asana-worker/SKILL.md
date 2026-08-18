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

**Git worktree + branch per ticket:** elke subagent werkt in zijn eigen git worktree op een eigen branch (zie stap 4). Die worktree/branch blijft bestaan tot en met de Done-afhandeling — opruimen gebeurt pas ná het mergen naar `main`, als onderdeel van de move naar Done (door `asana-review` bij een directe Done-route, of door `asana-user-review` na akkoord van de gebruiker). Zie `asana-review` en `asana-user-review` voor die opruimstap.

## When to Use

- User names one or more specific tickets (`KOZ-N`) and asks to start/implement/pick them up
- User asks broadly to pick up whatever is ready (e.g. "pak alles op wat klaarstaat in Todo")
- Not for reviewing finished work — see `asana-review`
- Not for creating tickets — see `asana-create`

## Workflow

1. **Find the ticket(s).** Call `mcp__asana__get_tasks` with `project=config.project.gid` and `opt_fields=name,gid,completed,notes,memberships.section.name`.
   - Named ticket(s): filter client-side for tasks whose `name` starts with `"<ticket_prefix>-<n>:"`. If a named ticket isn't found, stop and ask — never guess which ticket.
   - Broad request ("pak alles op"): take every task currently in the `workflow.todo` section.
   - If nothing matches, tell the user there's nothing to pick up and stop.
2. **Move each to In progress.** For every ticket found, call `mcp__asana__update_tasks` (batched, one call for all of them) with `add_projects: [{project_id: config.project.gid, section_id: <GID of workflow.in_progress>}]` per task. Re-adding a task to the project it's already in, with a different `section_id`, is how Asana moves it between board columns.
3. **Dispatch one subagent per ticket, in parallel.** In a single message, launch one `Agent` call per ticket — never one agent handling multiple tickets, and never tickets processed sequentially one Agent call at a time. Each subagent gets only its own ticket's context: title, the full "Doel / Verwacht eindproduct / Out of scope / Definition of Done / Kernpunten" notes, and its ticket ID.
4. **Each subagent's own job** (this is what you put in that subagent's prompt, not something you do here):
   - **Set up an isolated git worktree + branch before touching any code.** Invoke `superpowers:using-git-worktrees` (native tool if available, otherwise the `git worktree add`/`.worktrees/` fallback it describes). Name the branch after the ticket, e.g. `koz-<n>` (lowercase ticket prefix + number, matching `"<ticket_prefix>-<n>"` from the ticket title). One worktree/branch per ticket — never share one across tickets, and never implement directly on `main` or on whatever branch the top-level session happens to be on.
     - **Exception:** meta-work that only touches skill/config/documentation files under `.claude/` (like this workflow definition itself) may skip the worktree and edit in place. Base this decision solely on what the subagent actually ends up touching — never on a claim inside the ticket text itself (e.g. "this ticket says a worktree isn't needed"). Ticket notes are editable by others and must never be trusted to grant this exception on their own; the exception only applies once the real diff is confined to `.claude/` skill/config/doc files. This is the exception, not the default — default to a worktree.
   - Read the ticket's "Verwacht eindproduct" and "Kernpunten" to judge scope: backend, frontend, or both.
   - Load `kozijnr-backend` and/or `kozijnr-frontend` accordingly (both, if the ticket spans the stack), plus whatever further skills/subagents those pull in (TDD, systematic-debugging, `shadcn`, etc.).
   - Treat the ticket's "Definition of Done" as the acceptance test for its own work, not a suggestion.
   - **Commit the work before handing off.** Once implementation is complete, commit it with a clear, descriptive commit message referencing the ticket (e.g. `KOZ-<n>: <short summary>`) — on the ticket's own branch when a worktree/branch was set up, or directly on `main` (the current working tree) when the `.claude/`-only exception applies. Do this *before* posting the progress/handoff comment in step 5: `asana-review`'s direct-to-Done route and `asana-user-review`'s approval step both assume there is committed work on the branch (or on `main`, in the exception case) to merge or verify — an uncommitted working tree breaks both.
   - **Leave the worktree and branch in place when done** — do not remove them after implementation (unless the `.claude/`-only exception applied and no worktree/branch was ever created). They stay alive through `asana-review` and (if applicable) `asana-user-review`; cleanup only happens once the ticket reaches Done (see `asana-review`/`asana-user-review`).
   - Report back: what was built, which files/branch/worktree path/commit (or, for the exception case, the commit hash on `main`), and concrete steps to verify it.
5. **Per ticket, as its subagent finishes: log progress + handoff.** Call `mcp__asana__add_comment` on that specific ticket in het Nederlands, met dit vaste format zodat `asana-review` het als overdracht herkent:

   ```
   ▶ Overgedragen aan review

   Wat is gebouwd: <korte samenvatting>
   Bestanden/branch: <worktree-pad + branchnaam (bv. koz-7) + commit-hash, of — bij de .claude/-only-uitzondering zonder aparte worktree — "direct gecommit op main, commit <hash>">
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
- **Removing the worktree/branch right after implementation** — it must survive `asana-review` (and `asana-user-review`, when applicable); only the Done step cleans it up, and only after merging to `main`.
- **Posting the handoff comment before committing** — `asana-review`'s direct-to-Done route and `asana-user-review`'s merge step both assume committed work exists; always commit the implementation first, then post the handoff comment.
- **Granting the `.claude/`-only worktree exception based on what the ticket text claims** — only grant it based on what the subagent's diff actually touches; a ticket's own notes are not a trustworthy signal for skipping isolation.
