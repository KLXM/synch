# Synch

Stabile Synchronisation für Module, Templates und Actions zwischen Dateisystem und Datenbank.

## Version 2.0.0

Diese Version ist bewusst strenger und bricht mit altem Legacy-Verhalten.

## Alternative zum Developer-AddOn

Synch ist eine Alternative zum Developer-AddOn, aber nicht kompatibel dazu.
Das AddOn verfolgt einen anderen Ansatz:

- key-basierte Synchronisation statt ID-basierter Logik
- explizite Konfliktstrategie (bei bidirectional)
- hash-basierte Change-Detection statt nur Timestamp-Vergleich
- Sicherheits-Guards gegen unbeabsichtigte Massenlöschungen

Wichtig:
- Wer mit dem Developer-AddOn glücklich ist, muss nicht wechseln.
- Kein Mischbetrieb zwischen beiden AddOns.

## Kernfunktionen

- Richtungen: files_to_db, db_to_files, bidirectional
- Konfliktstrategie in bidirectional: newer_wins, filesystem_wins, database_wins
- Hash-basierte Change-Detection für DB und Dateisystem
- Schutz vor Massenlöschung: Kein DB-Cleanup bei leerem Dateisystem (Default)
- Dateinamen-Kompatibilität beim Lesen: key input.php oder input.php, key output.php oder output.php, key template.php oder template.php

## Sicherheit und Stabilität

- Alle schreibenden Aktionen in der Settings-Seite sind CSRF-geschützt.
- Auto-Sync ist standardmäßig deaktiviert.
- Empty-Filesystem-Cleanup ist standardmäßig deaktiviert.

## Einstellungen

Unter Synch > Einstellungen:

- Im Frontend synchronisieren
- Im Backend synchronisieren
- Synchronisations-Richtung
- Konfliktstrategie (bei bidirectional)
- Bestehende Items bei Key/Name-Konflikt aktualisieren
- DB-Cleanup bei leerem Dateisystem erlauben (gefährlich, daher default aus)

## Benutzeranleitung

### 1. Erstkonfiguration

1. AddOn installieren und aktivieren.
2. Unter Synch > Einstellungen den Modus wählen:
   - files_to_db für dateibasierte Entwicklung (empfohlen)
   - db_to_files für backend-zentriertes Arbeiten
   - bidirectional nur mit klarer Konfliktstrategie
3. Auto-Sync zunächst deaktiviert lassen und mit manuellem Sync testen.

### 2. Empfohlener Alltag (files_to_db)

1. Dateien unter `redaxo/data/addons/synch` anlegen oder ändern.
2. Sync manuell starten oder Auto-Sync aktivieren.
3. Ergebnis im Backend prüfen.

Hinweis: In files_to_db ist das Dateisystem Master. Backend-Änderungen können überschrieben werden.

Wichtig für die Erstinstallation:
- Wenn bereits Module/Templates/Actions in der Datenbank existieren, aber noch keine Synch-Verzeichnisse vorhanden sind,
  exportiert der erste Sync diese Inhalte einmalig ins Dateisystem (Initial-Bootstrap).
- Danach bleibt `files_to_db` wie gewohnt dateisystemführend.

### 3. Typische Aufgaben

1. Neues Modul aus Datei anlegen:
   - Ordner in `modules/<key>` erstellen
   - `metadata.yml`, `input.php` oder `<key> input.php`, `output.php` oder `<key> output.php` anlegen
   - Sync ausführen
2. Bestehendes Modul aktualisieren:
   - Dateiinhalt anpassen
   - Sync ausführen
3. Modul löschen:
   - Im files_to_db-Modus im Dateisystem löschen
   - Sync ausführen

### 4. Sicherer Betrieb

1. allow_empty_filesystem_cleanup nur aktivieren, wenn wirklich gewollt.
2. Für produktive Systeme vor größeren Sync-Läufen Backup erstellen.
3. Bei bidirectional Konfliktstrategie bewusst wählen und im Team dokumentieren.

## Console

```bash
# Vollständig
php redaxo/bin/console synch:sync

# Nur Module
php redaxo/bin/console synch:sync --modules-only

# Nur Templates
php redaxo/bin/console synch:sync --templates-only

# Nur Actions
php redaxo/bin/console synch:sync --actions-only

# Dry-Run
php redaxo/bin/console synch:sync --dry-run
```

## Empfohlene Betriebsprofile (Console)

### Development

Schnell und fokussiert, je nach Änderungstyp:

```bash
php redaxo/bin/console synch:sync --modules-only
php redaxo/bin/console synch:sync --templates-only
php redaxo/bin/console synch:sync --actions-only
```

### Staging

Vor dem Deploy prüfen und dann voll synchronisieren:

```bash
php redaxo/bin/console synch:sync --dry-run
php redaxo/bin/console synch:sync
```

### Production

Konservativer Ablauf mit Verifikation:

1. Backup erstellen
2. `php redaxo/bin/console synch:sync --dry-run`
3. `php redaxo/bin/console synch:sync`
4. Ergebnis im Backend prüfen

Hinweis:
- Für files_to_db ist die Console der empfohlene, reproduzierbare Betriebsweg.
- Bei bidirectional sollte die Konfliktstrategie vor dem Lauf explizit gesetzt sein.

## Dateistruktur

- `redaxo/data/addons/synch/modules/<key>/`
- `redaxo/data/addons/synch/templates/<key>/`
- `redaxo/data/addons/synch/actions/<key>/`

## Wichtige Hinweise

- In files_to_db ist das Dateisystem der Master.
- In db_to_files ist die Datenbank der Master.
- In bidirectional entscheidet die Konfliktstrategie.
- Legacy-Migrationspfad wurde entfernt.

## Abnahme

Für reproduzierbare Tests siehe `TESTMATRIX.md`.

## Changelog

Siehe `CHANGELOG.md`.
