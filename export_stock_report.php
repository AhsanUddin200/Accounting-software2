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
header('Content-Disposition: attachment; filename="stock_report_' . date('Y-m-d') . '.csv"');

// Create a file pointer connected to the output stream
$output = fopen('php://output', 'w');

// Add UTF-8 BOM for proper Excel display of Unicode characters
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Set the column headers
fputcsv($output, [
    'Item Code',
    'Name',
    'Category',
    'Description',
    'Quantity',
    'Unit',
    'Unit Price',
    'Total Value',
    'Location',
    'Minimum Quantity',
    'Last Restock Date',
    'Status',
    'Created At',
    'Updated At'
]);

// Fetch all stock items
try {
    $query = "SELECT * FROM stock_items ORDER BY name ASC";
    $result = $conn->query($query);

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            // Calculate total value
            $total_value = $row['quantity'] * $row['unit_price'];
            
            // Determine status
            $status = 'In Stock';
            if ($row['quantity'] == 0) {
                $status = 'Out of Stock';
            } elseif ($row['quantity'] <= $row['minimum_quantity']) {
                $status = 'Low Stock';
            }

            // Format dates
            $last_restock = $row['last_restock_date'] ? date('Y-m-d', strtotime($row['last_restock_date'])) : 'Never';
            $created_at = date('Y-m-d H:i:s', strtotime($row['created_at']));
            $updated_at = date('Y-m-d H:i:s', strtotime($row['updated_at']));

            // Write the data row
            fputcsv($output, [
                $row['item_code'],
                $row['name'],
                $row['category'],
                $row['description'],
                $row['quantity'],
                $row['unit'],
                number_format($row['unit_price'], 2),
                number_format($total_value, 2),
                $row['location'],
                $row['minimum_quantity'],
                $last_restock,
                $status,
                $created_at,
                $updated_at
            ]);
        }
    }

    // Add summary row
    fputcsv($output, []); // Empty row for spacing
    
    // Calculate totals
    $total_query = "SELECT 
        COUNT(*) as total_items,
        SUM(quantity * unit_price) as total_value,
        SUM(CASE WHEN quantity <= minimum_quantity AND quantity > 0 THEN 1 ELSE 0 END) as low_stock,
        SUM(CASE WHEN quantity = 0 THEN 1 ELSE 0 END) as out_of_stock
        FROM stock_items";
    
    $total_result = $conn->query($total_query);
    $totals = $total_result->fetch_assoc();

    // Add summary information
    fputcsv($output, ['Summary']);
    fputcsv($output, ['Total Items', $totals['total_items']]);
    fputcsv($output, ['Total Value', 'PKR ' . number_format($totals['total_value'], 2)]);
    fputcsv($output, ['Low Stock Items', $totals['low_stock']]);
    fputcsv($output, ['Out of Stock Items', $totals['out_of_stock']]);
    fputcsv($output, ['Report Generated', date('Y-m-d H:i:s')]);

} catch (Exception $e) {
    // Log error and exit
    error_log("Error exporting stock report: " . $e->getMessage());
    header("Location: stock_report.php?error=Export failed");
    exit();
}

// Close the file pointer
fclose($output);
exit(); 