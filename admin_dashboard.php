<?php
// admin_dashboard.php

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

// Security check
if ($_SESSION['role'] != 'admin') {
    header("Location: user_dashboard.php");
    exit();
}

// Set current month dates for the dashboard
$period_start = date('Y-m-01'); // First day of current month
$period_end = date('Y-m-t');    // Last day of current month

// Base query condition
$cost_center_condition = "";
$cost_center_params = [];
if (isset($_SESSION['cost_center_id'])) {
    $cost_center_condition = "AND cost_center_id = ?";
    $cost_center_params[] = $_SESSION['cost_center_id'];
}

// Income Total with YTD
$income_query = "SELECT 
    SUM(CASE WHEN date BETWEEN ? AND ? THEN amount ELSE 0 END) as current_amount,
    SUM(CASE WHEN YEAR(date) = YEAR(CURRENT_DATE()) THEN amount ELSE 0 END) as ytd_amount
    FROM transactions 
    WHERE type = 'income' $cost_center_condition";

$stmt = $conn->prepare($income_query);
if (isset($_SESSION['cost_center_id'])) {
    $stmt->bind_param("ssi", $period_start, $period_end, $_SESSION['cost_center_id']);
} else {
    $stmt->bind_param("ss", $period_start, $period_end);
}
$stmt->execute();
$income_data = $stmt->get_result()->fetch_assoc();
$income_total = $income_data['current_amount'] ?? 0;
$income_ytd = $income_data['ytd_amount'] ?? 0;

// Expenses Total with YTD
$expense_query = "SELECT 
    SUM(CASE WHEN date BETWEEN ? AND ? THEN amount ELSE 0 END) as current_amount,
    SUM(CASE WHEN YEAR(date) = YEAR(CURRENT_DATE()) THEN amount ELSE 0 END) as ytd_amount
    FROM transactions 
    WHERE type = 'expense' $cost_center_condition";

$stmt = $conn->prepare($expense_query);
if (isset($_SESSION['cost_center_id'])) {
    $stmt->bind_param("ssi", $period_start, $period_end, $_SESSION['cost_center_id']);
} else {
    $stmt->bind_param("ss", $period_start, $period_end);
}
$stmt->execute();
$expense_data = $stmt->get_result()->fetch_assoc();
$expense_total = $expense_data['current_amount'] ?? 0;
$expense_ytd = $expense_data['ytd_amount'] ?? 0;

// Assets Total with YTD
$assets_query = "SELECT 
    SUM(CASE WHEN date BETWEEN ? AND ? THEN amount ELSE 0 END) as current_amount,
    SUM(CASE WHEN YEAR(date) = YEAR(CURRENT_DATE()) THEN amount ELSE 0 END) as ytd_amount
    FROM transactions 
    WHERE type = 'asset' $cost_center_condition";

$stmt = $conn->prepare($assets_query);
if (isset($_SESSION['cost_center_id'])) {
    $stmt->bind_param("ssi", $period_start, $period_end, $_SESSION['cost_center_id']);
} else {
    $stmt->bind_param("ss", $period_start, $period_end);
}
$stmt->execute();
$assets_data = $stmt->get_result()->fetch_assoc();
$assets_total = $assets_data['current_amount'] ?? 0;
$assets_ytd = $assets_data['ytd_amount'] ?? 0;

// Liabilities Total with YTD
$liabilities_query = "SELECT 
    SUM(CASE WHEN date BETWEEN ? AND ? THEN amount ELSE 0 END) as current_amount,
    SUM(CASE WHEN YEAR(date) = YEAR(CURRENT_DATE()) THEN amount ELSE 0 END) as ytd_amount
    FROM transactions 
    WHERE type = 'liability' $cost_center_condition";

$stmt = $conn->prepare($liabilities_query);
if (isset($_SESSION['cost_center_id'])) {
    $stmt->bind_param("ssi", $period_start, $period_end, $_SESSION['cost_center_id']);
} else {
    $stmt->bind_param("ss", $period_start, $period_end);
}
$stmt->execute();
$liabilities_data = $stmt->get_result()->fetch_assoc();
$liabilities_total = $liabilities_data['current_amount'] ?? 0;
$liabilities_ytd = $liabilities_data['ytd_amount'] ?? 0;

// Equities Total with YTD
$equities_query = "SELECT 
    SUM(CASE WHEN date BETWEEN ? AND ? THEN amount ELSE 0 END) as current_amount,
    SUM(CASE WHEN YEAR(date) = YEAR(CURRENT_DATE()) THEN amount ELSE 0 END) as ytd_amount
    FROM transactions 
    WHERE type = 'equity' $cost_center_condition";

$stmt = $conn->prepare($equities_query);
if (isset($_SESSION['cost_center_id'])) {
    $stmt->bind_param("ssi", $period_start, $period_end, $_SESSION['cost_center_id']);
} else {
    $stmt->bind_param("ss", $period_start, $period_end);
}
$stmt->execute();
$equities_data = $stmt->get_result()->fetch_assoc();
$equities_total = $equities_data['current_amount'] ?? 0;
$equities_ytd = $equities_data['ytd_amount'] ?? 0;

// Calculate Net Balance for both current period and YTD
$net_balance = $income_total - $expense_total;
$net_balance_ytd = $income_ytd - $expense_ytd;

// Fetch total number of users
$user_count_result = $conn->query("SELECT COUNT(*) as total_users FROM users");
$user_count = $user_count_result->fetch_assoc()['total_users'] ?? 0;

// Fetch recent audit logs
$logs_query = "SELECT audit_logs.*, users.username 
               FROM audit_logs 
               LEFT JOIN users ON audit_logs.user_id = users.id 
               WHERE 1=1 ";
if (isset($_SESSION['cost_center_id'])) {
    $logs_query .= "AND users.cost_center_id = ? ";
}
$logs_query .= "ORDER BY audit_logs.timestamp DESC LIMIT 5";

$stmt = $conn->prepare($logs_query);
if (isset($_SESSION['cost_center_id'])) {
    $stmt->bind_param("i", $_SESSION['cost_center_id']);
}
$stmt->execute();
$recent_logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch statistics
$transaction_count_result = $conn->query("SELECT COUNT(*) as total_transactions FROM transactions");
$total_transactions = $transaction_count_result->fetch_assoc()['total_transactions'] ?? 0;

$category_count_result = $conn->query("SELECT COUNT(*) as total_categories FROM categories");
$total_categories = $category_count_result->fetch_assoc()['total_categories'] ?? 0;

// Fetch recent income entries
$income_entries_query = "SELECT description, amount, date 
                        FROM transactions 
                        WHERE type = 'income' ";
if (isset($_SESSION['cost_center_id'])) {
    $income_entries_query .= "AND cost_center_id = ? ";
}
$income_entries_query .= "ORDER BY date DESC LIMIT 5";

$stmt = $conn->prepare($income_entries_query);
if (isset($_SESSION['cost_center_id'])) {
    $stmt->bind_param("i", $_SESSION['cost_center_id']);
}
$stmt->execute();
$recent_incomes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Log dashboard view
log_action($conn, $_SESSION['user_id'], 'Viewed Admin Dashboard', 'Admin accessed the dashboard.');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | Financial Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3f37c9;
            --success-color: #4bb543;
            --info-color: #4895ef;
            --warning-color: #f72585;
            --danger-color: #ef233c;
            --light-color: #f8f9fa;
            --dark-color: #212529;
        }

        body {
            background-color: #f5f6fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .navbar {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .navbar-brand {
            font-weight: 600;
            color: white !important;
        }

        .nav-link {
            color: rgba(255,255,255,0.9) !important;
            transition: all 0.3s ease;
        }

        .nav-link:hover {
            color: white !important;
            transform: translateY(-1px);
        }

        .dashboard-card {
            border-radius: 15px;
            border: none;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            background: white;
            box-shadow: 0 4px 6px rgba(0,0,0,0.07);
        }

        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.1);
        }

        .stat-card {
            padding: 1.5rem;
            border-radius: 15px;
            color: white;
            position: relative;
            overflow: hidden;
            transition: all 0.4s ease;
            z-index: 1;
            height: 200px;
            cursor: pointer;
        }

        /* 3D Flip Effect */
        .stat-card-inner {
            position: relative;
            width: 100%;
            height: 100%;
            transition: transform 0.6s;
            transform-style: preserve-3d;
        }

        .stat-card:hover .stat-card-inner {
            transform: rotateY(180deg);
        }

        .stat-card-front, .stat-card-back {
            position: absolute;
            width: 100%;
            height: 100%;
            backface-visibility: hidden;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }

        .stat-card-back {
            transform: rotateY(180deg);
            background: inherit;
            padding: 20px;
            text-align: center;
        }

        /* Sparkle Effect */
        .stat-card::after {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(
                45deg,
                transparent 0%,
                rgba(255, 255, 255, 0.1) 45%,
                rgba(255, 255, 255, 0.5) 50%,
                rgba(255, 255, 255, 0.1) 55%,
                transparent 100%
            );
            transform: rotate(45deg);
            transition: all 0.5s;
            opacity: 0;
            z-index: -1;
        }

        .stat-card:hover::after {
            opacity: 1;
            animation: sparkle 1s ease-in-out;
        }

        @keyframes sparkle {
            0% { transform: translateX(-100%) rotate(45deg); }
            100% { transform: translateX(100%) rotate(45deg); }
        }

        /* Back side styling */
        .stat-card-back h4 {
            margin-bottom: 15px;
            font-size: 1.2rem;
            font-weight: bold;
        }

        .stat-card-back p {
            margin: 5px 0;
            font-size: 0.9rem;
        }

        .stat-card-back i {
            margin-right: 5px;
            width: 20px;
            text-align: center;
        }

        .stat-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            font-size: 1rem;
            opacity: 0.9;
        }

        .quick-action-btn {
            padding: 1rem 1.5rem;
            border-radius: 10px;
            font-weight: 500;
            transition: all 0.3s ease;
            border: none;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            min-width: 200px;
            text-align: center;
            flex: 1 1 0;
            max-width: calc(25% - 1rem);
        }

        .quick-action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }

        .table {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0,0,0,0.07);
        }

        .table thead th {
            background-color: var(--primary-color);
            color: white;
            font-weight: 500;
            border: none;
        }

        .table-hover tbody tr:hover {
            background-color: rgba(67, 97, 238, 0.05);
        }

        .section-title {
            color: var(--dark-color);
            font-weight: 600;
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--primary-color);
        }

        .progress {
            height: 10px;
            border-radius: 5px;
        }

        .card-footer {
            background: transparent;
            border-top: 1px solid rgba(0,0,0,0.05);
        }

        @media (max-width: 768px) {
            .stat-card {
                margin-bottom: 1rem;
            }
        }

        .d-flex.gap-3 {
            display: flex;
            flex-wrap: wrap;
            justify-content: flex-start;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
            <img src="https://images.crunchbase.com/image/upload/c_pad,h_170,w_170,f_auto,b_white,q_auto:eco,dpr_1/v1436326579/fv5juvmpaq9zxgnkueof.png" alt="FMS Logo" height="40" class="me-2">
                <i class=" me-2"></i>Financial Management System
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="manage_users.php">
                            <i class="fas fa-users me-1"></i> Users
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="manage_transactions.php">
                            <i class="fas fa-exchange-alt me-1"></i> Transactions
                        </a>
                        
                    </li>
                    <!-- <li class="nav-item">
                        <a class="nav-link" href="manage_categories.php">
                            <i class="fas fa-tags me-1"></i> Categories
                        </a>
                    </li> -->
                    <li class="nav-item">
                        <a class="nav-link" href="financial_reports.php">
                            <i class="fas fa-file-alt me-1"></i> Reports
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="add_transaction.php">
                            <i class="fas fa-plus me-1"></i>  Add New Transaction
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="manage_salaries.php">
                            <i class="fas fa-money-bill-wave me-1"></i> Salaries
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="laptop_report.php">
                            <i class="fas fa-laptop me-1"></i> Laptops
                        </a>
                    </li>
                    
                    <li class="nav-item">
                        <a class="nav-link" href="stock_report.php">
                            <i class="fas fa-boxes me-1"></i> Stock
                        </a>
                    </li>

                    <!-- Add Analytics Dashboard Button -->
                    <li class="nav-item">
                        <a class="nav-link" href="analytics_dashboard.php">
                            <i class="fas fa-chart-line me-1"></i> Analytics
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link" href="view_ledgers.php">
                            <i class="fas fa-book"></i> View Ledgers
                        </a>
                    </li>
                    <li class="nav-item">
    <a class="nav-link" href="library_dashboard.php">
        <i class="fas fa-atlas me-1"></i> Library
    </a>
</li>
                    <li class="nav-item">
                        <a class="nav-link" href="upload_attendance.php">
                            <i class="fas fa-clock me-1"></i> Upload Attendance
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">
                            <i class="fas fa-sign-out-alt me-1"></i> Logout
                            (<?php echo htmlspecialchars($_SESSION['username']); ?>)
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container mt-4">
        <!-- Welcome Section -->
        <div class="row mb-4">
            <div class="col">
                <h2 class="section-title">
                    <i class="fas fa-tachometer-alt me-2"></i>Dashboard Overview
                </h2>
            </div>
        </div>

        <!-- Stats Cards with Period -->
        <div class="period-header mb-3">
            <h4>
                <i class="fas fa-calendar me-2"></i>
                Period: <?php echo date('d M Y', strtotime($period_start)) . " - " . date('d M Y', strtotime($period_end)); ?>
            </h4>
        </div>

        <div class="row mb-4">
            <!-- Income Card -->
            <div class="col-md-4 mb-3">
                <div class="dashboard-card stat-card" 
                     onclick="handleCardClick(this, 'transaction_details.php?type=income')"
                     style="background: linear-gradient(135deg, #27ae60, #2ecc71)">
                    <div class="stat-card-inner">
                        <div class="stat-card-front">
                            <div class="stat-icon">
                                <i class="fas fa-money-bill-wave"></i>
                            </div>
                            <div class="stat-value">PKR <?php echo number_format($income_total, 2); ?></div>
                            <div class="stat-label">Total Income</div>
                        </div>
                        <div class="stat-card-back">
                            <h4>Income Analysis</h4>
                          
                            <p><i class="fas fa-calendar-alt"></i> YTD: PKR <?php echo number_format($income_ytd, 2); ?></p>
                            <p><i class="fas fa-chart-bar"></i> Avg Monthly: PKR <?php echo number_format($income_ytd / date('n'), 2); ?></p>
                            <p><i class="fas fa-clock"></i> Last Updated: <?php echo date('d M Y'); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Expenses Card -->
            <div class="col-md-4 mb-3">
                <div class="dashboard-card stat-card" 
                     onclick="handleCardClick(this, 'transaction_details.php?type=expense')"
                     style="background: linear-gradient(135deg, #e74c3c, #c0392b)">
                    <div class="stat-card-inner">
                        <div class="stat-card-front">
                            <div class="stat-icon">
                                <i class="fas fa-shopping-cart"></i>
                            </div>
                            <div class="stat-value">PKR <?php echo number_format($expense_total, 2); ?></div>
                            <div class="stat-label">Total Expenses</div>
                        </div>
                        <div class="stat-card-back">
                            <h4>Expense Analysis</h4>
                          
                            <p><i class="fas fa-calendar-alt"></i> YTD: PKR <?php echo number_format($expense_ytd, 2); ?></p>
                            <p><i class="fas fa-chart-bar"></i> Avg Monthly: PKR <?php echo number_format($expense_ytd / date('n'), 2); ?></p>
                            <p><i class="fas fa-clock"></i> Last Updated: <?php echo date('d M Y'); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Assets Card -->
            <div class="col-md-4 mb-3">
                <div class="dashboard-card stat-card" 
                     onclick="handleCardClick(this, 'transaction_details.php?type=asset')"
                     style="background: linear-gradient(135deg, #3498db, #2980b9)">
                    <div class="stat-card-inner">
                        <div class="stat-card-front">
                            <div class="stat-icon">
                                <i class="fas fa-landmark"></i>
                            </div>
                            <div class="stat-value">PKR <?php echo number_format($assets_total, 2); ?></div>
                            <div class="stat-label">Total Assets</div>
                        </div>
                        <div class="stat-card-back">
                            <h4>Asset Analysis</h4>
                       
                            <p><i class="fas fa-calendar-alt"></i> YTD: PKR <?php echo number_format($assets_ytd, 2); ?></p>
                            <p><i class="fas fa-chart-bar"></i> Avg Monthly: PKR <?php echo number_format($assets_ytd / date('n'), 2); ?></p>
                            <p><i class="fas fa-clock"></i> Last Updated: <?php echo date('d M Y'); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Liabilities Card -->
            <div class="col-md-4 mb-3">
                <div class="dashboard-card stat-card" 
                     onclick="handleCardClick(this, 'transaction_details.php?type=liability')"
                     style="background: linear-gradient(135deg, #9b59b6, #8e44ad)">
                    <div class="stat-card-inner">
                        <div class="stat-card-front">
                            <div class="stat-icon">
                                <i class="fas fa-hand-holding-usd"></i>
                            </div>
                            <div class="stat-value">PKR <?php echo number_format($liabilities_total, 2); ?></div>
                            <div class="stat-label">Total Liabilities</div>
                        </div>
                        <div class="stat-card-back">
                            <h4>Liability Analysis</h4>
                           
                            <p><i class="fas fa-calendar-alt"></i> YTD: PKR <?php echo number_format($liabilities_ytd, 2); ?></p>
                            <p><i class="fas fa-chart-bar"></i> Avg Monthly: PKR <?php echo number_format($liabilities_ytd / date('n'), 2); ?></p>
                            <p><i class="fas fa-clock"></i> Last Updated: <?php echo date('d M Y'); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Equities Card -->
            <div class="col-md-4 mb-3">
                <div class="dashboard-card stat-card" 
                     onclick="handleCardClick(this, 'transaction_details.php?type=equity')"
                     style="background: linear-gradient(135deg, #f1c40f, #f39c12)">
                    <div class="stat-card-inner">
                        <div class="stat-card-front">
                            <div class="stat-icon">
                                <i class="fas fa-chart-pie"></i>
                            </div>
                            <div class="stat-value">PKR <?php echo number_format($equities_total, 2); ?></div>
                            <div class="stat-label">Total Equities</div>
                        </div>
                        <div class="stat-card-back">
                            <h4>Equity Analysis</h4>
                            
                            <p><i class="fas fa-calendar-alt"></i> YTD: PKR <?php echo number_format($equities_ytd, 2); ?></p>
                            <p><i class="fas fa-chart-bar"></i> Avg Monthly: PKR <?php echo number_format($equities_ytd / date('n'), 2); ?></p>
                            <p><i class="fas fa-clock"></i> Last Updated: <?php echo date('d M Y'); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Net Balance Card -->
            <div class="col-md-4 mb-3">
                <div class="dashboard-card stat-card" style="background: linear-gradient(135deg, #1abc9c, #16a085)">
                    <div class="stat-card-inner">
                        <div class="stat-card-front">
                            <div class="stat-icon">
                                <i class="fas fa-balance-scale"></i>
                            </div>
                            <div class="stat-value">PKR <?php echo number_format($net_balance, 2); ?></div>
                            <div class="stat-label">Net Balance</div>
                        </div>
                        <div class="stat-card-back">
                            <h4>Net Balance Analysis</h4>
                            
                            <p><i class="fas fa-calendar-alt"></i> YTD: PKR <?php echo number_format($net_balance_ytd, 2); ?></p>
                            <p><i class="fas fa-clock"></i> Last Updated: <?php echo date('d M Y'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row mb-4">
            <div class="col">
                <h3 class="section-title">
                    <i class="fas fa-bolt me-2"></i>Quick Actions
                </h3>
                <div class="d-flex gap-3 flex-wrap">
                    <!-- Add Import Data button -->
                    <a href="import_transactions.php" class="quick-action-btn btn btn-primary flex-fill">
                        <i class="fas fa-f  me-2"></i>Import Finja Data
                    </a>

                    <!-- Add Manage Sub Categories button -->
                    <a href="manage_subcategories.php" class="quick-action-btn btn btn-primary flex-fill">
                        <i class="fas fa-sitemap me-2"></i>Manage Sub Categories
                    </a>

                    <!-- Existing buttons -->
                    <a href="petty_cash_expense.php" class="quick-action-btn btn btn-info flex-fill">
                        <i class="fas fa-money-bill-alt me-2"></i>Petty Cash
                    </a>
                    <a href="manage_salaries.php" class="quick-action-btn btn btn-primary flex-fill">
                        <i class="fas fa-money-check-alt me-2"></i>Manage Salaries
                    </a>
                    <a href="process_salaries.php" class="quick-action-btn btn btn-success flex-fill">
                        <i class="fas fa-tasks me-2"></i>Process Salaries
                    </a>
                    <!-- <a href="upload_attendance.php" class="quick-action-btn btn btn-secondary flex-fill">
                        <i class="fas fa-clock me-2"></i>Upload Attendance
                    </a> -->
                    <a href="view_audit_logs.php" class="quick-action-btn btn btn-info flex-fill">
                        <i class="fas fa-history me-2"></i>View Audit Logs
                    </a>
                    <a href="financial_reports.php" class="quick-action-btn btn btn-warning flex-fill">
                        <i class="fas fa-chart-bar me-2"></i>Financial Reports
                    </a>
                    <a href="accounting.php" class="quick-action-btn btn flex-fill" style="background-color: #0066ff; color: white;">
                        <i class="fas fa-book-open me-2"></i>Accounting
                    </a>
                    <a href="request_ledger_head.php" class="quick-action-btn btn btn-success flex-fill">
                        <i class="fas fa-plus-circle me-2"></i>Request New Ledger Head
                    </a>
                    <a href="view_petty_cash.php" class="quick-action-btn btn btn-info flex-fill">
    <i class="fas fa-money-bill-wave me-2"></i>View Petty Cash
</a>

                    <a href="cleanup_system.php" class="quick-action-btn btn btn-danger flex-fill">
                        <i class="fas fa-trash-alt me-2"></i>System Cleanup
                         <div class="text-muted" style="font-size: 0.75rem; margin-top: 4px;">
                    (use before production)
                </div>

                
                
                        
                    </a>
                      <?php 
                    // Show Create Sub User button for saim, admin, or any super admin (no cost center)
                    if (empty($_SESSION['cost_center_id']) && 
                        ($_SESSION['role'] === 'super_admin' || 
                         $_SESSION['username'] === 'saim' || 
                         $_SESSION['username'] === 'admin')): 
                    ?>
                    <div class="col-auto">
    <a href="create_user.php" class="btn btn-warning" style="width: 200px; height: 70px; font-size: 16px; font-weight: bold; color: #000; display: flex; align-items: center; justify-content: center;">
        <i class="fas fa-user-plus me-2"></i>Create Admin User
    </a>
</div>
                    <?php endif; ?>
                    
                </div>
            </div>
        </div>

        <!-- Recent Activities and Income -->
        <div class="row">
            <!-- Recent Activities -->
            <div class="col-md-7 mb-4">
                <h3 class="section-title">
                    <i class="fas fa-clock me-2"></i>Recent Activities
                </h3>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Action</th>
                                <th>Details</th>
                                <th>Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_logs as $log): ?>
                            <tr>
                                <td>
                                    <i class="fas fa-user-circle me-2"></i>
                                    <?php echo htmlspecialchars($log['username'] ?? 'System'); ?>
                                </td>
                                <td><?php echo htmlspecialchars($log['action']); ?></td>
                                <td><?php echo htmlspecialchars($log['details']); ?></td>
                                <td><?php echo htmlspecialchars(date('M d, H:i', strtotime($log['timestamp']))); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Recent Income -->
            <div class="col-md-5 mb-4">
                <h3 class="section-title">
                    <i class="fas fa-money-bill-wave me-2"></i>Recent Income
                </h3>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Description</th>
                                <th>Amount</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_incomes as $income): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($income['description']); ?></td>
                                <td>PKR <?php echo number_format($income['amount'], 2); ?></td>
                                <td><?php echo htmlspecialchars(date('M d, Y', strtotime($income['date']))); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Additional Statistics -->
        <div class="row mb-4">
            <div class="col-md-6 mb-4">
                <div class="dashboard-card">
                    <div class="card-body">
                        <h4 class="card-title">
                            <i class="fas fa-chart-pie me-2"></i>Transaction Statistics
                        </h4>
                        <div class="mt-4">
                            <div class="d-flex justify-content-between mb-2">
                                <span>Total Transactions</span>
                                <span class="badge bg-primary"><?php echo number_format($total_transactions); ?></span>
                            </div>
                            <div class="progress mb-4">
                                <div class="progress-bar bg-primary" role="progressbar" style="width: 75%"></div>
                            </div>
                            
                            <div class="d-flex justify-content-between mb-2">
                                <span>Total Categories</span>
                                <span class="badge bg-success"><?php echo number_format($total_categories); ?></span>
                            </div>
                            <div class="progress">
                                <div class="progress-bar bg-success" role="progressbar" style="width: 60%"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6 mb-4">
                <div class="dashboard-card">
                    <div class="card-body">
                        <h4 class="card-title">
                            <i class="fas fa-rupee-sign me-2"></i>Financial Overview
                        </h4>
                        <div class="mt-4">
                            <div class="d-flex justify-content-between mb-2">
                                <span>Income to Expense Ratio</span>
                                <span class="badge bg-info">
                                    <?php 
                                    $ratio = $expense_total > 0 ? ($income_total / $expense_total) : 0;
                                    echo number_format($ratio, 2);
                                    ?>
                                </span>
                            </div>
                            <div class="progress mb-4">
                                <div class="progress-bar bg-info" role="progressbar" 
                                     style="width: <?php echo min(100, $ratio * 100); ?>%"></div>
                            </div>

                            <div class="d-flex justify-content-between mb-2">
                                <span>Budget Utilization</span>
                                <span class="badge bg-warning">
                                    <?php 
                                    $utilization = $income_total > 0 ? ($expense_total / $income_total * 100) : 0;
                                    echo number_format($utilization, 1) . '%';
                                    ?>
                                </span>
                            </div>
                            <div class="progress">
                                <div class="progress-bar bg-warning" role="progressbar" 
                                     style="width: <?php echo min(100, $utilization); ?>%"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <footer class="mt-5 mb-4 text-center text-muted">
            <div class="container">
                <p class="mb-0">© <?php echo date('Y'); ?> Financial Management System. All rights reserved.</p>
                <small>Last login: <?php echo date('M d, Y H:i:s'); ?></small>
            </div>
        </footer>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Add smooth scrolling
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                document.querySelector(this.getAttribute('href')).scrollIntoView({
                    behavior: 'smooth'
                });
            });
        });

        // Add loading animation
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.dashboard-card').forEach(card => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    card.style.transition = 'all 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, 100);
            });
        });

        // Add tooltip initialization
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        });

        // Function to handle card clicks
        function handleCardClick(card, url) {
            // Check if card is flipped
            const cardInner = card.querySelector('.stat-card-inner');
            const isFlipped = getComputedStyle(cardInner).transform.includes('180');
            
            // Only redirect if card is not flipped
            if (!isFlipped) {
                window.location.href = url;
            }
        }

        // Add hover effect to all cards
        document.querySelectorAll('.stat-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.querySelector('.stat-card-inner').style.transform = 'rotateY(180deg)';
            });

            card.addEventListener('mouseleave', function() {
                this.querySelector('.stat-card-inner').style.transform = 'rotateY(0deg)';
            });
        });
    </script>
</body>
</html>     