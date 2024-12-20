<?php
// process_salaries.php

require_once __DIR__ . '/session.php';        // Include session management
// 'session.php' should include 'functions.php' and 'db.php'



// Ensure the user is an admin
if ($_SESSION['role'] != 'admin') {
    header("Location: user_dashboard.php");
    exit();
}

// Identify Admin's User ID from session
$admin_id = $_SESSION['user_id'];

// Handle salary processing
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Initialize variables for messages
    $success = '';
    $error = '';
    $failed_users = [];
    $processed = 0;
    $total_salary = 0; // Total salaries to be processed

    // Begin Transaction
    $conn->begin_transaction();

    try {
        // Fetch all users with their fixed salaries
        $result = $conn->query("SELECT id, username, fixed_salary FROM users WHERE fixed_salary > 0");
        if (!$result) {
            throw new Exception("Query failed: (" . $conn->errno . ") " . $conn->error);
        }

        // Fetch 'Salary' category ID
        $category_stmt = $conn->prepare("SELECT id FROM categories WHERE name = 'Salary' LIMIT 1");
        if (!$category_stmt) {
            throw new Exception("Prepare failed (Fetch Salary Category): (" . $conn->errno . ") " . $conn->error);
        }
        $category_stmt->execute();
        $category_stmt->bind_result($salary_category_id);
        if (!$category_stmt->fetch()) {
            throw new Exception("Salary category not found. Please ensure 'Salary' category exists.");
        }
        $category_stmt->close();

        $salary_date = date('Y-m-d'); // Current date as salary date

        while ($user = $result->fetch_assoc()) {
            $user_id = $user['id'];
            $username = $user['username'];
            $salary_amount = $user['fixed_salary'];
            $total_salary += $salary_amount;

            // Insert salary as a transaction for the user (Income, negative)
            $stmt = $conn->prepare("INSERT INTO transactions (user_id, amount, type, category_id, date, description) 
                                    VALUES (?, ?, 'income', ?, ?, 'Monthly Salary')");
            if (!$stmt) {
                throw new Exception("Prepare failed (Insert User Income): (" . $conn->errno . ") " . $conn->error);
            }
            $negative_salary = -abs($salary_amount); // Ensure it's negative
            $stmt->bind_param("idss", $user_id, $negative_salary, $salary_category_id, $salary_date);
            if (!$stmt->execute()) {
                throw new Exception("Execute failed (Insert User Income): (" . $stmt->errno . ") " . $stmt->error);
            }
            $stmt->close();

            // Insert salary as a transaction for the admin (Expense, positive)
            $stmt = $conn->prepare("INSERT INTO transactions (user_id, amount, type, category_id, date, description) 
                                    VALUES (?, ?, 'expense', ?, ?, 'Monthly Salary Payment')");
            if (!$stmt) {
                throw new Exception("Prepare failed (Insert Admin Expense): (" . $conn->errno . ") " . $conn->error);
            }
            $stmt->bind_param("idss", $admin_id, $salary_amount, $salary_category_id, $salary_date);
            if (!$stmt->execute()) {
                throw new Exception("Execute failed (Insert Admin Expense): (" . $stmt->errno . ") " . $stmt->error);
            }
            $stmt->close();

            // Create in-app notification for the user
            $notif_message = "Your monthly salary of $$salary_amount has been credited on $salary_date.";
            $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
            if ($notif_stmt) {
                $notif_stmt->bind_param("is", $user_id, $notif_message);
                if (!$notif_stmt->execute()) {
                    $failed_users[] = $username;
                }
                $notif_stmt->close();
            } else {
                $failed_users[] = $username;
            }

            // Log each salary transaction
            log_action($conn, $_SESSION['user_id'], 'Processed Salary', "Salary of $$salary_amount processed for user ID $user_id ($username).");

            $processed++;
        }

        // Insert total salaries as an expense transaction for Admin
        // (Already handled per user above)

        // Log the expense transaction
        log_action($conn, $_SESSION['user_id'], 'Processed Salaries Expense', "Total salaries of $$total_salary processed for all users.");

        // Commit Transaction
        $conn->commit();

        // Prepare success message
        $success_message = "Salaries processed successfully for $processed users.";
        if (!empty($failed_users)) {
            $success_message .= " However, failed to create notifications for: " . implode(', ', $failed_users) . ".";
        }

        $success = $success_message;
    } catch (Exception $e) {
        // Rollback Transaction
        $conn->rollback();
        $error = "Failed to process salaries: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Process Salaries</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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
                        <a class="nav-link" href="manage_salaries.php">Manage Salaries</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="process_salaries.php">Process Salaries</a>
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

    <!-- Process Salaries Content -->
    <div class="container mt-4">
        <h2>Process Monthly Salaries</h2>

        <!-- Display Success Message -->
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <!-- Display Error Message -->
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Process Salaries Form -->
        <form method="POST" action="process_salaries.php">
            <!-- CSRF Token for Security (Optional but Recommended) -->
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
            <button type="submit" class="btn btn-primary">Process Salaries for All Users</button>
        </form>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
