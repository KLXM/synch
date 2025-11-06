<?php

/**
 * Synchronizer für Module
 */
class synch_module_synchronizer extends synch_synchronizer
{
    public function __construct()
    {
        $baseDir = synch_manager::getModulesPath();
        
        parent::__construct(
            $baseDir,
            rex::getTable('module'),
            ['id', 'key', 'name', 'input', 'output', 'createdate', 'updatedate', 'createuser', 'updateuser']
        );
    }

    /**
     * Schreibt die Moduldateien ins Dateisystem
     */
    protected function writeItemFiles(string $dir, array $item): void
    {
        // metadata.yml
        $metadata = [
            'name' => $item['name'],
            'key' => $item['key'],
            'createdate' => $item['createdate'],
            'updatedate' => $item['updatedate'],
            'createuser' => $item['createuser'],
            'updateuser' => $item['updateuser']
        ];
        
        rex_file::putConfig($dir . self::METADATA_FILE, $metadata);
        
        // input.php (mit descriptive filename wenn aktiviert)
        if (!empty($item['input'])) {
            $inputFilename = $this->getInputFilename($item['name']);
            rex_file::put($dir . $inputFilename, $item['input']);
        }
        
        // output.php (mit descriptive filename wenn aktiviert)
        if (!empty($item['output'])) {
            $outputFilename = $this->getOutputFilename($item['name']);
            rex_file::put($dir . $outputFilename, $item['output']);
        }
    }

    /**
     * Aktualisiert ein existierendes Modul
     */
    protected function updateItem(int $id, string $dir, array $metadata): void
    {
        $sql = rex_sql::factory();
        $sql->setTable(rex::getTable('module'));
        $sql->setWhere(['id' => $id]);
        
        // Basis-Felder aktualisieren
        $sql->setValue('name', $metadata['name'] ?? 'Unnamed Module');
        $sql->setValue('key', $metadata['key']);
        $sql->setValue('updatedate', date('Y-m-d H:i:s'));
        $sql->setValue('updateuser', rex::getUser()?->getLogin() ?? 'synch');
        
        // Input/Output aus Dateien lesen (beide Formate unterstützen)
        $inputFile = $this->findInputFile($dir, $metadata['name'] ?? '');
        if ($inputFile && file_exists($inputFile)) {
            $sql->setValue('input', rex_file::get($inputFile));
        }
        
        $outputFile = $this->findOutputFile($dir, $metadata['name'] ?? '');
        if ($outputFile && file_exists($outputFile)) {
            $sql->setValue('output', rex_file::get($outputFile));
        }
        
        $sql->update();
    }

    /**
     * Erstellt ein neues Modul
     */
    protected function createItem(string $dir, array $metadata): void
    {
        $key = $metadata['key'];
        
        // Auto-Key-Generierung falls aktiviert und kein Key vorhanden
        if (empty($key) && rex_addon::get('synch')->getConfig('auto_generate_keys', true)) {
            $key = $this->generateKey($metadata['name'] ?? 'unnamed_module');
            $key = $this->ensureUniqueKey($key);
        }
        
        if (empty($key)) {
            throw new Exception('Kein Key für Modul verfügbar');
        }
        
        $sql = rex_sql::factory();
        $sql->setTable(rex::getTable('module'));
        
        // Basis-Felder setzen
        $sql->setValue('name', $metadata['name'] ?? 'Unnamed Module');
        $sql->setValue('key', $key);
        $sql->setValue('createdate', date('Y-m-d H:i:s'));
        $sql->setValue('updatedate', date('Y-m-d H:i:s'));
        $sql->setValue('createuser', rex::getUser()?->getLogin() ?? 'synch');
        $sql->setValue('updateuser', rex::getUser()?->getLogin() ?? 'synch');
        
        // Input/Output aus Dateien lesen (beide Formate unterstützen)
        $inputFile = $this->findInputFile($dir, $metadata['name'] ?? '');
        if ($inputFile && file_exists($inputFile)) {
            $sql->setValue('input', rex_file::get($inputFile));
        }
        
        $outputFile = $this->findOutputFile($dir, $metadata['name'] ?? '');
        if ($outputFile && file_exists($outputFile)) {
            $sql->setValue('output', rex_file::get($outputFile));
        }
        
        $sql->insert();
        
        // Metadata aktualisieren mit dem generierten Key
        if ($key !== $metadata['key']) {
            $metadata['key'] = $key;
            rex_file::putConfig($dir . self::METADATA_FILE, $metadata);
        }
    }
}