<?php
// view_audit_logs.php

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

// Pagination Settings
$limit = 20; // Number of logs per page
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Fetch total number of audit logs
$total_logs_result = $conn->query("SELECT COUNT(*) as total FROM audit_logs");
if (!$total_logs_result) {
    die("Query failed (Total Logs): (" . $conn->errno . ") " . $conn->error);
}
$total_logs = $total_logs_result->fetch_assoc()['total'] ?? 0;

// Calculate total pages
$total_pages = ceil($total_logs / $limit);

// Fetch audit logs with user details
$stmt = $conn->prepare("SELECT audit_logs.id, users.username, audit_logs.action, audit_logs.details, audit_logs.timestamp 
                        FROM audit_logs 
                        LEFT JOIN users ON audit_logs.user_id = users.id 
                        ORDER BY audit_logs.timestamp DESC 
                        LIMIT ? OFFSET ?");
if (!$stmt) {
    die("Prepare failed (Fetch Audit Logs): (" . $conn->errno . ") " . $conn->error);
}

$stmt->bind_param("ii", $limit, $offset);
if (!$stmt->execute()) {
    die("Execute failed (Fetch Audit Logs): (" . $stmt->errno . ") " . $stmt->error);
}

$result = $stmt->get_result();
$audit_logs = [];
while ($row = $result->fetch_assoc()) {
    $audit_logs[] = $row;
}
$stmt->close();
?>
<!DOCTYPE html>
<html>
<head>
    <title>View Audit Logs</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Add Font Awesome CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .table-responsive {
            max-height: 600px;
        }
        .pagination {
            justify-content: center;
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <i class="fas fa-calculator me-2"></i>Financial Management System
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" 
                aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="admin_dashboard.php">
                            <i class="fas fa-tachometer-alt me-1"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="manage_users.php">
                            <i class="fas fa-users me-1"></i>Manage Users
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="manage_transactions.php">
                            <i class="fas fa-exchange-alt me-1"></i>Transactions
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="manage_categories.php">
                            <i class="fas fa-tags me-1"></i>Categories
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="manage_salaries.php">
                            <i class="fas fa-money-check-alt me-1"></i>Manage Salaries
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="process_salaries.php">
                            <i class="fas fa-money-bill-wave me-1"></i>Process Salaries
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="financial_reports.php">
                            <i class="fas fa-chart-bar me-1"></i>Financial Reports
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="view_audit_logs.php">
                            <i class="fas fa-history me-1"></i>Audit Logs
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">
                            <i class="fas fa-sign-out-alt me-1"></i>Logout (<?php echo htmlspecialchars($_SESSION['username']); ?>)
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Audit Logs Content -->
    <div class="container mt-4">
        <h2>Audit Logs</h2>

        <!-- Search and Filter (Optional) -->
        <!-- 
        <form method="GET" action="view_audit_logs.php" class="row g-3 mb-4">
            <div class="col-md-4">
                <input type="text" name="username" class="form-control" placeholder="Username" value="<?php echo htmlspecialchars($_GET['username'] ?? ''); ?>">
            </div>
            <div class="col-md-3">
                <select name="action" class="form-select">
                    <option value="">All Actions</option>
                    <option value="Logged In" <?php echo (isset($_GET['action']) && $_GET['action'] == 'Logged In') ? 'selected' : ''; ?>>Logged In</option>
                    <option value="Updated Salary" <?php echo (isset($_GET['action']) && $_GET['action'] == 'Updated Salary') ? 'selected' : ''; ?>>Updated Salary</option>
                    <!-- Add more actions as needed 
                </select>
            </div>
            <div class="col-md-3">
                <input type="date" name="start_date" class="form-control" placeholder="Start Date" value="<?php echo htmlspecialchars($_GET['start_date'] ?? ''); ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary">Filter</button>
            </div>
        </form>
        -->

        <!-- Display Audit Logs -->
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
                    <?php if (count($audit_logs) > 0): ?>
                        <?php foreach ($audit_logs as $log): ?>
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
                            <td colspan="5" class="text-center">No audit logs found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <nav aria-label="Audit Logs Pagination">
                <ul class="pagination">
                    <!-- Previous Page Link -->
                    <li class="page-item <?php if ($page <= 1) echo 'disabled'; ?>">
                        <a class="page-link" href="?page=<?php echo $page - 1; ?>" aria-label="Previous">
                            <span aria-hidden="true">&laquo;</span>
                        </a>
                    </li>

                    <!-- Page Number Links -->
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?php if ($page == $i) echo 'active'; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>

                    <!-- Next Page Link -->
                    <li class="page-item <?php if ($page >= $total_pages) echo 'disabled'; ?>">
                        <a class="page-link" href="?page=<?php echo $page + 1; ?>" aria-label="Next">
                            <span aria-hidden="true">&raquo;</span>
                        </a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
