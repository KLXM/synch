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
        *
        * @param \rex_extension_point<string> $ep
     */
    public static function filter(\rex_extension_point $ep): string
    {
        $content = (string) $ep->getSubject();
        $addon = rex_addon::get('synch');
        $syncDirection = $addon->getConfig('sync_direction', 'files_to_db');
        
        // Nur bei files_to_db und bidirectional
        if (!in_array($syncDirection, ['files_to_db', 'bidirectional'])) {
            return $content;
        }
        
        $page = rex_be_controller::getCurrentPage();
        
        // Module-Seite
        if ($page === 'modules/modules') {
            $content = self::removeDeleteButtons($content);
            if ($syncDirection === 'files_to_db') {
                $content = self::removeModuleEditButtons($content);
                $content = self::addWarningMessage($content, 'Module können in diesem Modus nicht im Backend bearbeitet oder angelegt werden');
            } else {
                $content = self::addWarningMessage($content, 'Module können nur im Dateisystem gelöscht werden');
            }
        }
        
        // Template-Seite
        if ($page === 'templates') {
            $content = self::removeDeleteButtons($content);
            $content = self::addWarningMessage($content, 'Templates können nur im Dateisystem gelöscht werden');
        }
        
        // Action-Seite
        if ($page === 'modules/actions') {
            $content = self::removeDeleteButtons($content);
            $content = self::addWarningMessage($content, 'Actions können nur im Dateisystem gelöscht werden');
        }
        
        return $content;
    }

    /**
     * Entfernt Lösch-Buttons aus der Tabelle
     */
    protected static function removeDeleteButtons(string $content): string
    {
        // Lösch-Links entfernen (function=delete)
        $content = preg_replace(
            '/<a[^>]*href="[^"]*function=delete[^"]*"[^>]*>.*?<\/a>/is',
            '<span class="text-muted" title="Löschen nur im Dateisystem möglich"><i class="rex-icon rex-icon-delete-disabled"></i></span>',
            $content
        ) ?? $content;
        
        // Zusätzlich: data-confirm Attribute entfernen (falls noch vorhanden)
        $content = preg_replace(
            '/data-confirm="[^"]*löschen[^"]*"/i',
            '',
            $content
        ) ?? $content;
        
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
        ) ?? $content;
        
        return $content;
    }

    /**
     * Entfernt Edit- und Add-Links auf der Module-Seite.
     */
    protected static function removeModuleEditButtons(string $content): string
    {
        $content = preg_replace(
            '/<a[^>]*href="[^"]*(?:function|func)=(?:edit|add)[^"]*"[^>]*>.*?<\/a>/is',
            '<span class="text-muted" title="Bearbeiten nur im Dateisystem möglich"><i class="rex-icon rex-icon-edit"></i></span>',
            $content
        ) ?? $content;

        return $content;
    }
}
