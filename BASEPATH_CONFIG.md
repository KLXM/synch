# Synch Addon - Basis-Pfad Konfiguration

## Eigenen Basis-Pfad setzen

Das synch Addon kann so konfiguriert werden, dass es Sync-Dateien in einem eigenen Verzeichnis speichert, anstatt im Standard-Pfad `redaxo/data/addons/synch/`.

### 1. Konfiguration in boot.php

Erstellen oder erweitern Sie die `boot.php` in Ihrem Projekt:

```php
<?php
// redaxo/src/addons/project/boot.php

// Synch Addon Basis-Pfad konfigurieren
if (rex_addon::get('synch')->isAvailable()) {
    synch_manager::setBasePath(rex_path::src());
}
```

### 2. Mögliche Pfad-Optionen

```php
// Option 1: Im src/ Verzeichnis (empfohlen für Git)
synch_manager::setBasePath(rex_path::src());
// Resultat: redaxo/src/modules/, redaxo/src/templates/, redaxo/src/actions/

// Option 2: Eigener sync/ Ordner im Projekt-Root
synch_manager::setBasePath(rex_path::base('sync'));
// Resultat: sync/modules/, sync/templates/, sync/actions/

// Option 3: Absoluter Pfad (z.B. für Git-Repository außerhalb von REDAXO)
synch_manager::setBasePath('/var/www/my-project/redaxo-components');
// Resultat: /var/www/my-project/redaxo-components/modules/, etc.

// Option 4: Im assets/ Verzeichnis
synch_manager::setBasePath(rex_path::assets());
// Resultat: assets/modules/, assets/templates/, assets/actions/
```

### 3. Git Integration

Für optimale Git-Integration fügen Sie das Sync-Verzeichnis zur `.gitignore` hinzu, aber **nicht** die Inhalte:

```gitignore
# .gitignore

# REDAXO Standard-Verzeichnisse ignorieren
/redaxo/data/
/redaxo/cache/

# Aber Sync-Verzeichnisse einschließen
!/src/modules/
!/src/templates/
!/src/actions/
```

### 4. Team-Setup

Für Teams empfiehlt sich folgende Struktur in der `boot.php`:

```php
<?php
// Projekt-spezifische Konfiguration
if (rex_addon::get('synch')->isAvailable()) {
    // Entwicklungsumgebung: Sync-Dateien im src/
    if (rex::isDebugMode() || rex_server('SERVER_NAME') === 'localhost') {
        synch_manager::setBasePath(rex_path::src('redaxo-sync'));
    }
    // Produktionsumgebung: Standard-Pfad
    else {
        // synch_manager::setBasePath() nicht aufrufen = Standard-Pfad
    }
}
```

### 5. Migration von Standard-Pfad

Wenn Sie bereits Sync-Dateien im Standard-Pfad haben:

```bash
# Verschieben der bestehenden Dateien
mv redaxo/data/addons/synch/modules/* src/modules/
mv redaxo/data/addons/synch/templates/* src/templates/
mv redaxo/data/addons/synch/actions/* src/actions/
```

Dann die `boot.php` Konfiguration hinzufügen und einmal synchronisieren lassen.