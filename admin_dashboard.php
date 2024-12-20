<?php
// admin_dashboard.php

require_once __DIR__ . '/session.php';        // Include session management
require_once __DIR__ . '/db.php';             // Include database connection
require_once __DIR__ . '/functions.php';      // Include common functions

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

// Fetch recent income entries (last 5)
$recent_income_stmt = $conn->prepare("SELECT description, amount, date FROM transactions WHERE type = 'income' ORDER BY date DESC LIMIT 5");
if ($recent_income_stmt) {
    $recent_income_stmt->execute();
    $recent_income_result = $recent_income_stmt->get_result();
    $recent_incomes = [];
    while ($income = $recent_income_result->fetch_assoc()) {
        $recent_incomes[] = $income;
    }
    $recent_income_stmt->close();
} else {
    $recent_incomes = [];
}

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
        .btn-custom {
            width: 200px;
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">Accounting Software</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" 
                aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
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
                    <!-- Added Manage Salaries and Process Salaries to Navigation -->
                    <li class="nav-item">
                        <a class="nav-link" href="manage_salaries.php">Manage Salaries</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="process_salaries.php">Process Salaries</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="export_salary_report.php">export_salary_report</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="salary_report.php">salary_report.php</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="add_income.php">Add Income</a>
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
            <div class="col-md-12">
                <div class="card text-white bg-dark">
                    <div class="card-body">
                        <h5 class="card-title">Net Balance</h5>
                        <p class="card-text" style="font-size: 2em;">$<?php echo number_format($net_balance, 2); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions: Manage Salaries and Process Salaries -->
        <div class="mt-5">
            <h3>Quick Actions</h3>
            <div class="d-flex gap-3 flex-wrap">
                <a href="manage_salaries.php" class="btn btn-primary btn-custom">Manage Salaries</a>
                <a href="process_salaries.php" class="btn btn-success btn-custom">Process Salaries</a>
                <a href="view_audit_logs.php" class="btn btn-secondary btn-custom">View Audit Logs</a>
                <a href="financial_reports.php" class="btn btn-info btn-custom">Financial Reports</a>
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

        <!-- Recent Income Entries -->
        <div class="mt-5">
            <h3>Recent Income Entries</h3>
            <?php if (!empty($recent_incomes)): ?>
                <table class="table table-bordered table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>Description</th>
                            <th>Amount ($)</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_incomes as $income): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($income['description']); ?></td>
                                <td><?php echo number_format($income['amount'], 2); ?></td>
                                <td><?php echo htmlspecialchars($income['date']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No recent income entries found.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Include Bootstrap JS (Optional) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
