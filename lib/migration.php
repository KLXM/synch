<?php

/**
 * Migrationshilfe vom developer Addon zum synch Addon
 */
class synch_migration
{
    /**
     * Migriert Module vom developer Addon Format zum synch Format
     */
    public static function migrateModulesFromDeveloper(): array
    {
        $results = ['success' => 0, 'errors' => []];
        
        $developerPath = rex_addon::get('developer')->getDataPath('modules');
        $synchPath = rex_addon::get('synch')->getDataPath('modules');
        
        if (!is_dir($developerPath)) {
            $results['errors'][] = 'Developer Module-Verzeichnis nicht gefunden';
            return $results;
        }
        
        // Synch-Verzeichnis erstellen
        if (!is_dir($synchPath)) {
            rex_dir::create($synchPath);
        }
        
        $dirs = scandir($developerPath);
        foreach ($dirs as $dir) {
            if ($dir === '.' || $dir === '..' || !is_dir($developerPath . '/' . $dir)) {
                continue;
            }
            
            try {
                self::migrateSingleModule($developerPath . '/' . $dir, $synchPath, $dir);
                $results['success']++;
            } catch (Exception $e) {
                $results['errors'][] = "Modul '$dir': " . $e->getMessage();
            }
        }
        
        return $results;
    }

    /**
     * Migriert ein einzelnes Modul
     */
    private static function migrateSingleModule(string $sourcePath, string $targetBasePath, string $dirName): void
    {
        $metadataFile = $sourcePath . '/metadata.yml';
        $idFile = null;
        
        // ID-Datei finden (kann verschiedene Namen haben)
        $files = scandir($sourcePath);
        foreach ($files as $file) {
            if (preg_match('/^(\d+)\.rex_id$/', $file)) {
                $idFile = $file;
                break;
            }
        }
        
        $metadata = [];
        $key = null;
        
        // Metadata lesen falls vorhanden
        if (file_exists($metadataFile)) {
            $metadata = rex_file::getConfig($metadataFile);
            $key = $metadata['key'] ?? null;
        }
        
        // Key generieren wenn nicht vorhanden
        if (empty($key)) {
            // Versuche aus Ordnername zu extrahieren
            if (preg_match('/^(.+?)\s*\[(\d+)\]$/', $dirName, $matches)) {
                $baseName = trim($matches[1]);
            } else {
                $baseName = $dirName;
            }
            
            $key = self::generateKeyFromName($baseName);
        }
        
        // Sauberer Ordnername basierend auf Key
        $cleanDirName = self::cleanKey($key);
        $targetPath = $targetBasePath . '/' . $cleanDirName;
        
        // Ziel-Verzeichnis erstellen
        if (!is_dir($targetPath)) {
            rex_dir::create($targetPath);
        }
        
        // Neue Metadata erstellen
        $newMetadata = [
            'name' => $metadata['name'] ?? $baseName ?? 'Migrated Module',
            'key' => $key
        ];
        
        // Optionale Felder übernehmen
        foreach (['createdate', 'updatedate', 'createuser', 'updateuser'] as $field) {
            if (!empty($metadata[$field])) {
                $newMetadata[$field] = $metadata[$field];
            }
        }
        
        rex_file::putConfig($targetPath . '/metadata.yml', $newMetadata);
        
        // Dateien kopieren
        $filesToCopy = ['input.php', 'output.php'];
        foreach ($filesToCopy as $file) {
            if (file_exists($sourcePath . '/' . $file)) {
                copy($sourcePath . '/' . $file, $targetPath . '/' . $file);
            }
        }
    }

    /**
     * Migriert Templates vom developer Addon Format
     */
    public static function migrateTemplatesFromDeveloper(): array
    {
        $results = ['success' => 0, 'errors' => []];
        
        $developerPath = rex_addon::get('developer')->getDataPath('templates');
        $synchPath = rex_addon::get('synch')->getDataPath('templates');
        
        if (!is_dir($developerPath)) {
            $results['errors'][] = 'Developer Template-Verzeichnis nicht gefunden';
            return $results;
        }
        
        // Synch-Verzeichnis erstellen
        if (!is_dir($synchPath)) {
            rex_dir::create($synchPath);
        }
        
        $dirs = scandir($developerPath);
        foreach ($dirs as $dir) {
            if ($dir === '.' || $dir === '..' || !is_dir($developerPath . '/' . $dir)) {
                continue;
            }
            
            try {
                self::migrateSingleTemplate($developerPath . '/' . $dir, $synchPath, $dir);
                $results['success']++;
            } catch (Exception $e) {
                $results['errors'][] = "Template '$dir': " . $e->getMessage();
            }
        }
        
        return $results;
    }

    /**
     * Migriert ein einzelnes Template
     */
    private static function migrateSingleTemplate(string $sourcePath, string $targetBasePath, string $dirName): void
    {
        $metadataFile = $sourcePath . '/metadata.yml';
        
        $metadata = [];
        $key = null;
        
        // Metadata lesen falls vorhanden
        if (file_exists($metadataFile)) {
            $metadata = rex_file::getConfig($metadataFile);
            $key = $metadata['key'] ?? null;
        }
        
        // Key generieren wenn nicht vorhanden
        if (empty($key)) {
            // Versuche aus Ordnername zu extrahieren
            if (preg_match('/^(.+?)\s*\[(\d+)\]$/', $dirName, $matches)) {
                $baseName = trim($matches[1]);
            } else {
                $baseName = $dirName;
            }
            
            $key = self::generateKeyFromName($baseName);
        }
        
        // Sauberer Ordnername basierend auf Key
        $cleanDirName = self::cleanKey($key);
        $targetPath = $targetBasePath . '/' . $cleanDirName;
        
        // Ziel-Verzeichnis erstellen
        if (!is_dir($targetPath)) {
            rex_dir::create($targetPath);
        }
        
        // Neue Metadata erstellen
        $newMetadata = [
            'name' => $metadata['name'] ?? $baseName ?? 'Migrated Template',
            'key' => $key,
            'active' => $metadata['active'] ?? true
        ];
        
        // Optionale Felder übernehmen
        foreach (['createdate', 'updatedate', 'createuser', 'updateuser'] as $field) {
            if (!empty($metadata[$field])) {
                $newMetadata[$field] = $metadata[$field];
            }
        }
        
        rex_file::putConfig($targetPath . '/metadata.yml', $newMetadata);
        
        // Template-Datei kopieren
        if (file_exists($sourcePath . '/template.php')) {
            copy($sourcePath . '/template.php', $targetPath . '/template.php');
        }
    }

    /**
     * Generiert einen Key aus einem Namen
     */
    private static function generateKeyFromName(string $name): string
    {
        // Umlaute ersetzen
        $key = str_replace(['ä', 'ö', 'ü', 'Ä', 'Ö', 'Ü', 'ß'], 
                          ['ae', 'oe', 'ue', 'Ae', 'Oe', 'Ue', 'ss'], $name);
        
        // Nur alphanumerische Zeichen und Unterstriche
        $key = preg_replace('/[^a-zA-Z0-9_]/', '_', $key);
        
        // Mehrfache Unterstriche entfernen
        $key = preg_replace('/_+/', '_', $key);
        
        // Unterstriche am Anfang/Ende entfernen
        $key = trim($key, '_');
        
        return strtolower($key);
    }

    /**
     * Bereinigt einen Key
     */
    private static function cleanKey(string $key): string
    {
        return self::generateKeyFromName($key);
    }
}