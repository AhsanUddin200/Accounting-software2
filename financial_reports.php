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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .navbar {
            background-color: #4169E1;
        }

        .navbar-brand {
            color: white !important;
        }

        .nav-link {
            color: white !important;
        }

        .report-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            text-align: center;
            margin-bottom: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            height: 100%;
        }

        .report-card:hover {
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
            transform: translateY(-5px);
            transition: all 0.3s ease;
        }

        .report-icon {
            font-size: 3em;
            color: #4169E1;
            margin-bottom: 20px;
        }

        .card-title {
            color: #333;
            font-size: 1.5em;
            margin-bottom: 15px;
        }

        .card-text {
            color: #666;
            margin-bottom: 25px;
            min-height: 50px;
        }

        .btn-view {
            background-color: #4169E1;
            color: white;
            padding: 10px 25px;
            border-radius: 25px;
            text-decoration: none;
            display: inline-block;
            transition: background-color 0.3s ease;
        }

        .btn-view:hover {
            background-color: #2a4db7;
            color: white;
        }

        .container {
            max-width: 1200px;
            padding: 40px 20px;
        }
    </style>
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <img src="https://images.crunchbase.com/image/upload/c_pad,h_170,w_170,f_auto,b_white,q_auto:eco,dpr_1/v1436326579/fv5juvmpaq9zxgnkueof.png" alt="FMS Logo" height="40" class="me-2">
                Financial Reports
            </a>
            <div class="ms-auto">
                <a href="<?php echo $_SESSION['role'] == 'admin' ? 'admin_dashboard.php' : 'user_dashboard.php'; ?>" class="nav-link">
                    <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
                </a>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="row justify-content-center">
            <!-- First Row -->
            <div class="col-md-5 mb-4">
                <div class="report-card">
                    <i class="fas fa-book-open report-icon"></i>
                    <h3 class="card-title">Ledgers</h3>
                    <p class="card-text">View and manage detailed ledger entries for all accounts</p>
                    <a href="view_ledgers.php" class="btn-view">
                        View Ledgers <i class="fas fa-arrow-right ms-2"></i>
                    </a>
                </div>
            </div>
            
            <div class="col-md-5 mb-4">
                <div class="report-card">
                    <i class="fas fa-balance-scale report-icon"></i>
                    <h3 class="card-title">Trial Balance</h3>
                    <p class="card-text">View the trial balance report showing debits and credits</p>
                    <a href="new_trial_balance.php" class="btn-view">
                        View Trial Balance <i class="fas fa-arrow-right ms-2"></i>
                    </a>
                </div>
            </div>

            <!-- Second Row -->
            <div class="col-md-5 mb-4">
                <div class="report-card">
                    <i class="fas fa-chart-line report-icon"></i>
                    <h3 class="card-title">Income Statement</h3>
                    <p class="card-text">View profit and loss statement for selected periods</p>
                    <a href="income_statement.php" class="btn-view">
                        View Income Statement <i class="fas fa-arrow-right ms-2"></i>
                    </a>
                </div>
            </div>
            
            <div class="col-md-5 mb-4">
                <div class="report-card">
                    <i class="fas fa-file-invoice-dollar report-icon"></i>
                    <h3 class="card-title">Balance Sheet</h3>
                    <p class="card-text">View assets, liabilities, and equity statement</p>
                    <a href="balance_sheet.php" class="btn-view">
                        View Balance Sheet <i class="fas fa-arrow-right ms-2"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
