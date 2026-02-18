<?php
// Script untuk memperbaiki data updated_at yang kosong
require_once 'config/koneksi.php';

// Update tickets yang updated_at nya NULL atau kosong
$query = "UPDATE tickets SET updated_at = created_at WHERE updated_at IS NULL OR updated_at = '0000-00-00 00:00:00'";
$result = mysqli_query($conn, $query);

if ($result) {
    $affected = mysqli_affected_rows($conn);
    echo "Berhasil update $affected tickets dengan updated_at = created_at\n";
} else {
    echo "Error: " . mysqli_error($conn) . "\n";
}

// Verifikasi
$query_check = "SELECT id, judul, created_at, updated_at FROM tickets ORDER BY id DESC LIMIT 10";
$result_check = mysqli_query($conn, $query_check);

echo "\nSample 10 latest tickets:\n";
echo str_pad("ID", 5) . str_pad("Judul", 30) . str_pad("Created", 20) . str_pad("Updated", 20) . "\n";
echo str_repeat("-", 75) . "\n";

while ($row = mysqli_fetch_assoc($result_check)) {
    echo str_pad($row['id'], 5) . 
         str_pad(substr($row['judul'], 0, 28), 30) . 
         str_pad($row['created_at'], 20) . 
         str_pad($row['updated_at'], 20) . "\n";
}

mysqli_close($conn);
?>
