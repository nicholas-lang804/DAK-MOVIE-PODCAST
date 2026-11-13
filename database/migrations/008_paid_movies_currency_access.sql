-- Paid movie access, user currency preference and payment gateway metadata.
USE tmz_podcast;

ALTER TABLE movies
    ADD COLUMN access_type ENUM('free','paid') NOT NULL DEFAULT 'free' AFTER status,
    ADD COLUMN price DECIMAL(12,2) NULL AFTER access_type,
    ADD COLUMN price_currency VARCHAR(3) NOT NULL DEFAULT 'USD' AFTER price;

ALTER TABLE users
    ADD COLUMN preferred_currency VARCHAR(3) NOT NULL DEFAULT 'USD' AFTER phone;

ALTER TABLE payments
    ADD COLUMN movie_id INT NULL AFTER media_id,
    ADD COLUMN currency VARCHAR(3) NOT NULL DEFAULT 'USD' AFTER amount,
    ADD COLUMN gateway VARCHAR(50) NOT NULL DEFAULT 'pesapal' AFTER payment_method,
    ADD COLUMN gateway_reference VARCHAR(255) NULL AFTER gateway,
    ADD COLUMN paid_at TIMESTAMP NULL AFTER created_at;

CREATE TABLE movie_access (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    movie_id INT NOT NULL,
    payment_id INT NULL,
    granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_movie_access (user_id, movie_id),
    CONSTRAINT fk_movie_access_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_movie_access_movie FOREIGN KEY (movie_id) REFERENCES movies(id) ON DELETE CASCADE,
    CONSTRAINT fk_movie_access_payment FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE SET NULL
);
