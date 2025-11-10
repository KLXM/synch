<?php

/**
 * Install-Script für synch Addon
 * Erweitert die Tabellen um Key-Spalten und generiert Keys für bestehende Einträge
 */

$sql = rex_sql::factory();

// Automatische Synchronisation während Installation deaktivieren
$addon = rex_addon::get('synch');
$originalSyncBackend = $addon->getConfig('sync_backend', false);
$addon->setConfig('sync_backend', false);

/**
 * Hilfsfunktion zum Generieren eines sauberen Keys
 */
function generateCleanKey(string $name): string {
    // Umlaute ersetzen
    $key = str_replace(['ä', 'ö', 'ü', 'Ä', 'Ö', 'Ü', 'ß'], 
                      ['ae', 'oe', 'ue', 'Ae', 'Oe', 'Ue', 'ss'], $name);
    
    // Nur alphanumerische Zeichen und Unterstriche
    $key = preg_replace('/[^a-zA-Z0-9_]/', '_', $key);
    
    // Mehrfache Unterstriche entfernen
    $key = preg_replace('/_+/', '_', $key);
    
    // Unterstriche am Anfang/Ende entfernen
    $key = trim($key, '_');
    
    // Kleinbuchstaben
    return strtolower($key);
}

/**
 * Hilfsfunktion um eindeutigen Key zu generieren
 */
function ensureUniqueKey(string $baseKey, string $table, string $keyColumn): string {
    global $sql;
    $key = $baseKey;
    $counter = 1;
    
    while (true) {
        $sql->setQuery('SELECT COUNT(*) as count FROM ' . $table . ' WHERE ' . $keyColumn . ' = ?', [$key]);
        if ($sql->getValue('count') == 0) {
            break;
        }
        $key = $baseKey . '_' . $counter;
        $counter++;
    }
    
    return $key;
}

// 1. KEY-SPALTE ZU REX_MODULE HINZUFÜGEN
try {
    $sql->setQuery('DESCRIBE ' . rex::getTable('module'));
    $columns = [];
    
    while ($sql->hasNext()) {
        $columns[] = $sql->getValue('Field');
        $sql->next();
    }
    
    if (!in_array('key', $columns)) {
        $sql->setQuery('
            ALTER TABLE ' . rex::getTable('module') . ' 
            ADD COLUMN `key` varchar(191) NULL AFTER `id`,
            ADD UNIQUE KEY `key` (`key`)
        ');
        
        echo rex_view::success('Module-Tabelle erfolgreich um Key-Spalte erweitert');
        
        // Keys für bestehende Module generieren
        $sql->setQuery('SELECT id, name FROM ' . rex::getTable('module') . ' WHERE `key` IS NULL OR `key` = ""');
        while ($sql->hasNext()) {
            $id = $sql->getValue('id');
            $name = $sql->getValue('name') ?: 'module_' . $id;
            
            $baseKey = generateCleanKey($name);
            $uniqueKey = ensureUniqueKey($baseKey, rex::getTable('module'), 'key');
            
            $updateSql = rex_sql::factory();
            $updateSql->setTable(rex::getTable('module'));
            $updateSql->setWhere(['id' => $id]);
            $updateSql->setValue('key', $uniqueKey);
            $updateSql->update();
            
            $sql->next();
        }
        
        echo rex_view::success('Keys für bestehende Module generiert');
    }
    
} catch (Exception $e) {
    echo rex_view::error('Fehler beim Erweitern der Module-Tabelle: ' . $e->getMessage());
}

// 2. KEY-SPALTE ZU REX_TEMPLATE HINZUFÜGEN
try {
    $sql->setQuery('DESCRIBE ' . rex::getTable('template'));
    $columns = [];
    
    while ($sql->hasNext()) {
        $columns[] = $sql->getValue('Field');
        $sql->next();
    }
    
    if (!in_array('key', $columns)) {
        $sql->setQuery('
            ALTER TABLE ' . rex::getTable('template') . ' 
            ADD COLUMN `key` varchar(191) NULL AFTER `id`,
            ADD UNIQUE KEY `key` (`key`)
        ');
        
        echo rex_view::success('Template-Tabelle erfolgreich um Key-Spalte erweitert');
        
        // Keys für bestehende Templates generieren
        $sql->setQuery('SELECT id, name FROM ' . rex::getTable('template') . ' WHERE `key` IS NULL OR `key` = ""');
        while ($sql->hasNext()) {
            $id = $sql->getValue('id');
            $name = $sql->getValue('name') ?: 'template_' . $id;
            
            $baseKey = generateCleanKey($name);
            $uniqueKey = ensureUniqueKey($baseKey, rex::getTable('template'), 'key');
            
            $updateSql = rex_sql::factory();
            $updateSql->setTable(rex::getTable('template'));
            $updateSql->setWhere(['id' => $id]);
            $updateSql->setValue('key', $uniqueKey);
            $updateSql->update();
            
            $sql->next();
        }
        
        echo rex_view::success('Keys für bestehende Templates generiert');
    }
    
} catch (Exception $e) {
    echo rex_view::error('Fehler beim Erweitern der Template-Tabelle: ' . $e->getMessage());
}

// 3. KEY-SPALTE ZU REX_ACTION HINZUFÜGEN
try {
    $sql->setQuery('DESCRIBE ' . rex::getTable('action'));
    $columns = [];
    
    while ($sql->hasNext()) {
        $columns[] = $sql->getValue('Field');
        $sql->next();
    }
    
    if (!in_array('key', $columns)) {
        $sql->setQuery('
            ALTER TABLE ' . rex::getTable('action') . ' 
            ADD COLUMN `key` varchar(191) NULL AFTER `id`,
            ADD UNIQUE KEY `key` (`key`)
        ');
        
        echo rex_view::success('Action-Tabelle erfolgreich um Key-Spalte erweitert');
        
        // Keys für bestehende Actions generieren
        $sql->setQuery('SELECT id, name FROM ' . rex::getTable('action') . ' WHERE `key` IS NULL OR `key` = ""');
        while ($sql->hasNext()) {
            $id = $sql->getValue('id');
            $name = $sql->getValue('name') ?: 'action_' . $id;
            
            $baseKey = generateCleanKey($name);
            $uniqueKey = ensureUniqueKey($baseKey, rex::getTable('action'), 'key');
            
            $updateSql = rex_sql::factory();
            $updateSql->setTable(rex::getTable('action'));
            $updateSql->setWhere(['id' => $id]);
            $updateSql->setValue('key', $uniqueKey);
            $updateSql->update();
            
            $sql->next();
        }
        
        echo rex_view::success('Keys für bestehende Actions generiert');
    }
    
} catch (Exception $e) {
    echo rex_view::error('Fehler beim Erweitern der Action-Tabelle: ' . $e->getMessage());
}

// Ursprüngliche Synchronisations-Einstellung wiederherstellen
// WICHTIG: Per Default ist die automatische Synchronisation DEAKTIVIERT
// Der Benutzer muss sie explizit in den Einstellungen aktivieren
$addon->setConfig('sync_backend', false);  // Explizit deaktiviert lassen
$addon->setConfig('sync_frontend', false); // Explizit deaktiviert lassen

// Installation erfolgreich abgeschlossen
echo rex_view::success('Synch Addon erfolgreich installiert. Alle Tabellen wurden um Key-Spalten erweitert und bestehende Einträge haben automatisch Keys erhalten.');
echo rex_view::info('<strong>Hinweis:</strong> Die automatische Synchronisation ist standardmäßig <strong>deaktiviert</strong>. Sie können sie in den Addon-Einstellungen aktivieren.');