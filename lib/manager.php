<?php

/**
 * Synch Manager für Pfadverwaltung
 */
class synch_manager
{
    private static $basePath;

    /**
     * Setzt den Basis-Pfad für die Synchronisation
     */
    public static function setBasePath(string $basePath): void
    {
        self::$basePath = $basePath;
    }

    /**
     * Gibt den Basis-Pfad zurück
     */
    public static function getBasePath(): string
    {
        return self::$basePath ?: rex_path::addonData('synch');
    }

    /**
     * Gibt den Module-Pfad zurück
     */
    public static function getModulesPath(): string
    {
        return self::getBasePath() . '/modules';
    }

    /**
     * Gibt den Template-Pfad zurück
     */
    public static function getTemplatesPath(): string
    {
        return self::getBasePath() . '/templates';
    }

    /**
     * Gibt den Actions-Pfad zurück
     */
    public static function getActionsPath(): string
    {
        return self::getBasePath() . '/actions';
    }

    /**
     * Gibt den Actions-Verzeichnis-Pfad zurück
     */
    public static function getActionsDir(): string
    {
        return self::getActionsPath();
    }

    /**
     * Gibt den Pfad für eine spezifische Action-Datei zurück
     */
    public static function getActionPath(string $key): string
    {
        return self::getActionsPath() . '/' . $key . '.action.php';
    }

    /**
     * Pausiert die Auto-Synchronisation
     */
    public static function pauseAutoSync(): void
    {
        rex_addon::get('synch')->setConfig('auto_sync_paused', true);
        rex_addon::get('synch')->setConfig('auto_sync_paused_at', time());
    }

    /**
     * Setzt die Auto-Synchronisation fort
     */
    public static function resumeAutoSync(): void
    {
        rex_addon::get('synch')->setConfig('auto_sync_paused', false);
        rex_addon::get('synch')->removeConfig('auto_sync_paused_at');
    }

    /**
     * Prüft ob Auto-Sync pausiert ist
     */
    public static function isAutoSyncPaused(): bool
    {
        return rex_addon::get('synch')->getConfig('auto_sync_paused', false);
    }

    /**
     * Prüft ob Änderungen vorliegen die eine Synchronisation erfordern
     */
    public static function hasChanges(): bool
    {
        // Früh aussteigen wenn pausiert
        if (self::isAutoSyncPaused()) {
            return false;
        }
        
        static $lastCheck = null;
        static $lastResult = null;
        
        // Cache für 60 Sekunden
        if ($lastCheck && (time() - $lastCheck) < 60 && $lastResult !== null) {
            return $lastResult;
        }
        
        $lastCheck = time();
        
        try {
            // Prüfe Timestamp der letzten DB-Änderung vs. letzter Sync
            $lastSync = rex_addon::get('synch')->getConfig('last_auto_sync', 0);
            
            // Prüfe Module
            $sql = rex_sql::factory();
            $sql->setQuery('SELECT MAX(UNIX_TIMESTAMP(updatedate)) as last_update FROM rex_module');
            $moduleUpdate = (int)$sql->getValue('last_update');
            
            if ($moduleUpdate > $lastSync) {
                $lastResult = true;
                return true;
            }
            
            // Prüfe Templates  
            $sql->setQuery('SELECT MAX(UNIX_TIMESTAMP(updatedate)) as last_update FROM rex_template');
            $templateUpdate = (int)$sql->getValue('last_update');
            
            if ($templateUpdate > $lastSync) {
                $lastResult = true;
                return true;
            }
            
            // Prüfe Actions
            $sql->setQuery('SELECT MAX(UNIX_TIMESTAMP(updatedate)) as last_update FROM rex_action');
            $actionUpdate = (int)$sql->getValue('last_update');
            
            if ($actionUpdate > $lastSync) {
                $lastResult = true;
                return true;
            }
            
            // Prüfe Dateisystem-Timestamps (vereinfacht)
            $dataPath = self::getBasePath();
            if (is_dir($dataPath)) {
                $dirTime = filemtime($dataPath);
                if ($dirTime && $dirTime > $lastSync) {
                    $lastResult = true;
                    return true;
                }
            }
            
            $lastResult = false;
            return false;
            
        } catch (Exception $e) {
            error_log('SYNCH hasChanges() ERROR: ' . $e->getMessage());
            // Im Fehlerfall synchronisieren
            $lastResult = true;
            return true;
        }
    }

    /**
     * Startet die automatische Synchronisation
     */
    public static function start(): void
    {
        try {
            // Module synchronisieren
            $moduleSync = new synch_module_synchronizer();
            $moduleSync->sync();
            
            // Templates synchronisieren
            $templateSync = new synch_template_synchronizer();
            $templateSync->sync();

            // Actions synchronisieren
            $actionSync = new synch_action_synchronizer();
            $actionSync->sync();
            
            // Timestamp der letzten Synchronisation speichern
            rex_addon::get('synch')->setConfig('last_auto_sync', time());
            
        } catch (Exception $e) {
            // Fehler nur loggen, nicht abbrechen
            error_log('SYNCH AUTO-SYNC ERROR: ' . $e->getMessage());
        }
    }
}