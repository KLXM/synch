<?php

/**
 * Install-Script fuer das synch Addon.
 */

$addon = rex_addon::get('synch');

$cleanKey = static function (string $name): string {
    $key = strtolower($name);
    $key = preg_replace('/[^a-zA-Z0-9_]/', '_', $key) ?? $key;
    $key = preg_replace('/_+/', '_', $key) ?? $key;

    return strtolower(trim($key, '_'));
};

/**
 * @param string $tableName
 */
$ensureKeyColumn = static function (string $tableName): void {
    if ($tableName === '') {
        throw new \RuntimeException('Leerer Tabellenname ist nicht erlaubt.');
    }

    $table = rex_sql_table::get($tableName);
    $table->ensureColumn(new rex_sql_column('key', 'varchar(191)', true));
    $table->ensureIndex(new rex_sql_index('key', ['key'], rex_sql_index::UNIQUE));
    $table->alter();
};

/**
 * @param string $tableName
 * @param string $fallbackPrefix
 */
$fillMissingKeys = static function (string $tableName, string $fallbackPrefix) use ($cleanKey): void {
    $sql = rex_sql::factory();
    $sql->setQuery('SELECT id, name, `key` FROM ' . $tableName . ' ORDER BY id');

    while ($sql->hasNext()) {
        $id = (int) $sql->getValue('id');
        $name = (string) ($sql->getValue('name') ?: ($fallbackPrefix . '_' . $id));
        $existingKey = (string) $sql->getValue('key');

        if ($existingKey !== '') {
            $sql->next();
            continue;
        }

        $baseKey = $cleanKey($name);
        if ($baseKey === '') {
            $baseKey = $fallbackPrefix . '_' . $id;
        }

        $uniqueKey = $baseKey;
        $counter = 1;

        while (true) {
            $checkSql = rex_sql::factory();
            $checkSql->setQuery('SELECT id FROM ' . $tableName . ' WHERE `key` = ? AND id <> ? LIMIT 1', [$uniqueKey, $id]);
            if ($checkSql->getRows() === 0) {
                break;
            }

            $uniqueKey = $baseKey . '_' . $counter;
            ++$counter;
        }

        $updateSql = rex_sql::factory();
        $updateSql->setTable($tableName);
        $updateSql->setWhere(['id' => $id]);
        $updateSql->setValue('key', $uniqueKey);
        $updateSql->update();

        $sql->next();
    }
};

try {
    $ensureKeyColumn(rex::getTable('module'));
    $fillMissingKeys(rex::getTable('module'), 'module');

    $ensureKeyColumn(rex::getTable('template'));
    $fillMissingKeys(rex::getTable('template'), 'template');

    $ensureKeyColumn(rex::getTable('action'));
    $fillMissingKeys(rex::getTable('action'), 'action');

    // Stabile und sichere Defaults.
    $addon->setConfig('sync_frontend', false);
    $addon->setConfig('sync_backend', false);
    $addon->setConfig('sync_direction', 'files_to_db');
    $addon->setConfig('conflict_strategy', 'newer_wins');
    $addon->setConfig('update_existing_on_key_conflict', true);
    $addon->setConfig('allow_empty_filesystem_cleanup', false);

    echo rex_view::success('Synch Addon erfolgreich installiert. Key-Spalten sind vorhanden und fehlende Keys wurden erzeugt.');
    echo rex_view::info('<strong>Hinweis:</strong> Auto-Sync ist standardmaessig deaktiviert und kann in den Einstellungen aktiviert werden.');
} catch (Throwable $throwable) {
    echo rex_view::error('Fehler bei der Installation: ' . $throwable->getMessage());
}
