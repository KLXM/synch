<?php

namespace KLXM\Synch;

use rex;
use rex_sql;
use rex_file;
use Exception;

/**
 * Synchronizer für Templates
 * 
 * METADATA.YML FORMAT:
 * ===================
 * # Pflichtfelder:
 * name: "Mein Template"         # Name des Templates
 * key: "mein_template"           # Eindeutiger Key
 * 
 * # Optionale Felder:
 * active: 1                      # Aktiv (1) oder inaktiv (0)
 * attributes: {}                 # Template-Attribute (YAML-Format)
 * createdate: "2024-01-01 12:00:00"
 * updatedate: "2024-01-15 14:30:00"
 * createuser: "admin"
 * updateuser: "admin"
 * 
 * DATEIEN:
 * ========
 * - metadata.yml                 # Metadaten
 * - {key} template.php           # Template-Code
 * 
 * WORKFLOW: Siehe ModuleSynchronizer
 */
class TemplateSynchronizer extends Synchronizer
{
    public function __construct()
    {
        parent::__construct('templates', rex::getTable('template'));
    }

    /**
     * Schreibt die Template-Dateien ins Dateisystem
     */
    protected function writeItemFiles(string $dir, array $item): void
    {
        $key = $item['key'];
        
        // 1. metadata.yml
        $metadata = [
            'name' => $item['name'] ?? 'Unnamed Template',
            'key' => $key,
            'active' => $item['active'] ?? 1,
            'attributes' => $item['attributes'] ?? [],
            'createdate' => $item['createdate'] ?? date('Y-m-d H:i:s'),
            'updatedate' => $item['updatedate'] ?? date('Y-m-d H:i:s'),
            'createuser' => $item['createuser'] ?? '',
            'updateuser' => $item['updateuser'] ?? '',
        ];
        
        rex_file::putConfig($dir . self::METADATA_FILE, $metadata);
        
        // 2. Template-Datei (mit Key als Prefix)
        $templateFilename = $this->getFilename($key, 'template');
        rex_file::put($dir . $templateFilename, $item['content'] ?? '');
    }

    /**
     * Aktualisiert ein existierendes Template in der DB
     */
    protected function updateItem(int $id, string $dir, array $metadata): void
    {
        $key = $metadata['key'];
        
        $sql = rex_sql::factory();
        $sql->setTable(rex::getTable('template'));
        $sql->setWhere(['id' => $id]);
        
        // Attributes als Array sicherstellen
        $attributes = $metadata['attributes'] ?? [];
        if (!is_array($attributes)) {
            $attributes = [];
        }
        
        // Basis-Felder
        $sql->setValue('name', $metadata['name'] ?? 'Unnamed Template');
        $sql->setValue('key', $key);
        $sql->setValue('active', $metadata['active'] ?? 1);
        $sql->setArrayValue('attributes', $attributes);
        $sql->setValue('updatedate', date('Y-m-d H:i:s'));
        $sql->setValue('updateuser', rex::getUser()?->getLogin() ?? 'synch');
        
        // Template-Code aus Datei lesen
        $templateFile = $dir . $this->getFilename($key, 'template');
        if (file_exists($templateFile)) {
            $sql->setValue('content', rex_file::get($templateFile));
        }
        
        $sql->update();
        
        // Cache neu generieren
        \rex_template_cache::generate($id);
        
        // Metadata aktualisieren
        $metadata['updatedate'] = date('Y-m-d H:i:s');
        rex_file::putConfig($dir . self::METADATA_FILE, $metadata);
    }

    /**
     * Erstellt ein neues Template in der DB
     */
    protected function createItem(string $dir, array $metadata): void
    {
        $key = $metadata['key'] ?? '';
        
        // Key generieren falls leer
        if (empty($key)) {
            $key = $this->generateKey($metadata['name'] ?? 'unnamed_template');
            $key = $this->ensureUniqueKey($key);
        }
        
        $sql = rex_sql::factory();
        $sql->setTable(rex::getTable('template'));
        
        // Attributes als Array sicherstellen
        $attributes = $metadata['attributes'] ?? [];
        if (!is_array($attributes)) {
            $attributes = [];
        }
        
        // Basis-Felder (KEINE ID -> AUTO_INCREMENT!)
        $sql->setValue('name', $metadata['name'] ?? 'Unnamed Template');
        $sql->setValue('key', $key);
        $sql->setValue('active', $metadata['active'] ?? 1);
        $sql->setArrayValue('attributes', $attributes);
        $sql->setValue('createdate', date('Y-m-d H:i:s'));
        $sql->setValue('updatedate', date('Y-m-d H:i:s'));
        $sql->setValue('createuser', rex::getUser()?->getLogin() ?? 'synch');
        $sql->setValue('updateuser', rex::getUser()?->getLogin() ?? 'synch');
        
        // Template-Code aus Datei lesen
        $templateFile = $dir . $this->getFilename($key, 'template');
        if (file_exists($templateFile)) {
            $sql->setValue('content', rex_file::get($templateFile));
        } else {
            $sql->setValue('content', '');
        }
        
        $sql->insert();
        
        // Cache generieren für neue ID
        $newId = (int) $sql->getLastId();
        \rex_template_cache::generate($newId);
        
        // Metadata mit generiertem Key aktualisieren
        if ($key !== $metadata['key']) {
            $metadata['key'] = $key;
            $metadata['createdate'] = date('Y-m-d H:i:s');
            $metadata['updatedate'] = date('Y-m-d H:i:s');
            rex_file::putConfig($dir . self::METADATA_FILE, $metadata);
        }
    }

    /**
     * Löscht ein Template aus der Datenbank
     * Überschreibt Basis-Methode um Cache zu löschen
     */
    protected function deleteItem(int $id): void
    {
        parent::deleteItem($id);
        
        // Template-Cache löschen
        \rex_template_cache::delete($id);
    }
}
