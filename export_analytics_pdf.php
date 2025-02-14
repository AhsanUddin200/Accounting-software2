<?php
require_once 'session.php';
require_once 'db.php';
require_once 'vendor/autoload.php'; // For TCPDF

// Get parameters
$start_date = $_GET['from_date'] ?? date('Y-m-01');
$end_date = $_GET['to_date'] ?? date('Y-m-t');
$cost_center_id = $_GET['cost_center_id'] ?? null;

// Create new PDF document
$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);

// Set document information
$pdf->SetCreator('Financial Management System');
$pdf->SetAuthor('Your Company Name');
$pdf->SetTitle('Financial Analytics Report');

// Remove default header/footer
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

// Set margins
$pdf->SetMargins(15, 15, 15);

// Add a page
$pdf->AddPage();

// Set default font
$pdf->SetFont('helvetica', 'B', 20);

// Add company logo if exists
// $pdf->Image('path/to/logo.png', 15, 15, 30);

// Title
$pdf->Cell(0, 10, 'Financial Management System', 0, 1, 'C');
$pdf->SetFont('helvetica', '', 12);
$pdf->Cell(0, 10, 'Analytics Report', 0, 1, 'C');

// Add horizontal line
$pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
$pdf->Ln(5);

// Report Period
$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell(0, 10, 'Report Period: ' . date('d M Y', strtotime($start_date)) . ' to ' . date('d M Y', strtotime($end_date)), 0, 1, 'R');

// If cost center is specified, get its details
if ($cost_center_id) {
    $cost_center_query = "SELECT code, name FROM cost_centers WHERE id = ?";
    $stmt = $conn->prepare($cost_center_query);
    $stmt->bind_param("i", $cost_center_id);
    $stmt->execute();
    $cost_center = $stmt->get_result()->fetch_assoc();
    if ($cost_center) {
        $pdf->Cell(0, 10, 'Cost Center: ' . $cost_center['code'] . ' - ' . $cost_center['name'], 0, 1, 'R');
    }
}

$pdf->Ln(5);

// Get summary data
$where_clause = " WHERE t.date BETWEEN ? AND ?";
if ($cost_center_id) {
    $where_clause .= " AND t.cost_center_id = ?";
}

// Financial Summary Section
$summary_query = "SELECT 
    COUNT(*) as total_transactions,
    SUM(CASE WHEN t.type = 'income' THEN t.amount ELSE 0 END) as total_income,
    SUM(CASE WHEN t.type = 'expense' THEN t.amount ELSE 0 END) as total_expense,
    SUM(CASE WHEN t.type = 'asset' THEN t.amount ELSE 0 END) as total_assets,
    SUM(CASE WHEN t.type = 'liability' THEN t.amount ELSE 0 END) as total_liabilities,
    AVG(t.amount) as average_amount
    FROM transactions t" . $where_clause;

$stmt = $conn->prepare($summary_query);
if ($cost_center_id) {
    $stmt->bind_param("ssi", $start_date, $end_date, $cost_center_id);
} else {
    $stmt->bind_param("ss", $start_date, $end_date);
}
$stmt->execute();
$summary = $stmt->get_result()->fetch_assoc();

// Financial Summary Box
$pdf->SetFillColor(240, 240, 240);
$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(0, 10, 'Financial Summary', 0, 1, 'L');
$pdf->SetFont('helvetica', '', 11);

// Create a table-like structure for summary
$pdf->SetFillColor(245, 245, 245);
$pdf->Cell(95, 8, 'Total Transactions:', 1, 0, 'L', true);
$pdf->Cell(85, 8, number_format($summary['total_transactions']), 1, 1, 'R', true);

$pdf->Cell(95, 8, 'Total Income:', 1, 0, 'L');
$pdf->Cell(85, 8, 'PKR ' . number_format($summary['total_income'], 2), 1, 1, 'R');

$pdf->Cell(95, 8, 'Total Expenses:', 1, 0, 'L', true);
$pdf->Cell(85, 8, 'PKR ' . number_format($summary['total_expense'], 2), 1, 1, 'R', true);

$pdf->Cell(95, 8, 'Net Position:', 1, 0, 'L');
$net_position = $summary['total_income'] - $summary['total_expense'];
$pdf->Cell(85, 8, 'PKR ' . number_format($net_position, 2), 1, 1, 'R');

$pdf->Cell(95, 8, 'Total Assets:', 1, 0, 'L', true);
$pdf->Cell(85, 8, 'PKR ' . number_format($summary['total_assets'], 2), 1, 1, 'R', true);

$pdf->Cell(95, 8, 'Total Liabilities:', 1, 0, 'L');
$pdf->Cell(85, 8, 'PKR ' . number_format($summary['total_liabilities'], 2), 1, 1, 'R');

$pdf->Ln(10);

// Transaction Analysis Section
$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(0, 10, 'Transaction Analysis', 0, 1, 'L');
$pdf->SetFont('helvetica', '', 11);

// Get transaction types data
$types_query = "SELECT 
    t.type,
    COUNT(*) as count,
    SUM(t.amount) as total_amount,
    AVG(t.amount) as average_amount
    FROM transactions t" . $where_clause . "
    GROUP BY t.type
    ORDER BY total_amount DESC";

$stmt = $conn->prepare($types_query);
if ($cost_center_id) {
    $stmt->bind_param("ssi", $start_date, $end_date, $cost_center_id);
} else {
    $stmt->bind_param("ss", $start_date, $end_date);
}
$stmt->execute();
$types = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Table header with background color
$pdf->SetFillColor(52, 73, 94);
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell(45, 10, 'Transaction Type', 1, 0, 'C', true);
$pdf->Cell(30, 10, 'Count', 1, 0, 'C', true);
$pdf->Cell(50, 10, 'Total Amount', 1, 0, 'C', true);
$pdf->Cell(55, 10, 'Average Amount', 1, 1, 'C', true);

// Reset text color
$pdf->SetTextColor(0, 0, 0);

// Table data with alternating background
$fill = false;
foreach ($types as $type) {
    $pdf->SetFillColor(245, 245, 245);
    $pdf->Cell(45, 8, ucfirst($type['type']), 1, 0, 'L', $fill);
    $pdf->Cell(30, 8, number_format($type['count']), 1, 0, 'C', $fill);
    $pdf->Cell(50, 8, 'PKR ' . number_format($type['total_amount'], 2), 1, 0, 'R', $fill);
    $pdf->Cell(55, 8, 'PKR ' . number_format($type['average_amount'], 2), 1, 1, 'R', $fill);
    $fill = !$fill;
}

// Add footer note
$pdf->Ln(10);
$pdf->SetFont('helvetica', 'I', 8);
$pdf->Cell(0, 10, 'Report generated on ' . date('d M Y H:i:s'), 0, 1, 'R');

// Output PDF
$pdf->Output('financial_analytics_report.pdf', 'I'); 