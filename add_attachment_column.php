<?php
require_once 'config/koneksi.php';

$sql = "ALTER TABLE tickets ADD COLUMN attachment VARCHAR(255) NULL AFTER deskripsi";
$result = mysqli_query($conn, $sql);

if ($result) {
    echo "SUCCESS: Kolom attachment berhasil ditambahkan ke tabel tickets\n";
} else {
    echo "ERROR: " . mysqli_error($conn) . "\n";
}
?>
