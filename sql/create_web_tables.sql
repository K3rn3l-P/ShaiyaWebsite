-- ============================================================
-- Shaiya Website - Required Custom Tables
-- Database: PS_WebSite
-- Run this script on your SQL Server to create all
-- tables needed by the website. Safe to re-run (IF NOT EXISTS).
-- ============================================================

USE [PS_WebSite];
GO

-- ============================================================
-- 1. Web_Settings
--    Stores all admin-configurable settings (registration toggle,
--    download links, lucky chest toggle, drops config, blacklists)
-- ============================================================
IF NOT EXISTS (SELECT * FROM sys.objects WHERE object_id = OBJECT_ID(N'[dbo].[Web_Settings]') AND type in (N'U'))
BEGIN
    CREATE TABLE dbo.Web_Settings (
        SettingId    INT IDENTITY(1,1) PRIMARY KEY,
        SettingKey   VARCHAR(100)   NOT NULL UNIQUE,
        SettingValue VARCHAR(MAX)   NOT NULL
    );
    PRINT 'Created table: Web_Settings';
END
ELSE
    PRINT 'Table already exists: Web_Settings';
GO

-- ============================================================
-- 2. Web_News
--    Stores news articles displayed on the homepage.
--    Managed via the admin panel news editor.
-- ============================================================
IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'Web_News')
BEGIN
    CREATE TABLE dbo.Web_News (
        NewsID    INT IDENTITY(1,1) PRIMARY KEY,
        Title     NVARCHAR(255)  NOT NULL,
        Content   NVARCHAR(MAX)  NOT NULL,
        Author    NVARCHAR(50)   NOT NULL,
        CreatedAt DATETIME       DEFAULT GETDATE(),
        IsHidden  BIT            DEFAULT 0
    );
    PRINT 'Created table: Web_News';
END
ELSE
    PRINT 'Table already exists: Web_News';
GO

-- ============================================================
-- 3. Web_PointHistory
--    Logs every GM point grant (who added, how many, reason).
--    Created automatically on db.php load, but included here
--    for completeness.
-- ============================================================
IF NOT EXISTS (SELECT * FROM sys.tables WHERE name = 'Web_PointHistory')
BEGIN
    CREATE TABLE dbo.Web_PointHistory (
        RowID       INT IDENTITY(1,1) PRIMARY KEY,
        UserID      VARCHAR(32)    NOT NULL,
        PointsAdded INT            NOT NULL,
        Reason      VARCHAR(255)   NULL,
        GM_Account  VARCHAR(32)    NOT NULL,
        [Date]      DATETIME       DEFAULT GETDATE()
    );
    PRINT 'Created table: Web_PointHistory';
END
ELSE
    PRINT 'Table already exists: Web_PointHistory';
GO

-- ============================================================
-- 4. Web_LuckyCase
--    Tracks each player's last Lucky Chest roll time
--    for the 6-hour cooldown system.
-- ============================================================
IF NOT EXISTS (SELECT * FROM dbo.sysobjects WHERE name = 'Web_LuckyCase' AND xtype = 'U')
BEGIN
    CREATE TABLE dbo.Web_LuckyCase (
        UserUID      INT      PRIMARY KEY,
        LastRollTime DATETIME NOT NULL
    );
    PRINT 'Created table: Web_LuckyCase';
END
ELSE
BEGIN
    -- Migration: rename legacy "LastClaim" column to "LastRollTime" if it exists
    IF EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID('dbo.Web_LuckyCase') AND name = 'LastClaim')
    BEGIN
        EXEC sp_rename 'dbo.Web_LuckyCase.LastClaim', 'LastRollTime', 'COLUMN';
        PRINT 'Renamed column LastClaim -> LastRollTime in Web_LuckyCase';
    END
    PRINT 'Table already exists: Web_LuckyCase';
END
GO

-- ============================================================
-- 5. Web_Bosses
--    Configura la lista dei World Boss visualizzati in bosses.php.
--    Gestibile dall'admin panel (Bosses Management).
--    IsActive=0 nasconde il boss dalla pagina pubblica.
-- ============================================================
IF NOT EXISTS (SELECT * FROM sys.objects WHERE object_id = OBJECT_ID(N'[dbo].[Web_Bosses]') AND type = N'U')
BEGIN
    CREATE TABLE dbo.Web_Bosses (
        BossID         INT           IDENTITY(1,1) PRIMARY KEY,
        MobID          INT           NOT NULL,
        RespawnSeconds INT           NOT NULL DEFAULT 43200,
        IsActive       BIT           NOT NULL DEFAULT 1,
        SortOrder      INT           NOT NULL DEFAULT 0,
        Notes          NVARCHAR(200) NULL,
        AddedAt        DATETIME      NOT NULL DEFAULT GETDATE()
    );
    -- Seed: tutti i boss (vecchio sito + nuovi ID da verificare)
    INSERT INTO dbo.Web_Bosses (MobID, RespawnSeconds, SortOrder, Notes) VALUES
        (2469, 54000,  0,  'Dios Exiel 1st Form'),
        (2472, 54000,  1,  'Dios Exiel Real'),
        (835,  21600,  2,  'Kimuraku'),
        (1259, 21600,  3,  'Greendieta Seraphim'),
        (1975, 604800, 10, 'Haruhion'),
        (1974, 604800, 11, 'Freezing Mirage'),
        (1716, 604800, 12, 'Cryptic the Immortal AOL'),
        (1816, 604800, 13, 'Cryptic the Immortal UOF'),
        (3573, 604800, 14, 'Restriction Fire Guardian'),
        (3572, 604800, 15, 'Restriction Earth Guardian'),
        (2785, 43200,  20, 'Ales'),
        (2839, 43200,  21, 'Lumen'),
        (2821, 43200,  22, 'Dentatus'),
        (2803, 43200,  23, 'Secreta'),
        (3019, 90000,  30, 'Fierce Nantarios'),
        (3015, 90000,  31, 'Fury Kirhiross'),
        (3023, 90000,  32, 'Dlizabeth Eathory'),
        (3028, 90000,  33, 'Cannibal Hydra'),
        (3010, 90000,  34, 'Cryptic Bulldozer'),
        (3005, 90000,  35, 'Cryptic Peri Queen'),
        (3042, 90000,  36, 'Cryptic the Last'),
        (3539, 90000,  37, 'Opalus'),
        (871,  43200,  40, 'Cloron'),
        (872,  43200,  41, 'Cloron Dragon'),
        (1700, 604800, 50, 'Cryptic One'),
        (1702, 604800, 51, 'Blizabeth Eathory'),
        (1703, 604800, 52, 'Alcarian The Flame'),
        (1704, 604800, 53, 'Deinos The Dream'),
        (1705, 604800, 54, 'Kirhiross'),
        (1706, 604800, 55, 'Nantarios'),
        (1707, 604800, 56, 'Cryptic Devilkin'),
        (901,  43200,  60, 'Fantasma'),
        (902,  43200,  61, 'Fantasma Dragon'),
        (1800, 604800, 70, 'Cryptic One'),
        (1802, 604800, 71, 'Blizabeth Eathory'),
        (1803, 604800, 72, 'Alcarian The Flame'),
        (3043, 604800, 73, 'Fury Kirihiross'),
        (3576, 604800, 74, 'Sniper of Light'),
        (1804, 604800, 75, 'Deinos The Dream'),
        (1805, 604800, 76, 'Kirhiross'),
        (1806, 604800, 77, 'Nantarios'),
        (1807, 604800, 78, 'Cryptic Devilkin'),
        (3562, 604800, 80, 'Impregnable Iron Bear'),
        (3537, 604800, 81, 'Summoned Relic Protector'),
        (3081, 43200,  90, 'Young Bunny'),
        (3088, 43200,  91, '??? ???'),
        (2950, 43200,  92, 'Queen Vanus'),
        (2983, 43200,  93, 'Queen Kaizerin');
    PRINT 'Created table: Web_Bosses (with seed data)';
END
ELSE
    PRINT 'Table already exists: Web_Bosses';
GO

-- ============================================================
-- 6. Web_DropsEditLog
--    Changelog sessione del Drops Editor.
--    Registra ogni modifica a MobItems (Grade/DropRate) con
--    i valori vecchi e nuovi, permettendo l'UNDO per riga.
--    Auto-cleanup: righe > 24h cancellate al caricamento della pagina.
-- ============================================================
USE [PS_WebSite];
GO
IF NOT EXISTS (SELECT * FROM sys.objects WHERE object_id = OBJECT_ID(N'[dbo].[Web_DropsEditLog]') AND type = N'U')
BEGIN
    CREATE TABLE dbo.Web_DropsEditLog (
        LogID       INT           IDENTITY(1,1) PRIMARY KEY,
        AdminUID    INT           NOT NULL,
        AdminID     VARCHAR(32)   NOT NULL,
        RowID       INT           NOT NULL,
        MobID       INT           NOT NULL,
        MobName     NVARCHAR(100) NULL,
        ItemOrder   TINYINT       NOT NULL,
        OldGrade    SMALLINT      NULL,
        NewGrade    SMALLINT      NOT NULL,
        OldDropRate INT           NULL,
        NewDropRate INT           NOT NULL,
        ChangedAt   DATETIME      NOT NULL DEFAULT GETDATE()
    );
    CREATE INDEX IX_Web_DropsEditLog_AdminUID
        ON dbo.Web_DropsEditLog(AdminUID, ChangedAt DESC);
    PRINT 'Created table: Web_DropsEditLog';
END
ELSE
    PRINT 'Table already exists: Web_DropsEditLog';
GO

-- ============================================================
-- 8. Web_TaskJobs
--    Job definitions for the Task Scheduler module.
--    Managed via admin panel (Task Scheduler section).
-- ============================================================
IF NOT EXISTS (SELECT * FROM sys.objects WHERE object_id = OBJECT_ID(N'[dbo].[Web_TaskJobs]') AND type = N'U')
BEGIN
    CREATE TABLE dbo.Web_TaskJobs (
        JobID         INT           IDENTITY(1,1) PRIMARY KEY,
        JobName       NVARCHAR(200) NOT NULL,
        IsEnabled     BIT           NOT NULL DEFAULT 1,
        Status        VARCHAR(20)   NOT NULL DEFAULT 'active',
        ScheduleMode  VARCHAR(20)   NOT NULL DEFAULT 'simple',
        ScheduleValue VARCHAR(50)   NOT NULL DEFAULT 'hourly',
        TaskType      VARCHAR(20)   NOT NULL DEFAULT 'SERVICE_COMMAND',
        ServiceName   NVARCHAR(200) NULL,
        CommandText   NVARCHAR(500) NULL,
        SqlPayload    NVARCHAR(MAX) NULL,
        SqlSource     VARCHAR(50)   NULL DEFAULT 'none',
        CreatedBy     NVARCHAR(100) NOT NULL,
        CreatedAt     DATETIME      NOT NULL DEFAULT GETDATE(),
        UpdatedBy     NVARCHAR(100) NULL,
        UpdatedAt     DATETIME      NULL
    );
    PRINT 'Created table: Web_TaskJobs';
END
ELSE
    PRINT 'Table already exists: Web_TaskJobs';
GO

-- ============================================================
-- 9. Web_TaskQueue
--    Pending actions sent from admin panel to the worker.
--    Worker polls this table every minute and processes items.
-- ============================================================
IF NOT EXISTS (SELECT * FROM sys.objects WHERE object_id = OBJECT_ID(N'[dbo].[Web_TaskQueue]') AND type = N'U')
BEGIN
    CREATE TABLE dbo.Web_TaskQueue (
        QueueID       INT           IDENTITY(1,1) PRIMARY KEY,
        JobID         INT           NOT NULL,
        QueueAction   VARCHAR(20)   NOT NULL,
        Payload       NVARCHAR(MAX) NULL,
        Status        VARCHAR(20)   NOT NULL DEFAULT 'pending',
        Attempts      INT           NOT NULL DEFAULT 0,
        LockedAt      DATETIME      NULL,
        LockedBy      VARCHAR(100)  NULL,
        ProcessedAt   DATETIME      NULL,
        ResultMessage NVARCHAR(MAX) NULL,
        CreatedAt     DATETIME      NOT NULL DEFAULT GETDATE(),
        CreatedBy     NVARCHAR(100) NULL
    );
    CREATE INDEX IX_Web_TaskQueue_Status ON dbo.Web_TaskQueue(Status, QueueID ASC);
    PRINT 'Created table: Web_TaskQueue';
END
ELSE
    PRINT 'Table already exists: Web_TaskQueue';
GO

-- ============================================================
-- 10. Web_TaskJobRuns
--    Execution history: one row per job run, logged by worker.
-- ============================================================
IF NOT EXISTS (SELECT * FROM sys.objects WHERE object_id = OBJECT_ID(N'[dbo].[Web_TaskJobRuns]') AND type = N'U')
BEGIN
    CREATE TABLE dbo.Web_TaskJobRuns (
        RunID        INT           IDENTITY(1,1) PRIMARY KEY,
        JobID        INT           NOT NULL,
        QueueID      INT           NULL,
        RunStatus    VARCHAR(20)   NOT NULL,
        StartedAt    DATETIME      NOT NULL DEFAULT GETDATE(),
        FinishedAt   DATETIME      NULL,
        Output       NVARCHAR(MAX) NULL,
        ErrorMessage NVARCHAR(MAX) NULL,
        TriggeredBy  VARCHAR(100)  NULL
    );
    CREATE INDEX IX_Web_TaskJobRuns_JobID ON dbo.Web_TaskJobRuns(JobID, StartedAt DESC);
    PRINT 'Created table: Web_TaskJobRuns';
END
ELSE
    PRINT 'Table already exists: Web_TaskJobRuns';
GO

-- ============================================================
-- 11. Web_LuckyChest
--    Configura i premi del Lucky Chest (ItemID, icona, colore,
--    peso probabilita'). Gestibile dall'admin panel.
--    Sostituisce i valori hardcoded in lucky_case_api.php.
-- ============================================================
IF NOT EXISTS (SELECT * FROM sys.objects WHERE object_id = OBJECT_ID(N'[dbo].[Web_LuckyChest]') AND type = N'U')
BEGIN
    CREATE TABLE dbo.Web_LuckyChest (
        SlotID      INT           IDENTITY(1,1) PRIMARY KEY,
        ItemID      INT           NOT NULL DEFAULT 0,
        DisplayName NVARCHAR(100) NOT NULL,
        Icon        VARCHAR(50)   NOT NULL DEFAULT 'gem',
        Color       VARCHAR(20)   NOT NULL DEFAULT '#bdc3c7',
        Probability INT           NOT NULL DEFAULT 1,
        SortOrder   INT           NOT NULL DEFAULT 0,
        IsActive    BIT           NOT NULL DEFAULT 1
    );
    INSERT INTO dbo.Web_LuckyChest (ItemID, DisplayName, Icon, Color, Probability, SortOrder) VALUES
        (100251, '10 AP',      'gem',          '#bdc3c7', 20, 0),
        (100252, '100 AP',     'gem',          '#3498db', 15, 1),
        (100253, '1000 AP',    'gem',          '#9b59b6', 10, 2),
        (100254, '10.000 AP',  'gem',          '#ff3f34',  5, 3),
        (0,      'Nothing',    'times-circle', '#7f8c8d',  2, 4);
    PRINT 'Created table: Web_LuckyChest (with seed data)';
END
ELSE
    PRINT 'Table already exists: Web_LuckyChest';
GO

-- ============================================================
-- 12. Web_PatchHistory
--    Log di ogni patch caricata dal Patch Manager.
--    Registra versione, descrizione, modo upload, dimensione
--    e amministratore che ha eseguito l'upload.
--    Non viene mai cancellata automaticamente (audit permanente).
-- ============================================================
USE [PS_WebSite];
GO
IF NOT EXISTS (SELECT * FROM sys.objects WHERE object_id = OBJECT_ID(N'[dbo].[Web_PatchHistory]') AND type = N'U')
BEGIN
    CREATE TABLE dbo.Web_PatchHistory (
        PatchID             INT           IDENTITY(1,1) PRIMARY KEY,
        PatchFile           NVARCHAR(200) NOT NULL,
        PatchVersion        INT           NOT NULL,
        Description         NVARCHAR(200) NOT NULL,
        UploadMode          VARCHAR(10)   NOT NULL,   -- 'sah' | 'direct'
        FileSizeBytes       BIGINT        NOT NULL DEFAULT 0,
        HasDeleteLst        BIT           NOT NULL DEFAULT 0,
        ClientVersionBefore INT           NOT NULL DEFAULT 0,
        ClientVersionAfter  INT           NOT NULL DEFAULT 0,
        UploadedBy          NVARCHAR(100) NOT NULL,
        UploadedAt          DATETIME      NOT NULL DEFAULT GETDATE()
    );
    CREATE INDEX IX_Web_PatchHistory_Version
        ON dbo.Web_PatchHistory(PatchVersion DESC);
    PRINT 'Created table: Web_PatchHistory';
END
ELSE
    PRINT 'Table already exists: Web_PatchHistory';
GO
