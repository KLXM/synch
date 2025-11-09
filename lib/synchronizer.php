<?php

namespace KLXM\Synch;

use rex_dir;
use rex_addon;
use rex_file;
use rex_sql;
use rex;
use Exception;

/**
 * Moderne Key-basierte Synchronisation für Module und Templates
 * 
 * Diese Klasse implementiert eine saubere, key-basierte Synchronisation
 * ohne die Legacy ID-basierten Probleme des developer Addons.
 */
abstract class Synchronizer
{
    protected string $baseDir;
    protected string $tableName;
    protected array $columns;
    protected string $keyColumn = 'key';
    protected string $nameColumn = 'name';
    
    const METADATA_FILE = 'metadata.yml';
    const INPUT_FILE = 'input.php';
    const OUTPUT_FILE = 'output.php';

    /**
     * Gibt den passenden Dateinamen zurück (abhängig von descriptive_filenames Setting)
     */
    protected function getInputFilename(string $key = ''): string
    {
        if (rex_addon::get('synch')->getConfig('descriptive_filenames', false) && $key) {
            return $key . ' input.php';
        }
        return self::INPUT_FILE;
    }

    /**
     * Gibt den passenden Output-Dateinamen zurück
     */
    protected function getOutputFilename(string $key = ''): string
    {
        if (rex_addon::get('synch')->getConfig('descriptive_filenames', false) && $key) {
            return $key . ' output.php';
        }
        return self::OUTPUT_FILE;
    }

    /**
     * Gibt den passenden Template-Dateinamen zurück
     */
    protected function getTemplateFilename(string $key = ''): string
    {
        if (rex_addon::get('synch')->getConfig('descriptive_filenames', false) && $key) {
            return $key . ' template.php';
        }
        return 'template.php';
    }

    /**
     * Gibt den passenden Action-Dateinamen zurück
     */
    protected function getActionFilename(string $key = ''): string
    {
        if (rex_addon::get('synch')->getConfig('descriptive_filenames', false) && $key) {
            return $key . ' action.php';
        }
        return 'action.php';
    }

    /**
     * Findet Input-Datei (alt oder neues Format)
     */
    protected function findInputFile(string $dir, string $key = ''): ?string
    {
        $descriptiveFile = $dir . $key . ' input.php';
        $standardFile = $dir . self::INPUT_FILE;
        
        if (file_exists($descriptiveFile)) {
            return $descriptiveFile;
        }
        if (file_exists($standardFile)) {
            return $standardFile;
        }
        return null;
    }

    /**
     * Findet Output-Datei (alt oder neues Format)
     */
    protected function findOutputFile(string $dir, string $key = ''): ?string
    {
        $descriptiveFile = $dir . $key . ' output.php';
        $standardFile = $dir . self::OUTPUT_FILE;
        
        if (file_exists($descriptiveFile)) {
            return $descriptiveFile;
        }
        if (file_exists($standardFile)) {
            return $standardFile;
        }
        return null;
    }

    /**
     * Findet Template-Datei (alt oder neues Format)
     */
    protected function findTemplateFile(string $dir, string $key = ''): ?string
    {
        $descriptiveFile = $dir . $key . ' template.php';
        $standardFile = $dir . 'template.php';
        
        if (file_exists($descriptiveFile)) {
            return $descriptiveFile;
        }
        if (file_exists($standardFile)) {
            return $standardFile;
        }
        return null;
    }

    /**
     * Findet Action-Datei (alt oder neues Format)
     */
    protected function findActionFile(string $dir, string $key = ''): ?string
    {
        $descriptiveFile = $dir . $key . ' action.php';
        $standardFile = $dir . 'action.php';
        
        if (file_exists($descriptiveFile)) {
            return $descriptiveFile;
        }
        if (file_exists($standardFile)) {
            return $standardFile;
        }
        return null;
    }

    public function __construct(string $baseDir, string $tableName, array $columns)
    {
        $this->baseDir = rtrim($baseDir, '/') . '/';
        $this->tableName = $tableName;
        $this->columns = $columns;
        
        // Sicherstellen dass Base-Directory existiert
        if (!is_dir($this->baseDir)) {
            rex_dir::create($this->baseDir);
        }
    }

    /**
     * Hauptsynchronisations-Methode
     */
    public function sync(): bool
    {
        try {
            // Von Datenbank zu Dateisystem
            $this->syncFromDatabase();
            
            // Von Dateisystem zu Datenbank 
            $this->syncToDatabase();
            
            return true;
        } catch (Exception $e) {
            // Fehler loggen ohne rex_logger da das Probleme macht
            error_log('SYNCH ERROR: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Synchronisiert Items aus der Datenbank ins Dateisystem
     */
    protected function syncFromDatabase(): void
    {
        $sql = rex_sql::factory();
        $sql->setQuery('SELECT * FROM ' . $sql->escapeIdentifier($this->tableName));
        
        foreach ($sql->getArray() as $item) {
            $key = $item[$this->keyColumn] ?? null;
            $name = $item[$this->nameColumn] ?? 'Unnamed';
            
            // Key generieren falls nicht vorhanden
            if (empty($key)) {
                $key = $this->generateKey($name);
                $this->updateItemKey($item['id'], $key);
            }
            
            // Sauberer Ordnername basierend auf Key
            $dirName = $this->cleanKey($key);
            $itemDir = $this->baseDir . $dirName . '/';
            
            // Verzeichnis erstellen
            if (!is_dir($itemDir)) {
                rex_dir::create($itemDir);
                // Dateien schreiben nur bei neuem Verzeichnis oder wenn sich etwas geändert hat
                $this->writeItemFiles($itemDir, $item);
            } else {
                // Prüfe ob sich das Item geändert hat
                $metadataFile = $itemDir . self::METADATA_FILE;
                if (file_exists($metadataFile)) {
                    $existingMetadata = rex_file::getConfig($metadataFile);
                    $itemUpdateTime = strtotime($item['updatedate'] ?? '1970-01-01');
                    $fileUpdateTime = strtotime($existingMetadata['updatedate'] ?? '1970-01-01');
                    
                    // Nur schreiben wenn das DB-Item neuer ist
                    if ($itemUpdateTime > $fileUpdateTime) {
                        $this->writeItemFiles($itemDir, $item);
                    }
                } else {
                    // Metadata fehlt - neu schreiben
                    $this->writeItemFiles($itemDir, $item);
                }
            }
        }
    }

    /**
     * Synchronisiert Items aus dem Dateisystem in die Datenbank
     */
    protected function syncToDatabase(): void
    {
        if (!is_dir($this->baseDir)) {
            return;
        }
        
        $dirs = scandir($this->baseDir);
        foreach ($dirs as $dir) {
            if ($dir === '.' || $dir === '..' || !is_dir($this->baseDir . $dir)) {
                continue;
            }
            
            $itemDir = $this->baseDir . $dir . '/';
            $metadataFile = $itemDir . self::METADATA_FILE;
            
            // Nur Verzeichnisse mit metadata.yml verarbeiten
            if (!file_exists($metadataFile)) {
                continue;
            }
            
            try {
                $metadata = rex_file::getConfig($metadataFile);
                $key = $metadata['key'] ?? $dir;
                
                // Prüfen ob Item bereits existiert
                $existingItem = $this->findItemByKey($key);
                
                if ($existingItem && isset($existingItem['id'])) {
                    // Existierendes Item aktualisieren
                    if (rex_addon::get('synch')->getConfig('update_existing_on_key_conflict', true)) {
                        $this->updateItem((int)$existingItem['id'], $itemDir, $metadata);
                    }
                } else {
                    // Neues Item erstellen
                    $this->createItem($itemDir, $metadata);
                }
                
            } catch (Exception $e) {
                error_log('SYNCH ERROR processing directory ' . $dir . ': ' . $e->getMessage());
            }
        }
    }

    /**
     * Generiert einen sauberen Key aus einem Namen
     */
    protected function generateKey(string $name): string
    {
        $strategy = rex_addon::get('synch')->getConfig('key_generation_strategy', 'name_based');
        
        switch ($strategy) {
            case 'date_name':
                $prefix = date('Ymd_');
                return $prefix . $this->cleanKey($name);
                
            case 'hash_based':
                return substr(md5($name . time()), 0, 8) . '_' . $this->cleanKey($name);
                
            case 'name_based':
            default:
                return $this->cleanKey($name);
        }
    }

    /**
     * Bereinigt einen String für die Verwendung als Key/Ordnername
     */
    protected function cleanKey(string $input): string
    {
        // Umlaute ersetzen
        $input = str_replace(['ä', 'ö', 'ü', 'Ä', 'Ö', 'Ü', 'ß'], 
                           ['ae', 'oe', 'ue', 'Ae', 'Oe', 'Ue', 'ss'], $input);
        
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
    protected function ensureUniqueKey(string $baseKey): string
    {
        $key = $baseKey;
        $counter = 1;
        
        while ($this->findItemByKey($key)) {
            $key = $baseKey . '_' . $counter;
            $counter++;
        }
        
        return $key;
    }

    /**
     * Benennt alle Dateien um (bei Umstellung descriptive_filenames)
     */
    public function renameAllFiles(bool $toDescriptive = true): array
    {
        $results = ['renamed' => 0, 'errors' => []];
        
        if (!is_dir($this->baseDir)) {
            return $results;
        }
        
        $dirs = scandir($this->baseDir);
        foreach ($dirs as $dir) {
            if ($dir === '.' || $dir === '..' || !is_dir($this->baseDir . $dir)) {
                continue;
            }
            
            $fullDir = $this->baseDir . $dir . '/';
            $metadataFile = $fullDir . self::METADATA_FILE;
            
            if (!file_exists($metadataFile)) {
                continue;
            }
            
            try {
                $metadata = rex_file::getConfig($metadataFile);
                $name = $metadata['name'] ?? $dir;
                
                $key = $metadata['key'] ?? $dir;
                
                if ($toDescriptive) {
                    // Von standard zu descriptive (mit Key als Prefix)
                    $this->renameFile($fullDir, self::INPUT_FILE, $key . ' input.php');
                    $this->renameFile($fullDir, self::OUTPUT_FILE, $key . ' output.php');
                    $this->renameFile($fullDir, 'template.php', $key . ' template.php');
                    $this->renameFile($fullDir, 'action.php', $key . ' action.php');
                } else {
                    // Von descriptive zu standard
                    $this->renameFile($fullDir, $key . ' input.php', self::INPUT_FILE);
                    $this->renameFile($fullDir, $key . ' output.php', self::OUTPUT_FILE);
                    $this->renameFile($fullDir, $key . ' template.php', 'template.php');
                    $this->renameFile($fullDir, $key . ' action.php', 'action.php');
                }
                
                $results['renamed']++;
            } catch (Exception $e) {
                $results['errors'][] = "Fehler bei $dir: " . $e->getMessage();
            }
        }
        
        return $results;
    }

    /**
     * Benennt eine einzelne Datei um
     */
    private function renameFile(string $dir, string $oldName, string $newName): bool
    {
        $oldPath = $dir . $oldName;
        $newPath = $dir . $newName;
        
        if (!file_exists($oldPath) || $oldPath === $newPath) {
            return true;
        }
        
        // Ziel-Datei löschen falls vorhanden
        if (file_exists($newPath)) {
            unlink($newPath);
        }
        
        return rename($oldPath, $newPath);
    }

    /**
     * Findet ein Item anhand des Keys
     */
    protected function findItemByKey(string $key): ?array
    {
        $sql = rex_sql::factory();
        $sql->setQuery(
            'SELECT * FROM ' . $sql->escapeIdentifier($this->tableName) . ' WHERE ' . 
            $sql->escapeIdentifier($this->keyColumn) . ' = ?',
            [$key]
        );
        
        return $sql->getRows() > 0 ? $sql->getRow() : null;
    }

    /**
     * Aktualisiert den Key eines Items in der Datenbank
     */
    protected function updateItemKey(int $id, string $key): void
    {
        $key = $this->ensureUniqueKey($key);
        
        $sql = rex_sql::factory();
        $sql->setTable($this->tableName);
        $sql->setWhere(['id' => $id]);
        $sql->setValue($this->keyColumn, $key);
        $sql->update();
    }

    // Abstract Methoden - müssen von Subklassen implementiert werden
    abstract protected function writeItemFiles(string $dir, array $item): void;
    abstract protected function updateItem(int $id, string $dir, array $metadata): void;
    abstract protected function createItem(string $dir, array $metadata): void;
}