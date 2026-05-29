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
-- 5. Web_DropsEditLog
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
