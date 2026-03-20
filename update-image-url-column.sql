-- Update image_url column to TEXT to support multiple images
ALTER TABLE products MODIFY COLUMN image_url TEXT COMMENT 'Multiple images separated by |';
