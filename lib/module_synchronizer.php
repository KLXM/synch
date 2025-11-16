<?php

namespace KLXM\Synch;

use rex;
use rex_sql;
use rex_file;
use Exception;

/**
 * Synchronizer für Module
 * 
 * METADATA.YML FORMAT:
 * ===================
 * # Pflichtfelder:
 * name: "Mein Modul"           # Name des Moduls
 * key: "mein_modul"             # Eindeutiger Key (wird automatisch aus Name generiert wenn leer)
 * 
 * # Optionale Felder:
 * createdate: "2024-01-01 12:00:00"
 * updatedate: "2024-01-15 14:30:00"
 * createuser: "admin"
 * updateuser: "admin"
 * 
 * DATEIEN:
 * ========
 * - metadata.yml              # Metadaten
 * - {key} input.php           # Input (z.B. "mein_modul input.php")
 * - {key} output.php          # Output (z.B. "mein_modul output.php")
 * 
 * WORKFLOW:
 * =========
 * 1. Neues Modul anlegen: Ordner erstellen mit metadata.yml, input.php, output.php
 *    -> wird automatisch in DB importiert
 * 
 * 2. Modul in Backend ändern: 
 *    -> Dateien werden automatisch aktualisiert (wenn DB neuer)
 * 
 * 3. Dateien ändern:
 *    -> DB wird automatisch aktualisiert (wenn Dateien neuer)
 * 
 * 4. Modul in Backend löschen:
 *    -> Dateien werden automatisch gelöscht
 */
class ModuleSynchronizer extends Synchronizer
{
    public function __construct()
    {
        parent::__construct('modules', rex::getTable('module'));
    }

    /**
     * Schreibt die Moduldateien ins Dateisystem
     */
    protected function writeItemFiles(string $dir, array $item): void
    {
        $key = $item['key'];
        
        // 1. metadata.yml
        $metadata = [
            'name' => $item['name'] ?? 'Unnamed Module',
            'key' => $key,
            'createdate' => $item['createdate'] ?? date('Y-m-d H:i:s'),
            'updatedate' => $item['updatedate'] ?? date('Y-m-d H:i:s'),
            'createuser' => $item['createuser'] ?? '',
            'updateuser' => $item['updateuser'] ?? '',
        ];
        
        rex_file::putConfig($dir . self::METADATA_FILE, $metadata);
        
        // 2. Input-Datei (mit Key als Prefix)
        $inputFilename = $this->getFilename($key, 'input');
        rex_file::put($dir . $inputFilename, $item['input'] ?? '');
        
        // 3. Output-Datei (mit Key als Prefix)
        $outputFilename = $this->getFilename($key, 'output');
        rex_file::put($dir . $outputFilename, $item['output'] ?? '');
    }

    /**
     * Aktualisiert ein existierendes Modul in der DB aus dem Dateisystem
     */
    protected function updateItem(int $id, string $dir, array $metadata): void
    {
        $key = $metadata['key'];
        
        $sql = rex_sql::factory();
        $sql->setTable(rex::getTable('module'));
        $sql->setWhere(['id' => $id]);
        
        // Basis-Felder
        $sql->setValue('name', $metadata['name'] ?? 'Unnamed Module');
        $sql->setValue('key', $key);
        $sql->setValue('updatedate', date('Y-m-d H:i:s'));
        $sql->setValue('updateuser', rex::getUser()?->getLogin() ?? 'synch');
        
        // Input aus Datei lesen
        $inputFile = $dir . $this->getFilename($key, 'input');
        if (file_exists($inputFile)) {
            $sql->setValue('input', rex_file::get($inputFile));
        }
        
        // Output aus Datei lesen
        $outputFile = $dir . $this->getFilename($key, 'output');
        if (file_exists($outputFile)) {
            $sql->setValue('output', rex_file::get($outputFile));
        }
        
        $sql->update();
        
        // Metadata mit neuem updatedate aktualisieren
        $metadata['updatedate'] = date('Y-m-d H:i:s');
        rex_file::putConfig($dir . self::METADATA_FILE, $metadata);
    }

    /**
     * Erstellt ein neues Modul in der DB aus dem Dateisystem
     * Verwendet AUTO_INCREMENT für die ID
     */
    protected function createItem(string $dir, array $metadata): void
    {
        $key = $metadata['key'] ?? '';
        
        // Key generieren falls leer
        if (empty($key)) {
            $key = $this->generateKey($metadata['name'] ?? 'unnamed_module');
            $key = $this->ensureUniqueKey($key);
        }
        
        $sql = rex_sql::factory();
        $sql->setTable(rex::getTable('module'));
        
        // Basis-Felder (KEINE ID setzen -> AUTO_INCREMENT!)
        $sql->setValue('name', $metadata['name'] ?? 'Unnamed Module');
        $sql->setValue('key', $key);
        $sql->setValue('createdate', date('Y-m-d H:i:s'));
        $sql->setValue('updatedate', date('Y-m-d H:i:s'));
        $sql->setValue('createuser', rex::getUser()?->getLogin() ?? 'synch');
        $sql->setValue('updateuser', rex::getUser()?->getLogin() ?? 'synch');
        
        // Input aus Datei lesen
        $inputFile = $dir . $this->getFilename($key, 'input');
        if (file_exists($inputFile)) {
            $sql->setValue('input', rex_file::get($inputFile));
        } else {
            $sql->setValue('input', '');
        }
        
        // Output aus Datei lesen
        $outputFile = $dir . $this->getFilename($key, 'output');
        if (file_exists($outputFile)) {
            $sql->setValue('output', rex_file::get($outputFile));
        } else {
            $sql->setValue('output', '');
        }
        
        $sql->insert();
        
        // Metadata mit generiertem Key aktualisieren
        if ($key !== $metadata['key']) {
            $metadata['key'] = $key;
            $metadata['createdate'] = date('Y-m-d H:i:s');
            $metadata['updatedate'] = date('Y-m-d H:i:s');
            rex_file::putConfig($dir . self::METADATA_FILE, $metadata);
        }
        
        // Module Key-Mapping Cache löschen
        \rex_module_cache::deleteKeyMapping();
    }

    /**
     * Löscht ein Modul aus der Datenbank
     * Überschreibt Basis-Methode um Cache zu löschen
     */
    protected function deleteItem(int $id): void
    {
        parent::deleteItem($id);
        
        // Article-Cache für alle Artikel mit diesem Modul löschen
        $sql = rex_sql::factory();
        $sql->setQuery('
            SELECT DISTINCT article.id 
            FROM ' . rex::getTable('article') . ' article
            LEFT JOIN ' . rex::getTable('article_slice') . ' slice
            ON article.id = slice.article_id
            WHERE slice.module_id = ?
        ', [$id]);
        
        for ($i = 0, $rows = $sql->getRows(); $i < $rows; ++$i) {
            \rex_article_cache::delete($sql->getValue('id'));
            $sql->next();
        }
        
        // Module Key-Mapping Cache löschen
        \rex_module_cache::deleteKeyMapping();
    }
}
