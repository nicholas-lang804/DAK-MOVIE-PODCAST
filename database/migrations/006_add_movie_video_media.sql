-- Link a movie to one uploaded video from the media library.
USE tmz_podcast;

ALTER TABLE movies
    ADD COLUMN video_media_id INT NULL AFTER poster_media_id;

ALTER TABLE movies
    ADD CONSTRAINT fk_movies_video_media
    FOREIGN KEY (video_media_id) REFERENCES media_assets(id)
    ON DELETE SET NULL;
