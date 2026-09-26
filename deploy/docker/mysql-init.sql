-- Runs once, on first start of the local `mysql` compose service.
-- The main database (auto_ride_db) comes from MYSQL_DATABASE; this adds the
-- one phpunit.xml uses, so tests never touch a shared server.
CREATE DATABASE IF NOT EXISTS auto_ride_db_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON auto_ride_db_testing.* TO 'auto_ride'@'%';
FLUSH PRIVILEGES;
