<?php

/**
 * SYNCH - Modern Key-Based File Synchronization for REDAXO
 * 
 * Dieses Addon bietet eine moderne, key-basierte Synchronisation 
 * zwischen Dateisystem und Datenbank ohne die Legacy-Altlasten
 * des developer Addons.
 */

use KLXM\Synch\Manager;
use KLXM\Synch\OutputFilter;

// Console-Modus überspringen
if (rex::getConsole()) {
    return;
}

// Während Installation/Deinstallation nicht synchronisieren
if (rex::isSetup() || rex::isBackend() && rex_get('function') === 'install') {
    return;
}

// Output Filter für Backend registrieren (Lösch-Buttons entfernen)
if (rex::isBackend()) {
    $syncDirection = (string) rex_addon::get('synch')->getConfig('sync_direction', 'files_to_db');
    $currentPage = rex_be_controller::getCurrentPage();
    $backendFunction = rex_request('function', 'string');

    if (
        $syncDirection === 'files_to_db'
        && $currentPage === 'modules/modules'
        && in_array($backendFunction, ['add', 'edit', 'delete'], true)
    ) {
        rex_response::sendRedirect(rex_url::backendPage('modules/modules'));
    }

    OutputFilter::register();
}

// Automatische Synchronisation nur wenn explizit aktiviert
$addon = rex_addon::get('synch');

// Synchronisation ist standardmäßig deaktiviert bis explizit aktiviert
$syncBackend = $addon->getConfig('sync_backend', false);
$syncFrontend = $addon->getConfig('sync_frontend', false); // Default: false

// Auto-Sync pausiert?
$isPaused = $addon->getConfig('auto_sync_paused', false);

// Automatische Pause nach 30 Minuten aufheben
if ($isPaused) {
    $pausedAt = $addon->getConfig('auto_sync_paused_at', 0);
    if ($pausedAt && (time() - $pausedAt) > 30 * 60) { // 30 Minuten
        $addon->setConfig('auto_sync_paused', false);
        $addon->removeConfig('auto_sync_paused_at');
        $isPaused = false;
    }
}

if (
    !$isPaused && // Nicht pausiert
    ((!rex::isBackend() && $syncFrontend) ||
    (rex::getUser() && rex::isBackend() && $syncBackend))
) {
    rex_extension::register('PACKAGES_INCLUDED', static function (): void {
        // Nur für Admins ausführen (wie Developer Addon)
        if (rex::isDebugMode() || (rex::getUser() && rex::getUser()->isAdmin())) {
            // Change Detection - nur synchronisieren wenn sich etwas geändert hat
            if (Manager::hasChanges()) {
                Manager::start();
            }
        }
    });
}
