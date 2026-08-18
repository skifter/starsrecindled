# StarsRecindled – AI startup context

Last updated: 2026-08-18

This file is the persistent bootstrap context for AI-assisted development of StarsRecindled.
Read this file before proposing or applying changes.

## Source of truth

The Git repository is the source of truth for code.

- Project: StarsRecindled
- Public repository: https://github.com/skifter/starsrecindled
- Git remote used locally: `git@github.com:skifter/starsrecindled.git`
- Main branch: `main`
- Local development checkout: `/home/skifter/git/starsrecindled`
- Baseline when this file was introduced:
  - commit: `b663aa1d688f7a936dec2bd1be755e7ef5356c45`
  - commit subject: `Add versioned technology models and ship generations`
- At that baseline, local `HEAD`, `main`, `origin/main`, and `origin/HEAD` were aligned.

Always inspect the current repository before changing code. This file describes intent and state, but it must not override newer code.

Useful startup commands:

```bash
cd /home/skifter/git/starsrecindled
git status -sb
git log -10 --oneline --decorate
git rev-parse HEAD
git rev-parse origin/main
```

## Project goal

StarsRecindled is a modern reimplementation inspired by Stars!, with a browser-based player UI and server-side turn processing.

The project should preserve the strategic feel of persistent empire development while using a modern architecture and UI.

Important gameplay themes:

- persistent multiplayer game state
- colonies and economy
- production queues
- fleets and movement
- sensors and fog-of-war
- last-known intelligence
- political / sphere-of-influence borders
- research and technology progression
- versioned ship components and ship generations
- later: upgrades, refit, fuel, combat and diplomacy

## Technical stack

Primary server target:

- Debian 13
- Symfony 7.4 backend
- PHP 8.4 / PHP-FPM
- MariaDB 11.8
- Nginx
- SvelteKit frontend built as static assets

Known production-style layout:

- application base: `/var/www/stars`
- releases: `/var/www/stars/releases/<timestamp>`
- active release symlink: `/var/www/stars/current`

Frontend lives below `frontend/`.

## Current gameplay state at baseline 0.7.1

The project has progressed through the following major systems:

- account/login foundation
- live galaxy data
- player-specific home-system selection
- fleet movement and route planning
- colonies, economy and production
- sensor coverage
- fog-of-war and remembered / last-known intelligence
- real turn reports
- empire borders / sphere of influence
- weighted and smoothed influence borders
- contested zones
- persistent research
- technology progression
- versioned technology models
- ship generations / designs

The influence layer was considered visually good enough to move on from. It should feel like a political/influence border, not independent circular empire bubbles.

Sensor coverage is a separate layer from political influence.

## Technology and model design rules

These are deliberate architectural rules and should be preserved.

### Hardware does not magically upgrade existing equipment

Research that unlocks new hardware gives access to a new model/version.

Examples:

- new engine
- new weapon
- new scanner
- new armor/shield
- new factory
- new planetary defense

Existing ships/installations keep the model they were built with.

Example:

- `Scout Mk I` with `Chemical Drive I` stays `Scout Mk I`
- researching `Ion Drive I` allows creation of a newer design such as `Scout Mk II`
- it does not modify existing Scout Mk I ships

A fleet may therefore contain multiple generations at the same time.

### Applied/global improvements may affect existing equipment

Some research may legitimately improve existing fleets or infrastructure globally because it represents software, process or operational improvements.

Examples:

- fuel optimization
- navigation algorithms
- logistics doctrine
- production methods
- sensor processing software

Keep the distinction between hardware unlocks and global/applied improvements explicit.

### Production queues are version-specific

A production queue must refer to the concrete model/design/version being built.

Research completed after an item enters the queue must not silently change that queued item.

### Planet installations are versioned

Planetary installations follow the same model/version philosophy as ships/components.

Examples:

- `Orbital Factory I`
- `Deep Space Array I`
- `Defense Grid I`

Researching Mk II does not automatically replace Mk I.

Upgrades must be explicit actions with cost and time.

### Designs

Ship designs are concrete compositions of versioned components.

A design can include:

- hull
- engine
- weapons
- scanner
- armor/shield
- fuel capacity
- derived speed
- sensor range
- attack
- defense
- industry/build cost

Old designs may remain buildable or be marked obsolete according to gameplay/UI rules.

## Version roadmap

Current baseline:

- `0.7.1` – Models & Designs

Planned sequence:

- `0.7.2` – Upgrades, refit & fuel
- `0.8.0` – Combat
- `0.9.0` – Diplomacy
- `1.0.0` – coherent core gameplay loop

Do not rush Combat ahead of the equipment/refit/fuel foundation.

## Immediate next development

After dev5 AI test players are stable on the deployed server:
1. connect planet ship production to an exact saved ship design/generation;
2. add fleet refit between compatible explicit generations;
3. add fuel behavior and global fuel/software/logistics improvements;
4. move to Combat (`0.8.0`).

## Changelog policy

`CHANGELOG.md` must be maintained as part of normal development.

Use:

```text
## Unreleased
- next recommended implementation order: design cloning/editing -> ship refit -> fuel; do not start Combat before these are stable
- next recommended implementation: production design selection and fleet refit, then fuel; Combat remains after 0.7.2 is stable

### Added
### Changed
### Fixed
```

Rules:

- every meaningful gameplay feature goes into `Unreleased`
- meaningful API, persistence and migration changes go into `Unreleased`
- important fixes go into `Unreleased`
- trivial refactors do not need changelog entries
- do not bump the public application version merely because work on the next version starts
- when a release is considered complete, move the relevant `Unreleased` items under the released version and add the release date
- update frontend/application version metadata as part of the release step

## Development and deployment workflow

1. Apply AI-generated development packages only to the local checkout: `/home/skifter/git/starsrecindled`.
2. Run local automated checks (`php` smoke/lint, frontend check/build, `git diff --check`).
3. Commit locally and push `origin/main`.
4. Update/deploy the server from the pushed Git repository using the repository's install/update script.
5. Perform GUI and gameplay integration testing on the deployed server.

AI development ZIP/apply packages must never be applied directly to the server. Git `main` is the authoritative deployment source.

## Shell-command safety

Commands intended for direct paste into an interactive shell should be plain sequential commands.

Do not use interactive-shell commands that:

- change strict mode in the active shell
- terminate the current shell/session
- replace the current shell with `exec`
- reboot/shutdown the machine unless specifically requested

Strict mode is acceptable inside a separate script file such as an `install/apply-*.sh`.

When a dependent step must not run after a failed step, make that dependency explicit or put the logic inside the apply script.

## Editing conventions

When manual editing is needed, use `vim`.

Prefer a safe, directly pasteable command or complete patch/apply script instead of partial hand-edits.

Always include a verification/test command with server, code or configuration changes.

## UI / gameplay continuity rules

Preserve these established concepts unless a deliberate redesign is agreed:

- empire influence and sensor coverage are separate systems
- empire borders should meet around a strength-weighted median
- nearby empires may meet/contend naturally rather than rendering separate oversized blobs
- influence should move gradually when colony strength changes
- multiple colonies contribute to one empire-wide influence field
- fog-of-war must not reveal current hidden information
- remembered influence/intelligence should be stale/dimmed rather than magically current
- minimap and main galaxy map should be broadly consistent
- Turn Report should be actionable and link to the relevant gameplay area when practical

## How a new AI conversation should resume

The preferred startup instruction is:

> Read `AI_startup.md` first. Treat the current `main` branch of `git@github.com:skifter/starsrecindled.git` as the source of truth. Inspect `git status`, recent commits and the files relevant to the next task before proposing changes. Continue from the "Immediate next development" section unless newer code/changelog state shows that work has already moved on. Deliver development changes as a versioned ZIP + local `install/apply-*.sh` package, verify them in `/home/skifter/git/starsrecindled`, then commit/push locally. Server deployment must happen only through the repository installer/update script.

If the repository has advanced beyond the commit recorded in this file, update this file as part of the next meaningful change.

## Maintenance rule for this file

Update `AI_startup.md` whenever any of these change materially:

- repository / branch / local path
- stack or deployment layout
- current released version
- major completed gameplay systems
- core architecture decisions
- immediate next task
- roadmap
- established apply/test/release workflow

The purpose is that a new conversation should need the repository plus this file, not a full historical chat export.

## Development and deployment workflow

Local development → local tests → commit → push main → server installer/update script → deployment verification.
AI development ZIP/apply packages are for the local Git checkout only and must not be applied directly to the server.

## AI test players

- Players have a persistent controller type: `human` or `ai`. AI players also store an AI level; dev5 implements only `standard`.
- The web lobby can create a self-contained test game with the logged-in account plus 1-3 Standard AI seats.
- AI seats are real `Player` / `PlayerTurn` participants. They are not frontend mock players and must obey the same turn lifecycle as humans.
- Standard AI in dev5 is deliberately test-oriented: when a human submits, still-draft AI seats automatically submit a valid conservative order envelope with no fleet, production, research or design orders.
- AI test games do not send invitation/turn email batches to synthetic AI addresses.
- Future AI strategy should consume only information legitimately visible to that player. Do not give AI hidden map/fog information or alternate gameplay rules.
