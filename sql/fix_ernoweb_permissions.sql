-- ============================================================
-- Ernoweb@ Permission Fix — Least Privilege
-- Analizzato su: codice PHP htdocs + db.php bootstrap
-- ============================================================
-- PREREQUISITO: eseguire create_web_tables.sql PRIMA di questo.
-- db.php fa CREATE TABLE a runtime — fallisce silenziosamente se
-- le tabelle non esistono e Ernoweb@ non ha CREATE TABLE (DDL).
-- Le tabelle vanno pre-create dal DBA con create_web_tables.sql.
-- ============================================================

-- ============================================================
-- PS_UserData
-- OPS trovate (web + updater-api):
--   SELECT  (Users_Master, AdminUserWhitelist, AdminIPWhitelist,
--            Updater_SavedDeviceAccount)
--   INSERT  (AdminUserWhitelist, AdminIPWhitelist,
--            Updater_SavedDeviceAccount)
--   UPDATE  (Users_Master — Point/Status/Enpassword,
--            Updater_SavedDeviceAccount — LastUsedAt/LastIP)
--   DELETE  (Updater_SavedDeviceAccount — rimozione device salvato)
--   EXECUTE (CheckPw_Safe SP — validazione password updater)
-- NOTA: DELETE e EXECUTE servono all'updater-api, non solo al sito.
-- ============================================================
USE [PS_UserData];
GO
IF EXISTS (SELECT 1 FROM sys.database_principals WHERE name = 'Ernoweb@')
BEGIN
    IF IS_ROLEMEMBER('MioRuoloRead',    'Ernoweb@') = 0 EXEC sp_addrolemember 'MioRuoloRead',    'Ernoweb@';
    IF IS_ROLEMEMBER('MioRuoloInsert',  'Ernoweb@') = 0 EXEC sp_addrolemember 'MioRuoloInsert',  'Ernoweb@';
    IF IS_ROLEMEMBER('MioRuoloUpdate',  'Ernoweb@') = 0 EXEC sp_addrolemember 'MioRuoloUpdate',  'Ernoweb@';
    IF IS_ROLEMEMBER('MioRuoloDelete',  'Ernoweb@') = 0 EXEC sp_addrolemember 'MioRuoloDelete',  'Ernoweb@';
    IF IS_ROLEMEMBER('MioRuoloExecute', 'Ernoweb@') = 0 EXEC sp_addrolemember 'MioRuoloExecute', 'Ernoweb@';

    PRINT 'PS_UserData: Ernoweb@ verificato → READ + INSERT + UPDATE + DELETE + EXECUTE';
END
ELSE
    PRINT 'PS_UserData: Ernoweb@ non esiste — saltato';
GO

-- ============================================================
-- PS_Billing
-- OPS trovate: NESSUNA operazione rilevata nel codice PHP.
-- AZIONE: rimozione accesso completo (principio least privilege).
-- NOTA: se in futuro serve accesso billing (es. registrazione),
--       aggiungere MioRuoloRead o MioRuoloInsert manualmente.
-- ============================================================
USE [PS_Billing];
GO
IF EXISTS (SELECT 1 FROM sys.database_principals WHERE name = 'Ernoweb@')
BEGIN
    IF IS_ROLEMEMBER('MioRuoloInsert', 'Ernoweb@') = 1
        EXEC sp_droprolemember 'MioRuoloInsert', 'Ernoweb@';
    IF IS_ROLEMEMBER('MioRuoloRead', 'Ernoweb@') = 1
        EXEC sp_droprolemember 'MioRuoloRead', 'Ernoweb@';

    PRINT 'PS_Billing: Ernoweb@ → accesso rimosso (nessuna op trovata)';
END
ELSE
    PRINT 'PS_Billing: Ernoweb@ non esiste — saltato';
GO

-- ============================================================
-- PS_GameData
-- OPS trovate: SELECT (Chars, CharItems, UserMaxGrow, Guilds,
--                      GuildChars, UserStoredItems, UserStoredPointItems,
--                      MarketItems, GuildStoredItems)
--              INSERT (UserStoredPointItems, UserStoredItems)
--              UPDATE (Chars, CharItems, Guilds)
--              DELETE (UserStoredItems, GuildStoredItems)
-- AZIONE: nessuna modifica — già corretto.
-- ============================================================
USE [PS_GameData];
GO
IF EXISTS (SELECT 1 FROM sys.database_principals WHERE name = 'Ernoweb@')
BEGIN
    IF IS_ROLEMEMBER('MioRuoloRead',   'Ernoweb@') = 0 EXEC sp_addrolemember 'MioRuoloRead',   'Ernoweb@';
    IF IS_ROLEMEMBER('MioRuoloInsert', 'Ernoweb@') = 0 EXEC sp_addrolemember 'MioRuoloInsert', 'Ernoweb@';
    IF IS_ROLEMEMBER('MioRuoloUpdate', 'Ernoweb@') = 0 EXEC sp_addrolemember 'MioRuoloUpdate', 'Ernoweb@';
    IF IS_ROLEMEMBER('MioRuoloDelete', 'Ernoweb@') = 0 EXEC sp_addrolemember 'MioRuoloDelete', 'Ernoweb@';

    PRINT 'PS_GameData: Ernoweb@ verificato → READ + INSERT + UPDATE + DELETE';
END
ELSE
    PRINT 'PS_GameData: Ernoweb@ non esiste — saltato';
GO

-- ============================================================
-- PS_GameDefs
-- OPS trovate: SELECT (Mobs, Items, MobItems, MapNames)
--              UPDATE (MobItems, Mobs — drops editor + bosses editor)
--              EXECUTE ([dbo].[Command] — comandi gioco /nt /exp2x ecc.)
-- RIMOSSE:     MioRuoloInsert (nessun INSERT trovato)
-- AGGIUNTE:    MioRuoloExecute (EXEC Command SP da admin_actions.php)
-- ============================================================
USE [PS_GameDefs];
GO
IF EXISTS (SELECT 1 FROM sys.database_principals WHERE name = 'Ernoweb@')
BEGIN
    IF IS_ROLEMEMBER('MioRuoloInsert', 'Ernoweb@') = 1
        EXEC sp_droprolemember 'MioRuoloInsert', 'Ernoweb@';

    IF IS_ROLEMEMBER('MioRuoloRead',    'Ernoweb@') = 0 EXEC sp_addrolemember 'MioRuoloRead',    'Ernoweb@';
    IF IS_ROLEMEMBER('MioRuoloUpdate',  'Ernoweb@') = 0 EXEC sp_addrolemember 'MioRuoloUpdate',  'Ernoweb@';
    IF IS_ROLEMEMBER('MioRuoloExecute', 'Ernoweb@') = 0 EXEC sp_addrolemember 'MioRuoloExecute', 'Ernoweb@';

    PRINT 'PS_GameDefs: Ernoweb@ aggiornato → READ + UPDATE + EXECUTE';
END
ELSE
    PRINT 'PS_GameDefs: Ernoweb@ non esiste — saltato';
GO

-- ============================================================
-- PS_GameLog
-- OPS trovate: SELECT (ActionLog, Boss_Death_Log)
--              INSERT: nessuna | UPDATE: nessuna | DELETE: nessuna
-- RIMOSSE:     MioRuoloDelete, MioRuoloInsert
-- ============================================================
USE [PS_GameLog];
GO
IF EXISTS (SELECT 1 FROM sys.database_principals WHERE name = 'Ernoweb@')
BEGIN
    IF IS_ROLEMEMBER('MioRuoloDelete', 'Ernoweb@') = 1
        EXEC sp_droprolemember 'MioRuoloDelete', 'Ernoweb@';
    IF IS_ROLEMEMBER('MioRuoloInsert', 'Ernoweb@') = 1
        EXEC sp_droprolemember 'MioRuoloInsert', 'Ernoweb@';

    IF IS_ROLEMEMBER('MioRuoloRead', 'Ernoweb@') = 0
        EXEC sp_addrolemember 'MioRuoloRead', 'Ernoweb@';

    PRINT 'PS_GameLog: Ernoweb@ aggiornato → READ ONLY';
END
ELSE
    PRINT 'PS_GameLog: Ernoweb@ non esiste — saltato';
GO

-- ============================================================
-- PS_WebSite
-- OPS trovate: SELECT (Web_Settings, Web_News, Web_PointHistory,
--                      Web_LuckyCase, Web_LuckyChest, Web_Bosses,
--                      Web_DropsEditLog, Web_PatchHistory,
--                      Web_TaskJobs, Web_TaskQueue, Web_TaskJobRuns)
--              INSERT (Web_Settings, Web_PointHistory, Web_LuckyChest,
--                      Web_Bosses, Web_DropsEditLog, Web_PatchHistory,
--                      Web_TaskJobs, Web_TaskQueue, Web_TaskJobRuns)
--              UPDATE (Web_Settings, Web_LuckyChest, Web_DropsEditLog,
--                      Web_Bosses)
--              DELETE (Web_LuckyCase, Web_LuckyChest, Web_DropsEditLog)
--              ALTER  (db.php: ALTER TABLE Web_TaskQueue ADD LockedBy)
-- RIMOSSE:     MioRuoloOther (troppo ampio: include EXECUTE+ALTER insieme)
-- AGGIUNTE:    MioRuoloUpdate, MioRuoloDelete, MioRuoloAlter (separati)
-- NOTA:        EXECUTE rimosso — nessuna stored procedure chiamata da PHP
--              su PS_WebSite. db.php usa solo DDL/DML diretti.
-- ============================================================
USE [PS_WebSite];
GO
IF EXISTS (SELECT 1 FROM sys.database_principals WHERE name = 'Ernoweb@')
BEGIN
    IF IS_ROLEMEMBER('MioRuoloOther', 'Ernoweb@') = 1
        EXEC sp_droprolemember 'MioRuoloOther', 'Ernoweb@';

    IF IS_ROLEMEMBER('MioRuoloRead',   'Ernoweb@') = 0 EXEC sp_addrolemember 'MioRuoloRead',   'Ernoweb@';
    IF IS_ROLEMEMBER('MioRuoloInsert', 'Ernoweb@') = 0 EXEC sp_addrolemember 'MioRuoloInsert', 'Ernoweb@';
    IF IS_ROLEMEMBER('MioRuoloUpdate', 'Ernoweb@') = 0 EXEC sp_addrolemember 'MioRuoloUpdate', 'Ernoweb@';
    IF IS_ROLEMEMBER('MioRuoloDelete', 'Ernoweb@') = 0 EXEC sp_addrolemember 'MioRuoloDelete', 'Ernoweb@';
    IF IS_ROLEMEMBER('MioRuoloAlter',  'Ernoweb@') = 0 EXEC sp_addrolemember 'MioRuoloAlter',  'Ernoweb@';

    PRINT 'PS_WebSite: Ernoweb@ aggiornato → READ + INSERT + UPDATE + DELETE + ALTER';
END
ELSE
    PRINT 'PS_WebSite: Ernoweb@ non esiste — saltato';
GO

-- ============================================================
-- RIEPILOGO FINALE Ernoweb@
-- ============================================================
-- DB            | Ruoli DOPO questo script
-- --------------|------------------------------------------
-- PS_UserData   | READ, INSERT, UPDATE, DELETE, EXECUTE
-- PS_Billing    | (nessuno)
-- PS_GameData   | READ, INSERT, UPDATE, DELETE
-- PS_GameDefs   | READ, UPDATE, EXECUTE
-- PS_GameLog    | READ
-- PS_WebSite    | READ, INSERT, UPDATE, DELETE, ALTER
-- ============================================================
