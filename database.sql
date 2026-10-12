-- MySQL 8+; select/create your database before importing this file.
CREATE TABLE IF NOT EXISTS users (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(100) NOT NULL,
 email VARCHAR(190) NOT NULL UNIQUE,
 password_hash VARCHAR(255) NOT NULL,
 role ENUM('customer','staff','admin') NOT NULL DEFAULT 'customer',
 active TINYINT(1) NOT NULL DEFAULT 1,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS tour_packages (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 title VARCHAR(150) NOT NULL,
 destination VARCHAR(100) NOT NULL,
 category VARCHAR(50) NOT NULL,
 description TEXT NOT NULL,
 itinerary TEXT NOT NULL,
 inclusions TEXT NOT NULL,
 duration_days SMALLINT UNSIGNED NOT NULL,
 capacity SMALLINT UNSIGNED NOT NULL,
 price_cents BIGINT UNSIGNED NOT NULL,
 image_url VARCHAR(1000) NOT NULL,
 active TINYINT(1) NOT NULL DEFAULT 1,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX package_active (active, category),
 CONSTRAINT valid_duration CHECK (duration_days BETWEEN 1 AND 90),
 CONSTRAINT valid_capacity CHECK (capacity BETWEEN 1 AND 100)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS bookings (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL,
 package_id BIGINT UNSIGNED NOT NULL,
 package_title VARCHAR(150) NOT NULL,
 travel_date DATE NOT NULL,
 travellers SMALLINT UNSIGNED NOT NULL,
 unit_price_cents BIGINT UNSIGNED NOT NULL,
 total_cents BIGINT UNSIGNED NOT NULL,
 status ENUM('pending','confirmed','cancelled','completed') NOT NULL DEFAULT 'pending',
 request_token CHAR(64) NOT NULL UNIQUE,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
 FOREIGN KEY (package_id) REFERENCES tour_packages(id) ON DELETE RESTRICT,
 INDEX booking_capacity (package_id,travel_date,status),
 INDEX booking_owner (user_id,created_at),
 CONSTRAINT valid_travellers CHECK (travellers BETWEEN 1 AND 100)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS queries (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL,
 subject VARCHAR(150) NOT NULL,
 message TEXT NOT NULL,
 response TEXT NULL,
 responded_by BIGINT UNSIGNED NULL,
 responded_at TIMESTAMP NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
 FOREIGN KEY (responded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS login_attempts (
 attempt_key CHAR(64) PRIMARY KEY,
 attempts INT UNSIGNED NOT NULL DEFAULT 0,
 window_start BIGINT NOT NULL
) ENGINE=InnoDB;
