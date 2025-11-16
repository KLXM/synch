<?php

namespace KLXM\Synch;

use rex_addon;
use rex_be_controller;

/**
 * Output Filter für Backend-Seiten
 * Entfernt Lösch-Buttons bei files_to_db und bidirectional Sync
 */
class OutputFilter
{
    /**
     * Registriert den Output Filter
     */
    public static function register(): void
    {
        \rex_extension::register('OUTPUT_FILTER', [self::class, 'filter']);
    }

    /**
     * Filtert den HTML Output und entfernt Lösch-Buttons
     */
    public static function filter(\rex_extension_point $ep): string
    {
        $content = $ep->getSubject();
        $addon = rex_addon::get('synch');
        $syncDirection = $addon->getConfig('sync_direction', 'files_to_db');
        
        // Nur bei files_to_db und bidirectional
        if (!in_array($syncDirection, ['files_to_db', 'bidirectional'])) {
            return $content;
        }
        
        $page = rex_be_controller::getCurrentPage();
        
        // Module-Seite
        if ($page === 'modules/modules') {
            $content = self::removeDeleteButtons($content, 'module');
            $content = self::addWarningMessage($content, 'Module können nur im Dateisystem gelöscht werden');
        }
        
        // Template-Seite
        if ($page === 'templates') {
            $content = self::removeDeleteButtons($content, 'template');
            $content = self::addWarningMessage($content, 'Templates können nur im Dateisystem gelöscht werden');
        }
        
        // Action-Seite
        if ($page === 'modules/actions') {
            $content = self::removeDeleteButtons($content, 'action');
            $content = self::addWarningMessage($content, 'Actions können nur im Dateisystem gelöscht werden');
        }
        
        return $content;
    }

    /**
     * Entfernt Lösch-Buttons aus der Tabelle
     */
    protected static function removeDeleteButtons(string $content, string $type): string
    {
        // Lösch-Links entfernen (function=delete)
        $content = preg_replace(
            '/<a[^>]*href="[^"]*function=delete[^"]*"[^>]*>.*?<\/a>/is',
            '<span class="text-muted" title="Löschen nur im Dateisystem möglich"><i class="rex-icon rex-icon-delete-disabled"></i></span>',
            $content
        );
        
        // Zusätzlich: data-confirm Attribute entfernen (falls noch vorhanden)
        $content = preg_replace(
            '/data-confirm="[^"]*löschen[^"]*"/i',
            '',
            $content
        );
        
        return $content;
    }

    /**
     * Fügt eine Warnmeldung am Anfang der Seite hinzu
     */
    protected static function addWarningMessage(string $content, string $message): string
    {
        $addon = rex_addon::get('synch');
        $syncMode = $addon->getConfig('sync_direction', 'files_to_db');
        
        $modeText = $syncMode === 'files_to_db' 
            ? 'Dateisystem → DB (Filesystem ist Master)' 
            : 'Bidirektional (Filesystem ist Master)';
        
        $warning = '
        <div class="alert alert-info">
            <h4><i class="rex-icon rex-icon-info"></i> Synch Modus: ' . $modeText . '</h4>
            <p>' . $message . '. Entferne das entsprechende Verzeichnis in <code>redaxo/data/addons/synch/</code></p>
        </div>';
        
        // Warnung nach dem ersten <section> einfügen
        $content = preg_replace(
            '/(<section[^>]*class="[^"]*rex-page-section[^"]*"[^>]*>)/i',
            '$1' . $warning,
            $content,
            1
        );
        
        return $content;
    }
}
