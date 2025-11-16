# Synch - Key-basierte Synchronisation für REDAXO 5

Synch synchronisiert Module, Templates und Actions zwischen Dateisystem und Datenbank mit einem key-basierten System.

## 🎯 Hauptmerkmale

- **Key-basiertes System**: Jedes Item hat einen eindeutigen Key (wie in REDAXO Core)
- **Bidirektionale Synchronisation**: Dateisystem ↔ Datenbank mit konfigurierbarer Richtung
- **Zeitstempel-basiert**: Nur der neuere Stand wird übernommen (5 Sek. Toleranz)
- **Automatisches Cleanup**: Gelöschte Items werden automatisch synchronisiert
- **Sprechende Dateinamen**: `{key} input.php` statt `input.php` für bessere IDE-Integration
- **AUTO_INCREMENT IDs**: Keine ID-Konflikte bei neuen Items

## 📦 Installation

1. Synch-Addon herunterladen und im `/redaxo/src/addons/` Ordner entpacken
2. Im REDAXO-Backend unter "Addons" installieren und aktivieren
3. Unter "Synch → Einstellungen" die Synchronisations-Richtung wählen

## 🚀 Quick Start

### 1. Neues Modul erstellen

Erstelle einen Ordner: `/redaxo/data/addons/synch/modules/news_module/`

**metadata.yml:**
```yaml
name: "News Modul"
key: "news_module"
```

**news_module input.php:**
```php
<?php
$mform = new MForm();
$mform->addTextField('1.0', ['label' => 'Überschrift']);
$mform->addTextAreaField('2.0', ['label' => 'Text']);
echo $mform->show();
```

**news_module output.php:**
```php
<h2><?= rex_var::toMedia('REX_VALUE[1]') ?></h2>
<div><?= rex_var::toMedia('REX_VALUE[2]') ?></div>
```

### 2. Synchronisation ausführen

- **Automatisch**: Wird im Backend automatisch alle 60 Sekunden ausgeführt (wenn aktiviert)
- **Manuell**: Synch → Einstellungen → "Synchronisation durchführen"
- **Console**: `php redaxo/bin/console synch:sync` (siehe unten)

Das Modul erscheint automatisch in der Modulliste mit einer AUTO_INCREMENT ID!

## 💻 Console Commands

```bash
# Komplette Synchronisation (Module, Templates, Actions)
php redaxo/bin/console synch:sync

# Nur Module synchronisieren
php redaxo/bin/console synch:sync --modules-only
php redaxo/bin/console synch:sync -m

# Nur Templates synchronisieren
php redaxo/bin/console synch:sync --templates-only
php redaxo/bin/console synch:sync -t

# Nur Actions synchronisieren
php redaxo/bin/console synch:sync --actions-only
php redaxo/bin/console synch:sync -a

# Dry Run (Test ohne Änderungen)
php redaxo/bin/console synch:sync --dry-run
php redaxo/bin/console synch:sync -d
```

**Kombinationen möglich:**
```bash
# Dry Run nur für Module
php redaxo/bin/console synch:sync -m -d

# Deployment-Script
php redaxo/bin/console synch:sync && php redaxo/bin/console cache:clear
```

## 📁 Dateistruktur

```
redaxo/data/addons/synch/
├── modules/
│   └── news_module/
│       ├── metadata.yml
│       ├── news_module input.php
│       └── news_module output.php
├── templates/
│   └── default_template/
│       ├── metadata.yml
│       └── default_template template.php
└── actions/
    └── save_action/
        └── metadata.yml
```

## 🔧 Synchronisations-Modi

### 1. Dateien → DB (Empfohlen) ⬅️

**Dateisystem ist Master**

- ✅ Ideal für Entwicklung mit IDE und Git
- ✅ Dateien überschreiben Backend-Änderungen
- ✅ Gelöschte Ordner → Items werden aus DB entfernt
- ⚠️ Backend-Änderungen gehen verloren beim nächsten Sync
- 🔒 Lösch-Buttons im Backend deaktiviert

**Workflow:**
1. Modul in IDE erstellen/ändern
2. Sync läuft automatisch oder manuell triggern
3. Änderungen erscheinen im Backend

### 2. DB → Dateien ➡️

**Backend ist Master**

- ✅ Backend-Änderungen werden ins Dateisystem geschrieben
- ✅ Ideal wenn hauptsächlich im Backend gearbeitet wird
- ⚠️ Datei-Änderungen werden überschrieben
- ⚠️ Im Backend gelöschte Items → Dateien bleiben bestehen

**Workflow:**
1. Modul im Backend bearbeiten
2. Dateien werden automatisch aktualisiert
3. Git-Commit der geänderten Dateien

### 3. Bidirektional ↔️

**Zeitstempel entscheidet**

- ✅ Änderungen in beide Richtungen
- ✅ Der neuere Stand gewinnt (5 Sek. Toleranz)
- 🔒 Löschen nur im Dateisystem möglich (Backend-Lösch-Buttons deaktiviert)
- ⚠️ Kann zu unerwartetem Verhalten führen wenn parallel gearbeitet wird

**Workflow:**
1. Änderungen im Backend oder IDE
2. Sync übernimmt automatisch den neueren Stand
3. Löschen nur durch Entfernen des Ordners

## 📝 Metadata Format

### Module

```yaml
name: "News Modul"
key: "news_module"
createdate: "2024-11-16 14:00:00"  # Automatisch
updatedate: "2024-11-16 14:30:00"  # Automatisch
createuser: "admin"                 # Automatisch
updateuser: "admin"                 # Automatisch
```

### Templates

```yaml
name: "Standard Layout"
key: "standard_layout"
active: true
attributes:
  ctype: []
  modules:
    1: [1, 2, 3]
  categories: []
createdate: "2024-11-16 14:00:00"
updatedate: "2024-11-16 14:00:00"
createuser: "admin"
updateuser: "admin"
```

### Actions

```yaml
name: "Save Action"
key: "save_action"
preview: ""
presave: |
  // Code vor dem Speichern
  $params['article']->setValue('updateuser', rex::getUser()->getLogin());
postsave: |
  // Code nach dem Speichern
  rex_article_cache::delete($params['article']->getId());
createdate: "2024-11-16 14:00:00"
updatedate: "2024-11-16 14:00:00"
createuser: "admin"
updateuser: "admin"
```

## ⚙️ Einstellungen

### Auto-Sync

- **Im Backend**: Synchronisation alle 50 Sekunden im Backend
- **Im Frontend**: Synchronisation im Frontend (nur wenn als Admin eingeloggt)

### Key-Generierung

Aktivieren um automatisch Keys aus Namen zu generieren:
- "News Modul" → `news_modul`
- "Standard Layout 2024" → `standard_layout_2024`
- Sonderzeichen werden entfernt, Umlaute umgewandelt (ä→ae, ö→oe, ü→ue)

### Konfliktbehandlung

- **Dateien überschreiben**: Dateisystem-Änderungen haben Vorrang
- **DB überschreiben**: Datenbank-Änderungen haben Vorrang
- **Neuer gewinnt**: Zeitstempel entscheidet (nur bei bidirektional sinnvoll)

## 🔄 Workflows

### Entwickler-Workflow (Empfohlen)

```bash
# 1. Sync-Modus auf "Dateien → DB" stellen
# 2. In IDE arbeiten
cd redaxo/data/addons/synch/modules/
mkdir my_module
cd my_module

# 3. metadata.yml erstellen
echo 'name: "My Module"
key: "my_module"' > metadata.yml

# 4. Code-Dateien erstellen
touch "my_module input.php"
touch "my_module output.php"

# 5. Git-Commit
git add .
git commit -m "Add my_module"

# 6. Auf anderen Systemen: Git pull + Sync
git pull
# → Modul erscheint automatisch im Backend
```

### Backend-Workflow

```bash
# 1. Sync-Modus auf "DB → Dateien" stellen
# 2. Im REDAXO-Backend Modul erstellen/bearbeiten
# 3. Dateien werden automatisch erstellt/aktualisiert
# 4. Git-Commit der geänderten Dateien
git add redaxo/data/addons/synch/
git commit -m "Update module from backend"
```

## 🆚 Vergleich mit Developer-Addon

| Feature | Synch | Developer |
|---------|-------|-----------|
| Key-basiert | ✅ | ❌ (ID-basiert) |
| AUTO_INCREMENT IDs | ✅ | ❌ (IDs aus Dateien) |
| Dateien → DB | ✅ | ✅ |
| DB → Dateien | ✅ | ✅ |
| Sync-Modi wählbar | ✅ (3 Modi) | ❌ (immer beide Richtungen) |
| Zeitstempel-Vergleich | ✅ | ❌ (überschreibt immer) |
| Sprechende Dateinamen | ✅ (immer) | ✅ (optional) |
| Cleanup gelöschter Items | ✅ (konfigurierbar) | ✅ |
| Lösch-Schutz im Backend | ✅ (bei files_to_db + bidirectional) | ❌ |

## 🔒 Sicherheit

- **Lösch-Schutz**: In `files_to_db` und `bidirectional` Modi sind Backend-Lösch-Buttons deaktiviert
- **Pause-Mechanismus**: Nach Backend-Änderungen wird Auto-Sync 50 Sekunden pausiert
- **Zeitstempel-Toleranz**: 5 Sekunden Toleranz verhindert Race-Conditions

## 🐛 Troubleshooting

### "Modul wurde nicht gefunden"
- Cache leeren: System → Cache löschen
- Prüfen ob `metadata.yml` existiert und `key` gesetzt ist
- Sync manuell ausführen: Synch → Einstellungen → "Synchronisation durchführen"

### "Items werden doppelt angelegt"
- Prüfen ob alle Items eindeutige Keys haben
- Keys dürfen nicht `null` oder leer sein
- Alte Duplikate mit SQL entfernen: `DELETE FROM rex_module WHERE key IS NULL`

### "Gelöschte Items kommen zurück"
- Sync-Modus prüfen: Bei `bidirectional` müssen Items im Dateisystem gelöscht werden
- Bei `files_to_db`: Ordner löschen, dann Sync ausführen
- Bei `db_to_files`: Im Backend löschen (Dateien bleiben)

### "Änderungen werden nicht übernommen"
- Zeitstempel prüfen: Nur neuere Änderungen werden übernommen
- Sync-Modus prüfen: Richtige Richtung eingestellt?
- Auto-Sync aktiviert? Oder manuell triggern

## 📚 Best Practices

1. **Wähle einen Sync-Modus und bleibe dabei**: Häufiges Wechseln kann zu Verwirrung führen
2. **Verwende aussagekräftige Keys**: `news_teaser` statt `modul_1`
3. **Keys nie ändern**: Keys sind wie Primärschlüssel, Änderung = neues Item
4. **Git-Ignore für metadata Timestamps**: `updatedate` ändert sich ständig
5. **Teste neue Module in Entwicklungsumgebung**: Nicht direkt auf Live-System
6. **Dokumentiere Custom Keys**: Wenn Auto-Generierung deaktiviert

## 🔗 Links

- [REDAXO Website](https://redaxo.org)
- [GitHub Repository](https://github.com/klxm/synch)
- [Issue Tracker](https://github.com/klxm/synch/issues)

## 📄 Lizenz

MIT License

## 👥 Credits

Entwickelt von KLXM Web Development