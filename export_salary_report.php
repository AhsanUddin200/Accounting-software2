<?php
// export_salary_report.php

// Enable error reporting temporarily for debugging (remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include session.php from main directory
require_once __DIR__ . '/session.php';

// Ensure the user is an admin
if ($_SESSION['role'] != 'admin') {
    header("Location: user_dashboard.php");
    exit();
}

// Define the year for the report
$report_year = isset($_GET['year']) && is_numeric($_GET['year']) ? (int) $_GET['year'] : date('Y');

// Array of month names
$months = [
    1 => 'January',
    2 => 'February',
    3 => 'March',
    4 => 'April',
    5 => 'May',
    6 => 'June',
    7 => 'July',
    8 => 'August',
    9 => 'September',
    10 => 'October',
    11 => 'November',
    12 => 'December'
];

// Fetch all users
$user_stmt = $conn->prepare("SELECT id, username FROM users WHERE role = 'user' ORDER BY username ASC");
if (!$user_stmt) {
    die("Prepare failed (Fetch Users): (" . $conn->errno . ") " . $conn->error);
}

if (!$user_stmt->execute()) {
    die("Execute failed (Fetch Users): (" . $user_stmt->errno . ") " . $user_stmt->error);
}

$user_result = $user_stmt->get_result();
$users = [];
while ($user = $user_result->fetch_assoc()) {
    $users[] = $user;
}
$user_stmt->close();

// Initialize an array to hold salary data
$salary_data = [];

// Prepare the SQL statement to fetch salaries per user per month
$salary_stmt = $conn->prepare("
    SELECT 
        MONTH(date) as month,
        YEAR(date) as year,
        SUM(amount) as total_salary
    FROM transactions
    WHERE 
        type = 'income' AND 
        category_id = (SELECT id FROM categories WHERE name = 'Salary' LIMIT 1) AND
        user_id = ? AND
        YEAR(date) = ?
    GROUP BY MONTH(date), YEAR(date)
");
if (!$salary_stmt) {
    die("Prepare failed (Fetch Salaries): (" . $conn->errno . ") " . $conn->error);
}

// Iterate through each user to fetch their salaries
foreach ($users as $user) {
    $user_id = $user['id'];
    $username = $user['username'];

    // Execute the statement for the current user and year
    $salary_stmt->bind_param("ii", $user_id, $report_year);
    if (!$salary_stmt->execute()) {
        die("Execute failed (Fetch Salaries for User ID $user_id): (" . $salary_stmt->errno . ") " . $salary_stmt->error);
    }

    $salary_result = $salary_stmt->get_result();
    $user_salaries = [];
    while ($salary = $salary_result->fetch_assoc()) {
        $month_num = $salary['month'];
        $total_salary = $salary['total_salary'];
        $user_salaries[$month_num] = $total_salary;
    }
    $salary_result->free();

    // Assign salaries to the salary_data array
    $salary_data[$user_id] = $user_salaries;
}

$salary_stmt->close();

// Set headers to prompt file download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=monthly_salary_report_' . $report_year . '.csv');

// Open the output stream
$output = fopen('php://output', 'w');

// Output the column headings
$headers = ['Username'];
foreach ($months as $num => $name) {
    $headers[] = $name;
}
$headers[] = 'Total Salary';
fputcsv($output, $headers);

// Output the data
foreach ($users as $user) {
    $user_id = $user['id'];
    $username = $user['username'];
    $row = [$username];
    $total_salary = 0;
    for ($m = 1; $m <= 12; $m++) {
        $salary = isset($salary_data[$user_id][$m]) ? $salary_data[$user_id][$m] : 0;
        $row[] = number_format($salary, 2);
        $total_salary += $salary;
    }
    $row[] = number_format($total_salary, 2);
    fputcsv($output, $row);
}

// Close the output stream
fclose($output);
exit();
?>
