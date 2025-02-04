<?php
require_once 'db.php';

// Add error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

function getDateRange($period) {
    $today = date('Y-m-d');
    
    switch($period) {
        case 'today':   
            return [$today, $today];
        case 'yesterday':
            $yesterday = date('Y-m-d', strtotime('-1 day'));
            return [$yesterday, $yesterday];
        case 'this_week':
            $week_start = date('Y-m-d', strtotime('monday this week'));
            return [$week_start, $today];
        case 'last_week':
            $last_week_start = date('Y-m-d', strtotime('monday last week'));
            $last_week_end = date('Y-m-d', strtotime('sunday last week'));
            return [$last_week_start, $last_week_end];
        case 'this_month':
            $month_start = date('Y-m-01');
            return [$month_start, $today];
        case 'last_month':
            $last_month_start = date('Y-m-01', strtotime('last month'));
            $last_month_end = date('Y-m-t', strtotime('last month'));
            return [$last_month_start, $last_month_end];
        case 'custom':
            $from_date = $_POST['from_date'] ?? $today;
            $to_date = $_POST['to_date'] ?? $today;
            return [$from_date, $to_date];
        default:
            return [$today, $today];
    }
}

// Get the period from POST data
$period = $_POST['period'] ?? 'today';
list($from_date, $to_date) = getDateRange($period);

// Get summary data with proper date filtering
$summary_query = "
    SELECT 
        COUNT(*) as total_transactions,
        COALESCE(AVG(amount), 0) as avg_amount,
        COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END), 0) as total_income,
        COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0) as total_expense
    FROM transactions
    WHERE date BETWEEN ? AND ?";

$stmt = $conn->prepare($summary_query);
$stmt->bind_param("ss", $from_date, $to_date);
$stmt->execute();
$summary = $stmt->get_result()->fetch_assoc();

// Prepare and send response
$response = [
    'summary' => $summary,
    'monthly' => $monthly_data,
    'types' => $type_data,
    'daily' => $daily_data,
    'category' => $category_data,
    'balance' => $balance_data,
    'period' => [
        'from' => $from_date,
        'to' => $to_date
    ]
];

header('Content-Type: application/json');
echo json_encode($response);
?> 