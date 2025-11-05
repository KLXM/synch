<?php

/**
 * SYNCH - Modern Key-Based File Synchronization for REDAXO
 * 
 * Dieses Addon bietet eine moderne, key-basierte Synchronisation 
 * zwischen Dateisystem und Datenbank ohne die Legacy-Altlasten
 * des developer Addons.
 */

// Console-Modus überspringen
if (method_exists('rex', 'getConsole') && rex::getConsole()) {
    return;
}

// Autoloader für Klassen
rex_autoload::addDirectory(__DIR__ . '/lib');
rex_autoload::addDirectory(__DIR__ . '/console');

// Automatische Synchronisation basierend auf Einstellungen
$addon = rex_addon::get('synch');

if (
    !rex::isBackend() && $addon->getConfig('sync_frontend') ||
    rex::getUser() && rex::isBackend() && $addon->getConfig('sync_backend')
) {
    rex_extension::register('PACKAGES_INCLUDED', function () use ($addon) {
        // Nur für Admins ausführen (wie Developer Addon)
        if (rex::isDebugMode() || (rex::getUser() && rex::getUser()->isAdmin())) {
            // Change Detection - nur synchronisieren wenn sich etwas geändert hat
            if (synch_manager::hasChanges()) {
                synch_manager::start();
            }
        }
    });
}