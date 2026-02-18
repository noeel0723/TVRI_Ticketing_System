-- Pilih database yang akan digunakan
USE db_ticketing;

-- Tambahkan tabel ticket_logs untuk tracking riwayat
CREATE TABLE IF NOT EXISTS ticket_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    user_id INT NULL,
    action VARCHAR(50) NOT NULL,
    old_value TEXT,
    new_value TEXT,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Index untuk performa
CREATE INDEX idx_ticket_logs_ticket_id ON ticket_logs(ticket_id);
CREATE INDEX idx_ticket_logs_created_at ON ticket_logs(created_at DESC);

