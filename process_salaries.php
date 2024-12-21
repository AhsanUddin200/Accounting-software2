<?php
// process_salaries.php

require_once __DIR__ . '/session.php';        // Include session management
require_once __DIR__ . '/db.php';            // Include database connection
require_once __DIR__ . '/functions.php';     // Include helper functions

// Ensure the user is an admin
if ($_SESSION['role'] != 'admin') {
    header("Location: user_dashboard.php");
    exit();
}

$admin_id = $_SESSION['user_id']; // Admin's User ID

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $success = '';
    $error = '';
    $failed_users = [];
    $processed = 0;
    $total_salary = 0;

    // Start Transaction
    $conn->begin_transaction();

    try {
        // Fetch all users with fixed salaries
        $result = $conn->query("SELECT id, username, fixed_salary FROM users WHERE fixed_salary > 0");
        if (!$result) {
            throw new Exception("Failed to fetch users with salaries: " . $conn->error);
        }

        // Get 'Salary' category ID
        $category_stmt = $conn->prepare("SELECT id FROM categories WHERE name = 'Salary' LIMIT 1");
        if (!$category_stmt) {
            throw new Exception("Failed to fetch 'Salary' category: " . $conn->error);
        }
        $category_stmt->execute();
        $category_stmt->bind_result($salary_category_id);
        if (!$category_stmt->fetch()) {
            throw new Exception("Salary category not found. Please create it in the Categories section.");
        }
        $category_stmt->close();

        $salary_date = date('Y-m-d'); // Current date for salary processing

        while ($user = $result->fetch_assoc()) {
            $user_id = $user['id'];
            $username = $user['username'];
            $salary_amount = $user['fixed_salary'];
            $total_salary += $salary_amount;

            // Add salary to user's income (positive amount)
            $user_salary_stmt = $conn->prepare("INSERT INTO transactions (user_id, amount, type, category_id, date, description) 
                                                VALUES (?, ?, 'income', ?, ?, 'Monthly Salary')");
            if (!$user_salary_stmt) {
                throw new Exception("Failed to prepare user salary transaction: " . $conn->error);
            }
            $user_salary_stmt->bind_param("idis", $user_id, $salary_amount, $salary_category_id, $salary_date);
            if (!$user_salary_stmt->execute()) {
                throw new Exception("Failed to execute user salary transaction: " . $user_salary_stmt->error);
            }
            $user_salary_stmt->close();

            // Add expense for the admin (positive amount)
            $admin_expense_stmt = $conn->prepare("INSERT INTO transactions (user_id, amount, type, category_id, date, description) 
                                                  VALUES (?, ?, 'expense', ?, ?, 'Monthly Salary Payment')");
            if (!$admin_expense_stmt) {
                throw new Exception("Failed to prepare admin expense transaction: " . $conn->error);
            }
            $admin_expense_stmt->bind_param("idis", $admin_id, $salary_amount, $salary_category_id, $salary_date);
            if (!$admin_expense_stmt->execute()) {
                throw new Exception("Failed to execute admin expense transaction: " . $admin_expense_stmt->error);
            }
            $admin_expense_stmt->close();

            // Send notification to the user
            $notif_message = "Your monthly salary of $$salary_amount has been credited on $salary_date.";
            $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, is_read, timestamp) VALUES (?, ?, 0, NOW())");
            if ($notif_stmt) {
                $notif_stmt->bind_param("is", $user_id, $notif_message);
                if (!$notif_stmt->execute()) {
                    $failed_users[] = $username;
                }
                $notif_stmt->close();
            } else {
                $failed_users[] = $username;
            }

            log_action($conn, $admin_id, 'Processed Salary', "Salary of $$salary_amount processed for user $username.");

            $processed++;
        }

        // Commit transaction
        $conn->commit();

        // Success message
        $success_message = "Salaries processed successfully for $processed users.";
        if (!empty($failed_users)) {
            $success_message .= " However, notifications failed for: " . implode(', ', $failed_users) . ".";
        }
        $success = $success_message;
    } catch (Exception $e) {
        $conn->rollback(); // Rollback on error
        $error = "Failed to process salaries: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Process Salaries</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Navbar Styling */
        .navbar {
            background-color: #f8f9fa !important;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            padding: 0.8rem 0;
        }
        .navbar-brand {
            color: #2c3e50 !important;
            font-weight: 600;
            font-size: 1.3rem;
        }
        .nav-link {
            color: #4a5568 !important;
            padding: 0.5rem 1rem !important;
            margin: 0 0.2rem;
            border-radius: 6px;
            transition: all 0.3s ease;
        }
        .nav-link:hover {
            background-color: #e2e8f0;
            color: #2d3748 !important;
        }
        .nav-link.active {
            background-color: #e2e8f0 !important;
            color: #1a202c !important;
            font-weight: 500;
        }

        /* Content Styling */
        body {
            background-color: #f5f7fa;
        }
        .main-content {
            padding: 2rem;
        }
        .salary-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.05);
            padding: 2rem;
            margin-bottom: 2rem;
        }
        .salary-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f0f2f5;
        }
        .salary-header h2 {
            color: #2c3e50;
            font-weight: 600;
            margin: 0;
        }
        .process-form {
            background: #f8fafc;
            padding: 2rem;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
        }
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
        }
        .stat-card h3 {
            font-size: 0.9rem;
            color: #64748b;
            margin-bottom: 0.5rem;
        }
        .stat-card .value {
            font-size: 1.5rem;
            font-weight: 600;
            color: #2c3e50;
        }
        .btn-process {
            background: #3498db;
            border: none;
            padding: 0.8rem 2rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .btn-process:hover {
            background: #2980b9;
            transform: translateY(-1px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .alert {
            border-radius: 10px;
            border: none;
            padding: 1rem 1.5rem;
        }
        .alert-success {
            background-color: #d1fae5;
            color: #065f46;
        }
        .alert-danger {
            background-color: #fee2e2;
            color: #991b1b;
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
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="admin_dashboard.php">
                            <i class="fas fa-dashboard me-1"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="manage_salaries.php">
                            <i class="fas fa-money-bill me-1"></i>Manage Salaries
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="process_salaries.php">
                            <i class="fas fa-cogs me-1"></i>Process Salaries
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">
                            <i class="fas fa-sign-out-alt me-1"></i>Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="main-content">
        <div class="container">
            <div class="salary-card">
                <div class="salary-header">
                    <h2><i class="fas fa-money-check-alt me-2"></i>Process Monthly Salaries</h2>
                </div>

                <?php if (!empty($success) || !empty($error)): ?>
                    <div class="stats-container">
                        <?php if (isset($processed)): ?>
                        <div class="stat-card">
                            <h3><i class="fas fa-users me-2"></i>Processed Users</h3>
                            <div class="value"><?php echo $processed; ?></div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (isset($total_salary)): ?>
                        <div class="stat-card">
                            <h3><i class="fas fa-dollar-sign me-2"></i>Total Amount</h3>
                            <div class="value">$<?php echo number_format($total_salary, 2); ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($success)): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <div class="process-form">
                    <form method="POST" action="process_salaries.php">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                        <button type="submit" class="btn btn-primary btn-process">
                            <i class="fas fa-sync-alt me-2"></i>Process Salaries for All Users
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
