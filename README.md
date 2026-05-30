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

## Benutzeranleitung

### 1. Erstkonfiguration

1. AddOn installieren und aktivieren.
2. Unter Synch > Einstellungen den Modus waehlen:
	- files_to_db fuer dateibasierte Entwicklung (empfohlen)
	- db_to_files fuer Backend-zentriertes Arbeiten
	- bidirectional nur mit klarer Konfliktstrategie
3. Auto-Sync zunaechst deaktiviert lassen und mit manuellem Sync testen.

### 2. Empfohlener Alltag (files_to_db)

1. Dateien unter redaxo/data/addons/synch anlegen oder aendern.
2. Sync manuell starten oder Auto-Sync aktivieren.
3. Ergebnis im Backend pruefen.

Hinweis: In files_to_db ist das Dateisystem Master. Backend-Aenderungen koennen ueberschrieben werden.

### 3. Typische Aufgaben

1. Neues Modul aus Datei anlegen:
	- Ordner in modules/<key> erstellen
	- metadata.yml, input.php oder <key> input.php, output.php oder <key> output.php anlegen
	- Sync ausfuehren
2. Bestehendes Modul aktualisieren:
	- Dateiinhalt anpassen
	- Sync ausfuehren
3. Modul loeschen:
	- Im files_to_db-Modus im Dateisystem loeschen
	- Sync ausfuehren

### 4. Sicherer Betrieb

1. allow_empty_filesystem_cleanup nur aktivieren, wenn wirklich gewollt.
2. Fuer produktive Systeme vor groesseren Sync-Laeufen Backup erstellen.
3. Bei bidirectional Konfliktstrategie bewusst waehlen und im Team dokumentieren.

## Console

- Vollstaendig: php redaxo/bin/console synch:sync
- Nur Module: php redaxo/bin/console synch:sync --modules-only
- Nur Templates: php redaxo/bin/console synch:sync --templates-only
- Nur Actions: php redaxo/bin/console synch:sync --actions-only
- Dry-Run: php redaxo/bin/console synch:sync --dry-run

## Empfohlene Betriebsprofile (Console)

### Development

Schnell und fokussiert, je nach Aenderungstyp:

- Module: php redaxo/bin/console synch:sync --modules-only
- Templates: php redaxo/bin/console synch:sync --templates-only
- Actions: php redaxo/bin/console synch:sync --actions-only

### Staging

Vor dem Deploy pruefen und dann voll synchronisieren:

1. php redaxo/bin/console synch:sync --dry-run
2. php redaxo/bin/console synch:sync

### Production

Konservativer Ablauf mit Verifikation:

1. Backup erstellen
2. php redaxo/bin/console synch:sync --dry-run
3. php redaxo/bin/console synch:sync
4. Ergebnis im Backend pruefen

Hinweis:
- Fuer files_to_db ist die Console der empfohlene, reproduzierbare Betriebsweg.
- Bei bidirectional sollte die Konfliktstrategie vor dem Lauf explizit gesetzt sein.

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
