<?php
ob_start();
require_once __DIR__ . '/config/bootstrap.php';
startSecureSession();
require_once 'config/koneksi.php';
require_once 'fpdf/fpdf.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$ticket_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($ticket_id == 0) die('ID Tiket tidak valid');

$query = "SELECT t.*, u.nama as pelapor, u.username as username_pelapor,
          d.nama_divisi, tu.nama as teknisi_nama
          FROM tickets t
          LEFT JOIN users u ON t.user_id = u.id
          LEFT JOIN divisions d ON t.assigned_division_id = d.id
          LEFT JOIN users tu ON t.handled_by = tu.id
          WHERE t.id = $ticket_id";
$result = mysqli_query($conn, $query);
if (mysqli_num_rows($result) == 0) die('Tiket tidak ditemukan');
$ticket = mysqli_fetch_assoc($result);

$attachment_file  = $ticket['attachment']      ?? '';
$foto_perbaikan   = $ticket['foto_perbaikan']  ?? '';
$base_path        = __DIR__ . DIRECTORY_SEPARATOR;

// Authorization
$user_role = $_SESSION['role'];
$user_id   = $_SESSION['user_id'];
$user_div  = $_SESSION['division_id'] ?? null;
if ($user_role == 'user'    && $ticket['user_id']              != $user_id)  die('Akses ditolak');
if ($user_role == 'teknisi' && $ticket['assigned_division_id'] != $user_div) die('Akses ditolak');

// UTF-8 -> ISO-8859-1 helper
function enc($s) {
    return iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', (string)$s);
}

// ─── PDF CLASS ───────────────────────────────────────────────────────────────
class PDF extends FPDF
{
    var $ticket_no = '';
    var $print_date = '';

    function Header() {} // custom header via DrawPageHeader()

    function Footer() {
        $this->SetY(-13);
        $this->SetDrawColor(16, 54, 125);
        $this->SetLineWidth(0.3);
        $this->Line(12, $this->GetY(), 198, $this->GetY());
        $this->SetFont('Arial', 'I', 7.5);
        $this->SetTextColor(130, 130, 130);
        $this->Cell(0, 8, enc('Dokumen ini dicetak pada ' . $this->print_date .
            '  |  TVRI Sulawesi Utara - Sistem Ticketing  |  Halaman ' .
            $this->PageNo() . ' dari {nb}'), 0, 0, 'C');
    }

    // ── Dark header band ─────────────────────────────────────────────────────
    function DrawPageHeader($ticket_id, $logo_path) {
        $this->SetFillColor(16, 54, 125);
        $this->Rect(0, 0, 210, 36, 'F');

        // Logo (right side)
        if (file_exists($logo_path)) {
            $this->Image($logo_path, 158, 5, 0, 26);
        }

        // Title text (left side)
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 22);
        $this->SetXY(12, 7);
        $this->Cell(140, 12, enc('LAPORAN TIKET'), 0, 1, 'L');
        $this->SetFont('Arial', '', 8.5);
        $this->SetXY(12, 21);
        $this->Cell(140, 5,  enc('TVRI Sulawesi Utara  ·  Unit Layanan Teknis'), 0, 1, 'L');
        $this->SetFont('Arial', 'B', 8.5);
        $this->SetXY(12, 28);
        $this->Cell(80, 5, enc('No. Tiket: #' . str_pad($ticket_id, 4, '0', STR_PAD_LEFT)), 0, 0, 'L');
        $this->SetTextColor(0, 0, 0);
        $this->SetY(42);
    }

    // ── Metadata bar (2-column row of 4 cells) ───────────────────────────────
    function MetaBar($items) {
        $w = (210 - 24) / count($items);
        $x = 12;
        $y = $this->GetY();
        $h = 14;
        foreach ($items as $item) {
            $this->SetFillColor(240, 243, 250);
            $this->SetDrawColor(200, 210, 230);
            $this->SetLineWidth(0.1);
            $this->Rect($x, $y, $w - 1, $h, 'FD');
            $this->SetFont('Arial', '', 7);
            $this->SetTextColor(100, 110, 130);
            $this->SetXY($x + 2, $y + 1.5);
            $this->Cell($w - 3, 4, enc(strtoupper($item['label'])), 0, 0, 'L');
            $this->SetFont('Arial', 'B', 9);
            $this->SetTextColor(16, 54, 125);
            $this->SetXY($x + 2, $y + 7);
            $this->Cell($w - 3, 5, enc($item['value']), 0, 0, 'L');
            $x += $w;
        }
        $this->SetXY(12, $y + $h + 4);
        $this->SetTextColor(0, 0, 0);
    }

    // ── Status + Priority badges ──────────────────────────────────────────────
    function Badges($status, $priority) {
        $sc = ['Open'=>[220,53,69],'Assigned'=>[0,123,255],'In Progress'=>[255,152,0],'Resolved'=>[40,167,69]];
        $pc = ['Low'=>[108,117,125],'Medium'=>[0,123,255],'High'=>[255,152,0],'Urgent'=>[220,53,69]];
        $sc_r = isset($sc[$status])   ? $sc[$status]   : [90,90,90];
        $pc_r = isset($pc[$priority]) ? $pc[$priority] : [90,90,90];

        $this->SetFillColor($sc_r[0], $sc_r[1], $sc_r[2]);
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 8.5);
        $this->Cell(32, 7, enc('  ' . $status), 1, 0, 'L', true);
        $this->SetX($this->GetX() + 3);
        $this->SetFillColor($pc_r[0], $pc_r[1], $pc_r[2]);
        // High priority uses dark text for readability
        if ($priority == 'High') $this->SetTextColor(40, 40, 40);
        $this->Cell(32, 7, enc('  ' . $priority), 1, 1, 'L', true);
        $this->SetTextColor(0, 0, 0);
        $this->Ln(3);
    }

    // ── Section heading ───────────────────────────────────────────────────────
    function SectionTitle($title) {
        $this->SetFillColor(16, 54, 125);
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 8.5);
        $this->Cell(0, 7, enc('  ' . strtoupper($title)), 0, 1, 'L', true);
        $this->SetTextColor(0, 0, 0);
        $this->Ln(1);
    }

    // ── Two-column info row ────────────────────────────────────────────────────
    function InfoRow2($l1, $v1, $l2 = '', $v2 = '') {
        $hw = 91; // half width
        $this->SetFont('Arial', 'B', 8.5);
        $this->SetTextColor(100, 100, 100);
        $this->Cell(30, 6, enc($l1), 0, 0);
        $this->SetFont('Arial', '', 8.5);
        $this->SetTextColor(30, 30, 30);
        $this->Cell(61, 6, enc(': ' . $v1), 0, 0);
        if ($l2 !== '') {
            $this->SetFont('Arial', 'B', 8.5);
            $this->SetTextColor(100, 100, 100);
            $this->Cell(30, 6, enc($l2), 0, 0);
            $this->SetFont('Arial', '', 8.5);
            $this->SetTextColor(30, 30, 30);
            $this->Cell(61, 6, enc(': ' . $v2), 0, 1);
        } else {
            $this->Ln(6);
        }
    }

    // ── Full-width info row ───────────────────────────────────────────────────
    function InfoRow($label, $value) {
        $this->SetFont('Arial', 'B', 8.5);
        $this->SetTextColor(100, 100, 100);
        $this->Cell(40, 6, enc($label), 0, 0);
        $this->SetFont('Arial', '', 8.5);
        $this->SetTextColor(30, 30, 30);
        $this->Cell(0, 6, enc(': ' . $value), 0, 1);
    }

    // ── Divider line ──────────────────────────────────────────────────────────
    function Divider() {
        $this->SetDrawColor(220, 225, 235);
        $this->SetLineWidth(0.2);
        $this->Line(12, $this->GetY(), 198, $this->GetY());
        $this->Ln(3);
    }
}

// ─── BUILD PDF ───────────────────────────────────────────────────────────────
date_default_timezone_set('Asia/Makassar'); // WITA (UTC+8)
$pdf = new PDF('P', 'mm', 'A4');
$pdf->AliasNbPages();
$pdf->print_date = date('d/m/Y H:i:s');
$pdf->AddPage();
$pdf->SetMargins(12, 10, 12);
$pdf->SetAutoPageBreak(true, 22);

$logo_path = $base_path . 'assets/Logo_TVRI.svg.png';

// ── Page header ──
$pdf->DrawPageHeader($ticket['id'], $logo_path);

// ── Status & Priority badges ──
$pdf->SetX(12);
$pdf->Badges($ticket['status'], $ticket['priority']);

// ── Meta bar (4 tiles) ──
$pdf->MetaBar([
    ['label' => 'No. Tiket',       'value' => '#' . str_pad($ticket['id'],4,'0',STR_PAD_LEFT)],
    ['label' => 'Status',          'value' => $ticket['status']],
    ['label' => 'Prioritas',       'value' => $ticket['priority']],
    ['label' => 'Tanggal Lapor',   'value' => date('d/m/Y H:i', strtotime($ticket['created_at']))],
]);

// ── Section: Informasi Pelapor ──
$pdf->SectionTitle('Informasi Pelapor');
$pdf->InfoRow2('Nama Pelapor', $ticket['pelapor'], 'Username', $ticket['username_pelapor']);
$pdf->InfoRow2('Tanggal Lapor', date('d/m/Y H:i', strtotime($ticket['created_at'])), 'Terakhir Update', date('d/m/Y H:i', strtotime($ticket['updated_at'])));
$pdf->Ln(3);

// ── Section: Detail Tiket ──
$pdf->SectionTitle('Detail Tiket');
$pdf->InfoRow('Judul', $ticket['judul']);
$pdf->InfoRow('Lokasi', $ticket['lokasi'] ?? '-');
$pdf->InfoRow('Divisi Ditugaskan', $ticket['nama_divisi'] ?: '-');
$pdf->InfoRow('Teknisi Penanganan', $ticket['teknisi_nama'] ?: '-');
$pdf->Ln(3);

// ── Section: Deskripsi ──
$pdf->SectionTitle('Deskripsi Masalah');
$pdf->SetFont('Arial', '', 9);
$pdf->SetTextColor(40, 40, 40);
$pdf->SetX(12);
$pdf->MultiCell(186, 5, enc($ticket['deskripsi']), 0, 'L');
$pdf->Ln(3);

// ── Section: Catatan Teknisi ──
if (!empty($ticket['catatan_teknisi'])) {
    $pdf->SectionTitle('Catatan Teknisi');
    $pdf->SetFont('Arial', '', 9);
    $pdf->SetTextColor(40, 40, 40);
    $pdf->SetX(12);
    $pdf->MultiCell(186, 5, enc($ticket['catatan_teknisi']), 0, 'L');
    $pdf->Ln(3);
}

// ── Section: Foto Lampiran Pelapor ──
if (!empty($attachment_file)) {
    $img_path = $base_path . 'uploads/tickets/' . $attachment_file;
    if (file_exists($img_path)) {
        $sz = @getimagesize($img_path);
        if ($sz) {
            $pdf->SectionTitle('Foto Lampiran Pelapor');
            $r = min(186 / $sz[0], 100 / $sz[1]);
            $fw = $sz[0]*$r; $fh = $sz[1]*$r;
            $pdf->Image($img_path, 12, $pdf->GetY(), $fw, $fh);
            $pdf->Ln($fh + 5);
        }
    }
}

// ── Section: Foto Perbaikan ──
if (!empty($foto_perbaikan)) {
    $img_path = $base_path . 'uploads/perbaikan/' . $foto_perbaikan;
    if (file_exists($img_path)) {
        $sz = @getimagesize($img_path);
        if ($sz) {
            $pdf->SectionTitle('Foto Dokumentasi Perbaikan');
            $r = min(186 / $sz[0], 100 / $sz[1]);
            $fw = $sz[0]*$r; $fh = $sz[1]*$r;
            $pdf->Image($img_path, 12, $pdf->GetY(), $fw, $fh);
            $pdf->Ln($fh + 5);
        }
    }
}

ob_end_clean();
$pdf->Output('D', 'Tiket_' . str_pad($ticket['id'],4,'0',STR_PAD_LEFT) . '_' . date('Ymd') . '.pdf');
