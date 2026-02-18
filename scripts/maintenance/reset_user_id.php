<?php
require_once 'config/koneksi.php';

echo "=== Script Reset User ID ===\n\n";

// 1. Cek data users yang ada
$query = "SELECT id, nama, username, role FROM users ORDER BY id";
$result = mysqli_query($conn, $query);

echo "Users saat ini:\n";
while ($row = mysqli_fetch_assoc($result)) {
    echo "ID: {$row['id']} - {$row['nama']} ({$row['username']}) - {$row['role']}\n";
}

// 2. Cek apakah ada referensi ke user_id 7 di tabel tickets
$query_tickets = "SELECT COUNT(*) as total FROM tickets WHERE user_id = 7";
$result_tickets = mysqli_query($conn, $query_tickets);
$tickets_count = mysqli_fetch_assoc($result_tickets)['total'];

echo "\nTickets dengan user_id = 7: $tickets_count\n";

// 3. Cek apakah ada referensi ke user_id 7 di tabel ticket_logs
$query_logs = "SELECT COUNT(*) as total FROM ticket_logs WHERE user_id = 7";
$result_logs = mysqli_query($conn, $query_logs);
$logs_count = mysqli_fetch_assoc($result_logs)['total'];

echo "Ticket logs dengan user_id = 7: $logs_count\n\n";

// 4. Mulai transaction untuk keamanan
mysqli_begin_transaction($conn);

try {
    // Update semua referensi dari user_id 7 ke temporary ID (9999)
    echo "Step 1: Update referensi ke temporary ID...\n";
    
    if ($tickets_count > 0) {
        mysqli_query($conn, "UPDATE tickets SET user_id = 9999 WHERE user_id = 7");
        echo "  - Updated $tickets_count tickets\n";
    }
    
    if ($logs_count > 0) {
        mysqli_query($conn, "UPDATE ticket_logs SET user_id = 9999 WHERE user_id = 7");
        echo "  - Updated $logs_count ticket logs\n";
    }
    
    // Update user ID dari 7 ke 2
    echo "\nStep 2: Update user ID 7 menjadi ID 2...\n";
    mysqli_query($conn, "UPDATE users SET id = 2 WHERE id = 7");
    echo "  - User ID updated\n";
    
    // Update kembali referensi dari temporary ID ke ID 2
    echo "\nStep 3: Update referensi dari temporary ID ke ID 2...\n";
    
    if ($tickets_count > 0) {
        mysqli_query($conn, "UPDATE tickets SET user_id = 2 WHERE user_id = 9999");
        echo "  - Updated tickets\n";
    }
    
    if ($logs_count > 0) {
        mysqli_query($conn, "UPDATE ticket_logs SET user_id = 2 WHERE user_id = 9999");
        echo "  - Updated ticket logs\n";
    }
    
    // Reset AUTO_INCREMENT ke 3 (ID berikutnya setelah 2)
    echo "\nStep 4: Reset AUTO_INCREMENT ke 3...\n";
    mysqli_query($conn, "ALTER TABLE users AUTO_INCREMENT = 3");
    echo "  - AUTO_INCREMENT reset\n";
    
    // Commit transaction
    mysqli_commit($conn);
    
    echo "\n SUCCESS! User ID berhasil direset.\n\n";
    
    // Tampilkan hasil akhir
    echo "Users setelah direset:\n";
    $query = "SELECT id, nama, username, role FROM users ORDER BY id";
    $result = mysqli_query($conn, $query);
    while ($row = mysqli_fetch_assoc($result)) {
        echo "ID: {$row['id']} - {$row['nama']} ({$row['username']}) - {$row['role']}\n";
    }
    
} catch (Exception $e) {
    mysqli_rollback($conn);
    echo "\n ERROR: " . $e->getMessage() . "\n";
    echo "Transaction rolled back.\n";
}

mysqli_close($conn);
?>
