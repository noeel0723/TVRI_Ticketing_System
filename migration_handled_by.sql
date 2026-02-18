-- ================================================
-- MIGRATION: Tambah kolom handled_by ke tabel tickets
-- ================================================
-- Kolom ini menyimpan user_id dari teknisi yang menangani tiket
-- Digunakan untuk menampilkan teknisi yang tepat di detail page
-- ================================================

USE db_ticketing;

-- Tambah kolom handled_by
ALTER TABLE tickets 
ADD COLUMN handled_by INT DEFAULT NULL AFTER assigned_division_id,
ADD FOREIGN KEY (handled_by) REFERENCES users(id) ON DELETE SET NULL;

-- Update existing tickets: set handled_by ke teknisi pertama dari assigned_division untuk tiket yang sudah In Progress atau Resolved
UPDATE tickets t
INNER JOIN (
    SELECT t.id as ticket_id, MIN(u.id) as first_teknisi_id
    FROM tickets t
    LEFT JOIN users u ON u.division_id = t.assigned_division_id AND u.role = 'teknisi'
    WHERE t.assigned_division_id IS NOT NULL 
    AND t.status IN ('In Progress', 'Resolved')
    GROUP BY t.id
) as subq ON t.id = subq.ticket_id
SET t.handled_by = subq.first_teknisi_id
WHERE t.handled_by IS NULL;

SELECT 'Migration completed: handled_by column added to tickets table' AS status;
