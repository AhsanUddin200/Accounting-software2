<?php
// process_salaries.php

require_once __DIR__ . '/session.php';        // Include session management
require_once __DIR__ . '/db.php';            // Include database connection
require_once __DIR__ . '/functions.php';     // Include helper functions

// Enable error reporting for debugging (remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Ensure the user is an admin
if ($_SESSION['role'] != 'admin') {
    header("Location: user_dashboard.php");
    exit();
}

$admin_id = $_SESSION['user_id']; // Admin's User ID

// Generate CSRF token if not set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $success = '';
    $error = '';
    $failed_users = [];
    $processed = 0;
    $total_salary = 0;

    // CSRF Protection
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Invalid CSRF token.";
    } else {
        // Start Transaction
        $conn->begin_transaction();

        try {
            // Fetch all users with current_month_salary set
            $current_month = date('Y-m');
            $stmt = $conn->prepare("SELECT id, username, current_month_salary FROM users WHERE current_month_salary > 0");
            if (!$stmt) {
                throw new Exception("Failed to prepare statement: " . $conn->error);
            }
            $stmt->execute();
            $result = $stmt->get_result();

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

            while ($user = $result->fetch_assoc()) {
                $user_id = $user['id'];
                $username = $user['username'];
                $salary_amount = $user['current_month_salary'];
                $total_salary += $salary_amount;
                $salary_date = $current_month . "-01";

                // First, DEDUCT from admin's account (record as expense)
                $admin_expense_stmt = $conn->prepare("
                    INSERT INTO transactions (user_id, amount, type, category_id, date, description) 
                    VALUES (?, ?, 'expense', ?, ?, ?)
                ");
                if (!$admin_expense_stmt) {
                    throw new Exception("Failed to prepare admin expense transaction");
                }
                $admin_description = "Salary Payment: $username";
                $admin_expense_stmt->bind_param("idiss", $admin_id, $salary_amount, $salary_category_id, $salary_date, $admin_description);
                if (!$admin_expense_stmt->execute()) {
                    throw new Exception("Failed to record admin expense");
                }
                $admin_expense_stmt->close();

                // Then, add to user's account (record as income)
                $user_income_stmt = $conn->prepare("
                    INSERT INTO transactions (user_id, amount, type, category_id, date, description) 
                    VALUES (?, ?, 'income', ?, ?, ?)
                ");
                if (!$user_income_stmt) {
                    throw new Exception("Failed to prepare user income transaction");
                }
                $user_description = "Monthly Salary";
                $user_income_stmt->bind_param("idiss", $user_id, $salary_amount, $salary_category_id, $salary_date, $user_description);
                if (!$user_income_stmt->execute()) {
                    throw new Exception("Failed to record user income");
                }
                $user_income_stmt->close();

                // Update admin's total balance
                $update_admin_balance = $conn->prepare("
                    UPDATE users 
                    SET total_balance = total_balance - ? 
                    WHERE id = ?
                ");
                if ($update_admin_balance) {
                    $update_admin_balance->bind_param("di", $salary_amount, $admin_id);
                    $update_admin_balance->execute();
                    $update_admin_balance->close();
                }

                // Update user's total balance
                $update_user_balance = $conn->prepare("
                    UPDATE users 
                    SET total_balance = total_balance + ? 
                    WHERE id = ?
                ");
                if ($update_user_balance) {
                    $update_user_balance->bind_param("di", $salary_amount, $user_id);
                    $update_user_balance->execute();
                    $update_user_balance->close();
                }

                // Send notification to the user
                $notif_message = "Your monthly salary of $" . number_format($salary_amount, 2) . " has been credited on " . date('F Y', strtotime($salary_date)) . ".";
                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, is_read, timestamp) VALUES (?, ?, 0, NOW())");
                if ($notif_stmt) {
                    $notif_stmt->bind_param("is", $user_id, $notif_message);
                    if (!$notif_stmt->execute()) {
                        // Log notification failure but do not throw exception
                        $failed_users[] = $username;
                    }
                    $notif_stmt->close();
                } else {
                    $failed_users[] = $username;
                }

                // Log the action
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
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Process Salaries</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Your existing styles */
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        }

        .navbar {
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            background: linear-gradient(to right, #1e40af, #3b82f6);
        }

        .navbar .navbar-brand {
            font-weight: bold;
        }

        .main-content {
            padding: 2rem;
        }

        .salary-card {
            background: white;
            padding: 2rem;
            border-radius: 1rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 
                        0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }

        .salary-header h2 {
            display: flex;
            align-items: center;
        }

        .stats-container {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .stat-card {
            flex: 1;
            background: #f1f5f9;
            padding: 1rem;
            border-radius: 0.5rem;
            text-align: center;
        }

        .stat-card h3 {
            margin-bottom: 0.5rem;
            font-size: 1.2rem;
        }

        .stat-card .value {
            font-size: 1.5rem;
            font-weight: bold;
        }

        .process-form {
            text-align: center;
            margin-top: 1.5rem;
        }

        .btn-process {
            background-color: #1e40af;
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 0.5rem;
            font-size: 1rem;
            transition: background-color 0.3s;
        }

        .btn-process:hover {
            background-color: #3b82f6;
        }

        .alert {
            margin-top: 1rem;
        }
    </style>
</head>
<body>
    <!-- Navigation Bar (Same as before) -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <i class="fas fa-calculator me-2"></i>Financial Management System
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="admin_dashboard.php">
                            <i class="fas fa-dashboard me-1"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="manage_salaries.php">
                            <i class="fas fa-money-bill-wave me-1"></i>Manage Salaries
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
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <button type="submit" class="btn btn-primary btn-process">
                            <i class="fas fa-sync-alt me-2"></i>Process Salaries for All Users
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
