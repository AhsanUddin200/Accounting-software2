<?php
// export_salary.php

// Include necessary files
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/db.php';

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch salary records for the user
$stmt = $conn->prepare("SELECT month, total_present, total_absent, total_salary 
                        FROM salaries 
                        WHERE user_id = ? 
                        ORDER BY month DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$salaries = [];
while ($row = $result->fetch_assoc()) {
    $salaries[] = $row;
}
$stmt->close();

// Set headers to download file
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=salary_history_' . date('Y-m-d') . '.csv');

// Open the output stream
$output = fopen('php://output', 'w');

// Output the column headings
fputcsv($output, ['Month', 'Total Present', 'Total Absent', 'Total Salary (Rs)']);

// Output the data
foreach ($salaries as $salary) {
    fputcsv($output, [
        date('F Y', strtotime($salary['month'] . '-01')),
        $salary['total_present'],
        $salary['total_absent'],
        number_format($salary['total_salary'], 2)
    ]);
}

fclose($output);
exit();
?>
