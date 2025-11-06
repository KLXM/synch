<?php

/**
 * Synchronizer für Templates
 */
class synch_template_synchronizer extends synch_synchronizer
{
    const TEMPLATE_FILE = 'template.php';
    
    public function __construct()
    {
        $baseDir = synch_manager::getTemplatesPath();
        
        parent::__construct(
            $baseDir,
            rex::getTable('template'),
            ['id', 'key', 'name', 'content', 'active', 'createdate', 'updatedate', 'createuser', 'updateuser']
        );
    }

    /**
     * Schreibt die Template-Dateien ins Dateisystem
     */
    protected function writeItemFiles(string $dir, array $item): void
    {
        // metadata.yml
        $metadata = [
            'name' => $item['name'],
            'key' => $item['key'],
            'active' => (bool) $item['active'],
            'createdate' => $item['createdate'],
            'updatedate' => $item['updatedate'],
            'createuser' => $item['createuser'],
            'updateuser' => $item['updateuser']
        ];
        
        rex_file::putConfig($dir . self::METADATA_FILE, $metadata);
        
                // template.php (mit descriptive filename wenn aktiviert)
        if (!empty($item['content'])) {
            $templateFilename = $this->getTemplateFilename($item['key']);
            rex_file::put($dir . $templateFilename, $item['content']);
        }
    }

    /**
     * Aktualisiert ein existierendes Template
     */
    protected function updateItem(int $id, string $dir, array $metadata): void
    {
        $sql = rex_sql::factory();
        $sql->setTable(rex::getTable('template'));
        $sql->setWhere(['id' => $id]);
        
        // Basis-Felder aktualisieren
        $sql->setValue('name', $metadata['name'] ?? 'Unnamed Template');
        $sql->setValue('key', $metadata['key']);
        $sql->setValue('active', $metadata['active'] ?? true ? 1 : 0);
        $sql->setValue('updatedate', date('Y-m-d H:i:s'));
        $sql->setValue('updateuser', rex::getUser()?->getLogin() ?? 'synch');
        
        // Content aus Datei lesen (beide Formate unterstützen)
        $templateFile = $this->findTemplateFile($dir, $metadata['key'] ?? '');
        if ($templateFile && file_exists($templateFile)) {
            $sql->setValue('content', rex_file::get($templateFile));
        }
        
        $sql->update();
    }

    /**
     * Erstellt ein neues Template
     */
    protected function createItem(string $dir, array $metadata): void
    {
        $key = $metadata['key'];
        
        // Auto-Key-Generierung falls aktiviert und kein Key vorhanden
        if (empty($key) && rex_addon::get('synch')->getConfig('auto_generate_keys', true)) {
            $key = $this->generateKey($metadata['name'] ?? 'unnamed_template');
            $key = $this->ensureUniqueKey($key);
        }
        
        if (empty($key)) {
            throw new Exception('Kein Key für Template verfügbar');
        }
        
        $sql = rex_sql::factory();
        $sql->setTable(rex::getTable('template'));
        
        // Basis-Felder setzen
        $sql->setValue('name', $metadata['name'] ?? 'Unnamed Template');
        $sql->setValue('key', $key);
        $sql->setValue('active', $metadata['active'] ?? true ? 1 : 0);
        $sql->setValue('createdate', date('Y-m-d H:i:s'));
        $sql->setValue('updatedate', date('Y-m-d H:i:s'));
        $sql->setValue('createuser', rex::getUser()?->getLogin() ?? 'synch');
        $sql->setValue('updateuser', rex::getUser()?->getLogin() ?? 'synch');
        
        // Content aus Datei lesen (beide Formate unterstützen)
        $templateFile = $this->findTemplateFile($dir, $metadata['key'] ?? '');
        if ($templateFile && file_exists($templateFile)) {
            $sql->setValue('content', rex_file::get($templateFile));
        }
        
        $sql->insert();
        
        // Metadata aktualisieren mit dem generierten Key
        if ($key !== $metadata['key']) {
            $metadata['key'] = $key;
            rex_file::putConfig($dir . self::METADATA_FILE, $metadata);
        }
    }
}