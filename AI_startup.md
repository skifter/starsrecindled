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

## Immediate next development: 0.7.2

The first 0.7.2 slice — explicit planetary installation upgrades — is now implemented as Unreleased development work. The public application version remains `0.7.1` until the complete 0.7.2 release is ready.

### Implemented in the current Unreleased slice

- sequential `Mk I -> Mk II -> Mk III` installation upgrades; generations cannot be skipped
- upgrade target must be unlocked by completed research
- production orders persist the exact source and target model/version
- upgrade industry cost is paid when the order starts
- current installation stays active during the upgrade
- Mk II/Mk III installation upgrades currently take two turn-processing cycles
- pending work is stored in each system's existing universe-state JSON as `installationUpgrades`; no database migration is required
- completion replaces the installed model and applies only the stat delta between old and new versions
- Orbital Factory income improvements begin on the turn after completion because current-turn income is collected before upgrades advance
- Planet UI shows available, queued and in-progress upgrades
- Turn Report records completed installation upgrades
- `tests/installation-upgrade-smoke.php` verifies sequencing and defense/factory/sensor stat deltas

- Planets overview is collapsible and starts with every colony collapsed; population, happiness, development, defenses and activity remain visible in the compact row.
- Planet and galaxy detail panels expose sequential installation upgrades directly instead of presenting installed older models as disabled build choices.
- Legacy normal-build orders for already-installed families are cleaned from loaded local drafts; backend validation still rejects invalid direct/API orders.
- Production validation messages prefer human-readable colony/model names over internal ids.

- Planets uses native `<details>` collapse/expand rather than a shared Svelte expanded-id list; colonies remain collapsed by default and toggling is browser-native.
- Compact planet rows use a lightweight planet icon; sensor range remains available in expanded colony details.
- Galaxy planet details include Development percentage in the top overview metrics.
- Draft installation upgrades are disabled/greyed and labelled `IN QUEUE`; the collapsed colony status shows `UPGRADE QUEUED` until turn processing starts.

### Next 0.7.2 slices

- design cloning/editing so researched components can be assembled into intentional new ship generations
- ship refit between compatible generations
- refit prerequisites / shipyard requirements
- refit industry cost and time
- fleet current fuel and per-hop consumption
- applied research such as fuel optimization affecting existing fleets

The installation-upgrade state machine should be reused where practical for ship refit instead of inventing a separate unrelated timing model.

## Changelog policy

`CHANGELOG.md` must be maintained as part of normal development.

Use:

```text
## Unreleased
- next recommended implementation order: design cloning/editing -> ship refit -> fuel; do not start Combat before these are stable

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

Development is performed on the developer's local workstation.

- Local repository: `/home/skifter/git/starsrecindled`
- Remote repository: `git@github.com:skifter/starsrecindled.git`
- Branch: `main`

### Local development

1. AI-generated ZIP/apply packages are applied **only to the local Git checkout**.
2. Changes are reviewed and tested locally.
3. `git diff --check`, relevant PHP checks/tests, and frontend `check`/`build` must pass.
4. Changes are committed on the local workstation.
5. The commit is pushed to `origin/main`.
6. The Git repository is the authoritative deployment source.

AI development ZIP/apply packages must not be applied directly to the server.

Typical local apply flow:

```bash
rm -rf /tmp/stars-<feature>-<version>
cd /tmp
unzip /home/skifter/tmp/stars-<feature>-<version>.zip
/tmp/stars-<feature>-<version>/install/apply-<version>.sh /home/skifter/git/starsrecindled
```

Typical local verification:

```bash
cd /home/skifter/git/starsrecindled
npm --prefix frontend run check
npm --prefix frontend run build
git diff --check
git status -sb
```

Also run `php -l` on modified PHP files and any feature-specific tests supplied with the change.

### Server deployment

After the tested change is committed and pushed to `main`, update the server using the StarsRecindled installer/update script contained in the Git repository.

The canonical flow is:

```text
local apply/development
-> local tests
-> local commit
-> push origin/main
-> server installer/update script from the repository
-> deployment verification in the running application
```

Do not bypass Git by copying AI payload files directly to the server. This separation is intentional and must be preserved for future releases.

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
