---
name: asana-create
description: Use when the user asks to create one or more Asana tickets for the Kozijnr project, e.g. "maak een ticket aan voor X", "los dit op in Asana", "zet deze bugs/features in Asana", or when new work needs a KOZ-N ticket before implementation starts.
---

# Asana Create

## Overview

Creates KOZ-numbered tickets in the Kozijnr Asana board, using `.claude/asana.config.json` as the single source of truth for the project GID, section GIDs, and the next ticket number. Asana has no native sequential ticket ID, so the number is baked into the task name.

**REQUIRED BACKGROUND:** Read `.claude/asana.config.json` at the repo root before doing anything else in this skill.

**Taal:** alle output naar de gebruiker en alle Asana-comments/notes die je zelf schrijft zijn in het Nederlands.

## When to Use

- User wants new work tracked as an Asana ticket, before or instead of touching code
- A backlog grooming request lists several bugs/features at once

Not for moving or updating existing tickets — see `asana-worker`, `asana-review`, `asana-user-review` for that.

## Workflow

1. **Read config.** Load `.claude/asana.config.json`. You need `project.gid`, `ticket_prefix`, `next_number`, `sections` (name→gid), `default_section` ("Backlog").
2. **Sanity-check de teller tegen Asana.** Haal alle tasks in het project op (`mcp__asana__get_tasks`, `project=config.project.gid`, `opt_fields=name`, evt. `completed_since`/pagination voor volledige dekking) en bepaal het hoogste bestaande `<ticket_prefix>-<n>` uit de namen. Verwacht `hoogste_n + 1 === config.next_number`.
   - **Komt overeen:** ga door met `config.next_number`.
   - **`config.next_number` is te laag** (er bestaan al tickets met hogere nummers dan de config aangeeft — bv. na een handmatige wijziging of een andere sessie): meld dit kort aan de gebruiker en ga verder met `hoogste_n + 1`, niet met de verouderde waarde uit de config. Herstel de config in stap 7 naar deze correcte waarde.
   - **`config.next_number` is hoger dan verwacht:** dat is geen probleem (nummers kunnen gaps hebben door mislukte creates) — ga gewoon door met `config.next_number`.
3. **Decide one ticket or several.** Judge from the request: a single coherent piece of work is one ticket; a list of distinct bugs/features/tasks is bulk. When in doubt, propose the split to the user in one sentence before creating anything — don't silently guess on ambiguous scope.
3b. **Check for blockers/dependencies.** Before writing the notes, judge whether the new ticket's work realistically depends on other work that doesn't exist yet or isn't done yet — e.g. KOZ-2 "Docker-opzet" depending on KOZ-3 "Symfony installeren" and KOZ-4 "NextJS installeren" still being in Backlog is the concrete precedent for this check. Look at the existing tickets fetched in step 2 (name + section) for anything the new ticket clearly builds on top of (same feature area, an explicit "na X" in the request, infra/setup the new work assumes exists).
   - **Found a likely blocker that's not yet Done:** don't silently proceed. Either (a) ask the user in one sentence how to handle it (e.g. "KOZ-N lijkt afhankelijk van KOZ-M dat nog in Backlog staat — wil je dat ik dit toch aanmaak met KOZ-M als blocker vermeld, of eerst KOZ-M laten oppakken?"), or, when the dependency is clear-cut and the user's request doesn't need a pause, (b) proceed but record it — add a "Blokkers" section to the notes (see step 6) listing the blocking ticket(s) by `KOZ-N: title`, and mention it in the report-back (step 9).
   - **No plausible blocker found:** proceed as normal; omit the "Blokkers" section from the notes (see step 6).
   - This is a judgment call, not exhaustive dependency analysis — don't fetch the full notes of every existing ticket; a quick scan of names/sections from step 2 is enough. When genuinely unsure, ask rather than guess.
4. **Assign numbers.** Starting at the number confirmed in step 2, assign one integer per ticket, incrementing. Name each task `"<ticket_prefix>-<n>: <short title>"` (e.g. `KOZ-4: Login formulier valideert e-mail niet`).
5. **Pick the section.** Use the GID for `default_section` unless the user names a different stage (e.g. "zet 'm meteen in Todo").
6. **Schrijf de notes volgens het vaste sjabloon.** Houd elk onderdeel zo bondig mogelijk — een ticket is basaal, geen ontwerpdocument. Gebruik dit format (Nederlands, plain text) voor `notes`:

   ```
   Doel
   <waarom dit ticket bestaat, in 1-2 zinnen>

   Verwacht eindproduct
   <wat er concreet opgeleverd wordt>

   Out of scope
   <wat expliciet niet wordt meegenomen — leeg laten als niet van toepassing>

   Definition of Done
   - <toetsbaar criterium>
   - <toetsbaar criterium>

   Kernpunten
   - <belangrijk aandachtspunt of constraint>
   ```

   **Blokkers (optioneel, alleen als stap 3b een afhankelijkheid vond):** voeg een extra sectie toe ná "Kernpunten":

   ```
   Blokkers
   - <KOZ-N: titel> — <in 1 zin waarom dit ticket daarop wacht>
   ```

   Laat deze sectie volledig weg (niet als leeg kopje) wanneer stap 3b geen blocker vond — een leeg "Blokkers"-kopje suggereert ten onrechte dat er expliciet is gecontroleerd en niets is gevonden versus het onderwerp simpelweg niet relevant is.

   Vul een onderdeel nooit met vage taal ("moet goed werken") — elk punt moet objectief te checken zijn, want `asana-review` en `asana-user-review` toetsen hier later tegen.
7. **Create.** Call `mcp__asana__create_tasks` with one entry per ticket:
   - `project_id`: `project.gid`
   - `section_id`: the chosen section GID
   - `name`: the numbered title from step 4, in het Nederlands
   - `notes`: het ingevulde sjabloon uit stap 6
   - Batch all tickets from one request into a single `create_tasks` call (it accepts 1-50 tasks).
8. **Persist the counter.** Edit `.claude/asana.config.json` and set `next_number` to `<startnummer uit stap 2/4> + <count created>`. Do this even if some tasks in the batch failed — only count `succeeded` tasks from the tool response.
9. **Rapporteer terug.** Noem elk aangemaakt ticket als `KOZ-N: Titel` met de Asana-task-URL (`https://app.asana.com/1/<workspace.gid>/project/<project.gid>/task/<task_gid>`), in het Nederlands. Als stap 3b een blocker vond en die in de notes is opgenomen (of aan de gebruiker is voorgelegd), noem dat kort erbij.

## Quick Reference

| Field | Source |
|---|---|
| Project GID | `config.project.gid` |
| Section GID | `config.sections[].gid` matched by name |
| Next number | `config.next_number`, geverifieerd tegen bestaande `<prefix>-N` tasknamen (stap 2), increment after creation |
| Task name format | `"<ticket_prefix>-<n>: <title>"`, titel in het Nederlands |
| Notes format | Doel / Verwacht eindproduct / Out of scope / Definition of Done / Kernpunten (+ optioneel Blokkers) |
| Blocker check | Stap 3b — scan bestaande tickets (naam + sectie) op afhankelijkheden die nog niet Done zijn |

## Common Mistakes

- **Forgetting to update `next_number`** — the next ticket will collide or duplicate. Always write the config back after a successful create.
- **De sanity-check overslaan** — als de config-teller achterloopt op de werkelijke Asana-stand, krijg je een dubbel `KOZ-N` nummer. Stap 2 is niet optioneel.
- **Using a section name instead of its GID** — `create_tasks` needs `section_id`, not a name.
- **Numbering skipped tickets** — if a task in the batch fails (see `failed` in the tool response), don't burn a number on it; only increment by how many actually `succeeded`.
- **Een sjabloon-onderdeel overslaan of vaag invullen** — een half ingevuld ticket geeft `asana-worker` te weinig om op te bouwen en `asana-review`/`asana-user-review` niets om tegen te toetsen.
- **Een duidelijke blocker negeren** — bv. een ticket voor "Docker-opzet" aanmaken terwijl de onderliggende "Symfony installeren"/"NextJS installeren"-tickets nog in Backlog staan, zonder dat te vermelden of de gebruiker te waarschuwen (zie stap 3b).
- **Een leeg "Blokkers"-kopje toevoegen als er geen blocker is** — laat de sectie volledig weg in plaats van een leeg kopje te posten.
