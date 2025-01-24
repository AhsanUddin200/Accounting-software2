<?php
require_once 'session.php';
require_once 'db.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Get date from URL parameters or set default to current date
$as_of_date = $_GET['as_of_date'] ?? date('Y-m-d');

try {
    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="balance_sheet_' . date('Y-m-d') . '.csv"');

    // Create output stream
    $output = fopen('php://output', 'w');

    // Add UTF-8 BOM for Excel
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    // Add title and date
    fputcsv($output, ['BALANCE SHEET']);
    fputcsv($output, ['As of Date:', date('F d, Y', strtotime($as_of_date))]);
    fputcsv($output, []); // Empty line

    // Assets Section
    fputcsv($output, ['ASSETS']);
    fputcsv($output, ['Account Head', 'Category', 'Amount (PKR)']);
    
    $assets_query = "SELECT 
        ah.name as head_name,
        ac.name as category_name,
        SUM(l.debit) - SUM(l.credit) as balance
        FROM ledgers l
        JOIN transactions t ON l.transaction_id = t.id
        JOIN accounting_heads ah ON t.head_id = ah.id
        JOIN account_categories ac ON t.category_id = ac.id
        WHERE t.type = 'asset' 
        AND t.date <= ?
        GROUP BY ah.name, ac.name
        HAVING balance != 0
        ORDER BY ah.name, ac.name";

    $stmt = $conn->prepare($assets_query);
    $stmt->bind_param("s", $as_of_date);
    $stmt->execute();
    $assets = $stmt->get_result();

    $total_assets = 0;
    $current_head = '';
    while ($row = $assets->fetch_assoc()) {
        if ($current_head != $row['head_name']) {
            $current_head = $row['head_name'];
            fputcsv($output, [$row['head_name'], '', '']);
        }
        fputcsv($output, ['', $row['category_name'], number_format($row['balance'], 2)]);
        $total_assets += $row['balance'];
    }
    fputcsv($output, ['Total Assets', '', number_format($total_assets, 2)]);
    fputcsv($output, []); // Empty line

    // Liabilities Section
    fputcsv($output, ['LIABILITIES']);
    fputcsv($output, ['Account Head', 'Category', 'Amount (PKR)']);
    
    $liabilities_query = "SELECT 
        ah.name as head_name,
        ac.name as category_name,
        SUM(l.credit) - SUM(l.debit) as balance
        FROM ledgers l
        JOIN transactions t ON l.transaction_id = t.id
        JOIN accounting_heads ah ON t.head_id = ah.id
        JOIN account_categories ac ON t.category_id = ac.id
        WHERE t.type = 'liability'
        AND t.date <= ?
        GROUP BY ah.name, ac.name
        HAVING balance != 0
        ORDER BY ah.name, ac.name";

    $stmt = $conn->prepare($liabilities_query);
    $stmt->bind_param("s", $as_of_date);
    $stmt->execute();
    $liabilities = $stmt->get_result();

    $total_liabilities = 0;
    $current_head = '';
    while ($row = $liabilities->fetch_assoc()) {
        if ($current_head != $row['head_name']) {
            $current_head = $row['head_name'];
            fputcsv($output, [$row['head_name'], '', '']);
        }
        fputcsv($output, ['', $row['category_name'], number_format($row['balance'], 2)]);
        $total_liabilities += $row['balance'];
    }
    fputcsv($output, ['Total Liabilities', '', number_format($total_liabilities, 2)]);
    fputcsv($output, []); // Empty line

    // Equity Section
    fputcsv($output, ['EQUITY']);
    fputcsv($output, ['Account Head', 'Category', 'Amount (PKR)']);
    
    $equity_query = "SELECT 
        ah.name as head_name,
        ac.name as category_name,
        SUM(l.credit) - SUM(l.debit) as balance
        FROM ledgers l
        JOIN transactions t ON l.transaction_id = t.id
        JOIN accounting_heads ah ON t.head_id = ah.id
        JOIN account_categories ac ON t.category_id = ac.id
        WHERE t.type = 'equity'
        AND t.date <= ?
        GROUP BY ah.name, ac.name
        HAVING balance != 0
        ORDER BY ah.name, ac.name";

    $stmt = $conn->prepare($equity_query);
    $stmt->bind_param("s", $as_of_date);
    $stmt->execute();
    $equity = $stmt->get_result();

    $total_equity = 0;
    $current_head = '';
    while ($row = $equity->fetch_assoc()) {
        if ($current_head != $row['head_name']) {
            $current_head = $row['head_name'];
            fputcsv($output, [$row['head_name'], '', '']);
        }
        fputcsv($output, ['', $row['category_name'], number_format($row['balance'], 2)]);
        $total_equity += $row['balance'];
    }
    
    // Add Retained Earnings
    $net_income_query = "SELECT 
        (SELECT COALESCE(SUM(l.credit) - SUM(l.debit), 0)
         FROM ledgers l
         JOIN transactions t ON l.transaction_id = t.id
         WHERE t.type = 'income'
         AND t.date <= ?) -
        (SELECT COALESCE(SUM(l.debit) - SUM(l.credit), 0)
         FROM ledgers l
         JOIN transactions t ON l.transaction_id = t.id
         WHERE t.type = 'expense'
         AND t.date <= ?) as net_income";

    $stmt = $conn->prepare($net_income_query);
    $stmt->bind_param("ss", $as_of_date, $as_of_date);
    $stmt->execute();
    $net_income_result = $stmt->get_result();
    $net_income_row = $net_income_result->fetch_assoc();
    $net_income = $net_income_row['net_income'] ?? 0;
    
    fputcsv($output, ['Retained Earnings', '', number_format($net_income, 2)]);
    $total_equity += $net_income;
    
    fputcsv($output, ['Total Equity', '', number_format($total_equity, 2)]);
    fputcsv($output, []); // Empty line

    // Summary Section
    fputcsv($output, ['SUMMARY']);
    $total_liab_equity = $total_liabilities + $total_equity;
    fputcsv($output, ['Total Assets', '', number_format($total_assets, 2)]);
    fputcsv($output, ['Total Liabilities and Equity', '', number_format($total_liab_equity, 2)]);
    
    $difference = $total_assets - $total_liab_equity;
    if (abs($difference) > 0.01) {
        fputcsv($output, ['Difference', '', number_format(abs($difference), 2) . 
            ($difference > 0 ? ' (Excess Assets)' : ' (Deficit)')]);
    } else {
        fputcsv($output, ['Status', '', 'Balanced']);
    }

    fclose($output);
    exit();

} catch (Exception $e) {
    error_log("Error exporting balance sheet: " . $e->getMessage());
    header("Location: balance_sheet.php?error=Export failed");
    exit();
} 