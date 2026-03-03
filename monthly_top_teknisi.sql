-- ================================================
-- Tabel: monthly_top_teknisi
-- Menyimpan snapshot top teknisi setiap bulan
-- Diisi otomatis setiap awal bulan baru saat admin
-- membuka dashboard (lazy monthly snapshot).
-- ================================================

USE db_ticketing;

CREATE TABLE IF NOT EXISTS monthly_top_teknisi (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    teknisi_id    INT           DEFAULT NULL,
    teknisi_nama  VARCHAR(100)  NOT NULL,
    teknisi_foto  VARCHAR(255)  DEFAULT NULL,
    bulan         TINYINT       NOT NULL COMMENT '1-12',
    tahun         SMALLINT      NOT NULL,
    rank_position TINYINT       NOT NULL COMMENT '1 = peringkat 1, dst',
    resolved_count INT          NOT NULL DEFAULT 0,
    snapshotted_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY unique_rank_per_month (bulan, tahun, rank_position),
    KEY teknisi_id (teknisi_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Snapshot peringkat teknisi bulanan';
