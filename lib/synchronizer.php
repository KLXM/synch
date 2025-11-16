<?php

namespace KLXM\Synch;

use rex_dir;
use rex_addon;
use rex_file;
use rex_sql;
use rex_path;
use rex;
use Exception;

/**
 * Key-basierte Synchronisation für Module, Templates und Actions
 * 
 * Inspiriert vom developer Addon, aber mit Keys statt IDs.
 * Bidirektionale Synchronisation basierend auf Zeitstempeln.
 * 
 * WORKFLOW:
 * =========
 * 1. Neues Item im Dateisystem anlegen (Ordner + metadata.yml + input.php + output.php)
 *    -> wird in DB importiert (AUTO_INCREMENT ID)
 * 
 * 2. Item in DB ändern
 *    -> Dateien werden aktualisiert (wenn DB neuer als Dateien)
 * 
 * 3. Dateien ändern
 *    -> DB wird aktualisiert (wenn Dateien neuer als DB)
 * 
 * 4. Item in DB löschen
 *    -> Dateien werden gelöscht
 * 
 * 5. Dateien löschen
 *    -> Item bleibt in DB (wird beim nächsten Sync wieder angelegt)
 *    -> Um Item komplett zu löschen: In DB löschen!
 */
abstract class Synchronizer
{
    protected string $baseDir;
    protected string $tableName;
    protected string $keyColumn = 'key';
    protected string $nameColumn = 'name';
    protected string $dirname;
    
    const METADATA_FILE = 'metadata.yml';

    public function __construct(string $dirname, string $tableName)
    {
        $this->dirname = $dirname;
        $this->tableName = $tableName;
        $this->baseDir = rex_path::addonData('synch', $dirname . '/');
        
        // Sicherstellen dass Base-Directory existiert
        if (!is_dir($this->baseDir)) {
            rex_dir::create($this->baseDir);
        }
    }

    /**
     * Hauptsynchronisations-Methode
     * Bidirektionale Sync: DB <-> Dateisystem
     * Respektiert sync_direction Setting
     */
    public function sync(): bool
    {
        try {
            $syncDirection = rex_addon::get('synch')->getConfig('sync_direction', 'bidirectional');
            
            // 1. Existierende Items aus DB holen und ihre Keys tracken
            $dbItems = $this->getItemsFromDatabase();
            $dbKeys = [];
            
            // 2. Existierende Verzeichnisse scannen
            $fsItems = $this->getItemsFromFilesystem();
            
            // 3. DB Items mit Dateisystem synchronisieren (nur wenn nicht files_to_db)
            if ($syncDirection !== 'files_to_db') {
                foreach ($dbItems as $item) {
                    $key = $item[$this->keyColumn] ?? null;
                    
                    // Key fehlt? -> generieren
                    if (empty($key)) {
                        $name = $item[$this->nameColumn] ?? 'unnamed';
                        $key = $this->generateKey($name);
                        $key = $this->ensureUniqueKey($key);
                        $this->updateItemKey($item['id'], $key);
                        $item[$this->keyColumn] = $key;
                    }
                    
                    $dbKeys[] = $key;
                    $this->syncItemToFilesystem($item, $fsItems);
                }
            } else {
                // files_to_db mode: nur Keys sammeln, keine DB→Files Sync
                foreach ($dbItems as $item) {
                    $key = $item[$this->keyColumn] ?? null;
                    if (!empty($key)) {
                        $dbKeys[] = $key;
                    }
                }
            }
            
            // 4. Dateisystem Items zur DB hinzufügen (nur wenn nicht db_to_files)
            if ($syncDirection !== 'db_to_files') {
                foreach ($fsItems as $key => $dirPath) {
                    if (!in_array($key, $dbKeys)) {
                        $this->syncItemToDatabase($key, $dirPath);
                    }
                }
            }
            
            // 5. Cleanup-Logik je nach Sync-Richtung
            if ($syncDirection === 'files_to_db' || $syncDirection === 'bidirectional') {
                // files_to_db oder bidirectional: Gelöschte Dateien → aus DB löschen
                $this->cleanupDeletedItemsFromDatabase(array_keys($fsItems));
            }
            
            if ($syncDirection === 'db_to_files' || $syncDirection === 'bidirectional') {
                // db_to_files oder bidirectional: Gelöschte DB Items → Dateien löschen
                $this->cleanupDeletedItems($dbKeys, $fsItems);
            }
            
            return true;
        } catch (Exception $e) {
            error_log('SYNCH ERROR: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Holt alle Items aus der Datenbank
     */
    protected function getItemsFromDatabase(): array
    {
        $sql = rex_sql::factory();
        $sql->setQuery('SELECT * FROM ' . $sql->escapeIdentifier($this->tableName) . ' ORDER BY id');
        return $sql->getArray();
    }

    /**
     * Scannt Dateisystem nach Item-Verzeichnissen
     * Return: ['key' => '/full/path/to/dir/', ...]
     */
    protected function getItemsFromFilesystem(): array
    {
        $items = [];
        
        if (!is_dir($this->baseDir)) {
            return $items;
        }
        
        $dirs = scandir($this->baseDir);
        foreach ($dirs as $dir) {
            if ($dir === '.' || $dir === '..') {
                continue;
            }
            
            $fullPath = $this->baseDir . $dir . '/';
            if (!is_dir($fullPath)) {
                continue;
            }
            
            $metadataFile = $fullPath . self::METADATA_FILE;
            if (file_exists($metadataFile)) {
                $metadata = rex_file::getConfig($metadataFile);
                $key = $metadata['key'] ?? $dir;
                $items[$key] = $fullPath;
            }
        }
        
        return $items;
    }

    /**
     * Synchronisiert ein DB-Item ins Dateisystem
     * Vergleicht Zeitstempel und aktualisiert nur wenn DB neuer ist
     */
    protected function syncItemToFilesystem(array $item, array &$fsItems): void
    {
        $key = $item[$this->keyColumn];
        $dirName = $this->cleanKey($key);
        $dirPath = $this->baseDir . $dirName . '/';
        
        // Verzeichnis existiert noch nicht -> erstellen und Dateien schreiben
        if (!is_dir($dirPath)) {
            rex_dir::create($dirPath);
            $this->writeItemFiles($dirPath, $item);
            $fsItems[$key] = $dirPath; // Zum Tracking hinzufügen
            return;
        }
        
        // Verzeichnis existiert -> Zeitstempel vergleichen
        $metadataFile = $dirPath . self::METADATA_FILE;
        if (!file_exists($metadataFile)) {
            // Metadata fehlt -> neu schreiben
            $this->writeItemFiles($dirPath, $item);
            return;
        }
        
        $metadata = rex_file::getConfig($metadataFile);
        $dbTime = strtotime($item['updatedate'] ?? '1970-01-01 00:00:00');
        $fsTime = strtotime($metadata['updatedate'] ?? '1970-01-01 00:00:00');
        
        // DB ist neuer -> Dateien überschreiben
        if ($dbTime > $fsTime) {
            $this->writeItemFiles($dirPath, $item);
        }
        // Dateisystem ist neuer -> DB aktualisieren
        else if ($fsTime > $dbTime + 5) { // 5 Sek Toleranz
            $this->updateItem((int)$item['id'], $dirPath, $metadata);
        }
    }

    /**
     * Synchronisiert ein Dateisystem-Item zur DB
     * Wird nur für NEUE Items aufgerufen (nicht in DB vorhanden)
     */
    protected function syncItemToDatabase(string $key, string $dirPath): void
    {
        $metadataFile = $dirPath . self::METADATA_FILE;
        
        if (!file_exists($metadataFile)) {
            return;
        }
        
        try {
            $metadata = rex_file::getConfig($metadataFile);
            
            // Prüfe ob Item mit gleichem Namen existiert (um Duplikate zu vermeiden)
            if (!empty($metadata['name'])) {
                $existingItem = $this->findItemByName($metadata['name']);
                if ($existingItem) {
                    error_log('SYNCH INFO: Skipping "' . $metadata['name'] . '" - item with same name exists');
                    return;
                }
            }
            
            // Item erstellen (verwendet AUTO_INCREMENT für ID)
            $this->createItem($dirPath, $metadata);
            
        } catch (Exception $e) {
            error_log('SYNCH ERROR creating item from ' . $dirPath . ': ' . $e->getMessage());
        }
    }

    /**
     * Löscht Verzeichnisse für Items die nicht mehr in DB existieren
     */
    protected function cleanupDeletedItems(array $dbKeys, array $fsItems): void
    {
        foreach ($fsItems as $key => $dirPath) {
            if (!in_array($key, $dbKeys)) {
                // Item existiert nicht mehr in DB -> Verzeichnis löschen
                if (is_dir($dirPath)) {
                    rex_dir::delete($dirPath);
                    error_log('SYNCH: Deleted directory for removed item: ' . $key);
                }
            }
        }
    }

    /**
     * Löscht DB Items die nicht mehr im Dateisystem existieren
     * Für files_to_db und bidirectional Mode
     */
    protected function cleanupDeletedItemsFromDatabase(array $fsKeys): void
    {
        $dbItems = $this->getItemsFromDatabase();
        
        foreach ($dbItems as $item) {
            $key = $item[$this->keyColumn] ?? null;
            
            if (empty($key)) {
                continue; // Items ohne Key überspringen
            }
            
            // Item existiert nicht mehr im Dateisystem -> aus DB löschen
            if (!in_array($key, $fsKeys)) {
                $this->deleteItem($item['id']);
                error_log('SYNCH: Deleted DB item for removed directory: ' . $key);
            }
        }
    }

    /**
     * Generiert einen sauberen Key aus einem Namen
     */
    protected function generateKey(string $name): string
    {
        if (empty($name) || trim($name) === '') {
            $name = 'unnamed_' . time();
        }
        
        return $this->cleanKey($name);
    }

    /**
     * Bereinigt einen String für die Verwendung als Key/Ordnername
     */
    protected function cleanKey(string $input): string
    {
        if (empty($input)) {
            return 'unnamed';
        }
        
        // Umlaute ersetzen
        $input = str_replace(
            ['ä', 'ö', 'ü', 'Ä', 'Ö', 'Ü', 'ß'],
            ['ae', 'oe', 'ue', 'Ae', 'Oe', 'Ue', 'ss'],
            $input
        );
        
        // Nur alphanumerische Zeichen und Unterstriche
        $input = preg_replace('/[^a-zA-Z0-9_]/', '_', $input);
        
        // Mehrfache Unterstriche entfernen
        $input = preg_replace('/_+/', '_', $input);
        
        // Unterstriche am Anfang/Ende entfernen
        $input = trim($input, '_');
        
        // Kleinbuchstaben
        return strtolower($input);
    }

    /**
     * Stellt sicher dass ein Key eindeutig ist
     */
    protected function ensureUniqueKey(string $baseKey, int $excludeId = null): string
    {
        $key = $baseKey;
        $counter = 1;
        
        while (true) {
            $existingItem = $this->findItemByKey($key);
            
            // Key ist frei oder gehört zum ausgeschlossenen Item
            if (!$existingItem || ($excludeId && $existingItem['id'] == $excludeId)) {
                break;
            }
            
            $key = $baseKey . '_' . $counter;
            $counter++;
        }
        
        return $key;
    }

    /**
     * Findet ein Item anhand des Keys
     */
    protected function findItemByKey(string $key): ?array
    {
        $sql = rex_sql::factory();
        $sql->setQuery(
            'SELECT * FROM ' . $sql->escapeIdentifier($this->tableName) . 
            ' WHERE ' . $sql->escapeIdentifier($this->keyColumn) . ' = ?',
            [$key]
        );
        
        return $sql->getRows() > 0 ? $sql->getRow() : null;
    }

    /**
     * Findet ein Item anhand des Namens
     */
    protected function findItemByName(string $name): ?array
    {
        $sql = rex_sql::factory();
        $sql->setQuery(
            'SELECT * FROM ' . $sql->escapeIdentifier($this->tableName) . 
            ' WHERE ' . $sql->escapeIdentifier($this->nameColumn) . ' = ?',
            [$name]
        );
        
        return $sql->getRows() > 0 ? $sql->getRow() : null;
    }

    /**
     * Aktualisiert den Key eines Items in der Datenbank
     */
    protected function updateItemKey(int $id, string $key): void
    {
        $key = $this->ensureUniqueKey($key, $id);
        
        $sql = rex_sql::factory();
        $sql->setTable($this->tableName);
        $sql->setWhere(['id' => $id]);
        $sql->setValue($this->keyColumn, $key);
        $sql->update();
    }

    /**
     * Löscht ein Item aus der Datenbank
     */
    protected function deleteItem(int $id): void
    {
        $sql = rex_sql::factory();
        $sql->setTable($this->tableName);
        $sql->setWhere(['id' => $id]);
        $sql->delete();
    }

    /**
     * Gibt den Dateinamen für Input/Output/Template/Action zurück
     * Immer mit Key als Prefix (sprechende Dateinamen)
     */
    protected function getFilename(string $key, string $type): string
    {
        return $key . ' ' . $type . '.php';
    }

    // Abstract Methoden - müssen von Subklassen implementiert werden
    
    /**
     * Schreibt alle Dateien eines Items ins Dateisystem
     * (metadata.yml + input.php + output.php / template.php / action.php)
     */
    abstract protected function writeItemFiles(string $dir, array $item): void;
    
    /**
     * Aktualisiert ein existierendes Item in der DB aus dem Dateisystem
     */
    abstract protected function updateItem(int $id, string $dir, array $metadata): void;
    
    /**
     * Erstellt ein neues Item in der DB aus dem Dateisystem
     * Verwendet AUTO_INCREMENT für die ID
     */
    abstract protected function createItem(string $dir, array $metadata): void;
}
