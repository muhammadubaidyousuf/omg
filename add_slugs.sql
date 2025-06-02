-- Add slug column to videos table
ALTER TABLE videos ADD COLUMN IF NOT EXISTS slug VARCHAR(255) AFTER title;

-- Add slug column to categories table
ALTER TABLE categories ADD COLUMN IF NOT EXISTS slug VARCHAR(255) AFTER name;

-- Update category slugs
UPDATE categories SET slug = LOWER(REPLACE(name, ' ', '-')) WHERE slug IS NULL OR slug = '';

-- Update video slugs
UPDATE videos SET slug = LOWER(REPLACE(REPLACE(title, ' ', '-'), '.', '-')) WHERE slug IS NULL OR slug = '';

-- Add indexes for better performance
ALTER TABLE videos ADD INDEX idx_slug (slug);
ALTER TABLE categories ADD INDEX idx_slug (slug);
