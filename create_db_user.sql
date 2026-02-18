-- SQL helper to create an application DB user 'tvri'
-- Import this in phpMyAdmin or run in MySQL shell (XAMPP Shell)

-- Replace 'GantiPassword123!' with a strong password you choose
CREATE USER IF NOT EXISTS 'tvri'@'127.0.0.1' IDENTIFIED BY 'GantiPassword123!';
GRANT ALL PRIVILEGES ON db_ticketing.* TO 'tvri'@'127.0.0.1';

CREATE USER IF NOT EXISTS 'tvri'@'localhost' IDENTIFIED BY 'GantiPassword123!';
GRANT ALL PRIVILEGES ON db_ticketing.* TO 'tvri'@'localhost';

FLUSH PRIVILEGES;

-- Optional: show grants
-- SELECT user, host FROM mysql.user WHERE user = 'tvri';
