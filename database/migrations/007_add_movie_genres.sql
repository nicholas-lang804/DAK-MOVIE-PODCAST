-- Connect movies to the existing movie categories/genres.
USE tmz_podcast;

CREATE TABLE movie_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    movie_id INT NOT NULL,
    category_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_movie_category (movie_id, category_id),
    CONSTRAINT fk_movie_categories_movie FOREIGN KEY (movie_id) REFERENCES movies(id) ON DELETE CASCADE,
    CONSTRAINT fk_movie_categories_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
);
