# Synch - Moderne Key-basierte Synchronisation für REDAXO

Das **Synch** Addon bietet eine moderne, key-basierte Synchronisation zwischen Dateisystem und Datenbank als Alternative zum Developer Addon. Entwickelt für REDAXO 5.15+ ohne Legacy-Altlasten.

## Features

✅ **Key-basierte Synchronisation** - Module, Templates und Actions mit eindeutigen Keys  
✅ **Saubere Ordnernamen** - Nur der Key als Ordnername, keine ID-Anhänge wie `[23]`  
✅ **Actions-Support** - Vollständige Synchronisation für Actions (Preview, Presave, Postsave)  
✅ **Automatische Key-Generierung** - Intelligente Key-Erstellung aus Namen  
✅ **Change-Detection** - Synchronisation nur bei tatsächlichen Änderungen (Performance)  
✅ **Pausieren-Funktion** - Auto-Sync temporär deaktivieren für Entwicklung  
✅ **Console Commands** - `synch:sync` mit erweiterten Optionen  
✅ **Migration-Support** - Einfache Migration vom developer Addon  

## Installation

1. Addon in das REDAXO-Verzeichnis `src/addons/synch/` kopieren
2. Addon im Backend aktivieren
3. Einstellungen nach Bedarf anpassen

### Eigener Basis-Pfad (Optional)

Der Standard-Pfad für Sync-Dateien ist `redaxo/data/addons/synch/`. Ein eigener Pfad kann definiert werden, z.B. im Projekt-Root:

```php
// In boot.php oder config.php
if (rex_addon::get('synch')->isAvailable()) {
    synch_manager::setBasePath(rex_path::src());
}
```

**Beispiele für eigene Pfade:**
```php
// Alle Sync-Dateien im src/ Verzeichnis
synch_manager::setBasePath(rex_path::src());

// Eigener sync/ Ordner im Projekt-Root  
synch_manager::setBasePath(rex_path::base('sync'));

// In einem Git-Repository außerhalb von REDAXO
synch_manager::setBasePath('/path/to/your/git-repo/redaxo-sync');
```

**Ordnerstruktur bei eigenem Pfad:**
```
src/                              # Bei setBasePath(rex_path::src())
├── modules/
│   ├── news_module/
│   └── contact_form/
├── templates/ 
│   ├── default_template/
│   └── mobile_template/
└── actions/
    ├── newsletter_signup/
    └── contact_validation/
```

## Verwendung

### Backend
- **Synch > Einstellungen**: Konfiguration der Synchronisations-Optionen
- **"Synchronisation ausführen"** Button für manuelle Sync

### Console
```bash
# Komplette Synchronisation (Module, Templates, Actions)
php redaxo/bin/console synch:sync

# Nur Module
php redaxo/bin/console synch:sync --modules-only

# Nur Templates  
php redaxo/bin/console synch:sync --templates-only

# Nur Actions
php redaxo/bin/console synch:sync --actions-only

# Dry Run (keine Änderungen)
php redaxo/bin/console synch:sync --dry-run
```

## Ordnerstruktur

### Module
```
redaxo/data/addons/synch/modules/
├── news_module/
│   ├── metadata.yml
│   ├── input.php
│   └── output.php
└── contact_form/
    ├── metadata.yml
    ├── input.php
    └── output.php
```

### Templates
```
redaxo/data/addons/synch/templates/
├── default_template/
│   ├── metadata.yml
│   └── template.php
└── news_detail/
    ├── metadata.yml
    └── template.php
```

### Actions
```
redaxo/data/addons/synch/actions/
├── newsletter_signup/
│   ├── metadata.yml
│   └── action.php
└── contact_form/
    ├── metadata.yml
    └── action.php
```

## Konfiguration

| Option | Beschreibung | Standard |
|--------|--------------|----------|
| `auto_generate_keys` | Automatische Key-Generierung für Items ohne Key | `true` |
| `key_generation_strategy` | Strategie für Key-Generierung | `name_based` |
| `update_existing_on_key_conflict` | Aktualisiert existierende Items bei Konflikten | `true` |
| `sync_frontend` | Auto-Sync im Frontend (nur für Admins) | `false` |
| `sync_backend` | Auto-Sync im Backend (nur für Admins) | `true` |

### Key-Generierungs-Strategien

- **`name_based`** (empfohlen): `"News Module" → "news_module"`
- **`date_name`**: `"News Module" → "20241105_news_module"`  
- **`hash_based`**: `"News Module" → "a1b2c3d4_news_module"`

## Performance & Entwicklung

### Change-Detection
Das Addon nutzt intelligente Change-Detection:
- Prüft nur alle 60 Sekunden auf Änderungen (Cache)
- Synchronisiert nur bei tatsächlichen Updates
- Vergleicht Timestamps zwischen DB und Dateisystem

### Auto-Sync Pausieren
Für die Entwicklung lässt sich die automatische Synchronisation pausieren:
- **Pausieren-Button** in den Einstellungen
- Pausierung endet automatisch nach 30 Minuten
- Status wird mit Countdown angezeigt

## Migration vom developer Addon

```php
// Module migrieren
$results = synch_migration::migrateModulesFromDeveloper();

// Templates migrieren  
$results = synch_migration::migrateTemplatesFromDeveloper();
```

## Dateiformate

### metadata.yml (Module)
```yaml
name: "News Module"
key: "news_module" 
createdate: "2025-11-05 12:00:00"
updatedate: "2025-11-05 15:30:00"
createuser: "admin"
updateuser: "developer"
```

### metadata.yml (Templates)
```yaml
name: "Default Template"
key: "default_template"
active: true
createdate: "2025-11-05 12:00:00"  
updatedate: "2025-11-05 15:30:00"
createuser: "admin"
updateuser: "developer"
```

### metadata.yml (Actions)
```yaml
name: "Newsletter Signup"
key: "newsletter_signup"
createdate: "2025-11-05 12:00:00"
updatedate: "2025-11-05 15:30:00" 
createuser: "admin"
updateuser: "developer"
```

### action.php (Actions)
```php
<?php

/**
 * Newsletter Signup
 * Key: newsletter_signup
 */

// === PREVIEW ===
echo "Newsletter Anmeldung Vorschau";

// === PRESAVE ===
if (!$_POST['email']) {
    echo "E-Mail-Adresse ist erforderlich";
    exit;
}

// === POSTSAVE ===
mail('admin@example.com', 'Neue Newsletter-Anmeldung', $_POST['email']);
```

## Best Practices

1. **Eindeutige Keys**: Beschreibende, eindeutige Keys verwenden
2. **Naming Convention**: `module_name`, `template_name` (lowercase, underscores)
3. **Git-Integration**: Ordner in Version Control einbeziehen
4. **Automatisierung**: Sync in Deploy-Prozess integrieren
5. **Eigener Basis-Pfad**: Für bessere Git-Integration außerhalb von `data/`

### Basis-Pfad Empfehlungen

**Standard-Pfad** (`redaxo/data/addons/synch/`):
- ✅ Funktioniert sofort ohne Konfiguration
- ✅ Wird automatisch bei Addon-Installation erstellt
- ❌ Liegt im `data/` Verzeichnis (oft nicht in Git)

**Eigener Pfad** (z.B. `src/`):
- ✅ **Git-Integration**: Sync-Dateien direkt im Repository
- ✅ **Team-Entwicklung**: Einheitliche Pfade für alle Entwickler
- ✅ **CI/CD-freundlich**: Deploy-Prozesse einfacher
- ✅ **Backup-sicher**: Teil des Code-Repositories
- ❌ Erfordert einmalige Konfiguration

## Vorteile für Teams

- 🎯 **Keine ID-Konflikte** mehr zwischen Entwicklern
- 🧹 **Saubere Ordnernamen** für bessere Übersicht
- 🔄 **Einfache Synchronisation** zwischen Umgebungen
- 📦 **Git-freundlich** durch konsistente Dateinamen
- ⚡ **Schnellere Entwicklung** ohne manuelle ID-Verwaltung

## Troubleshooting

**Problem**: Module werden doppelt erstellt  
**Lösung**: `update_existing_on_key_conflict` aktivieren

**Problem**: Keys kollidieren  
**Lösung**: Eindeutige Keys in metadata.yml definieren

**Problem**: Ordner haben immer noch IDs  
**Lösung**: Einmal manuell synchronisieren - saubere Ordnernamen sind immer aktiv