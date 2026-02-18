<?php
ob_start();
require_once __DIR__ . '/config/bootstrap.php';
startSecureSession();
require_once 'config/koneksi.php';
require_once 'fpdf/fpdf.php';

// Cek apakah user sudah login
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Ambil ID tiket
$ticket_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($ticket_id == 0) {
    die('ID Tiket tidak valid');
}

// Ambil data tiket
$query = "SELECT t.*, 
          u.nama as pelapor, u.username as username_pelapor,
          d.nama_divisi
          FROM tickets t
          LEFT JOIN users u ON t.user_id = u.id
          LEFT JOIN divisions d ON t.assigned_division_id = d.id
          WHERE t.id = $ticket_id";

$result = mysqli_query($conn, $query);

if (mysqli_num_rows($result) == 0) {
    die('Tiket tidak ditemukan');
}

$ticket = mysqli_fetch_assoc($result);

// Simpan nama file sebelum konversi encoding
$attachment_file = $ticket['attachment'] ?? '';
$foto_perbaikan_file = $ticket['foto_perbaikan'] ?? '';
$base_path = __DIR__ . DIRECTORY_SEPARATOR;

// Sanitize data untuk PDF - FPDF menggunakan ISO-8859-1, bukan UTF-8
foreach ($ticket as $key => $value) {
    if (is_string($value) && !empty($value)) {
        $ticket[$key] = iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $value);
    }
}

// Set default values untuk field yang mungkin kosong 
$ticket['nama_divisi'] = $ticket['nama_divisi'] ?? '';
$ticket['catatan_teknisi'] = $ticket['catatan_teknisi'] ?? '';

// Authorization check based on role
$user_role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];
$user_division = isset($_SESSION['division_id']) ? $_SESSION['division_id'] : null;

// User hanya bisa mencetak tiket miliknya
if ($user_role == 'user' && $ticket['user_id'] != $user_id) {
    die('Anda tidak memiliki akses untuk mencetak tiket ini');
}

// Teknisi hanya bisa mencetak tiket divisinya
if ($user_role == 'teknisi' && $ticket['assigned_division_id'] != $user_division) {
    die('Anda tidak memiliki akses untuk mencetak tiket ini');
}

// Buat PDF
class PDF extends FPDF
{
    function Header()
    {
        $this->SetFont('Arial','B',16);
        $this->Cell(0,10,'TVRI - Sistem Ticketing',0,1,'C');
        $this->SetFont('Arial','',10);
        $this->Cell(0,5,'Laporan Detail Tiket',0,1,'C');
        $this->Ln(5);
        $this->SetLineWidth(0.5);
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        $this->Ln(8);
    }
    
    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial','I',8);
        $this->Cell(0,10,'Dicetak pada: ' . date('d/m/Y H:i:s') . ' - Halaman ' . $this->PageNo(),0,0,'C');
    }
    
    function InfoRow($label, $value, $height = 6)
    {
        $this->SetFont('Arial','B',10);
        $this->Cell(40, $height, $label, 0, 0);
        $this->SetFont('Arial','',10);
        $this->Cell(0, $height, ': ' . $value, 0, 1);
    }
    
    function MultiCellRow($label, $value)
    {
        $this->SetFont('Arial','B',10);
        $this->Cell(40, 6, $label, 0, 1);
        $this->SetFont('Arial','',10);
        $x = $this->GetX();
        $y = $this->GetY();
        $this->MultiCell(0, 5, $value);
        $this->Ln(2);
    }
}

$pdf = new PDF();
$pdf->AddPage();

// Header Info
$pdf->SetFont('Arial','B',14);
$pdf->Cell(0, 10, 'Tiket #' . $ticket['id'], 0, 1);
$pdf->Ln(3);

// Status Badge Background
$status_colors = [
    'Open' => [255, 0, 0],
    'Assigned' => [0, 123, 255],
    'In Progress' => [255, 193, 7],
    'Resolved' => [40, 167, 69]
];

$color = isset($status_colors[$ticket['status']]) ? $status_colors[$ticket['status']] : [128, 128, 128];
$pdf->SetFillColor($color[0], $color[1], $color[2]);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('Arial','B',10);
$pdf->Cell(35, 7, 'Status: ' . $ticket['status'], 1, 0, 'C', true);

// Priority Badge
$priority_colors = [
    'Low' => [108, 117, 125],
    'Medium' => [0, 123, 255],
    'High' => [255, 193, 7],
    'Urgent' => [220, 53, 69]
];

$p_color = isset($priority_colors[$ticket['priority']]) ? $priority_colors[$ticket['priority']] : [128, 128, 128];
$pdf->SetFillColor($p_color[0], $p_color[1], $p_color[2]);
$pdf->Cell(35, 7, 'Priority: ' . $ticket['priority'], 1, 1, 'C', true);

$pdf->SetTextColor(0, 0, 0);
$pdf->Ln(5);

// Informasi Pelapor
$pdf->SetFont('Arial','B',12);
$pdf->Cell(0, 8, 'Informasi Pelapor', 0, 1);
$pdf->SetLineWidth(0.2);
$pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
$pdf->Ln(3);

$pdf->InfoRow('Nama Pelapor', $ticket['pelapor']);
$pdf->InfoRow('Username', $ticket['username_pelapor']);
$pdf->Ln(3);

// Informasi Tiket
$pdf->SetFont('Arial','B',12);
$pdf->Cell(0, 8, 'Detail Tiket', 0, 1);
$pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
$pdf->Ln(3);

$pdf->InfoRow('Judul', $ticket['judul']);
$pdf->InfoRow('Lokasi', $ticket['lokasi']);
$pdf->InfoRow('Tanggal Lapor', date('d/m/Y H:i', strtotime($ticket['created_at'])));

if (!empty($ticket['nama_divisi'])) {
    $pdf->InfoRow('Divisi Ditugaskan', $ticket['nama_divisi']);
}

$pdf->Ln(2);
$pdf->MultiCellRow('Deskripsi', $ticket['deskripsi']);

// Foto Lampiran Pelapor (jika ada)
if (!empty($attachment_file)) {
    $attachment_path = $base_path . 'uploads/tickets/' . $attachment_file;
    if (file_exists($attachment_path)) {
        $size = @getimagesize($attachment_path);
        if ($size) {
            $pdf->Ln(3);
            $pdf->SetFont('Arial','B',12);
            $pdf->Cell(0, 8, 'Foto Lampiran Pelapor', 0, 1);
            $pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
            $pdf->Ln(3);
            
            $max_width = 180;
            $max_height = 100;
            $img_width = $size[0];
            $img_height = $size[1];
            $ratio = min($max_width / $img_width, $max_height / $img_height);
            $final_width = $img_width * $ratio;
            $final_height = $img_height * $ratio;
            
            $pdf->Image($attachment_path, 15, $pdf->GetY(), $final_width, $final_height);
            $pdf->Ln($final_height + 5);
        }
    }
}

// Catatan Teknisi (jika ada)
if (!empty($ticket['catatan_teknisi'])) {
    $pdf->SetFont('Arial','B',12);
    $pdf->Cell(0, 8, 'Catatan Teknisi', 0, 1);
    $pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
    $pdf->Ln(3);
    $pdf->SetFont('Arial','',10);
    $pdf->MultiCell(0, 5, $ticket['catatan_teknisi']);
    $pdf->Ln(2);
}

// Foto Perbaikan Teknisi (jika ada)
if (!empty($foto_perbaikan_file)) {
    $perbaikan_path = $base_path . 'uploads/perbaikan/' . $foto_perbaikan_file;
    if (file_exists($perbaikan_path)) {
        $size = @getimagesize($perbaikan_path);
        if ($size) {
            $pdf->Ln(3);
            $pdf->SetFont('Arial','B',12);
            $pdf->Cell(0, 8, 'Foto Dokumentasi Perbaikan', 0, 1);
            $pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
            $pdf->Ln(3);
            
            $max_width = 180;
            $max_height = 100;
            $img_width = $size[0];
            $img_height = $size[1];
            $ratio = min($max_width / $img_width, $max_height / $img_height);
            $final_width = $img_width * $ratio;
            $final_height = $img_height * $ratio;
            
            $pdf->Image($perbaikan_path, 15, $pdf->GetY(), $final_width, $final_height);
            $pdf->Ln($final_height + 5);
        }
    }
}

// Timeline
$pdf->Ln(3);
$pdf->SetFont('Arial','B',12);
$pdf->Cell(0, 8, 'Timeline', 0, 1);
$pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
$pdf->Ln(3);

$pdf->InfoRow('Dibuat', date('d/m/Y H:i:s', strtotime($ticket['created_at'])));
$pdf->InfoRow('Terakhir Diupdate', date('d/m/Y H:i:s', strtotime($ticket['updated_at'])));

ob_end_clean();

// Output PDF
$pdf->Output('D', 'Tiket_' . $ticket['id'] . '_' . date('Ymd') . '.pdf');

