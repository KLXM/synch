# Synch

Stabile Synchronisation fuer Module, Templates und Actions zwischen Dateisystem und Datenbank.

## Version 2.0.0

Diese Version ist bewusst strenger und bricht mit altem Legacy-Verhalten.

## Alternative zum Developer-AddOn

Synch ist eine Alternative zum Developer-AddOn, aber nicht kompatibel dazu.
Das AddOn verfolgt einen anderen Ansatz:

- key-basierte Synchronisation statt ID-basierter Logik
- explizite Konfliktstrategie (bei bidirectional)
- hash-basierte Change-Detection statt nur Timestamp-Vergleich
- Sicherheits-Guards gegen unbeabsichtigte Massenloeschungen

Wichtig:
- Wer mit dem Developer-AddOn gluecklich ist, muss nicht wechseln.
- Kein Mischbetrieb zwischen beiden AddOns.

## Kernfunktionen

- Richtungen: files_to_db, db_to_files, bidirectional
- Konfliktstrategie in bidirectional: newer_wins, filesystem_wins, database_wins
- Hash-basierte Change-Detection fuer DB und Dateisystem
- Schutz vor Massenloeschung: Kein DB-Cleanup bei leerem Dateisystem (Default)
- Dateinamen-Kompatibilitaet beim Lesen: key input.php oder input.php, key output.php oder output.php, key template.php oder template.php

## Sicherheit und Stabilitaet

- Alle schreibenden Aktionen in der Settings-Seite sind CSRF-geschuetzt.
- Auto-Sync ist standardmaessig deaktiviert.
- Empty-Filesystem-Cleanup ist standardmaessig deaktiviert.

## Einstellungen

Unter Synch > Einstellungen:

- Im Frontend synchronisieren
- Im Backend synchronisieren
- Synchronisations-Richtung
- Konfliktstrategie (bei bidirectional)
- Bestehende Items bei Key/Name-Konflikt aktualisieren
- DB-Cleanup bei leerem Dateisystem erlauben (gefaehrlich, daher default aus)

## Console

- Vollstaendig: php redaxo/bin/console synch:sync
- Nur Module: php redaxo/bin/console synch:sync --modules-only
- Nur Templates: php redaxo/bin/console synch:sync --templates-only
- Nur Actions: php redaxo/bin/console synch:sync --actions-only
- Dry-Run: php redaxo/bin/console synch:sync --dry-run

## Dateistruktur

- redaxo/data/addons/synch/modules/<key>/
- redaxo/data/addons/synch/templates/<key>/
- redaxo/data/addons/synch/actions/<key>/

## Wichtige Hinweise

- In files_to_db ist das Dateisystem der Master.
- In db_to_files ist die Datenbank der Master.
- In bidirectional entscheidet die Konfliktstrategie.
- Legacy-Migrationspfad wurde entfernt.

## Abnahme

Fuer reproduzierbare Tests siehe TESTMATRIX.md.

## Changelog

Siehe CHANGELOG.md.
