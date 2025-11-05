<?php

/**
 * Install-Script für synch Addon
 * Erweitert die Action-Tabelle um eine Key-Spalte
 */

$sql = rex_sql::factory();

// Key-Spalte zu rex_action hinzufügen falls nicht vorhanden
try {
    $sql->setQuery('DESCRIBE ' . rex::getTable('action'));
    $columns = [];
    
    while ($sql->hasNext()) {
        $columns[] = $sql->getValue('Field');
        $sql->next();
    }
    
    if (!in_array('key', $columns)) {
        $sql->setQuery('
            ALTER TABLE ' . rex::getTable('action') . ' 
            ADD COLUMN `key` varchar(191) NULL AFTER `id`,
            ADD UNIQUE KEY `key` (`key`)
        ');
        
        echo rex_view::success('Action-Tabelle erfolgreich um Key-Spalte erweitert');
    }
    
} catch (Exception $e) {
    echo rex_view::error('Fehler beim Erweitern der Action-Tabelle: ' . $e->getMessage());
}