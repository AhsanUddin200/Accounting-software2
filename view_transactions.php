<?php
// view_transactions.php
require 'session.php';
require 'db.php';

// Initialize variables
$transactions = [];
$categories = [];
$success = "";
$error = "";

// Fetch categories for filter
$cat_query = "SELECT id, name FROM categories ORDER BY name ASC";
$cat_result = $conn->query($cat_query);
if ($cat_result) {
    while ($row = $cat_result->fetch_assoc()) {
        $categories[] = $row;
    }
} else {
    die("Error fetching categories: " . $conn->error);
}

// Handle Filters
$where_clauses = [];
$params = [];
$types = "";

if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    // Filter by type
    if (!empty($_GET['type'])) {
        $where_clauses[] = "type = ?";
        $params[] = $_GET['type'];
        $types .= "s";
    }

    // Filter by category
    if (!empty($_GET['category'])) {
        $where_clauses[] = "category_id = ?";
        $params[] = $_GET['category'];
        $types .= "i";
    }

    // Filter by date range
    if (!empty($_GET['start_date']) && !empty($_GET['end_date'])) {
        $where_clauses[] = "date BETWEEN ? AND ?";
        $params[] = $_GET['start_date'];
        $params[] = $_GET['end_date'];
        $types .= "ss";
    }
}

// Build the query
$query = "SELECT transactions.id, transactions.amount, transactions.type, categories.name AS category, transactions.date, transactions.description
          FROM transactions
          JOIN categories ON transactions.category_id = categories.id
          WHERE user_id = ?";

$params[] = $_SESSION['user_id'];
$types .= "i";

if (!empty($where_clauses)) {
    $query .= " AND " . implode(" AND ", $where_clauses);
}

$query .= " ORDER BY transactions.date DESC";

// Prepare and execute the statement
$stmt = $conn->prepare($query);
if ($stmt) {
    if (!empty($types)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $transactions[] = $row;
    }
    $stmt->close();
} else {
    die("Error preparing statement: " . $conn->error);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>View Transactions</title>
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
        form { margin-bottom: 30px; }
        input, select { padding: 8px; margin: 5px 0; border: 1px solid #ccc; border-radius: 4px; }
        input[type="submit"] { background: #5bc0de; border: none; color: #fff; cursor: pointer; }
        input[type="submit"]:hover { background: #31b0d5; }
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
        .filter-section { margin-bottom: 20px; }
        .filter-section label { margin-right: 10px; }
    </style>
</head>
<body>
    <div class="container">
        <h2>View Transactions</h2>

        <!-- Filter Form -->
        <div class="filter-section">
            <form method="GET" action="view_transactions.php">
                <label for="type">Type:</label>
                <select id="type" name="type">
                    <option value="">All</option>
                    <option value="income" <?php echo (isset($_GET['type']) && $_GET['type'] == 'income') ? 'selected' : ''; ?>>Income</option>
                    <option value="expense" <?php echo (isset($_GET['type']) && $_GET['type'] == 'expense') ? 'selected' : ''; ?>>Expense</option>
                </select>

                <label for="category">Category:</label>
                <select id="category" name="category">
                    <option value="">All</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo htmlspecialchars($category['id']); ?>" <?php echo (isset($_GET['category']) && $_GET['category'] == $category['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($category['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label for="start_date">From:</label>
                <input type="date" id="start_date" name="start_date" value="<?php echo isset($_GET['start_date']) ? htmlspecialchars($_GET['start_date']) : ''; ?>">

                <label for="end_date">To:</label>
                <input type="date" id="end_date" name="end_date" value="<?php echo isset($_GET['end_date']) ? htmlspecialchars($_GET['end_date']) : ''; ?>">

                <input type="submit" value="Filter">
                <a href="view_transactions.php"><input type="button" value="Reset"></a>
            </form>
        </div>

        <!-- Transactions Table -->
        <table>
            <tr>
                <th>ID</th>
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
                        <td><?php echo htmlspecialchars(number_format($transaction['amount'], 2)); ?></td>
                        <td><?php echo htmlspecialchars(ucfirst($transaction['type'])); ?></td>
                        <td><?php echo htmlspecialchars($transaction['category']); ?></td>
                        <td><?php echo htmlspecialchars($transaction['date']); ?></td>
                        <td><?php echo htmlspecialchars($transaction['description']); ?></td>
                        <td>
                            <a href="edit_transaction.php?id=<?php echo $transaction['id']; ?>" class="edit-button">Edit</a> | 
                            <a href="view_transactions.php?delete=<?php echo $transaction['id']; ?>" class="delete-button" onclick="return confirm('Are you sure you want to delete this transaction?');">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7">No transactions found.</td>
                </tr>
            <?php endif; ?>
        </table>

        <!-- Back Button -->
        <div class="back-button">
            <a href="user_dashboard.php">Back to Dashboard</a>
        </div>
    </div>
</body>
</html>
