<?php
// salary_report.php

// Enable error reporting temporarily for debugging (remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include session.php from main directory using relative path
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
?>
<!DOCTYPE html>
<html>
<head>
    <title>Monthly Salary Report - <?php echo htmlspecialchars($report_year); ?></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .table-responsive {
            max-height: 800px;
            overflow-y: auto;
        }
        th, td {
            text-align: center;
            vertical-align: middle;
        }
        .total-column {
            background-color: #f8f9fa;
            font-weight: bold;
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
                        <a class="nav-link" href="admin_dashboard.php">Dashboard</a>
                    </li>
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
                        <a class="nav-link" href="manage_salaries.php">Manage Salaries</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="process_salaries.php">Process Salaries</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="financial_reports.php">Financial Reports</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="view_audit_logs.php">View Audit Logs</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">Logout (<?php echo htmlspecialchars($_SESSION['username']); ?>)</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Salary Report Content -->
    <div class="container mt-4">
        <h2>Monthly Salary Report - <?php echo htmlspecialchars($report_year); ?></h2>
        <div class="mb-3">
            <!-- Optional: Allow admin to select year -->
            <!-- 
            <form method="GET" action="salary_report.php" class="row g-3">
                <div class="col-auto">
                    <label for="year" class="col-form-label">Select Year:</label>
                </div>
                <div class="col-auto">
                    <select name="year" id="year" class="form-select">
                        <?php
                        $current_year = date('Y');
                        for ($y = $current_year - 5; $y <= $current_year + 1; $y++) {
                            $selected = ($y == $report_year) ? 'selected' : '';
                            echo "<option value=\"$y\" $selected>$y</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary mb-3">Generate Report</button>
                </div>
            </form>
            -->
        </div>
        <div class="table-responsive">
            <table class="table table-bordered table-hover">
                <thead class="table-dark">
                    <tr>
                        <th rowspan="2">Username</th>
                        <?php foreach ($months as $num => $name): ?>
                            <th><?php echo htmlspecialchars($name); ?></th>
                        <?php endforeach; ?>
                        <th rowspan="2">Total Salary</th>
                    </tr>
                    <tr>
                        <?php foreach ($months as $num => $name): ?>
                            <th><?php echo htmlspecialchars($name); ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    foreach ($users as $user): 
                        $user_id = $user['id'];
                        $username = $user['username'];
                        $total_salary = 0;
                    ?>
                        <tr>
                            <td><?php echo htmlspecialchars($username); ?></td>
                            <?php 
                            for ($m = 1; $m <= 12; $m++): 
                                $salary = isset($salary_data[$user_id][$m]) ? $salary_data[$user_id][$m] : 0;
                                $total_salary += $salary;
                            ?>
                                <td><?php echo number_format($salary, 2); ?></td>
                            <?php endfor; ?>
                            <td><?php echo number_format($total_salary, 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="table-secondary">
                    <tr>
                        <th>Total</th>
                        <?php 
                        $monthly_totals = [];
                        foreach ($months as $num => $name) {
                            $monthly_totals[$num] = 0;
                        }
                        $grand_total = 0;
                        foreach ($users as $user) {
                            $user_id = $user['id'];
                            for ($m = 1; $m <= 12; $m++) {
                                $salary = isset($salary_data[$user_id][$m]) ? $salary_data[$user_id][$m] : 0;
                                $monthly_totals[$m] += $salary;
                                $grand_total += $salary;
                            }
                        }
                        foreach ($months as $num => $name) {
                            echo "<th>" . number_format($monthly_totals[$num], 2) . "</th>";
                        }
                        ?>
                        <th><?php echo number_format($grand_total, 2); ?></th>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Optional: Export Button -->
        <div class="mt-3">
            <a href="export_salary_report.php?year=<?php echo htmlspecialchars($report_year); ?>" class="btn btn-success">Export as CSV</a>
            <!-- Similarly, add Export as Excel if implemented -->
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
