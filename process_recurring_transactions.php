// process_recurring_transactions.php
<?php
require 'db.php';

// Get today's date
$today = date('Y-m-d');

// Fetch due recurring transactions
$stmt = $conn->prepare("SELECT * FROM recurring_transactions WHERE next_occurrence <= ?");
$stmt->bind_param("s", $today);
$stmt->execute();
$result = $stmt->get_result();

while ($recurring = $result->fetch_assoc()) {
    // Insert into transactions
    $insert_stmt = $conn->prepare("INSERT INTO transactions (user_id, amount, type, category_id, date, description) VALUES (?, ?, ?, ?, ?, ?)");
    $insert_stmt->bind_param("idsiss", $recurring['user_id'], $recurring['amount'], $recurring['type'], $recurring['category_id'], $today, $recurring['description']);
    $insert_stmt->execute();
    $insert_stmt->close();

    // Calculate next occurrence
    switch ($recurring['frequency']) {
        case 'daily':
            $next = date('Y-m-d', strtotime('+1 day', strtotime($recurring['next_occurrence'])));
            break;
        case 'weekly':
            $next = date('Y-m-d', strtotime('+1 week', strtotime($recurring['next_occurrence'])));
            break;
        case 'monthly':
            $next = date('Y-m-d', strtotime('+1 month', strtotime($recurring['next_occurrence'])));
            break;
        case 'yearly':
            $next = date('Y-m-d', strtotime('+1 year', strtotime($recurring['next_occurrence'])));
            break;
        default:
            $next = $today; // Default to today
    }

    // Update next_occurrence
    $update_stmt = $conn->prepare("UPDATE recurring_transactions SET next_occurrence = ? WHERE id = ?");
    $update_stmt->bind_param("si", $next, $recurring['id']);
    $update_stmt->execute();
    $update_stmt->close();
}

$stmt->close();
?>
