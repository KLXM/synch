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
    private const CONFLICT_NEWER_WINS = 'newer_wins';
    private const CONFLICT_FILESYSTEM_WINS = 'filesystem_wins';
    private const CONFLICT_DATABASE_WINS = 'database_wins';

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
                        $key = $this->generateKey((string) $name);
                        $key = $this->ensureUniqueKey($key);
                        $this->updateItemKey((int) $item['id'], $key);
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
                    if (!in_array($key, $dbKeys, true)) {
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
        *
        * @return list<array<string, mixed>>
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
        *
        * @return array<string, string>
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
                $key = (string) ($metadata['key'] ?? $dir);
                $items[$key] = $fullPath;
            }
        }
        
        return $items;
    }

    /**
     * Synchronisiert ein DB-Item ins Dateisystem
     * Vergleicht Zeitstempel und aktualisiert nur wenn DB neuer ist
        *
        * @param array<string, mixed> $item
        * @param array<string, string> $fsItems
     */
    protected function syncItemToFilesystem(array $item, array &$fsItems): void
    {
        $key = (string) $item[$this->keyColumn];
        $dirName = $this->cleanKey($key);
        $dirPath = $this->baseDir . $dirName . '/';
        $syncDirection = (string) rex_addon::get('synch')->getConfig('sync_direction', 'files_to_db');
        
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

        if ($syncDirection === 'db_to_files') {
            $this->writeItemFiles($dirPath, $item);
            return;
        }

        // Nur bidirectional erreicht diesen Zweig.
        $decision = $this->resolveConflict($dbTime, $fsTime);
        if ($decision === self::CONFLICT_DATABASE_WINS) {
            $this->writeItemFiles($dirPath, $item);
            return;
        }

        if ($decision === self::CONFLICT_FILESYSTEM_WINS) {
            $this->updateItem((int) $item['id'], $dirPath, $metadata);
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
            
            $updateExisting = (bool) rex_addon::get('synch')->getConfig('update_existing_on_key_conflict', true);

            // Gleiches Key-Item explizit behandeln
            $existingByKey = $this->findItemByKey($key);
            if ($existingByKey !== null) {
                if ($updateExisting) {
                    $existingByKeyId = $this->extractRowId($existingByKey);
                    if ($existingByKeyId !== null) {
                        $this->updateItem($existingByKeyId, $dirPath, $metadata);
                    }
                }

                return;
            }

            // Prüfe ob Item mit gleichem Namen existiert
            if (!empty($metadata['name'])) {
                $existingItem = $this->findItemByName((string) $metadata['name']);
                if ($existingItem) {
                    if ($updateExisting) {
                        $existingItemId = $this->extractRowId($existingItem);
                        if ($existingItemId !== null) {
                            $this->updateItem($existingItemId, $dirPath, $metadata);
                        }
                    }

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
        *
        * @param list<string> $dbKeys
        * @param array<string, string> $fsItems
     */
    protected function cleanupDeletedItems(array $dbKeys, array $fsItems): void
    {
        foreach ($fsItems as $key => $dirPath) {
            if (!in_array($key, $dbKeys, true)) {
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
        *
        * @param list<string> $fsKeys
     */
    protected function cleanupDeletedItemsFromDatabase(array $fsKeys): void
    {
        $allowEmptyCleanup = (bool) rex_addon::get('synch')->getConfig('allow_empty_filesystem_cleanup', false);
        if (!$allowEmptyCleanup && count($fsKeys) === 0) {
            error_log('SYNCH SAFETY: Skip DB cleanup because filesystem scan is empty.');
            return;
        }

        $dbItems = $this->getItemsFromDatabase();
        
        foreach ($dbItems as $item) {
            $key = $item[$this->keyColumn] ?? null;
            
            if (empty($key)) {
                continue; // Items ohne Key überspringen
            }
            
            // Item existiert nicht mehr im Dateisystem -> aus DB löschen
            if (!in_array((string) $key, $fsKeys, true)) {
                $this->deleteItem((int) $item['id']);
                error_log('SYNCH: Deleted DB item for removed directory: ' . $key);
            }
        }
    }

    /**
     * Generiert einen sauberen Key aus einem Namen
     */
    protected function generateKey(string $name): string
    {
        if ($name === '' || trim($name) === '') {
            $name = 'unnamed_' . time();
        }
        
        return $this->cleanKey($name);
    }

    /**
     * Bereinigt einen String für die Verwendung als Key/Ordnername
     */
    protected function cleanKey(string $input): string
    {
        if ($input === '') {
            return 'unnamed';
        }
        
        // Umlaute ersetzen
        $input = str_replace(
            ['ä', 'ö', 'ü', 'Ä', 'Ö', 'Ü', 'ß'],
            ['ae', 'oe', 'ue', 'Ae', 'Oe', 'Ue', 'ss'],
            $input
        );
        
        // Nur alphanumerische Zeichen und Unterstriche
        $input = preg_replace('/[^a-zA-Z0-9_]/', '_', $input) ?? $input;
        
        // Mehrfache Unterstriche entfernen
        $input = preg_replace('/_+/', '_', $input) ?? $input;
        
        // Unterstriche am Anfang/Ende entfernen
        $input = trim($input, '_');
        
        // Kleinbuchstaben
        return strtolower($input);
    }

    /**
     * Stellt sicher dass ein Key eindeutig ist
     */
    protected function ensureUniqueKey(string $baseKey, ?int $excludeId = null): string
    {
        $key = $baseKey;
        $counter = 1;
        
        while (true) {
            $existingItem = $this->findItemByKey($key);
            
            // Key ist frei oder gehört zum ausgeschlossenen Item
            if ($existingItem === null || ($excludeId !== null && (int) $existingItem['id'] === $excludeId)) {
                break;
            }
            
            $key = $baseKey . '_' . $counter;
            $counter++;
        }
        
        return $key;
    }

    /**
     * Findet ein Item anhand des Keys
        *
        * @return array<string, mixed>|null
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
        *
        * @return array<string, mixed>|null
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
     * @param array<string, mixed> $row
     */
    private function extractRowId(array $row): ?int
    {
        $rawId = $row['id'] ?? $row['ID'] ?? null;
        if ($rawId === null || $rawId === '') {
            return null;
        }

        return (int) $rawId;
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

    /**
     * @return string
     */
    public function calculateStateHash(): string
    {
        $dbHash = $this->calculateDatabaseStateHash();
        $fsHash = $this->calculateFilesystemStateHash();

        return hash('sha256', $this->tableName . '|' . $dbHash . '|' . $fsHash);
    }

    /**
     * @return string
     */
    protected function calculateDatabaseStateHash(): string
    {
        $hashBase = [];
        foreach ($this->getItemsFromDatabase() as $item) {
            $line = [
                (string) ($item['id'] ?? ''),
                (string) ($item[$this->keyColumn] ?? ''),
                (string) ($item[$this->nameColumn] ?? ''),
                (string) ($item['updatedate'] ?? ''),
                hash('sha256', json_encode($item) ?: ''),
            ];
            $hashBase[] = implode('|', $line);
        }

        return hash('sha256', implode("\n", $hashBase));
    }

    /**
     * @return string
     */
    protected function calculateFilesystemStateHash(): string
    {
        $parts = [];

        foreach ($this->getItemsFromFilesystem() as $key => $dirPath) {
            $metadataFile = $dirPath . self::METADATA_FILE;
            $metadataContent = file_exists($metadataFile) ? (string) rex_file::get($metadataFile) : '';

            $contentParts = [
                'key=' . $key,
                'meta=' . hash('sha256', $metadataContent),
            ];

            foreach ($this->getKnownFileTypes() as $type) {
                $contentParts[] = $type . '=' . hash('sha256', $this->readContentFile($dirPath, (string) $key, $type));
            }

            sort($contentParts);
            $parts[] = implode('|', $contentParts);
        }

        sort($parts);

        return hash('sha256', implode("\n", $parts));
    }

    protected function readContentFile(string $dir, string $key, string $type): string
    {
        $candidates = [
            $dir . $this->getFilename($key, $type),
            $dir . $type . '.php',
        ];

        foreach ($candidates as $candidate) {
            if (file_exists($candidate)) {
                return (string) rex_file::get($candidate);
            }
        }

        // Kompatibilität: irgendeine Datei im Muster "* type.php"
        $patternMatches = glob($dir . '* ' . $type . '.php');
        if (is_array($patternMatches)) {
            foreach ($patternMatches as $match) {
                if (file_exists($match)) {
                    return (string) rex_file::get($match);
                }
            }
        }

        return '';
    }

    /**
     * @return list<string>
     */
    protected function getKnownFileTypes(): array
    {
        return [];
    }

    private function resolveConflict(int|false $dbTime, int|false $fsTime): string
    {
        $strategy = (string) rex_addon::get('synch')->getConfig('conflict_strategy', self::CONFLICT_NEWER_WINS);
        if ($strategy === self::CONFLICT_DATABASE_WINS || $strategy === self::CONFLICT_FILESYSTEM_WINS) {
            return $strategy;
        }

        $db = (int) $dbTime;
        $fs = (int) $fsTime;
        $toleranceSeconds = 5;

        if ($db > $fs + $toleranceSeconds) {
            return self::CONFLICT_DATABASE_WINS;
        }

        if ($fs > $db + $toleranceSeconds) {
            return self::CONFLICT_FILESYSTEM_WINS;
        }

        // Bei Gleichstand stabil Dateisystem bevorzugen.
        return self::CONFLICT_FILESYSTEM_WINS;
    }

    // Abstract Methoden - müssen von Subklassen implementiert werden
    
    /**
     * Schreibt alle Dateien eines Items ins Dateisystem
     * (metadata.yml + input.php + output.php / template.php / action.php)
        *
        * @param array<string, mixed> $item
     */
    abstract protected function writeItemFiles(string $dir, array $item): void;
    
    /**
     * Aktualisiert ein existierendes Item in der DB aus dem Dateisystem
        *
        * @param array<string, mixed> $metadata
     */
    abstract protected function updateItem(int $id, string $dir, array $metadata): void;
    
    /**
     * Erstellt ein neues Item in der DB aus dem Dateisystem
     * Verwendet AUTO_INCREMENT für die ID
     *
     * @param array<string, mixed> $metadata
     */
    abstract protected function createItem(string $dir, array $metadata): void;
}
