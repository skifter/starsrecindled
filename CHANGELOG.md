# Changelog

<!-- AI_STARTUP_MODERN_HISTORY -->
## Unreleased

### Added

### Changed

### Fixed

## 0.7.3 - 2026-08-18

### Added

- Explicit multi-turn upgrades for versioned planetary installations through production orders.
- Planet UI for queued/in-progress installation upgrades and Turn Report completion events.
- Collapsible Planets overview with all colony cards collapsed by default and key colony stats kept in the header row.
- Added an explicit ship design editor that clones an existing immutable generation and lets the player choose only researched component models.
- Added turn draft orders for new ship generations, including server-side component/research validation, persistent design lineage and Turn Report events.
- Added a design-generation smoke test covering immutable old generations, unlocked component selection and rejection of duplicate/locked designs.

- Added persistent human/AI player controller metadata with a first Standard AI level.
- Added lobby creation of self-contained test games with one logged-in human and one to three Standard AI players.
- Added automatic AI turn submission so AI test games advance without additional player logins.
- Added an AI player smoke test and database migration for controller type / AI level.
- Added Colony Module Mk I as a baseline-unlocked optional ship utility component for repeatable expansion.
- Added component-backed colony ships that carry explicit COL capacity and are consumed when they successfully found a colony.
- Added a colony-ship smoke test covering immutable Scout/Colony Ship coexistence, exact cost/batch behavior and legacy colony-module compatibility.
- Added fleet management for renaming, splitting, transferring and merging fleets while preserving exact immutable ship generations.
- Added explicit fleet refit from an installed ship generation to a newer compatible generation at an owned colony, with industry cost and turn duration.
- Added fleet management/refit smoke coverage for structural orders, partial refits, exact composition preservation and completion reports.
### Changed

- Installation upgrades validate exact sequential source/target models and research unlocks.
- The old installation remains active until the upgrade completes; upgrade effects are applied as version deltas at completion.
- Planet and galaxy detail-panel installation actions are version-aware: installed hardware offers the next sequential Mk upgrade instead of a disabled older build action.
- Planet cards use native collapsed details and lightweight compact headers so expand/collapse does not trigger a full Svelte list-state update.
- Queued installation upgrades are disabled and visually muted with an explicit `IN QUEUE` state until the submitted turn is processed.
- The Galaxy planet detail overview now shows Development percentage alongside Population, Happiness and Sensor range.
- Completing hardware research now unlocks component models without automatically creating or replacing a ship design; a new generation requires an explicit player design order.
- A newly queued ship design becomes the current new-build design only after the turn is processed; production already queued this turn keeps its exact design version.

- Player/turn status now exposes AI controller information and the Players screen labels computer-controlled seats.
- AI test games suppress synthetic-player invitation/turn email batches; Standard AI currently submits a conservative valid no-op order envelope for deterministic testing.
- Ship designs can now contain an optional utility model; colony-capable designs build as one strategic ship per production unit while ordinary light designs retain their existing batch size.
- Planet production can queue any persisted non-obsolete ship generation by exact design id, allowing Scout and Colony Ship generations to coexist in production.
- Fleet and Turn Report views expose colony capacity and report when a colony ship was consumed during colonization.
- Fleet refit charges only changed hardware, reuses unchanged components, applies salvage credit to replaced components and leaves old hardware active until work completes.
- Fleets involved in split, transfer, merge or refit cannot move or colonize in the same processing cycle; active refits lock structural fleet actions until completion.
- Released the accumulated 0.7.2 development work as public application version 0.7.3.
### Fixed

- Loaded legacy installation build orders are removed from the local draft when that installation family is already present; the player must use an explicit upgrade order instead.
- Production validation errors use the colony name and installed model name instead of internal system/family identifiers where possible.
- Reduced lag when expanding or collapsing colonies in the Planets overview.
- Kept legacy starting-fleet colony capacity separate from component-backed colony ships so fleet normalization and reorganization do not duplicate or silently discard it.

## 0.7.1

### Added

- Versioned technology models for ship components and planetary installations.
- Ship generations/designs so new technology can produce new models without mutating existing ships.
- Designs-oriented UI and model information across research, fleets, planets and turn reports.

### Changed

- Hardware research now unlocks concrete models instead of globally upgrading all existing hardware.

## 0.7.0

### Added

- Persistent research state and technology progression.
- Research UI and turn-report integration for completed research.

## 0.6.7

### Changed

- Polished empire influence borders and contested zones for a calmer, more political border presentation.

## 0.6.6

### Changed

- Replaced independent empire blobs with weighted influence borders.

## 0.6.5

### Changed

- Added smoother strength-based empire influence.

## 0.6.4

### Changed

- Separated empire borders/influence from sensor coverage.

## 0.6.3

### Added

- Empire-border presentation and actionable intelligence/report links.

## 0.6.2

### Changed

- Installer/update flow reuses the current release for faster application updates.

## 0.6.1

### Changed

- Installer/update flow preserves and reuses existing configuration.

## 0.6.0

### Added

- Fog-of-war memory / last-known intelligence.
- Real turn reports.

## 0.5.3

### Fixed

- Galaxy system selection while route planning.

## 0.5.2

### Changed

- Select each player's own home system by default.

## 0.5.1

### Added

- Live galaxy data and fleet movement.

<!-- /AI_STARTUP_MODERN_HISTORY -->

## 0.1.1 - 2026-07-26
- Added frontend demo

## 0.1.0 - 2026-07-26

- Første Symfony 7.4/8.0-kompatible MVP.
- MariaDB-entiteter og migration.
- Messenger-baseret rundegenerering og mailnotifikationer.
- API til status, kladde, aflevering og genåbning.
- CLI til spiloprettelse og kø-reconciliation.
- SvelteKit-testklient.
