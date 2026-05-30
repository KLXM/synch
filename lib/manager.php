<?php

namespace KLXM\Synch;

use rex_path;
use rex_dir;
use rex_addon;
use Exception;

/**
 * Synch Manager für Pfadverwaltung
 */
class Manager
{
    private static string $basePath = '';

    /**
     * Setzt den Basis-Pfad für die Synchronisation
     */
    public static function setBasePath(string $basePath): void
    {
        // Normalisiere den Pfad (entferne trailing slash)
        self::$basePath = rtrim($basePath, '/\\');
    }

    /**
     * Gibt den Basis-Pfad zurück
     */
    public static function getBasePath(): string
    {
        $path = self::$basePath !== '' ? self::$basePath : rex_path::addonData('synch');
        
        // Stelle sicher, dass der Pfad existiert
        if (!is_dir($path)) {
            rex_dir::create($path);
        }
        
        return $path;
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
        $addon = rex_addon::get('synch');
        $isPaused = $addon->getConfig('auto_sync_paused', false);
        
        // Wenn pausiert, prüfe ob 30 Minuten abgelaufen sind
        if ($isPaused) {
            $pausedAt = $addon->getConfig('auto_sync_paused_at', 0);
            $thirtyMinutesAgo = time() - (30 * 60); // 30 Minuten in Sekunden
            
            // Automatisch fortsetzen nach 30 Minuten
            if ($pausedAt && $pausedAt < $thirtyMinutesAgo) {
                self::resumeAutoSync();
                return false;
            }
        }
        
        return $isPaused;
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
        
        static $lastCheck = 0;
        static $lastResult = null;
        
        // Cache für 60 Sekunden
        if ($lastCheck > 0 && (time() - $lastCheck) < 60 && $lastResult !== null) {
            return $lastResult;
        }
        
        $lastCheck = time();
        
        try {
            $addon = rex_addon::get('synch');
            $previousHash = (string) $addon->getConfig('last_state_hash', '');
            $currentHash = self::calculateGlobalStateHash();

            // Erstlauf oder Hash-Änderung => synchronisieren
            $lastResult = $previousHash === '' || $currentHash !== $previousHash;

            return $lastResult;
            
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
            $moduleSync = new ModuleSynchronizer();
            $templateSync = new TemplateSynchronizer();
            $actionSync = new ActionSynchronizer();

            // Module synchronisieren
            $moduleSync->sync();

            // Templates synchronisieren
            $templateSync->sync();

            // Actions synchronisieren
            $actionSync->sync();

            // Letzten erfolgreichen Zustand speichern
            $addon = rex_addon::get('synch');
            $addon->setConfig('last_auto_sync', time());
            $addon->setConfig(
                'last_state_hash',
                self::hashParts([
                    (string) $addon->getConfig('sync_direction', 'files_to_db'),
                    $moduleSync->calculateStateHash(),
                    $templateSync->calculateStateHash(),
                    $actionSync->calculateStateHash(),
                ])
            );
            
        } catch (Exception $e) {
            // Fehler nur loggen, nicht abbrechen
            error_log('SYNCH AUTO-SYNC ERROR: ' . $e->getMessage());
        }
    }

    private static function calculateGlobalStateHash(): string
    {
        $addon = rex_addon::get('synch');

        return self::hashParts([
            (string) $addon->getConfig('sync_direction', 'files_to_db'),
            (new ModuleSynchronizer())->calculateStateHash(),
            (new TemplateSynchronizer())->calculateStateHash(),
            (new ActionSynchronizer())->calculateStateHash(),
        ]);
    }

    /**
     * @param list<string> $parts
     */
    private static function hashParts(array $parts): string
    {
        return hash('sha256', implode('|', $parts));
    }
}