<?php
// admin_dashboard.php
require 'session.php';
require 'db.php';

// Check if the user is an admin
if ($_SESSION['role'] != 'admin') {
    header("Location: user_dashboard.php");
    exit();
}

// Fetch total number of users
$user_count_result = $conn->query("SELECT COUNT(*) as total_users FROM users");
if (!$user_count_result) {
    die("Query failed (Total Users): (" . $conn->errno . ") " . $conn->error);
}
$user_count = $user_count_result->fetch_assoc()['total_users'] ?? 0;

// Fetch total income across all users
$income_result = $conn->query("SELECT SUM(amount) as total_income FROM transactions WHERE type = 'income'");
if (!$income_result) {
    die("Query failed (Total Income): (" . $conn->errno . ") " . $conn->error);
}
$total_income = $income_result->fetch_assoc()['total_income'] ?? 0;

// Fetch total expenses across all users
$expense_result = $conn->query("SELECT SUM(amount) as total_expenses FROM transactions WHERE type = 'expense'");
if (!$expense_result) {
    die("Query failed (Total Expenses): (" . $conn->errno . ") " . $conn->error);
}
$total_expenses = $expense_result->fetch_assoc()['total_expenses'] ?? 0;

// Calculate net balance
$net_balance = $total_income - $total_expenses;

// Fetch recent audit logs (last 5)
$stmt = $conn->prepare("SELECT audit_logs.*, users.username FROM audit_logs 
                        LEFT JOIN users ON audit_logs.user_id = users.id 
                        ORDER BY audit_logs.timestamp DESC LIMIT 5");
if (!$stmt) {
    die("Prepare failed (Audit Logs): (" . $conn->errno . ") " . $conn->error);
}

if (!$stmt->execute()) {
    die("Execute failed (Audit Logs): (" . $stmt->errno . ") " . $stmt->error);
}

$result = $stmt->get_result();
$recent_logs = [];
while ($row = $result->fetch_assoc()) {
    $recent_logs[] = $row;
}
$stmt->close();

// Fetch total number of transactions
$transaction_count_result = $conn->query("SELECT COUNT(*) as total_transactions FROM transactions");
if (!$transaction_count_result) {
    die("Query failed (Total Transactions): (" . $conn->errno . ") " . $conn->error);
}
$total_transactions = $transaction_count_result->fetch_assoc()['total_transactions'] ?? 0;

// Fetch total number of categories
$category_count_result = $conn->query("SELECT COUNT(*) as total_categories FROM categories");
if (!$category_count_result) {
    die("Query failed (Total Categories): (" . $conn->errno . ") " . $conn->error);
}
$total_categories = $category_count_result->fetch_assoc()['total_categories'] ?? 0;

// Log dashboard view
log_action($conn, $_SESSION['user_id'], 'Viewed Admin Dashboard', 'Admin accessed the dashboard.');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard</title>
    <!-- Include Bootstrap CSS for styling (Optional but recommended) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .card { margin: 15px 0; }
        .table-responsive { max-height: 400px; }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">Accounting Software</a>
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="manage_users.php">Manage Users</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="manage_transactions.php">Manage Transactions</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="manage_categories.php">Manage Categories</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="financial_reports.php">Financial Reports</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="admin_dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">Logout (<?php echo htmlspecialchars($_SESSION['username']); ?>)</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Dashboard Content -->
    <div class="container mt-4">
        <h2>Admin Dashboard</h2>
        <div class="row">
            <!-- Users Card -->
            <div class="col-md-4">
                <div class="card text-white bg-primary">
                    <div class="card-body">
                        <h5 class="card-title">Total Users</h5>
                        <p class="card-text" style="font-size: 2em;"><?php echo $user_count; ?></p>
                        <a href="manage_users.php" class="btn btn-light">Manage Users</a>
                    </div>
                </div>
            </div>
            <!-- Transactions Card -->
            <div class="col-md-4">
                <div class="card text-white bg-success">
                    <div class="card-body">
                        <h5 class="card-title">Total Transactions</h5>
                        <p class="card-text" style="font-size: 2em;"><?php echo $total_transactions; ?></p>
                        <a href="manage_transactions.php" class="btn btn-light">Manage Transactions</a>
                    </div>
                </div>
            </div>
            <!-- Categories Card -->
            <div class="col-md-4">
                <div class="card text-white bg-warning">
                    <div class="card-body">
                        <h5 class="card-title">Total Categories</h5>
                        <p class="card-text" style="font-size: 2em;"><?php echo $total_categories; ?></p>
                        <a href="manage_categories.php" class="btn btn-light">Manage Categories</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Financial Overview -->
        <div class="row mt-4">
            <div class="col-md-6">
                <div class="card text-white bg-info">
                    <div class="card-body">
                        <h5 class="card-title">Total Income</h5>
                        <p class="card-text" style="font-size: 2em;">$<?php echo number_format($total_income, 2); ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card text-white bg-danger">
                    <div class="card-body">
                        <h5 class="card-title">Total Expenses</h5>
                        <p class="card-text" style="font-size: 2em;">$<?php echo number_format($total_expenses, 2); ?></p>
                    </div>
                </div>
            </div>
        </div>
        <div class="row mt-2">
            <div class="col-md-12">
                <div class="card text-white bg-dark">
                    <div class="card-body">
                        <h5 class="card-title">Net Balance</h5>
                        <p class="card-text" style="font-size: 2em;">$<?php echo number_format($net_balance, 2); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Audit Logs -->
        <div class="mt-5">
            <h3>Recent Activities</h3>
            <div class="table-responsive">
                <table class="table table-striped table-bordered">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>User</th>
                            <th>Action</th>
                            <th>Details</th>
                            <th>Timestamp</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($recent_logs) > 0): ?>
                            <?php foreach ($recent_logs as $log): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($log['id']); ?></td>
                                    <td><?php echo htmlspecialchars($log['username'] ?? 'System'); ?></td>
                                    <td><?php echo htmlspecialchars($log['action']); ?></td>
                                    <td><?php echo htmlspecialchars($log['details']); ?></td>
                                    <td><?php echo htmlspecialchars($log['timestamp']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center">No recent activities found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <a href="view_audit_logs.php" class="btn btn-secondary">View All Audit Logs</a>
        </div>
    </div>

    <!-- Include Bootstrap JS (Optional) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
