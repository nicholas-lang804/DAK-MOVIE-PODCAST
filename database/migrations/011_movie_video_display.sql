-- Allow admins to control where a free movie video appears in public video feeds.
USE tmz_podcast;

ALTER TABLE movies
    ADD COLUMN video_display ENUM('none', 'homepage', 'video_centre', 'both') NOT NULL DEFAULT 'video_centre' AFTER status;
