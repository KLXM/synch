<?php

/**
 * Settings-Seite fuer das Synch Addon
 */

use KLXM\Synch\ActionSynchronizer;
use KLXM\Synch\Manager;
use KLXM\Synch\ModuleSynchronizer;
use KLXM\Synch\TemplateSynchronizer;

$addon = rex_addon::get('synch');
$csrfToken = rex_csrf_token::factory('synch_settings');

$message = '';
$error = '';

$allowedDirections = ['files_to_db', 'db_to_files', 'bidirectional'];
$allowedStrategies = ['newer_wins', 'filesystem_wins', 'database_wins'];

if (rex_post('save_sync_settings', 'boolean')) {
    if (!$csrfToken->isValid()) {
        $error = 'Ungueltiger CSRF-Token. Bitte Seite neu laden.';
    } else {
        $syncDirection = rex_post('sync_direction', 'string', 'files_to_db');
        if (!in_array($syncDirection, $allowedDirections, true)) {
            $syncDirection = 'files_to_db';
        }

        $conflictStrategy = rex_post('conflict_strategy', 'string', 'newer_wins');
        if (!in_array($conflictStrategy, $allowedStrategies, true)) {
            $conflictStrategy = 'newer_wins';
        }

        $addon->setConfig('sync_frontend', rex_post('sync_frontend', 'boolean', false));
        $addon->setConfig('sync_backend', rex_post('sync_backend', 'boolean', false));
        $addon->setConfig('sync_direction', $syncDirection);
        $addon->setConfig('conflict_strategy', $conflictStrategy);
        $addon->setConfig('update_existing_on_key_conflict', rex_post('update_existing_on_key_conflict', 'boolean', true));
        $addon->setConfig('allow_empty_filesystem_cleanup', rex_post('allow_empty_filesystem_cleanup', 'boolean', false));
        $message = 'Sync-Einstellungen gespeichert';
    }
}

if (rex_post('pause_auto_sync', 'boolean')) {
    if (!$csrfToken->isValid()) {
        $error = 'Ungueltiger CSRF-Token. Bitte Seite neu laden.';
    } else {
        Manager::pauseAutoSync();
        $message = 'Auto-Sync pausiert';
    }
}

if (rex_post('resume_auto_sync', 'boolean')) {
    if (!$csrfToken->isValid()) {
        $error = 'Ungueltiger CSRF-Token. Bitte Seite neu laden.';
    } else {
        Manager::resumeAutoSync();
        $message = 'Auto-Sync fortgesetzt';
    }
}

if (rex_post('run_sync', 'boolean')) {
    if (!$csrfToken->isValid()) {
        $error = 'Ungueltiger CSRF-Token. Bitte Seite neu laden.';
    } else {
        try {
            (new ModuleSynchronizer())->sync();
            (new TemplateSynchronizer())->sync();
            (new ActionSynchronizer())->sync();

            $addon->setConfig('last_auto_sync', time());
            $message = $addon->i18n('synch_sync_success');
        } catch (Throwable $throwable) {
            $error = $addon->i18n('synch_sync_error') . ': ' . $throwable->getMessage();
        }
    }
}

$moduleCount = (int) (rex_sql::factory()->getArray('SELECT COUNT(*) AS count FROM ' . rex::getTable('module'))[0]['count'] ?? 0);
$templateCount = (int) (rex_sql::factory()->getArray('SELECT COUNT(*) AS count FROM ' . rex::getTable('template'))[0]['count'] ?? 0);
$actionCount = (int) (rex_sql::factory()->getArray('SELECT COUNT(*) AS count FROM ' . rex::getTable('action'))[0]['count'] ?? 0);

$moduleFiles = 0;
$templateFiles = 0;
$actionFiles = 0;

$moduleDataPath = Manager::getModulesPath();
$templateDataPath = Manager::getTemplatesPath();
$actionDataPath = Manager::getActionsPath();

if (is_dir($moduleDataPath)) {
    $moduleFiles = count(array_filter(scandir($moduleDataPath) ?: [], static function ($item) use ($moduleDataPath): bool {
        return $item !== '.' && $item !== '..' && is_dir($moduleDataPath . '/' . $item);
    }));
}

if (is_dir($templateDataPath)) {
    $templateFiles = count(array_filter(scandir($templateDataPath) ?: [], static function ($item) use ($templateDataPath): bool {
        return $item !== '.' && $item !== '..' && is_dir($templateDataPath . '/' . $item);
    }));
}

if (is_dir($actionDataPath)) {
    $actionFiles = count(array_filter(scandir($actionDataPath) ?: [], static function ($item) use ($actionDataPath): bool {
        return $item !== '.' && $item !== '..' && is_dir($actionDataPath . '/' . $item);
    }));
}

if ($message !== '') {
    echo rex_view::success($message);
}
if ($error !== '') {
    echo rex_view::error($error);
}

$syncDirection = (string) $addon->getConfig('sync_direction', 'files_to_db');
$conflictStrategy = (string) $addon->getConfig('conflict_strategy', 'newer_wins');
$allowEmptyCleanup = (bool) $addon->getConfig('allow_empty_filesystem_cleanup', false);
?>

<div class="row">
    <div class="col-sm-8">
        <div class="panel panel-primary">
            <div class="panel-heading">
                <h3 class="panel-title"><i class="rex-icon fa-refresh"></i> <?= $addon->i18n('synch_sync_now') ?></h3>
            </div>
            <div class="panel-body">
                <p><?= $addon->i18n('synch_sync_description') ?></p>

                <form method="post" style="display:inline-block;">
                    <?= $csrfToken->getHiddenField() ?>
                    <button type="submit" name="run_sync" value="1" class="btn btn-primary btn-lg">
                        <i class="rex-icon fa-refresh"></i> <?= $addon->i18n('synch_sync_now') ?>
                    </button>
                </form>

                <?php if (Manager::isAutoSyncPaused()): ?>
                    <form method="post" style="display:inline-block; margin-left:10px;">
                        <?= $csrfToken->getHiddenField() ?>
                        <button type="submit" name="resume_auto_sync" value="1" class="btn btn-success">
                            <i class="rex-icon fa-play"></i> Auto-Sync fortsetzen
                        </button>
                    </form>
                <?php else: ?>
                    <form method="post" style="display:inline-block; margin-left:10px;">
                        <?= $csrfToken->getHiddenField() ?>
                        <button type="submit" name="pause_auto_sync" value="1" class="btn btn-warning">
                            <i class="rex-icon fa-pause"></i> Auto-Sync pausieren
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title"><i class="rex-icon fa-cogs"></i> Synchronisationsregeln</h3>
            </div>
            <div class="panel-body">
                <form method="post">
                    <?= $csrfToken->getHiddenField() ?>

                    <div class="checkbox">
                        <label>
                            <input type="checkbox" name="sync_frontend" value="1" <?= $addon->getConfig('sync_frontend', false) ? 'checked' : '' ?>>
                            <strong>Im Frontend synchronisieren</strong>
                        </label>
                    </div>

                    <div class="checkbox">
                        <label>
                            <input type="checkbox" name="sync_backend" value="1" <?= $addon->getConfig('sync_backend', false) ? 'checked' : '' ?>>
                            <strong>Im Backend synchronisieren</strong>
                        </label>
                    </div>

                    <div class="form-group">
                        <label for="sync-direction">Synchronisations-Richtung:</label>
                        <select class="form-control" id="sync-direction" name="sync_direction">
                            <option value="files_to_db" <?= $syncDirection === 'files_to_db' ? 'selected' : '' ?>>Dateisystem zu DB (empfohlen)</option>
                            <option value="db_to_files" <?= $syncDirection === 'db_to_files' ? 'selected' : '' ?>>DB zu Dateisystem</option>
                            <option value="bidirectional" <?= $syncDirection === 'bidirectional' ? 'selected' : '' ?>>Bidirektional</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="conflict-strategy">Konfliktstrategie (nur bidirectional):</label>
                        <select class="form-control" id="conflict-strategy" name="conflict_strategy">
                            <option value="newer_wins" <?= $conflictStrategy === 'newer_wins' ? 'selected' : '' ?>>Neuer gewinnt (Timestamp + Toleranz)</option>
                            <option value="filesystem_wins" <?= $conflictStrategy === 'filesystem_wins' ? 'selected' : '' ?>>Dateisystem gewinnt</option>
                            <option value="database_wins" <?= $conflictStrategy === 'database_wins' ? 'selected' : '' ?>>Datenbank gewinnt</option>
                        </select>
                    </div>

                    <div class="checkbox">
                        <label>
                            <input type="checkbox" name="update_existing_on_key_conflict" value="1" <?= $addon->getConfig('update_existing_on_key_conflict', true) ? 'checked' : '' ?>>
                            <strong>Bestehende Items bei Key/Name-Konflikt aktualisieren</strong>
                        </label>
                    </div>

                    <div class="checkbox">
                        <label>
                            <input type="checkbox" name="allow_empty_filesystem_cleanup" value="1" <?= $allowEmptyCleanup ? 'checked' : '' ?>>
                            <strong>DB-Cleanup bei leerem Dateisystem erlauben</strong>
                        </label>
                        <p class="text-warning" style="margin-top:6px;">
                            Standard ist deaktiviert. So werden Massenloeschungen bei leerem Mount/Volume vermieden.
                        </p>
                    </div>

                    <button type="submit" name="save_sync_settings" value="1" class="btn btn-success">
                        <i class="rex-icon fa-save"></i> Sync-Einstellungen speichern
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-sm-4">
        <div class="panel panel-success">
            <div class="panel-heading">
                <h3 class="panel-title"><i class="rex-icon fa-info-circle"></i> Status</h3>
            </div>
            <div class="panel-body">
                <ul class="list-unstyled">
                    <li><strong>Module:</strong> <?= $moduleCount ?> in DB, <?= $moduleFiles ?> Verzeichnisse</li>
                    <li><strong>Templates:</strong> <?= $templateCount ?> in DB, <?= $templateFiles ?> Verzeichnisse</li>
                    <li><strong>Actions:</strong> <?= $actionCount ?> in DB, <?= $actionFiles ?> Verzeichnisse</li>
                </ul>

                <hr>

                <ul class="list-unstyled">
                    <li><strong>Richtung:</strong> <?= htmlspecialchars($syncDirection, ENT_QUOTES) ?></li>
                    <li><strong>Konflikt:</strong> <?= htmlspecialchars($conflictStrategy, ENT_QUOTES) ?></li>
                    <li><strong>Empty-Cleanup:</strong> <?= $allowEmptyCleanup ? 'aktiv' : 'gesperrt' ?></li>
                    <li><strong>Hash-Detection:</strong> aktiv</li>
                    <li><strong>Auto-Sync:</strong> <?= Manager::isAutoSyncPaused() ? 'pausiert' : 'aktiv' ?></li>
                </ul>
            </div>
        </div>
    </div>
</div>
