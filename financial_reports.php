<?php
// financial_reports.php
require 'session.php';
require 'db.php';

// Initialize variables
$income = 0;
$expenses = 0;
$balance = 0;
$start_date = '';
$end_date = '';
$error = "";

// Handle Filters
if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    // User Report Filters
    if (isset($_GET['export_user_report']) || isset($_GET['admin_export'])) {
        // Export is handled in export_report.php
    } else {
        if (!empty($_GET['start_date']) && !empty($_GET['end_date'])) {
            $start_date = $_GET['start_date'];
            $end_date = $_GET['end_date'];

            // Validate dates
            if ($start_date > $end_date) {
                $error = "Start date cannot be greater than end date.";
            } else {
                // Calculate Total Income
                $stmt = $conn->prepare("SELECT SUM(amount) AS total_income FROM transactions WHERE user_id = ? AND type = 'income' AND date BETWEEN ? AND ?");
                $stmt->bind_param("iss", $_SESSION['user_id'], $start_date, $end_date);
                $stmt->execute();
                $stmt->bind_result($income_result);
                $stmt->fetch();
                $income = $income_result ? $income_result : 0;
                $stmt->close();

                // Calculate Total Expenses
                $stmt = $conn->prepare("SELECT SUM(amount) AS total_expenses FROM transactions WHERE user_id = ? AND type = 'expense' AND date BETWEEN ? AND ?");
                $stmt->bind_param("iss", $_SESSION['user_id'], $start_date, $end_date);
                $stmt->execute();
                $stmt->bind_result($expenses_result);
                $stmt->fetch();
                $expenses = $expenses_result ? $expenses_result : 0;
                $stmt->close();

                // Calculate Balance
                $balance = $income - $expenses;
            }
        } else {
            // If no date filter, calculate for all time
            // Total Income
            $stmt = $conn->prepare("SELECT SUM(amount) AS total_income FROM transactions WHERE user_id = ? AND type = 'income'");
            $stmt->bind_param("i", $_SESSION['user_id']);
            $stmt->execute();
            $stmt->bind_result($income_result);
            $stmt->fetch();
            $income = $income_result ? $income_result : 0;
            $stmt->close();

            // Total Expenses
            $stmt = $conn->prepare("SELECT SUM(amount) AS total_expenses FROM transactions WHERE user_id = ? AND type = 'expense'");
            $stmt->bind_param("i", $_SESSION['user_id']);
            $stmt->execute();
            $stmt->bind_result($expenses_result);
            $stmt->fetch();
            $expenses = $expenses_result ? $expenses_result : 0;
            $stmt->close();

            // Calculate Balance
            $balance = $income - $expenses;
        }
    }
}

// If Admin, can view all users' reports
if ($_SESSION['role'] == 'admin') {
    // Admin can filter by user as well
    $users = [];
    $result = $conn->query("SELECT id, username FROM users ORDER BY username ASC");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
    } else {
        die("Error fetching users: " . $conn->error);
    }

    // Initialize variables for admin report
    $admin_income = 0;
    $admin_expenses = 0;
    $admin_balance = 0;
    $selected_user = '';

    if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['admin_export'])) {
        $selected_user = $_GET['user_id'];
        $start_date_admin = $_GET['start_date_admin'];
        $end_date_admin = $_GET['end_date_admin'];

        // Validate dates
        if ($start_date_admin > $end_date_admin) {
            $error = "Start date cannot be greater than end date.";
        } else {
            // Calculate Total Income for Admin
            $stmt = $conn->prepare("SELECT SUM(amount) AS total_income FROM transactions WHERE user_id = ? AND type = 'income' AND date BETWEEN ? AND ?");
            $stmt->bind_param("iss", $selected_user, $start_date_admin, $end_date_admin);
            $stmt->execute();
            $stmt->bind_result($admin_income_result);
            $stmt->fetch();
            $admin_income = $admin_income_result ? $admin_income_result : 0;
            $stmt->close();

            // Calculate Total Expenses for Admin
            $stmt = $conn->prepare("SELECT SUM(amount) AS total_expenses FROM transactions WHERE user_id = ? AND type = 'expense' AND date BETWEEN ? AND ?");
            $stmt->bind_param("iss", $selected_user, $start_date_admin, $end_date_admin);
            $stmt->execute();
            $stmt->bind_result($admin_expenses_result);
            $stmt->fetch();
            $admin_expenses = $admin_expenses_result ? $admin_expenses_result : 0;
            $stmt->close();

            // Calculate Balance
            $admin_balance = $admin_income - $admin_expenses;
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Financial Reports</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        :root {
            --primary: #2C3E50;
            --secondary: #34495E;
            --success: #27AE60;
            --danger: #C0392B;
            --gray-100: #F7FAFC;
            --gray-200: #EDF2F7;
            --gray-300: #E2E8F0;
            --gray-400: #CBD5E0;
            --gray-500: #A0AEC0;
            --gray-600: #718096;
            --gray-700: #4A5568;
            --gray-800: #2D3748;
            --gray-900: #1A202C;
        }

        body {
            background-color: var(--gray-100);
            font-family: 'Segoe UI', sans-serif;
            color: var(--gray-800);
        }

        /* Header */
        .page-header {
            background: var(--primary);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }

        /* Report Cards */
        .report-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border: 1px solid var(--gray-200);
        }

        .stats-card {
            background: linear-gradient(to right, var(--gray-800), var(--gray-700));
            color: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .stats-value {
            font-size: 2rem;
            font-weight: 600;
            margin: 0.5rem 0;
        }

        .stats-label {
            color: var(--gray-400);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Filter Section */
        .filter-section {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border: 1px solid var(--gray-200);
        }

        .form-control, .form-select {
            border-radius: 8px;
            border: 2px solid var(--gray-300);
            padding: 0.75rem 1rem;
            background-color: white;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--gray-600);
            box-shadow: 0 0 0 3px rgba(113, 128, 150, 0.2);
        }

        /* Buttons */
        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .btn-primary {
            background: var(--gray-800);
            border: none;
        }

        .btn-primary:hover {
            background: var(--gray-900);
            transform: translateY(-1px);
        }

        .btn-export {
            background: var(--gray-600);
            color: white;
            border: none;
        }

        .btn-export:hover {
            background: var(--gray-700);
            transform: translateY(-1px);
        }

        /* Income/Expense Indicators */
        .income-text {
            color: var(--success);
            font-weight: 600;
        }

        .expense-text {
            color: var(--danger);
            font-weight: 600;
        }

        .balance-text {
            color: #3498db ;
            font-weight: 600;
        }

        /* Admin Section */
        .admin-section {
            background: var(--gray-100);
            border-radius: 12px;
            padding: 2rem;
            margin-top: 2rem;
            border: 1px solid var(--gray-300);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .stats-card {
                margin-bottom: 1rem;
            }
            
            .btn {
                width: 100%;
                margin-bottom: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="page-header">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <h1 class="h3 mb-0">Financial Reports</h1>
                <a href="user_dashboard.php" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Display Success or Error Messages -->
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Financial Overview -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="stats-card">
                    <div class="stats-label">Total Income</div>
                    <div class="stats-value income-text">
                        $<?php echo number_format($income, 2); ?>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card">
                    <div class="stats-label">Total Expenses</div>
                    <div class="stats-value expense-text">
                        $<?php echo number_format($expenses, 2); ?>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card">
                    <div class="stats-label">Net Balance</div>
                    <div class="stats-value balance-text">
                        $<?php echo number_format($balance, 2); ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="filter-section">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Start Date</label>
                    <input type="date" class="form-control" name="start_date" 
                           value="<?php echo htmlspecialchars($start_date); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">End Date</label>
                    <input type="date" class="form-control" name="end_date" 
                           value="<?php echo htmlspecialchars($end_date); ?>">
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="bi bi-funnel"></i> Filter
                    </button>
                    <a href="financial_reports.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-counterclockwise"></i> Reset
                    </a>
                </div>
            </form>
        </div>

        <!-- Export Section -->
        <div class="report-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4 class="mb-0">Export Reports</h4>
                <form method="POST" action="export_report.php">
                    <input type="hidden" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
                    <input type="hidden" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
                    <button type="submit" name="export_user_report" class="btn btn-export">
                        <i class="bi bi-download"></i> Export as CSV
                    </button>
                </form>
            </div>
            <!-- Chart for User Reports -->
            <canvas id="userReportChart" height="100"></canvas>
        </div>

        <!-- Admin Section -->
        <?php if ($_SESSION['role'] == 'admin'): ?>
        <div class="admin-section">
            <h4 class="mb-4">Admin Reports</h4>
            <form method="GET" class="row g-3">
                <input type="hidden" name="admin_export" value="1">
                
                <div class="col-md-3">
                    <label class="form-label">Select User</label>
                    <select class="form-select" name="user_id" required>
                        <option value="">Choose user...</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?php echo $user['id']; ?>" 
                                <?php echo (isset($_GET['user_id']) && $_GET['user_id'] == $user['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($user['username']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">Start Date</label>
                    <input type="date" class="form-control" name="start_date_admin" 
                           value="<?php echo isset($_GET['start_date_admin']) ? htmlspecialchars($_GET['start_date_admin']) : ''; ?>">
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">End Date</label>
                    <input type="date" class="form-control" name="end_date_admin" 
                           value="<?php echo isset($_GET['end_date_admin']) ? htmlspecialchars($_GET['end_date_admin']) : ''; ?>">
                </div>
                
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">Generate Report</button>
                </div>
            </form>

            <?php if (isset($_GET['admin_export'])): ?>
                <div class="report-card mt-4">
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <div class="stats-label">Total Income</div>
                            <div class="stats-value income-text">
                                $<?php echo number_format($admin_income, 2); ?>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="stats-label">Total Expenses</div>
                            <div class="stats-value expense-text">
                                $<?php echo number_format($admin_expenses, 2); ?>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="stats-label">Net Balance</div>
                            <div class="stats-value balance-text">
                                $<?php echo number_format($admin_balance, 2); ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0">Export Admin Report</h5>
                        <form method="POST" action="export_report.php">
                            <input type="hidden" name="admin_export" value="1">
                            <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($selected_user); ?>">
                            <input type="hidden" name="start_date_admin" value="<?php echo htmlspecialchars($start_date_admin); ?>">
                            <input type="hidden" name="end_date_admin" value="<?php echo htmlspecialchars($end_date_admin); ?>">
                            <button type="submit" name="export_admin_report" class="btn btn-export">
                                <i class="bi bi-download"></i> Export as CSV
                            </button>
                        </form>
                    </div>
                    <!-- Chart for Admin Reports -->
                    <canvas id="adminReportChart" height="100"></canvas>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Charts Section -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="report-card">
                    <h5 class="mb-4">Income vs Expenses Over Time</h5>
                    <canvas id="incomeExpenseChart" height="100"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Chart.js Scripts -->
    <script>
        // User Report Chart
        <?php if (!empty($start_date) && !empty($end_date)): ?>
            // Fetch data for the chart (e.g., monthly breakdown)
            // For simplicity, we'll use total income and expenses
            const userReportCtx = document.getElementById('userReportChart').getContext('2d');
            const userReportChart = new Chart(userReportCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Income', 'Expenses'],
                    datasets: [{
                        data: [<?php echo $income; ?>, <?php echo $expenses; ?>],
                        backgroundColor: ['#27AE60', '#C0392B'],
                        hoverBackgroundColor: ['#2ECC71', '#E74C3C']
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom',
                        },
                        title: {
                            display: false,
                        }
                    }
                },
            });
        <?php endif; ?>

        // Admin Report Chart
        <?php if ($_SESSION['role'] == 'admin' && isset($_GET['admin_export'])): ?>
            const adminReportCtx = document.getElementById('adminReportChart').getContext('2d');
            const adminReportChart = new Chart(adminReportCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Income', 'Expenses'],
                    datasets: [{
                        data: [<?php echo $admin_income; ?>, <?php echo $admin_expenses; ?>],
                        backgroundColor: ['#27AE60', '#C0392B'],
                        hoverBackgroundColor: ['#2ECC71', '#E74C3C']
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom',
                        },
                        title: {
                            display: false,
                        }
                    }
                },
            });
        <?php endif; ?>

        // Overall Income vs Expenses Chart
        <?php
        // Fetch data for the overall chart
        // Here we can create monthly data for the past 6 months
        $months = [];
        $income_data = [];
        $expense_data = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = date('Y-m', strtotime("-$i months"));
            $months[] = date('M Y', strtotime($month));

            // Income
            $stmt = $conn->prepare("SELECT SUM(amount) FROM transactions WHERE user_id = ? AND type = 'income' AND DATE_FORMAT(date, '%Y-%m') = ?");
            $stmt->bind_param("is", $_SESSION['user_id'], $month);
            $stmt->execute();
            $stmt->bind_result($monthly_income);
            $stmt->fetch();
            $income_data[] = $monthly_income ? $monthly_income : 0;
            $stmt->close();

            // Expenses
            $stmt = $conn->prepare("SELECT SUM(amount) FROM transactions WHERE user_id = ? AND type = 'expense' AND DATE_FORMAT(date, '%Y-%m') = ?");
            $stmt->bind_param("is", $_SESSION['user_id'], $month);
            $stmt->execute();
            $stmt->bind_result($monthly_expenses);
            $stmt->fetch();
            $expense_data[] = $monthly_expenses ? $monthly_expenses : 0;
            $stmt->close();
        }
        ?>

        const incomeExpenseCtx = document.getElementById('incomeExpenseChart').getContext('2d');
        const incomeExpenseChart = new Chart(incomeExpenseCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($months); ?>,
                datasets: [
                    {
                        label: 'Income',
                        data: <?php echo json_encode($income_data); ?>,
                        backgroundColor: '#27AE60',
                    },
                    {
                        label: 'Expenses',
                        data: <?php echo json_encode($expense_data); ?>,
                        backgroundColor: '#C0392B',
                    }
                ]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    title: {
                        display: true,
                        text: 'Income vs Expenses Over the Past 6 Months'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value, index, values) {
                                return '$' + value;
                            }
                        }
                    }
                }
            },
        });
    </script>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
