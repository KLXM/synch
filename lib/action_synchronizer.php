<?php

/**
 * Action Synchronizer für das Synch AddOn
 * Synchronisiert Actions zwischen Dateisystem und Datenbank basierend auf Keys
 */
class synch_action_synchronizer extends synch_synchronizer
{
    public function __construct()
    {
        parent::__construct(
            synch_manager::getActionsPath(),
            'rex_action',
            ['key', 'name', 'preview', 'presave', 'postsave']
        );
    }

    /**
     * Schreibt Action-Dateien ins Dateisystem
     */
    protected function writeItemFiles(string $dir, array $item): void
    {
        // Metadata schreiben
        $metadata = [
            'key' => $item['key'],
            'name' => $item['name'],
            'createdate' => $item['createdate'],
            'updatedate' => $item['updatedate'],
            'createuser' => $item['createuser'],
            'updateuser' => $item['updateuser']
        ];
        rex_file::putConfig($dir . self::METADATA_FILE, $metadata);

        // Action PHP-Datei schreiben
        $content = $this->generateActionContent($item);
        rex_file::put($dir . 'action.php', $content);
    }

    /**
     * Aktualisiert eine Action in der Datenbank
     */
    protected function updateItem(int $id, string $dir, array $metadata): void
    {
        $actionFile = $dir . 'action.php';
        if (!file_exists($actionFile)) {
            return;
        }

        $data = $this->parseActionFile($actionFile);
        
        $sql = rex_sql::factory();
        $sql->setTable($this->tableName);
        $sql->setWhere(['id' => $id]);
        
        $sql->setValue('name', $metadata['name'] ?? $metadata['key']);
        $sql->setValue('preview', $data['preview'] ?? '');
        $sql->setValue('presave', $data['presave'] ?? '');
        $sql->setValue('postsave', $data['postsave'] ?? '');
        $sql->setValue('updatedate', date('Y-m-d H:i:s'));
        $sql->setValue('updateuser', rex::getUser() ? rex::getUser()->getLogin() : 'synch');
        
        $sql->update();
    }

    /**
     * Erstellt eine neue Action in der Datenbank
     */
    protected function createItem(string $dir, array $metadata): void
    {
        $actionFile = $dir . 'action.php';
        if (!file_exists($actionFile)) {
            return;
        }

        $data = $this->parseActionFile($actionFile);
        $key = $metadata['key'];  // Verwende den Key direkt aus metadata
        
        $sql = rex_sql::factory();
        $sql->setTable($this->tableName);
        
        $sql->setValue('key', $key);
        $sql->setValue('name', $metadata['name'] ?? $key);
        $sql->setValue('preview', $data['preview'] ?? '');
        $sql->setValue('presave', $data['presave'] ?? '');
        $sql->setValue('postsave', $data['postsave'] ?? '');
        $sql->setValue('createdate', date('Y-m-d H:i:s'));
        $sql->setValue('updatedate', date('Y-m-d H:i:s'));
        $sql->setValue('createuser', rex::getUser() ? rex::getUser()->getLogin() : 'synch');
        $sql->setValue('updateuser', rex::getUser() ? rex::getUser()->getLogin() : 'synch');
        $sql->setValue('revision', 0);
        
        $sql->insert();
    }

    /**
     * Generiert den Action-PHP-Content
     */
    private function generateActionContent(array $item): string
    {
        $content = "<?php\n\n";
        $content .= "/**\n";
        $content .= " * " . ($item['name'] ?? 'Unbenannte Action') . "\n";
        $content .= " * Key: " . ($item['key'] ?? '') . "\n";
        $content .= " */\n\n";
        
        if (!empty($item['preview'])) {
            $content .= "// === PREVIEW ===\n";
            $content .= $item['preview'] . "\n\n";
        }
        
        if (!empty($item['presave'])) {
            $content .= "// === PRESAVE ===\n";
            $content .= $item['presave'] . "\n\n";
        }
        
        if (!empty($item['postsave'])) {
            $content .= "// === POSTSAVE ===\n";
            $content .= $item['postsave'] . "\n\n";
        }
        
        return $content;
    }

    /**
     * Parst eine Action-PHP-Datei
     */
    private function parseActionFile(string $filePath): array
    {
        $content = rex_file::get($filePath);
        if (!$content) {
            return [];
        }

        $data = [];
        
        // Preview Code extrahieren
        if (preg_match('/\/\/ === PREVIEW ===\s*\n(.*?)(?=\/\/ === |$)/s', $content, $matches)) {
            $data['preview'] = trim($matches[1]);
        }
        
        // Presave Code extrahieren  
        if (preg_match('/\/\/ === PRESAVE ===\s*\n(.*?)(?=\/\/ === |$)/s', $content, $matches)) {
            $data['presave'] = trim($matches[1]);
        }
        
        // Postsave Code extrahieren
        if (preg_match('/\/\/ === POSTSAVE ===\s*\n(.*?)(?=\/\/ === |$)/s', $content, $matches)) {
            $data['postsave'] = trim($matches[1]);
        }
        
        return $data;
    }
}