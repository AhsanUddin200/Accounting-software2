<?php
// manage_transactions.php
require 'session.php';
require 'db.php';

// Check if the logged-in user is an admin
if ($_SESSION['role'] != 'admin') {
    header("Location: user_dashboard.php");
    exit();
}

// Initialize variables
$success = "";
$error = "";

// Handle Delete Transaction
if (isset($_GET['delete'])) {
    $delete_id = intval($_GET['delete']);

    $stmt = $conn->prepare("DELETE FROM transactions WHERE id = ?");
    $stmt->bind_param("i", $delete_id);
    if ($stmt->execute()) {
        $success = "Transaction deleted successfully.";
    } else {
        $error = "Error deleting transaction: " . $stmt->error;
    }
    $stmt->close();
}

// Fetch all transactions with user and category details
$transactions = [];
$query = "SELECT transactions.id, users.username, transactions.amount, transactions.type, categories.name AS category, transactions.date, transactions.description
          FROM transactions
          JOIN users ON transactions.user_id = users.id
          JOIN categories ON transactions.category_id = categories.id
          ORDER BY transactions.id DESC";
$result = $conn->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $transactions[] = $row;
    }
} else {
    die("Error fetching transactions: " . $conn->error);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Transactions</title>
    <style>
        /* Basic styling */
        body { font-family: Arial, sans-serif; background-color: #f4f4f4; }
        .container { width: 95%; margin: 20px auto; padding: 20px; background: #fff; border-radius: 5px; }
        h2 { text-align: center; }
        .message { padding: 10px; margin-bottom: 20px; border-radius: 4px; }
        .success { background-color: #dff0d8; color: #3c763d; }
        .error { background-color: #f2dede; color: #a94442; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        table, th, td { border: 1px solid #ccc; }
        th, td { padding: 10px; text-align: left; }
        th { background-color: #f9f9f9; }
        .delete-button { color: #a94442; text-decoration: none; }
        .delete-button:hover { text-decoration: underline; }
        .edit-button { color: #337ab7; text-decoration: none; }
        .edit-button:hover { text-decoration: underline; }
        .back-button { text-align: center; margin-top: 20px; }
        .back-button a { 
            padding: 10px 20px; 
            background-color: #5bc0de; 
            color: #fff; 
            text-decoration: none; 
            border-radius: 4px; 
        }
        .back-button a:hover { background-color: #31b0d5; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Manage Transactions</h2>

        <!-- Display Success or Error Messages -->
        <?php if (!empty($success)): ?>
            <div class="message success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <div class="message error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Transactions Table -->
        <table>
            <tr>
                <th>ID</th>
                <th>User</th>
                <th>Amount</th>
                <th>Type</th>
                <th>Category</th>
                <th>Date</th>
                <th>Description</th>
                <th>Actions</th>
            </tr>
            <?php if (count($transactions) > 0): ?>
                <?php foreach ($transactions as $transaction): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($transaction['id']); ?></td>
                        <td><?php echo htmlspecialchars($transaction['username']); ?></td>
                        <td><?php echo htmlspecialchars(number_format($transaction['amount'], 2)); ?></td>
                        <td><?php echo htmlspecialchars(ucfirst($transaction['type'])); ?></td>
                        <td><?php echo htmlspecialchars($transaction['category']); ?></td>
                        <td><?php echo htmlspecialchars($transaction['date']); ?></td>
                        <td><?php echo htmlspecialchars($transaction['description']); ?></td>
                        <td>
                            <a href="edit_transaction.php?id=<?php echo $transaction['id']; ?>" class="edit-button">Edit</a> | 
                            <a href="manage_transactions.php?delete=<?php echo $transaction['id']; ?>" class="delete-button" onclick="return confirm('Are you sure you want to delete this transaction?');">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="8">No transactions found.</td>
                </tr>
            <?php endif; ?>
        </table>

        <!-- Back Button -->
        <div class="back-button">
            <a href="admin_dashboard.php">Back to Dashboard</a>
        </div>
    </div>
</body>
</html>
