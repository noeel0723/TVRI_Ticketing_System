<?php
require_once '../config/auth.php';
checkRole('admin');
require_once '../config/koneksi.php';
require_once '../config/logger.php';
require_once '../config/email_helper.php';

$user = getUserInfo();

// Cek apakah form disubmit
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // Ambil data dari form
    $ticket_id = isset($_POST['ticket_id']) ? intval($_POST['ticket_id']) : 0;
    $division_id = isset($_POST['division_id']) ? intval($_POST['division_id']) : 0;
    $priority = isset($_POST['priority']) ? trim($_POST['priority']) : '';
    
    // Validasi input
    if ($ticket_id == 0 || $division_id == 0 || empty($priority)) {
        header('Location: assign.php?id=' . $ticket_id . '&error=empty');
        exit();
    }
    
    // Validasi priority
    $valid_priorities = ['Low', 'Medium', 'High', 'Urgent'];
    if (!in_array($priority, $valid_priorities)) {
        header('Location: assign.php?id=' . $ticket_id . '&error=invalid_priority');
        exit();
    }
    
    // Escape data
    $priority = mysqli_real_escape_string($conn, $priority);
    
    // Update tiket
    $query = "UPDATE tickets 
              SET assigned_division_id = $division_id,
                  status = 'Assigned',
                  priority = '$priority',
                  updated_at = NOW()
              WHERE id = $ticket_id";
    
    $result = mysqli_query($conn, $query);
    
    if ($result) {
        // Ambil nama divisi
        $div_query = "SELECT nama_divisi FROM divisions WHERE id = $division_id";
        $div_result = mysqli_query($conn, $div_query);
        $division_name = mysqli_fetch_assoc($div_result)['nama_divisi'];
        
        // Log aktivitas
        logTicketActivity($conn, $ticket_id, $user['id'], 'assign', 'Open', 'Assigned', "Tiket di-assign ke divisi $division_name dengan priority $priority");
        
        // Kirim email notifikasi ke teknisi divisi terkait
        // Send email notification to all teknisi in the division
        $teknisiQuery = mysqli_query($conn, "SELECT id, nama, email FROM users WHERE role = 'teknisi' AND division_id = $division_id AND email IS NOT NULL AND email != ''");
        $admin_id = $user['id'];
        while ($teknisi = mysqli_fetch_assoc($teknisiQuery)) {
            notifyAssignment($conn, $ticket_id, $teknisi['id'], $admin_id);
        }
        
        // Berhasil - redirect ke dashboard dengan notifikasi
        header('Location: dashboard.php?success=assign&id=' . $ticket_id);
        exit();
    } else {
        // Gagal
        header('Location: assign.php?id=' . $ticket_id . '&error=gagal');
        exit();
    }
    
} else {
    // Jika diakses langsung tanpa POST
    header('Location: dashboard.php');
    exit();
}
?>