<?php
/**
 * Bulk Delete Tickets
 * POST: bulk_action=delete, ticket_ids[]=...
 */
require_once '../config/auth.php';
checkRole(['admin','user','teknisi']);
require_once '../config/koneksi.php';
require_once '../config/logger.php';

$user = getUserInfo();
$role = $user['role'];
$dashboard = '../' . $role . '/dashboard.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $dashboard);
    exit;
}

$action     = isset($_POST['bulk_action']) ? trim($_POST['bulk_action']) : '';
$ticket_ids = isset($_POST['ticket_ids']) ? array_map('intval', (array)$_POST['ticket_ids']) : [];
$ticket_ids = array_filter($ticket_ids, function($v){ return $v > 0; });

if (empty($action) || empty($ticket_ids)) {
    header('Location: ' . $dashboard . '?error=bulk_empty');
    exit;
}

function tableExists($conn, $tableName) {
    $tableName = mysqli_real_escape_string($conn, $tableName);
    $check = mysqli_query($conn, "SHOW TABLES LIKE '$tableName'");
    return $check && mysqli_num_rows($check) > 0;
}

$relatedTables = [];
foreach (['ticket_history', 'ticket_assignments', 'ticket_attachments', 'ticket_activities', 'ticket_comments'] as $table) {
    if (tableExists($conn, $table)) {
        $relatedTables[] = $table;
    }
}

$success_count = 0;
$fail_count    = 0;
$ids_csv       = implode(',', $ticket_ids);
$user_id       = (int)$user['id'];

if ($action === 'delete') {
    if ($role === 'admin') {
        $sql = "SELECT id, judul, status FROM tickets WHERE id IN ($ids_csv)";
    } elseif ($role === 'user') {
        $sql = "SELECT id, judul, status FROM tickets WHERE id IN ($ids_csv) AND user_id = $user_id AND status = 'Resolved'";
    } else {
        $division_id = (int)$user['division_id'];
        $sql = "SELECT id, judul, status FROM tickets WHERE id IN ($ids_csv) AND assigned_division_id = $division_id AND status = 'Resolved'";
    }

    $result = mysqli_query($conn, $sql);
    if (!$result) {
        header('Location: ' . $dashboard . '?error=bulk_query_failed');
        exit;
    }

    while ($row = mysqli_fetch_assoc($result)) {
        $tid = (int)$row['id'];
        $judul = $row['judul'];
        $status = $row['status'];
        // Log activity BEFORE deleting (to avoid foreign key constraint error)
        logTicketActivity($conn, $tid, $user['id'], 'ticket_deleted', $status, 'Deleted', 'Bulk delete tiket #' . $tid . ': ' . $judul);
        foreach ($relatedTables as $table) {
            mysqli_query($conn, "DELETE FROM $table WHERE ticket_id = $tid");
        }

        if ($role === 'admin') {
            $del = mysqli_query($conn, "DELETE FROM tickets WHERE id = $tid");
        } elseif ($role === 'user') {
            $del = mysqli_query($conn, "DELETE FROM tickets WHERE id = $tid AND user_id = $user_id");
        } else {
            $division_id = (int)$user['division_id'];
            $del = mysqli_query($conn, "DELETE FROM tickets WHERE id = $tid AND assigned_division_id = $division_id");
        }

        if ($del && mysqli_affected_rows($conn) > 0) {
            $success_count++;
        } else {
            $fail_count++;
        }
    }

    header('Location: ' . $dashboard . "?success=bulk_delete&count=$success_count&fail=$fail_count");
    exit;
}

header('Location: ' . $dashboard . '?error=bulk_invalid_action');
exit;