# Synch Testmatrix

Ziel: Reproduzierbare Abnahme fuer Stabilitaet, Konfliktbehandlung und Sicherheitsregeln.

## Vorbereitung

1. Backup der Tabellen rex_module, rex_template, rex_action erstellen.
2. Sicherstellen, dass die Verzeichnisse vorhanden sind:
- redaxo/data/addons/synch/modules
- redaxo/data/addons/synch/templates
- redaxo/data/addons/synch/actions
3. Alle Caches leeren.
4. Sync zunaechst manuell ueber Backend oder Console ausfuehren.

## Matrix A: Richtungen x Konfliktstrategie

Hinweis: Konfliktstrategie ist nur bei bidirectional relevant.

| ID | Richtung | Konfliktstrategie | Setup | Erwartung |
|---|---|---|---|---|
| A1 | files_to_db | newer_wins | Dateiinhalt aendern, DB aelter | DB wird aus Datei aktualisiert |
| A2 | files_to_db | filesystem_wins | Dateiinhalt aendern, DB neuer | DB wird aus Datei aktualisiert |
| A3 | files_to_db | database_wins | Dateiinhalt aendern, DB neuer | DB wird aus Datei aktualisiert |
| A4 | db_to_files | newer_wins | DB aendern, Datei aelter | Datei wird aus DB aktualisiert |
| A5 | db_to_files | filesystem_wins | DB aendern, Datei neuer | Datei wird aus DB aktualisiert |
| A6 | db_to_files | database_wins | DB aendern, Datei neuer | Datei wird aus DB aktualisiert |
| A7 | bidirectional | newer_wins | DB und Datei unterschiedlich, Zeitstempel klar | Neuere Seite gewinnt |
| A8 | bidirectional | filesystem_wins | DB und Datei unterschiedlich | Dateisystem gewinnt immer |
| A9 | bidirectional | database_wins | DB und Datei unterschiedlich | Datenbank gewinnt immer |

## Matrix B: Sicherheitsfaelle

| ID | Fall | Setup | Erwartung |
|---|---|---|---|
| B1 | Leeres Dateisystem, Cleanup aus | allow_empty_filesystem_cleanup = false, Verzeichnisse leer | Keine DB-Loeschung |
| B2 | Leeres Dateisystem, Cleanup an | allow_empty_filesystem_cleanup = true, Verzeichnisse leer | DB-Cleanup erfolgt |
| B3 | CSRF Schutz | POST ohne gueltigen Token auf Settings | Aktion wird abgewiesen |
| B4 | Auto-Sync pausiert | pause ausfuehren, danach Auto-Sync Trigger | Kein Sync waehrend Pause |
| B5 | Auto-Sync fortsetzen | resume ausfuehren | Auto-Sync wieder aktiv |

## Matrix C: Dateinamen-Kompatibilitaet

| ID | Fall | Setup | Erwartung |
|---|---|---|---|
| C1 | Legacy Moduldatei | Nur input.php und output.php vorhanden | Inhalte werden korrekt gelesen |
| C2 | Key Moduldatei | Nur key input.php und key output.php vorhanden | Inhalte werden korrekt gelesen |
| C3 | Legacy Template | Nur template.php vorhanden | Inhalt wird korrekt gelesen |
| C4 | Key Template | Nur key template.php vorhanden | Inhalt wird korrekt gelesen |

## Matrix D: Key- und Konfliktfaelle

| ID | Fall | Setup | Erwartung |
|---|---|---|---|
| D1 | Neuer Datensatz ohne key | metadata ohne key | Key wird erzeugt |
| D2 | Key-Konflikt, Update an | update_existing_on_key_conflict = true | Bestehender Datensatz wird aktualisiert |
| D3 | Key-Konflikt, Update aus | update_existing_on_key_conflict = false | Kein Ueberschreiben, kein Duplikat |
| D4 | Namenskonflikt, Update an | gleicher name, anderer key | Bestehender Datensatz wird aktualisiert |

## Matrix E: Hash-Detection

| ID | Fall | Setup | Erwartung |
|---|---|---|---|
| E1 | Keine Aenderung | 2x Sync direkt nacheinander | Zweiter Lauf ohne inhaltliche Aenderungen |
| E2 | Datei geaendert | Eine relevante Datei aendern | Hash-Aenderung erkannt, Sync laeuft |
| E3 | DB geaendert | Feld in DB aendern | Hash-Aenderung erkannt, Sync laeuft |

## Schneller Smoke-Test

1. Dry-Run starten:
php redaxo/bin/console synch:sync --dry-run
2. Nur Module:
php redaxo/bin/console synch:sync --modules-only
3. Vollsync:
php redaxo/bin/console synch:sync

Erwartung:
- Exit Code 0
- Keine Exceptions
- Keine unbeabsichtigten Loeschungen

## Abnahmekriterien

1. Alle Faelle A1 bis A9 bestehen.
2. B1 muss bestehen, sonst kein Release.
3. C1 bis C4 muessen ohne Datenverlust bestehen.
4. E1 bis E3 muessen reproduzierbar sein.
5. Rexstan auf Addon-Pfad ohne Fehler.
