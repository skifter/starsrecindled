# Changelog

<!-- AI_STARTUP_MODERN_HISTORY -->
## Unreleased

### Added

- Explicit multi-turn upgrades for versioned planetary installations through production orders.
- Planet UI for queued/in-progress installation upgrades and Turn Report completion events.

### Changed

- Installation upgrades validate exact sequential source/target models and research unlocks.
- The old installation remains active until the upgrade completes; upgrade effects are applied as version deltas at completion.

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
