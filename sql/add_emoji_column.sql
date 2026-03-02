-- ════════════════════════════════════════════════════════════════
-- bloom-aura/sql/add_emoji_column.sql
-- Add emoji column to categories table for admin selection
-- Run once to update the database:
--   mysql -u root -p bloom_aura_db < add_emoji_column.sql
-- ════════════════════════════════════════════════════════════════

USE bloom_aura_db;

-- Add emoji column to categories if it doesn't exist
ALTER TABLE categories ADD COLUMN emoji VARCHAR(10) DEFAULT NULL AFTER description;

-- Optional: Add index for faster queries
ALTER TABLE categories ADD INDEX idx_emoji (emoji);

-- Update message
SELECT 'Emoji column added to categories table!' AS status;
