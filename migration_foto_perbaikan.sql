-- Migration: Add foto_perbaikan column to tickets table
-- Date: February 10, 2026
-- Description: Add column to store repair/fix photo uploaded by teknisi

-- Check if column doesn't exist before adding
ALTER TABLE tickets 
ADD COLUMN IF NOT EXISTS foto_perbaikan VARCHAR(255) NULL 
AFTER attachment;

-- Note: If your MySQL version doesn't support IF NOT EXISTS, use this instead:
-- ALTER TABLE tickets ADD COLUMN foto_perbaikan VARCHAR(255) NULL AFTER attachment;