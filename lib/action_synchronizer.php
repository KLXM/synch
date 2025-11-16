<?php

namespace KLXM\Synch;

use rex;
use rex_sql;
use rex_file;
use Exception;

/**
 * Synchronizer für Actions
 * 
 * METADATA.YML FORMAT:
 * ===================
 * # Pflichtfelder:
 * name: "Meine Action"           # Name der Action
 * key: "meine_action"            # Eindeutiger Key
 * 
 * # Optionale Felder:
 * preview: ""                    # Preview-Code (YAML String)
 * presave: ""                    # Presave-Code (YAML String)
 * postsave: ""                   # Postsave-Code (YAML String)
 * previewmode: 1                 # Preview-Modus
 * status: 1                      # Status
 * createdate: "2024-01-01 12:00:00"
 * updatedate: "2024-01-15 14:30:00"
 * createuser: "admin"
 * updateuser: "admin"
 * 
 * DATEIEN:
 * ========
 * - metadata.yml                 # Metadaten
 * - {key} action.php             # Action-Code (optional, wird in metadata.yml gespeichert)
 * 
 * HINWEIS: Actions haben typischerweise keinen separaten Code in Dateien.
 * Der Code wird in den Metadata-Feldern preview/presave/postsave gespeichert.
 * 
 * WORKFLOW: Siehe ModuleSynchronizer
 */
class ActionSynchronizer extends Synchronizer
{
    public function __construct()
    {
        parent::__construct('actions', rex::getTable('action'));
    }

    /**
     * Schreibt die Action-Dateien ins Dateisystem
     */
    protected function writeItemFiles(string $dir, array $item): void
    {
        $key = $item['key'];
        
        // metadata.yml (Actions speichern den Code in metadata, nicht in separaten Dateien)
        $metadata = [
            'name' => $item['name'] ?? 'Unnamed Action',
            'key' => $key,
            'preview' => $item['preview'] ?? '',
            'presave' => $item['presave'] ?? '',
            'postsave' => $item['postsave'] ?? '',
            'previewmode' => $item['previewmode'] ?? 1,
            'status' => $item['status'] ?? 1,
            'createdate' => $item['createdate'] ?? date('Y-m-d H:i:s'),
            'updatedate' => $item['updatedate'] ?? date('Y-m-d H:i:s'),
            'createuser' => $item['createuser'] ?? '',
            'updateuser' => $item['updateuser'] ?? '',
        ];
        
        rex_file::putConfig($dir . self::METADATA_FILE, $metadata);
    }

    /**
     * Aktualisiert eine existierende Action in der DB
     */
    protected function updateItem(int $id, string $dir, array $metadata): void
    {
        $key = $metadata['key'];
        
        $sql = rex_sql::factory();
        $sql->setTable(rex::getTable('action'));
        $sql->setWhere(['id' => $id]);
        
        // Basis-Felder
        $sql->setValue('name', $metadata['name'] ?? 'Unnamed Action');
        $sql->setValue('key', $key);
        $sql->setValue('preview', $metadata['preview'] ?? '');
        $sql->setValue('presave', $metadata['presave'] ?? '');
        $sql->setValue('postsave', $metadata['postsave'] ?? '');
        $sql->setValue('previewmode', $metadata['previewmode'] ?? 1);
        $sql->setValue('status', $metadata['status'] ?? 1);
        $sql->setValue('updatedate', date('Y-m-d H:i:s'));
        $sql->setValue('updateuser', rex::getUser()?->getLogin() ?? 'synch');
        
        $sql->update();
        
        // Metadata aktualisieren
        $metadata['updatedate'] = date('Y-m-d H:i:s');
        rex_file::putConfig($dir . self::METADATA_FILE, $metadata);
    }

    /**
     * Erstellt eine neue Action in der DB
     */
    protected function createItem(string $dir, array $metadata): void
    {
        $key = $metadata['key'] ?? '';
        
        // Key generieren falls leer
        if (empty($key)) {
            $key = $this->generateKey($metadata['name'] ?? 'unnamed_action');
            $key = $this->ensureUniqueKey($key);
        }
        
        $sql = rex_sql::factory();
        $sql->setTable(rex::getTable('action'));
        
        // Basis-Felder (KEINE ID -> AUTO_INCREMENT!)
        $sql->setValue('name', $metadata['name'] ?? 'Unnamed Action');
        $sql->setValue('key', $key);
        $sql->setValue('preview', $metadata['preview'] ?? '');
        $sql->setValue('presave', $metadata['presave'] ?? '');
        $sql->setValue('postsave', $metadata['postsave'] ?? '');
        $sql->setValue('previewmode', $metadata['previewmode'] ?? 1);
        $sql->setValue('status', $metadata['status'] ?? 1);
        $sql->setValue('createdate', date('Y-m-d H:i:s'));
        $sql->setValue('updatedate', date('Y-m-d H:i:s'));
        $sql->setValue('createuser', rex::getUser()?->getLogin() ?? 'synch');
        $sql->setValue('updateuser', rex::getUser()?->getLogin() ?? 'synch');
        
        $sql->insert();
        
        // Metadata mit generiertem Key aktualisieren
        if ($key !== $metadata['key']) {
            $metadata['key'] = $key;
            $metadata['createdate'] = date('Y-m-d H:i:s');
            $metadata['updatedate'] = date('Y-m-d H:i:s');
            rex_file::putConfig($dir . self::METADATA_FILE, $metadata);
        }
    }
}
