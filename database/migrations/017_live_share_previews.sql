-- Add a dedicated cover image for public live-show link previews.
USE tmz_podcast;

ALTER TABLE live_podcasts
    ADD COLUMN cover_image_path VARCHAR(500) NULL AFTER description;
