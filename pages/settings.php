<?php

/**
 * Settings-Seite für das Synch Addon - Moderne Panel-Struktur
 */

$addon = rex_addon::get('synch');

$message = '';
$error = '';

// Konfiguration speichern
if (rex_post('save_settings', 'boolean')) {
    $settings = rex_post('settings', [
        ['auto_generate_keys', 'boolean'],
        ['key_generation_strategy', 'string'],
        ['update_existing_on_key_conflict', 'boolean'],
        ['sync_frontend', 'boolean'],
        ['sync_backend', 'boolean']
    ]);
    
    // Saubere Ordnernamen sind immer aktiviert - das ist der Zweck des synch Addons
    $settings['clean_folders'] = true;
    
    $addon->setConfig($settings);
    $message = $addon->i18n('config_saved');
}

// Auto-Sync pausieren/fortsetzen
if (rex_post('pause_auto_sync', 'boolean')) {
    synch_manager::pauseAutoSync();
    $message = $addon->i18n('auto_sync_paused', 'Auto-Sync pausiert');
}

if (rex_post('resume_auto_sync', 'boolean')) {
    synch_manager::resumeAutoSync();
    $message = $addon->i18n('auto_sync_resumed', 'Auto-Sync fortgesetzt');
}

// Synchronisation ausführen
if (rex_post('run_sync', 'boolean')) {
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
        
        $message = $addon->i18n('sync_success');
    } catch (Exception $e) {
        $error = $addon->i18n('sync_error') . ': ' . $e->getMessage();
    }
}

// Status-Informationen
$moduleCount = rex_sql::factory()->getArray('SELECT COUNT(*) as count FROM ' . rex::getTable('module'))[0]['count'] ?? 0;
$templateCount = rex_sql::factory()->getArray('SELECT COUNT(*) as count FROM ' . rex::getTable('template'))[0]['count'] ?? 0;
$actionCount = rex_sql::factory()->getArray('SELECT COUNT(*) as count FROM ' . rex::getTable('action'))[0]['count'] ?? 0;

$moduleFiles = 0;
$templateFiles = 0;
$actionFiles = 0;
$moduleDataPath = synch_manager::getModulesPath();
$templateDataPath = synch_manager::getTemplatesPath();
$actionDataPath = synch_manager::getActionsPath();

if (is_dir($moduleDataPath)) {
    $moduleFiles = count(array_filter(scandir($moduleDataPath), function($item) use ($moduleDataPath) {
        return $item !== '.' && $item !== '..' && is_dir($moduleDataPath . '/' . $item);
    }));
}

if (is_dir($templateDataPath)) {
    $templateFiles = count(array_filter(scandir($templateDataPath), function($item) use ($templateDataPath) {
        return $item !== '.' && $item !== '..' && is_dir($templateDataPath . '/' . $item);
    }));
}

if (is_dir($actionDataPath)) {
    $actionFiles = count(array_filter(scandir($actionDataPath), function($item) use ($actionDataPath) {
        return $item !== '.' && $item !== '..' && is_dir($actionDataPath . '/' . $item);
    }));
}

// Nachrichten anzeigen
if ($message) {
    echo rex_view::success($message);
}
if ($error) {
    echo rex_view::error($error);
}

?>

<div class="row">
    <div class="col-sm-8">
        
        <!-- Synchronisation -->
        <div class="panel panel-primary">
            <div class="panel-heading">
                <h3 class="panel-title"><i class="rex-icon fa-refresh"></i> <?= $addon->i18n('sync_now') ?></h3>
            </div>
            <div class="panel-body">
                <p><?= $addon->i18n('sync_description') ?></p>
                
                <form method="post" style="display: inline-block;">
                    <button type="submit" name="run_sync" value="1" class="btn btn-primary btn-lg">
                        <i class="rex-icon fa-refresh"></i> <?= $addon->i18n('sync_now') ?>
                    </button>
                </form>
                
                <?php if (synch_manager::isAutoSyncPaused()): ?>
                <form method="post" style="display: inline-block; margin-left: 10px;">
                    <button type="submit" name="resume_auto_sync" value="1" class="btn btn-success">
                        <i class="rex-icon fa-play"></i> Auto-Sync fortsetzen
                    </button>
                </form>
                <?php else: ?>
                <form method="post" style="display: inline-block; margin-left: 10px;">
                    <button type="submit" name="pause_auto_sync" value="1" class="btn btn-warning">
                        <i class="rex-icon fa-pause"></i> Auto-Sync pausieren
                    </button>
                </form>
                <?php endif; ?>
                
                <hr>
                
                <div class="row">
                    <div class="col-sm-4">
                        <h5><i class="rex-icon fa-cubes"></i> Module</h5>
                        <p>DB: <strong><?= $moduleCount ?></strong> | Dateien: <strong><?= $moduleFiles ?></strong></p>
                    </div>
                    <div class="col-sm-4">
                        <h5><i class="rex-icon fa-files-o"></i> Templates</h5>
                        <p>DB: <strong><?= $templateCount ?></strong> | Dateien: <strong><?= $templateFiles ?></strong></p>
                    </div>
                    <div class="col-sm-4">
                        <h5><i class="rex-icon fa-flash"></i> Actions</h5>
                        <p>DB: <strong><?= $actionCount ?></strong> | Dateien: <strong><?= $actionFiles ?></strong></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Einstellungen -->
        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title"><i class="rex-icon fa-cog"></i> <?= $addon->i18n('settings_title') ?></h3>
            </div>
            <div class="panel-body">
                <form method="post">
                    

                    <h4><i class="rex-icon fa-key"></i> Key-Generierung</h4>
                    
                    <div class="checkbox">
                        <label>
                            <input type="checkbox" name="settings[auto_generate_keys]" value="1" 
                                   <?= $addon->getConfig('auto_generate_keys', true) ? 'checked' : '' ?>>
                            <strong><?= $addon->i18n('auto_generate_keys') ?></strong>
                        </label>
                        <p class="text-muted"><?= $addon->i18n('auto_generate_keys_note') ?></p>
                    </div>
                    
                    <div class="form-group">
                        <label for="key-strategy"><?= $addon->i18n('key_generation_strategy') ?>:</label>
                        <select class="form-control" id="key-strategy" name="settings[key_generation_strategy]">
                            <option value="name_based" <?= $addon->getConfig('key_generation_strategy', 'name_based') === 'name_based' ? 'selected' : '' ?>>
                                <?= $addon->i18n('strategy_name_based') ?>
                            </option>
                            <option value="date_name" <?= $addon->getConfig('key_generation_strategy', 'name_based') === 'date_name' ? 'selected' : '' ?>>
                                <?= $addon->i18n('strategy_date_name') ?>
                            </option>
                            <option value="hash_based" <?= $addon->getConfig('key_generation_strategy', 'name_based') === 'hash_based' ? 'selected' : '' ?>>
                                <?= $addon->i18n('strategy_hash_based') ?>
                            </option>
                        </select>
                        <small class="text-muted"><?= $addon->i18n('key_generation_strategy_note') ?></small>
                    </div>
                    

                    
                    <hr>
                    <h4><i class="rex-icon fa-refresh"></i> Automatische Synchronisation</h4>
                    
                    <div class="checkbox">
                        <label>
                            <input type="checkbox" name="settings[sync_frontend]" value="1" 
                                   <?= $addon->getConfig('sync_frontend', false) ? 'checked' : '' ?>>
                            <strong><?= $addon->i18n('sync_frontend', 'Im Frontend synchronisieren') ?></strong>
                        </label>
                        <p class="text-muted"><?= $addon->i18n('sync_frontend_note', 'Nur wenn als Admin im Backend eingeloggt') ?></p>
                    </div>
                    
                    <div class="checkbox">
                        <label>
                            <input type="checkbox" name="settings[sync_backend]" value="1" 
                                   <?= $addon->getConfig('sync_backend', true) ? 'checked' : '' ?>>
                            <strong><?= $addon->i18n('sync_backend', 'Im Backend synchronisieren') ?></strong>
                        </label>
                        <p class="text-muted"><?= $addon->i18n('sync_backend_note', 'Nur wenn als Admin eingeloggt') ?></p>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="rex-icon fa-info-circle"></i> <strong>Performance-Optimierung:</strong>
                        Das synch-Addon nutzt intelligente Change-Detection und prüft nur alle 60 Sekunden auf Änderungen. 
                        Synchronisation erfolgt nur bei tatsächlichen Updates!
                    </div>
                    
                    <?php if (synch_manager::isAutoSyncPaused()): ?>
                    <?php
                    $pausedAt = $addon->getConfig('auto_sync_paused_at', 0);
                    $resumeTime = $pausedAt + (30 * 60); // 30 Minuten später
                    $remainingMinutes = max(0, ceil(($resumeTime - time()) / 60));
                    ?>
                    <div class="alert alert-warning">
                        <i class="rex-icon fa-pause"></i> <strong>Auto-Sync pausiert:</strong>
                        Die automatische Synchronisation ist pausiert und wird automatisch in <strong><?= $remainingMinutes ?> Minuten</strong> fortgesetzt.
                        Sie können sie auch manuell über den "Fortsetzen" Button reaktivieren.
                    </div>
                    <?php endif; ?>
                    
                    <hr>
                    <h4><i class="rex-icon fa-shield"></i> Konflikte</h4>
                    
                    <div class="checkbox">
                        <label>
                            <input type="checkbox" name="settings[update_existing_on_key_conflict]" value="1" 
                                   <?= $addon->getConfig('update_existing_on_key_conflict', true) ? 'checked' : '' ?>>
                            <strong><?= $addon->i18n('update_existing_on_key_conflict') ?></strong>
                        </label>
                        <p class="text-muted"><?= $addon->i18n('update_existing_on_key_conflict_note') ?></p>
                    </div>
                    
                    <button type="submit" name="save_settings" value="1" class="btn btn-success">
                        <i class="rex-icon fa-save"></i> <?= $addon->i18n('save_settings') ?>
                    </button>
                </form>
            </div>
        </div>
        
    </div>
    
    <div class="col-sm-4">
        
        <!-- Status -->
        <div class="panel panel-success">
            <div class="panel-heading">
                <h3 class="panel-title"><i class="rex-icon fa-info-circle"></i> Status</h3>
            </div>
            <div class="panel-body">
                <h5>📊 Übersicht</h5>
                <ul class="list-unstyled">
                    <li><strong>Module:</strong> <?= $moduleCount ?> in DB, <?= $moduleFiles ?> Dateien</li>
                    <li><strong>Templates:</strong> <?= $templateCount ?> in DB, <?= $templateFiles ?> Dateien</li>
                    <li><strong>Actions:</strong> <?= $actionCount ?> in DB, <?= $actionFiles ?> Dateien</li>
                </ul>
                
                <h5>⚙️ Konfiguration</h5>
                <ul class="list-unstyled">
                    <li>
                        <span class="text-<?= $addon->getConfig('auto_generate_keys', true) ? 'success' : 'muted' ?>">
                            <i class="rex-icon fa-<?= $addon->getConfig('auto_generate_keys', true) ? 'check' : 'times' ?>"></i> 
                            Auto-Key-Generierung
                        </span>
                    </li>
                    <li>
                        <span class="text-success">
                            <i class="rex-icon fa-check"></i> 
                            Saubere Ordnernamen (immer aktiv)
                        </span>
                    </li>
                    <li>
                        <span class="text-success">
                            <i class="rex-icon fa-check"></i> 
                            Strategie: <?= ucfirst(str_replace('_', ' ', $addon->getConfig('key_generation_strategy', 'name_based'))) ?>
                        </span>
                    </li>
                    <li>
                        <span class="text-<?= $addon->getConfig('sync_frontend', false) ? 'success' : 'muted' ?>">
                            <i class="rex-icon fa-<?= $addon->getConfig('sync_frontend', false) ? 'check' : 'times' ?>"></i> 
                            Frontend-Sync
                        </span>
                    </li>
                    <li>
                        <span class="text-<?= $addon->getConfig('sync_backend', true) ? 'success' : 'muted' ?>">
                            <i class="rex-icon fa-<?= $addon->getConfig('sync_backend', true) ? 'check' : 'times' ?>"></i> 
                            Backend-Sync
                        </span>
                    </li>
                    <li>
                        <span class="text-success">
                            <i class="rex-icon fa-dashboard"></i> 
                            Change-Detection (60s Cache)
                        </span>
                    </li>
                    <li>
                        <?php 
                        $isPaused = synch_manager::isAutoSyncPaused();
                        $pausedAt = $addon->getConfig('auto_sync_paused_at');
                        ?>
                        <span class="text-<?= $isPaused ? 'warning' : 'success' ?>">
                            <i class="rex-icon fa-<?= $isPaused ? 'pause' : 'play' ?>"></i> 
                            Auto-Sync: <?= $isPaused ? 'Pausiert' : 'Aktiv' ?>
                            <?php if ($isPaused && $pausedAt): ?>
                                <?php 
                                $resumeTime = $pausedAt + (30 * 60);
                                $remainingMinutes = max(0, ceil(($resumeTime - time()) / 60));
                                ?>
                                <small class="text-muted">(<?= $remainingMinutes ?>min verbleibend)</small>
                            <?php endif; ?>
                        </span>
                    </li>
                </ul>
                
                <h5>📁 Pfade</h5>
                <p class="text-muted small">
                    <strong>Module:</strong><br>
                    <code>redaxo/data/addons/synch/modules/</code>
                </p>
                <p class="text-muted small">
                    <strong>Templates:</strong><br>
                    <code>redaxo/data/addons/synch/templates/</code>
                </p>
                <p class="text-muted small">
                    <strong>Actions:</strong><br>
                    <code>redaxo/data/addons/synch/actions/</code>
                </p>
            </div>
        </div>
        
        <!-- Console Command -->
        <div class="panel panel-info">
            <div class="panel-heading">
                <h3 class="panel-title"><i class="rex-icon fa-terminal"></i> Console</h3>
            </div>
            <div class="panel-body">
                <h5>Automatisierung</h5>
                <p>Für Deploy-Prozesse und CI/CD:</p>
                
                <div class="well well-sm">
                    <code>php redaxo/bin/console synch:sync</code>
                </div>
                
                <p class="text-muted small">
                    Optionen:<br>
                    <code>--modules-only</code> - Nur Module<br>
                    <code>--templates-only</code> - Nur Templates<br>
                    <code>--actions-only</code> - Nur Actions<br>
                    <code>--dry-run</code> - Test ohne Änderungen
                </p>
            </div>
        </div>
        
        <!-- Vorteile -->
        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title">🎯 Vorteile</h3>
            </div>
            <div class="panel-body">
                <ul class="list-unstyled">
                    <li><i class="rex-icon fa-check text-success"></i> Keine ID-Konflikte</li>
                    <li><i class="rex-icon fa-check text-success"></i> Saubere Ordnernamen</li>
                    <li><i class="rex-icon fa-check text-success"></i> Git-freundlich</li>
                    <li><i class="rex-icon fa-check text-success"></i> Team-tauglich</li>
                    <li><i class="rex-icon fa-check text-success"></i> Duplikate-frei</li>
                </ul>
            </div>
        </div>
        
    </div>
</div>