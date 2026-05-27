-- ============================================
-- Password Hashing Migration Script
-- Expands Enpassword column for bcrypt/argon2
-- Execution: Run once on production
-- ============================================

USE [PS_UserData];
GO

-- Step 1: Alter Enpassword column to VARCHAR(255) to store bcrypt/argon2 hashes
-- bcrypt: 60 chars (default cost 10)
-- argon2id: ~95 chars (default params)
-- Using VARCHAR(255) for future compatibility

ALTER TABLE [dbo].[Users_Master]
ALTER COLUMN [Enpassword] VARCHAR(255) NULL;

-- Step 2: Add audit column to track migration status (optional, for reference)
-- This helps identify which accounts have been migrated
IF NOT EXISTS (
    SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_NAME = 'Users_Master' AND COLUMN_NAME = 'PasswordHashedAt'
)
BEGIN
    ALTER TABLE [dbo].[Users_Master]
    ADD [PasswordHashedAt] DATETIME NULL DEFAULT NULL;
END

-- Step 3: Create index on PasswordHashedAt for quick migration status checks (optional)
IF NOT EXISTS (
    SELECT 1 FROM sys.indexes WHERE name = 'IX_PasswordHashedAt'
)
BEGIN
    CREATE INDEX [IX_PasswordHashedAt] ON [dbo].[Users_Master]([PasswordHashedAt]);
END

-- Output message
PRINT 'Password hashing migration prepared successfully.';
PRINT 'Enpassword column expanded to VARCHAR(255).';
PRINT 'Users will be migrated on next login (on-login migration pattern).';
GO
