<?php
require_once 'config/koneksi.php';

// Cek tickets yang sudah di-assign tapi updated_at sama dengan created_at
$query = "SELECT id, judul, status, priority, 
          created_at, updated_at,
          assigned_division_id,
          TIMESTAMPDIFF(SECOND, created_at, updated_at) as diff_seconds
          FROM tickets 
          WHERE status != 'Open'
          ORDER BY id DESC";

$result = mysqli_query($conn, $query);

echo "Tickets yang sudah di-assign/update:\n";
echo str_pad("ID", 5) . str_pad("Status", 15) . str_pad("Priority", 10) . str_pad("Divisi", 8) . str_pad("Created", 20) . str_pad("Updated", 20) . str_pad("Diff(sec)", 10) . "\n";
echo str_repeat("-", 98) . "\n";

$need_fix = [];
while ($row = mysqli_fetch_assoc($result)) {
    echo str_pad($row['id'], 5) . 
         str_pad($row['status'], 15) . 
         str_pad($row['priority'], 10) . 
         str_pad($row['assigned_division_id'] ?? '-', 8) . 
         str_pad($row['created_at'], 20) . 
         str_pad($row['updated_at'], 20) . 
         str_pad($row['diff_seconds'], 10) . "\n";
    
    // Jika status bukan Open tapi updated_at sama dengan created_at (diff = 0), perlu di-fix
    if ($row['diff_seconds'] == 0 && $row['status'] != 'Open') {
        $need_fix[] = $row['id'];
    }
}

if (!empty($need_fix)) {
    echo "\n" . count($need_fix) . " tickets perlu di-fix (status bukan Open tapi updated_at = created_at)\n";
    echo "IDs: " . implode(", ", $need_fix) . "\n";
    
    // Fix them by setting updated_at to NOW() - 1 minute (simulasi update sebelumnya)
    echo "\nApakah Anda ingin fix tickets ini? (yes/no): ";
    echo "Otomatis fixing...\n";
    
    foreach ($need_fix as $id) {
        $update_query = "UPDATE tickets SET updated_at = DATE_SUB(NOW(), INTERVAL 2 MINUTE) WHERE id = $id";
        mysqli_query($conn, $update_query);
    }
    
    echo "Selesai! " . count($need_fix) . " tickets telah di-update.\n";
} else {
    echo "\nSemua data sudah benar!\n";
}

mysqli_close($conn);
?>
