<?php
require_once 'session.php';
require_once 'db.php';

// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Ensure user has appropriate permissions
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Get date range from URL parameters or set defaults
$from_date = $_GET['from_date'] ?? date('Y-m-01');
$to_date = $_GET['to_date'] ?? date('Y-m-t');

try {
    // Fetch revenue data
    $revenue_query = "SELECT 
        ac.name as category_name,
        SUM(l.debit) as debit,
        SUM(l.credit) as credit
        FROM transactions t
        JOIN ledgers l ON t.id = l.transaction_id
        JOIN account_categories ac ON t.category_id = ac.id
        WHERE t.type = 'income'
        AND t.date BETWEEN ? AND ?
        GROUP BY ac.name
        ORDER BY ac.name";

    $stmt = $conn->prepare($revenue_query);
    $stmt->bind_param("ss", $from_date, $to_date);
    $stmt->execute();
    $revenue_result = $stmt->get_result();

    // Fetch operating expenses
    $operating_query = "SELECT 
        ac.name as category_name,
        SUM(l.debit) as debit,
        SUM(l.credit) as credit
        FROM transactions t
        JOIN ledgers l ON t.id = l.transaction_id
        JOIN account_categories ac ON t.category_id = ac.id
        WHERE t.type = 'expense' 
        AND ac.name NOT IN ('Interest Expense', 'Depreciation', 'Tax Expense')
        AND t.date BETWEEN ? AND ?
        GROUP BY ac.name
        ORDER BY ac.name";

    $stmt = $conn->prepare($operating_query);
    $stmt->bind_param("ss", $from_date, $to_date);
    $stmt->execute();
    $operating_result = $stmt->get_result();

    // Fetch non-operating expenses
    $nonoperating_query = "SELECT 
        ac.name as category_name,
        SUM(l.debit) as debit,
        SUM(l.credit) as credit
        FROM transactions t
        JOIN ledgers l ON t.id = l.transaction_id
        JOIN account_categories ac ON t.category_id = ac.id
        WHERE t.type = 'expense'
        AND ac.name IN ('Interest Expense', 'Depreciation', 'Tax Expense')
        AND t.date BETWEEN ? AND ?
        GROUP BY ac.name
        ORDER BY ac.name";

    $stmt = $conn->prepare($nonoperating_query);
    $stmt->bind_param("ss", $from_date, $to_date);
    $stmt->execute();
    $nonoperating_result = $stmt->get_result();

    // Set headers for CSV download
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="income_statement_' . date('Y-m-d') . '.csv"');

    // Create output stream
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // Add BOM for Excel UTF-8 support

    // Add heading and date range
    fputcsv($output, ['', '', '', 'Financial Management System'], ',', '"');
    fputcsv($output, ['', '', '', 'Income Statement'], ',', '"');
    fputcsv($output, ['', '', '', 'Period:', date('F d, Y', strtotime($from_date)) . ' - ' . date('F d, Y', strtotime($to_date))], ',', '"');
    fputcsv($output, [], ',', '"'); // Empty row for spacing

    // Revenue section
    fputcsv($output, ['', '', 'Revenue', 'Amount (PKR)', 'Type'], ',', '"');
    $total_revenue = 0;
    while ($row = $revenue_result->fetch_assoc()) {
        $balance = $row['credit'] - $row['debit'];
        $total_revenue += $balance;
        fputcsv($output, [
            '',
            '',
            $row['category_name'],
            number_format(abs($balance), 2),
            ($balance < 0 ? 'Dr' : 'Cr')
        ], ',', '"');
    }
    fputcsv($output, ['', '', 'Total Revenue', number_format(abs($total_revenue), 2), ($total_revenue < 0 ? 'Dr' : 'Cr')], ',', '"');
    fputcsv($output, [], ',', '"'); // Empty row for spacing

    // Operating Expenses section
    fputcsv($output, ['', '', 'Operating Expenses', 'Amount (PKR)', 'Type'], ',', '"');
    $total_operating_expenses = 0;
    while ($row = $operating_result->fetch_assoc()) {
        $balance = $row['debit'] - $row['credit'];
        $total_operating_expenses += $balance;
        fputcsv($output, [
            '',
            '',
            $row['category_name'],
            number_format(abs($balance), 2),
            ($balance < 0 ? 'Cr' : 'Dr')
        ], ',', '"');
    }
    fputcsv($output, ['', '', 'Total Operating Expenses', number_format(abs($total_operating_expenses), 2), ($total_operating_expenses < 0 ? 'Cr' : 'Dr')], ',', '"');
    fputcsv($output, [], ',', '"'); // Empty row for spacing

    // Non-Operating Expenses section
    fputcsv($output, ['', '', 'Non-Operating Expenses', 'Amount (PKR)', 'Type'], ',', '"');
    $total_non_operating_expenses = 0;
    while ($row = $nonoperating_result->fetch_assoc()) {
        $balance = $row['debit'] - $row['credit'];
        $total_non_operating_expenses += $balance;
        fputcsv($output, [
            '',
            '',
            $row['category_name'],
            number_format(abs($balance), 2),
            ($balance < 0 ? 'Cr' : 'Dr')
        ], ',', '"');
    }
    fputcsv($output, ['', '', 'Total Non-Operating Expenses', number_format(abs($total_non_operating_expenses), 2), ($total_non_operating_expenses < 0 ? 'Cr' : 'Dr')], ',', '"');
    fputcsv($output, [], ',', '"'); // Empty row for spacing

    // Summary section
    $gross_profit = $total_revenue - $total_operating_expenses;
    $net_income = $gross_profit - $total_non_operating_expenses;
    $total_expenses = $total_operating_expenses + $total_non_operating_expenses;

    fputcsv($output, ['', '', 'Summary'], ',', '"');
    fputcsv($output, ['', '', 'Gross Profit', number_format(abs($gross_profit), 2), ($gross_profit < 0 ? 'Dr' : 'Cr')], ',', '"');
    fputcsv($output, ['', '', 'Total Expenses', number_format(abs($total_expenses), 2), 'Dr'], ',', '"');
    fputcsv($output, ['', '', 'Net Income', number_format(abs($net_income), 2), ($net_income < 0 ? 'Dr' : 'Cr')], ',', '"');

    // Close the output stream
    fclose($output);
} catch (Exception $e) {
    error_log("Error exporting income statement: " . $e->getMessage());
    header("Location: income_statement.php?error=Export failed");
}

exit();