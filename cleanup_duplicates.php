<?php

/**
 * Cleanup Script für doppelte Module/Templates/Actions
 * 
 * ACHTUNG: Dieses Script löscht Duplikate! Vorher Backup erstellen!
 * 
 * Aufruf über Browser: /redaxo/src/addons/synch/cleanup_duplicates.php
 * Oder Console: php cleanup_duplicates.php
 */

// REDAXO Bootstrap
$redaxoPath = dirname(dirname(dirname(__DIR__)));
require_once $redaxoPath . '/src/core/boot.php';
rex::setProperty('setup', true);

// Sicherheitscheck
if (!rex::isDebugMode() && !rex_get('confirm', 'bool')) {
    echo '<h1>⚠️ ACHTUNG: Duplikat Bereinigung</h1>';
    echo '<p><strong>Dieses Script löscht doppelte Module, Templates und Actions!</strong></p>';
    echo '<p>Erstellen Sie vorher unbedingt ein Backup Ihrer Datenbank!</p>';
    echo '<p><a href="?confirm=1" style="background:red;color:white;padding:10px;text-decoration:none;">⚠️ JA, DUPLIKATE LÖSCHEN</a></p>';
    exit;
}

echo "<h1>🧹 Synch Duplikat-Bereinigung</h1>\n";
echo "<hr>\n";

$sql = rex_sql::factory();
$deletedCount = 0;
$errors = [];

/**
 * Bereinigt Duplikate in einer Tabelle
 */
function cleanupDuplicates(string $table, string $nameField = 'name'): array {
    global $sql, $deletedCount, $errors;
    
    $results = ['deleted' => 0, 'kept' => 0, 'errors' => []];
    
    echo "<h2>📋 Bereinige Tabelle: $table</h2>\n";
    
    try {
        // Finde alle Duplikate basierend auf Name
        $sql->setQuery("
            SELECT $nameField, COUNT(*) as count, 
                   GROUP_CONCAT(id ORDER BY id) as ids,
                   GROUP_CONCAT(`key` ORDER BY id) as keys
            FROM $table 
            WHERE $nameField != '' AND $nameField IS NOT NULL
            GROUP BY $nameField 
            HAVING COUNT(*) > 1
            ORDER BY $nameField
        ");
        
        $duplicateGroups = $sql->getArray();
        
        if (empty($duplicateGroups)) {
            echo "✅ Keine Duplikate gefunden in $table<br>\n";
            return $results;
        }
        
        echo "⚠️ " . count($duplicateGroups) . " Duplikat-Gruppen gefunden:<br>\n";
        
        foreach ($duplicateGroups as $group) {
            $name = $group[$nameField];
            $ids = explode(',', $group['ids']);
            $keys = explode(',', $group['keys'] ?? '');
            $count = $group['count'];
            
            echo "<strong>$name</strong> ($count Einträge):<br>\n";
            
            // Behalte den ersten Eintrag (niedrigste ID), lösche den Rest
            $keepId = array_shift($ids);
            $keepKey = array_shift($keys);
            
            echo "  ✅ Behalte ID $keepId" . ($keepKey ? " (Key: $keepKey)" : "") . "<br>\n";
            
            foreach ($ids as $index => $deleteId) {
                $deleteKey = $keys[$index] ?? '';
                
                try {
                    $deleteSql = rex_sql::factory();
                    $deleteSql->setQuery("DELETE FROM $table WHERE id = ?", [$deleteId]);
                    
                    echo "  🗑️ Gelöscht ID $deleteId" . ($deleteKey ? " (Key: $deleteKey)" : "") . "<br>\n";
                    $results['deleted']++;
                    $deletedCount++;
                    
                } catch (Exception $e) {
                    $error = "Fehler beim Löschen von ID $deleteId: " . $e->getMessage();
                    echo "  ❌ $error<br>\n";
                    $results['errors'][] = $error;
                    $errors[] = $error;
                }
            }
            
            $results['kept']++;
            echo "<br>\n";
        }
        
        // Zusätzlich: Duplikate basierend auf identischen Keys bereinigen
        if ($table !== rex::getTable('action')) { // Actions haben manchmal leere Keys
            echo "<h3>🔑 Prüfe Key-Duplikate in $table</h3>\n";
            
            $sql->setQuery("
                SELECT `key`, COUNT(*) as count, 
                       GROUP_CONCAT(id ORDER BY id) as ids,
                       GROUP_CONCAT($nameField ORDER BY id) as names
                FROM $table 
                WHERE `key` != '' AND `key` IS NOT NULL
                GROUP BY `key` 
                HAVING COUNT(*) > 1
                ORDER BY `key`
            ");
            
            $keyDuplicates = $sql->getArray();
            
            if (!empty($keyDuplicates)) {
                echo "⚠️ " . count($keyDuplicates) . " Key-Duplikate gefunden:<br>\n";
                
                foreach ($keyDuplicates as $group) {
                    $key = $group['key'];
                    $ids = explode(',', $group['ids']);
                    $names = explode(',', $group['names']);
                    $count = $group['count'];
                    
                    echo "<strong>Key: $key</strong> ($count Einträge):<br>\n";
                    
                    // Behalte den ersten Eintrag
                    $keepId = array_shift($ids);
                    $keepName = array_shift($names);
                    
                    echo "  ✅ Behalte ID $keepId ($keepName)<br>\n";
                    
                    foreach ($ids as $index => $deleteId) {
                        $deleteName = $names[$index];
                        
                        try {
                            $deleteSql = rex_sql::factory();
                            $deleteSql->setQuery("DELETE FROM $table WHERE id = ?", [$deleteId]);
                            
                            echo "  🗑️ Gelöscht ID $deleteId ($deleteName)<br>\n";
                            $results['deleted']++;
                            $deletedCount++;
                            
                        } catch (Exception $e) {
                            $error = "Fehler beim Löschen von Key-Duplikat ID $deleteId: " . $e->getMessage();
                            echo "  ❌ $error<br>\n";
                            $results['errors'][] = $error;
                            $errors[] = $error;
                        }
                    }
                    echo "<br>\n";
                }
            } else {
                echo "✅ Keine Key-Duplikate in $table<br>\n";
            }
        }
        
    } catch (Exception $e) {
        $error = "Fehler bei der Duplikat-Bereinigung in $table: " . $e->getMessage();
        echo "❌ $error<br>\n";
        $results['errors'][] = $error;
        $errors[] = $error;
    }
    
    echo "<hr>\n";
    return $results;
}

// Bereinige alle Tabellen
$moduleResults = cleanupDuplicates(rex::getTable('module'));
$templateResults = cleanupDuplicates(rex::getTable('template'));
$actionResults = cleanupDuplicates(rex::getTable('action'));

// Zusammenfassung
echo "<h2>📊 Zusammenfassung</h2>\n";
echo "<strong>Gesamt gelöschte Einträge:</strong> $deletedCount<br>\n";
echo "<strong>Module:</strong> {$moduleResults['deleted']} gelöscht, {$moduleResults['kept']} behalten<br>\n";
echo "<strong>Templates:</strong> {$templateResults['deleted']} gelöscht, {$templateResults['kept']} behalten<br>\n";
echo "<strong>Actions:</strong> {$actionResults['deleted']} gelöscht, {$actionResults['kept']} behalten<br>\n";

if (!empty($errors)) {
    echo "<br><h3>❌ Fehler:</h3>\n";
    foreach ($errors as $error) {
        echo "• $error<br>\n";
    }
} else {
    echo "<br>✅ <strong>Bereinigung erfolgreich abgeschlossen!</strong><br>\n";
}

// Aufräumen: Cache leeren
if (function_exists('rex_delete_cache')) {
    rex_delete_cache();
    echo "<br>🧹 Cache geleert<br>\n";
}

echo "<br><a href='/redaxo/index.php?page=packages'>🔙 Zurück zu den Paketen</a><br>\n";