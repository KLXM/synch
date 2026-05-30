# Changelog

## 2.0.0 - 2026-05-30

### Breaking Changes

- Legacy-Migrationscode entfernt.
- Default fuer sync_backend auf false gesetzt.
- Neue Default-Strategie fuer Sync-Absicherung eingefuehrt.

### Added

- Konfliktstrategie fuer bidirectional:
  - newer_wins
  - filesystem_wins
  - database_wins
- Hash-basierte Change-Detection ueber DB- und Dateisystemzustand.
- Sicherheitsoption allow_empty_filesystem_cleanup (Default false).
- Dateinamen-Kompatibilitaet beim Lesen:
  - key input.php und input.php
  - key output.php und output.php
  - key template.php und template.php
- CSRF-Schutz in der Settings-Seite fuer alle schreibenden Aktionen.
- Abnahme-Testmatrix in TESTMATRIX.md fuer reproduzierbare Freigabetests.

### Changed

- Installationsskript auf robustere Schema- und Key-Initialisierung umgestellt.
- Settings-Seite bereinigt und auf wirksame Optionen fokussiert.
- Boot-Logik und Defaults konsolidiert.

### Fixed

- Risiko von Massenloeschung in der DB bei leerem Dateisystem standardmaessig unterbunden.
- Konfliktbehandlung war zuvor effektiv nicht konfigurierbar; jetzt wirksam.
- Konsistenzprobleme zwischen package.yml, Install-Defaults und UI-Defaults behoben.
