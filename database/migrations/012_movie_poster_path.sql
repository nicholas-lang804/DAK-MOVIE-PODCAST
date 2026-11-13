-- Keep movie posters attached to each movie instead of the shared media library.
USE tmz_podcast;

ALTER TABLE movies
    ADD COLUMN poster_path VARCHAR(500) NULL AFTER poster_media_id;
