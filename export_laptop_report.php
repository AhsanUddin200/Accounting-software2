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

// Set headers for CSV download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="laptop_report_' . date('Y-m-d') . '.csv"');

// Create a file pointer connected to the output stream
$output = fopen('php://output', 'w');

// Add UTF-8 BOM for proper Excel display
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Set the column headers
fputcsv($output, [
    'Asset ID',
    'Model',
    'Serial Number',
    'Status',
    'Custodian',
    'Location',
    'Purchase Date',
    'Purchase Price (PKR)',
    'Current Value (PKR)',
    'Specifications',
    'Notes',
    'Last Updated'
]);

// Fetch all laptop records
$query = "SELECT 
    l.*,
    u.username as custodian_name
    FROM laptops l
    LEFT JOIN users u ON l.custodian_id = u.id
    ORDER BY l.purchase_date DESC";

try {
    $result = $conn->query($query);

    if ($result) {
        // Output each row of data
        while ($row = $result->fetch_assoc()) {
            fputcsv($output, [
                $row['asset_id'],
                $row['model'],
                $row['serial_number'],
                ucfirst($row['status']),
                $row['custodian_name'] ?? 'Not Assigned',
                $row['location'] ?? 'Not Specified',
                date('Y-m-d', strtotime($row['purchase_date'])),
                number_format($row['purchase_price'], 2),
                number_format($row['current_value'], 2),
                $row['specifications'],
                $row['notes'],
                date('Y-m-d H:i:s', strtotime($row['updated_at']))
            ]);
        }
    } else {
        // Log error if query fails
        error_log("Failed to execute query: " . $conn->error);
        die("Failed to generate report");
    }

} catch (Exception $e) {
    // Log any other errors
    error_log("Error generating CSV: " . $e->getMessage());
    die("Failed to generate report");
}

// Close the file pointer
fclose($output);
exit(); 