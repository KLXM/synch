<?php

/**
 * SYNCH - Modern Key-Based File Synchronization for REDAXO
 * 
 * Dieses Addon bietet eine moderne, key-basierte Synchronisation 
 * zwischen Dateisystem und Datenbank ohne die Legacy-Altlasten
 * des developer Addons.
 */

use KLXM\Synch\Manager;

// Console-Modus überspringen
if (method_exists('rex', 'getConsole') && rex::getConsole()) {
    return;
}

// Während Installation/Deinstallation nicht synchronisieren
if (rex::isSetup() || rex::isBackend() && rex_get('function') === 'install') {
    return;
}

// Automatische Synchronisation nur wenn explizit aktiviert
$addon = rex_addon::get('synch');

// Synchronisation ist standardmäßig DEAKTIVIERT - muss explizit aktiviert werden
$syncBackend = $addon->getConfig('sync_backend', false);  // Default: false
$syncFrontend = $addon->getConfig('sync_frontend', false); // Default: false

if (
    (!rex::isBackend() && $syncFrontend) ||
    (rex::getUser() && rex::isBackend() && $syncBackend)
) {
    rex_extension::register('PACKAGES_INCLUDED', function () use ($addon) {
        // Nur für Admins ausführen (wie Developer Addon)
        if (rex::isDebugMode() || (rex::getUser() && rex::getUser()->isAdmin())) {
            // Change Detection - nur synchronisieren wenn sich etwas geändert hat
            if (Manager::hasChanges()) {
                Manager::start();
            }
        }
    });
}
