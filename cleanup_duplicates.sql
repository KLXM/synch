-- SQL Script zum Löschen aller Duplikate mit _1 am Ende des Keys
-- ACHTUNG: Vorher Backup erstellen!

-- Module mit _1 am Ende löschen
DELETE FROM rex_module WHERE `key` LIKE '%_1';

-- Templates mit _1 am Ende löschen  
DELETE FROM rex_template WHERE `key` LIKE '%_1';

-- Actions mit _1 am Ende löschen
DELETE FROM rex_action WHERE `key` LIKE '%_1';

-- Anzahl der betroffenen Einträge vorher prüfen (Optional):
-- SELECT COUNT(*) as modul_duplikate FROM rex_module WHERE `key` LIKE '%_1';
-- SELECT COUNT(*) as template_duplikate FROM rex_template WHERE `key` LIKE '%_1';
-- SELECT COUNT(*) as action_duplikate FROM rex_action WHERE `key` LIKE '%_1';

-- Alle Einträge mit _1 am Ende anzeigen (Optional, vor dem Löschen):
-- SELECT 'MODULE' as type, id, name, `key` FROM rex_module WHERE `key` LIKE '%_1'
-- UNION ALL
-- SELECT 'TEMPLATE' as type, id, name, `key` FROM rex_template WHERE `key` LIKE '%_1'  
-- UNION ALL
-- SELECT 'ACTION' as type, id, name, `key` FROM rex_action WHERE `key` LIKE '%_1';