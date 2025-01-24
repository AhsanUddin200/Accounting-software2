<?php
require_once 'session.php';
require_once 'db.php';
require_once 'functions.php';

// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Get date range
$from_date = $_GET['from_date'] ?? date('Y-m-01');
$to_date = $_GET['to_date'] ?? date('Y-m-t');

try {
    // Set headers for CSV download
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="trial_balance_' . date('Y-m-d') . '.csv"');
    
    // Create output stream
    $output = fopen('php://output', 'w');
    
    // Add UTF-8 BOM for Excel
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    // Add heading
    fputcsv($output, ['', '', '', 'Financial Management System'], ',', '"');
    fputcsv($output, ['', '', '', 'Trial Balance'], ',', '"');
    fputcsv($output, ['', '', '', 'Period:', date('F d, Y', strtotime($from_date)) . ' - ' . date('F d, Y', strtotime($to_date))], ',', '"');
    fputcsv($output, []); // Empty line

    // Headers
    fputcsv($output, ['', 'Account Head', 'Type', 'Debit (PKR)', 'Credit (PKR)'], ',', '"');
    fputcsv($output, []); // Empty line for better readability

    // Get data with proper ordering
    $query = "SELECT 
        ah.name as head_name,
        ac.name as category_name,
        t.type,
        SUM(l.debit) as total_debit,
        SUM(l.credit) as total_credit,
        CASE 
            WHEN SUM(l.debit) > SUM(l.credit) THEN SUM(l.debit) - SUM(l.credit)
            ELSE 0 
        END as debit_balance,
        CASE 
            WHEN SUM(l.credit) > SUM(l.debit) THEN SUM(l.credit) - SUM(l.debit)
            ELSE 0 
        END as credit_balance
        FROM ledgers l
        JOIN transactions t ON l.transaction_id = t.id
        JOIN accounting_heads ah ON t.head_id = ah.id
        JOIN account_categories ac ON t.category_id = ac.id
        WHERE t.date BETWEEN ? AND ?
        GROUP BY ah.name, ac.name, t.type
        ORDER BY 
        CASE t.type
            WHEN 'asset' THEN 1
            WHEN 'liability' THEN 2
            WHEN 'equity' THEN 3
            WHEN 'income' THEN 4
            WHEN 'expense' THEN 5
        END, 
        ah.name, ac.name";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $from_date, $to_date);
    $stmt->execute();
    $result = $stmt->get_result();

    $total_debit = 0;
    $total_credit = 0;
    $current_type = '';

    while ($row = $result->fetch_assoc()) {
        if ($current_type != $row['type']) {
            $current_type = $row['type'];
            fputcsv($output, []); // Empty line before new section
            fputcsv($output, ['', strtoupper(ucfirst($current_type) . 's')], ',', '"'); // Section header
        }

        // Format numbers with commas and 2 decimal places
        $debit = $row['debit_balance'] > 0 ? number_format($row['debit_balance'], 2) : '';
        $credit = $row['credit_balance'] > 0 ? number_format($row['credit_balance'], 2) : '';

        fputcsv($output, [
            '',
            $row['category_name'], // Removed indent for better visibility
            $row['type'],
            $debit,
            $credit
        ], ',', '"');

        $total_debit += $row['debit_balance'];
        $total_credit += $row['credit_balance'];
    }

    // Add totals with a separator line
    fputcsv($output, []); // Empty line
    fputcsv($output, ['', '', '', str_repeat('-', 15), str_repeat('-', 15)], ',', '"'); // Separator line
    fputcsv($output, [
        '',
        'Total',
        '',
        number_format($total_debit, 2),
        number_format($total_credit, 2)
    ], ',', '"');

    // Add balance status
    fputcsv($output, []); // Empty line
    $difference = $total_debit - $total_credit;
    if (abs($difference) < 0.01) {
        fputcsv($output, ['', 'Status:', 'Trial Balance is Balanced'], ',', '"');
        fputcsv($output, ['', 'Total Debits and Credits:', number_format($total_debit, 2)], ',', '"');
    } else {
        fputcsv($output, ['', 'Status:', 'Trial Balance is NOT Balanced'], ',', '"');
        fputcsv($output, ['', 'Difference:', number_format(abs($difference), 2)], ',', '"');
    }

    fclose($output);
    exit();

} catch (Exception $e) {
    error_log("Error exporting trial balance: " . $e->getMessage());
    header("Location: new_trial_balance.php?error=Export failed");
    exit();
} 