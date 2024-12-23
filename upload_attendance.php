<?php
// upload_attendance.php

// Include necessary files
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

// Ensure the user is an admin or HR
if ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'hr') {
    header("Location: user_dashboard.php");
    exit();
}

// Generate CSRF token if not set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$success = "";
$error = "";

// Initialize salary report array
$salary_report = [];

// Get current month in YYYY-MM format
$current_month = date('Y-m');

// Handle CSV Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_csv'])) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Invalid CSRF token.";
    } else {
        if (isset($_FILES['attendance_csv']) && $_FILES['attendance_csv']['error'] === UPLOAD_ERR_OK) {
            $fileTmpPath = $_FILES['attendance_csv']['tmp_name'];
            $fileName = $_FILES['attendance_csv']['name'];
            $fileSize = $_FILES['attendance_csv']['size'];
            $fileType = $_FILES['attendance_csv']['type'];
            $fileNameCmps = explode(".", $fileName);
            $fileExtension = strtolower(end($fileNameCmps));

            if ($fileExtension === 'csv') {
                // Open the file
                if (($handle = fopen($fileTmpPath, 'r')) !== FALSE) {
                    // Assuming first row contains headers: user_id,present_days,absent_days
                    $header = fgetcsv($handle, 1000, ",");
                    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                        $user_id = intval(trim($data[0]));
                        $present_days = intval(trim($data[1]));
                        $absent_days = intval(trim($data[2]));

                        if ($user_id > 0) {
                            // Insert or update attendance record
                            // Check if a salary record for this user and month already exists
                            $stmt_check = $conn->prepare("SELECT id FROM salaries WHERE user_id = ? AND month = ?");
                            if (!$stmt_check) {
                                $error .= "Database error: " . $conn->error . "<br>";
                                continue;
                            }
                            $stmt_check->bind_param("is", $user_id, $current_month);
                            if (!$stmt_check->execute()) {
                                $error .= "Database execution error: " . $stmt_check->error . "<br>";
                                $stmt_check->close();
                                continue;
                            }
                            $stmt_check->store_result();
                            $exists = $stmt_check->num_rows > 0;
                            $stmt_check->close();

                            if ($exists) {
                                // Update existing record
                                $stmt_update = $conn->prepare("UPDATE salaries 
                                    SET total_present = total_present + ?, 
                                        total_absent = total_absent + ? 
                                    WHERE user_id = ? AND month = ?");
                                if (!$stmt_update) {
                                    $error .= "Database error: " . $conn->error . "<br>";
                                    continue;
                                }
                                $stmt_update->bind_param("iiis", $present_days, $absent_days, $user_id, $current_month);
                                if (!$stmt_update->execute()) {
                                    $error .= "Error updating attendance for User ID $user_id: " . $stmt_update->error . "<br>";
                                }
                                $stmt_update->close();
                            } else {
                                // Insert new record
                                $stmt_insert = $conn->prepare("INSERT INTO salaries 
                                    (user_id, month, total_present, total_absent, total_salary) 
                                    VALUES (?, ?, ?, ?, 0)");
                                if (!$stmt_insert) {
                                    $error .= "Database error: " . $conn->error . "<br>";
                                    continue;
                                }
                                $stmt_insert->bind_param("isii", $user_id, $current_month, $present_days, $absent_days);
                                if (!$stmt_insert->execute()) {
                                    $error .= "Error inserting attendance for User ID $user_id: " . $stmt_insert->error . "<br>";
                                }
                                $stmt_insert->close();
                            }
                        } else {
                            $error .= "Invalid User ID '$user_id' in CSV.<br>";
                        }
                    }
                    fclose($handle);
                    if (empty($error)) {
                        $success = "Attendance sheet uploaded and attendance records updated successfully.";
                    }
                } else {
                    $error = "Error opening the CSV file.";
                }
            } else {
                $error = "Please upload a valid CSV file with .csv extension.";
            }
        } else {
            $error = "Error uploading the file. Please ensure the file is not corrupted and try again.";
        }
    }
}

// Handle Export Salary
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export_salary'])) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Invalid CSRF token.";
    } else {
        // Fetch all users with their monthly_salary and attendance
        $stmt = $conn->prepare("SELECT u.id, u.username, u.monthly_salary, 
                                   s.total_present, s.total_absent 
                            FROM users u 
                            LEFT JOIN salaries s ON u.id = s.user_id AND s.month = ?");
        if (!$stmt) {
            $error = "Database error: " . $conn->error;
        } else {
            $stmt->bind_param("s", $current_month);
            if (!$stmt->execute()) {
                $error = "Database error: " . $stmt->error;
            } else {
                $result = $stmt->get_result();
                $salaries = [];
                while ($row = $result->fetch_assoc()) {
                    $salaries[] = $row;
                }
                $stmt->close();

                // Define penalty per absent day if any (optional)
                $absent_penalty = 0; // Set to 0 if no penalty

                // Begin transaction
                $conn->begin_transaction();

                $error_in_salary = false;

                foreach ($salaries as $salary) {
                    $user_id = $salary['id'];
                    $username = $salary['username'];
                    $monthly_salary = $salary['monthly_salary'];
                    $total_present = $salary['total_present'] ?? 0;
                    $total_absent = $salary['total_absent'] ?? 0;

                    // Calculate per day salary as integer
                    if ($monthly_salary > 0) {
                        $per_day_salary = intdiv($monthly_salary, 30); // Integer division
                    } else {
                        $per_day_salary = 0; // Default value
                    }

                    // Calculate total salary
                    $total_salary = ($total_present * $per_day_salary) - ($total_absent * $absent_penalty);
                    if ($total_salary < 0) {
                        $total_salary = 0;
                    }

                    // Update the salary record with calculated total_salary
                    $stmt_update_salary = $conn->prepare("UPDATE salaries 
                        SET total_salary = ? 
                        WHERE user_id = ? AND month = ?");
                    if (!$stmt_update_salary) {
                        $error .= "Database error: " . $conn->error . "<br>";
                        $error_in_salary = true;
                        break;
                    }
                    $stmt_update_salary->bind_param("iis", $total_salary, $user_id, $current_month);
                    if (!$stmt_update_salary->execute()) {
                        $error .= "Error updating salary for '$username': " . $stmt_update_salary->error . "<br>";
                        $error_in_salary = true;
                        $stmt_update_salary->close();
                        break;
                    }
                    $stmt_update_salary->close();

                    // Add to salary report
                    $salary_report[] = [
                        'user_id' => $user_id,
                        'username' => $username,
                        'monthly_salary' => $monthly_salary,
                        'present_days' => $total_present,
                        'absent_days' => $total_absent,
                        'per_day_salary' => $per_day_salary,
                        'total_salary' => $total_salary
                    ];
                }

                if (!$error_in_salary) {
                    $conn->commit();
                    $success = "Salaries for " . date('F Y', strtotime($current_month . '-01')) . " have been calculated and updated successfully.";
                } else {
                    $conn->rollback();
                }
            }
        }
    }
}

// Handle Process Salary for Individual User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_salary_individual'])) 
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Invalid CSRF token.";
    } else {
        // Ensure the user is an admin
        if ($_SESSION['role'] != 'admin') {
            $error = "Only administrators can process salaries.";
        } else {
            // Get the user_id from the form
            $user_id = intval($_POST['user_id'] ?? 0);
            if ($user_id <= 0) {
                $error = "Invalid User ID.";
            } else {
                // Fetch the salary record for the user for the current month
                $stmt = $conn->prepare("SELECT s.id, u.username, u.monthly_salary, 
                                           s.total_present, s.total_absent, s.total_salary 
                                    FROM salaries s 
                                    JOIN users u ON s.user_id = u.id 
                                    WHERE s.user_id = ? AND s.month = ?");
                if (!$stmt) {
                    $error = "Database error: " . $conn->error;
                } else {
                    $stmt->bind_param("is", $user_id, $current_month);
                    if (!$stmt->execute()) {
                        $error = "Database error: " . $stmt->error;
                    } else {
                        $result = $stmt->get_result();
                        if ($result->num_rows === 0) {
                            $error = "No salary record found for the selected user for the current month.";
                        } else {
                            $salary = $result->fetch_assoc();
                            $stmt->close();

                            // Check if the salary has already been processed
                            if ($salary['total_salary'] > 0) {
                                $error = "Salary for " . htmlspecialchars($salary['username']) . " has already been processed.";
                            } else {
                                // Begin transaction
                                $conn->begin_transaction();

                                try {
                                    $username = $salary['username'];
                                    $monthly_salary = $salary['monthly_salary'];
                                    $total_present = $salary['total_present'] ?? 0;
                                    $total_absent = $salary['total_absent'] ?? 0;

                                    // Calculate per day salary as integer
                                    if ($monthly_salary > 0) {
                                        $per_day_salary = intdiv($monthly_salary, 30); // Integer division
                                    } else {
                                        $per_day_salary = 0; // Default value
                                    }

                                    // Calculate total salary
                                    $total_salary = ($total_present * $per_day_salary); // Assuming no penalty

                                    // Update the salary record with calculated total_salary
                                    $stmt_update_salary = $conn->prepare("UPDATE salaries 
                                        SET total_salary = ? 
                                        WHERE id = ?");
                                    if (!$stmt_update_salary) {
                                        throw new Exception("Database error: " . $conn->error);
                                    }
                                    $stmt_update_salary->bind_param("ii", $total_salary, $salary['id']);
                                    if (!$stmt_update_salary->execute()) {
                                        throw new Exception("Error updating salary: " . $stmt_update_salary->error);
                                    }
                                    $stmt_update_salary->close();

                                    // Insert 'income' transaction for user
                                    $stmt_income = $conn->prepare("INSERT INTO transactions (user_id, amount, type, category_id, date, description) 
                                                                    VALUES (?, ?, 'income', ?, ?, 'Monthly Salary')");
                                    if (!$stmt_income) {
                                        throw new Exception("Database error: " . $conn->error);
                                    }
                                    // Fetch 'Salary' category ID
                                    $category_stmt = $conn->prepare("SELECT id FROM categories WHERE name = 'Salary' LIMIT 1");
                                    if (!$category_stmt) {
                                        throw new Exception("Database error: " . $conn->error);
                                    }
                                    $category_stmt->execute();
                                    $category_stmt->bind_result($salary_category_id);
                                    if (!$category_stmt->fetch()) {
                                        throw new Exception("Salary category not found. Please create it in the Categories section.");
                                    }
                                    $category_stmt->close();

                                    $stmt_income->bind_param("idis", $user_id, $total_salary, $salary_category_id, $current_month . "-01");
                                    if (!$stmt_income->execute()) {
                                        throw new Exception("Error inserting income transaction: " . $stmt_income->error);
                                    }
                                    $stmt_income->close();

                                    // Insert 'expense' transaction for admin
                                    $admin_id = $_SESSION['user_id'];
                                    $stmt_expense = $conn->prepare("INSERT INTO transactions (user_id, amount, type, category_id, date, description) 
                                                                    VALUES (?, ?, 'expense', ?, ?, 'Monthly Salary Payment to $username')");
                                    if (!$stmt_expense) {
                                        throw new Exception("Database error: " . $conn->error);
                                    }
                                    $stmt_expense->bind_param("idis", $admin_id, $total_salary, $salary_category_id, $current_month . "-01");
                                    if (!$stmt_expense->execute()) {
                                        throw new Exception("Error inserting expense transaction: " . $stmt_expense->error);
                                    }
                                    $stmt_expense->close();

                                    // Send notification to the user
                                    $notif_message = "Your monthly salary of $" . number_format($total_salary, 2) . " has been credited for " . date('F Y', strtotime($current_month . '-01')) . ".";
                                    $stmt_notif = $conn->prepare("INSERT INTO notifications (user_id, message, is_read, timestamp) VALUES (?, ?, 0, NOW())");
                                    if ($stmt_notif) {
                                        $stmt_notif->bind_param("is", $user_id, $notif_message);
                                        if (!$stmt_notif->execute()) {
                                            // Log notification failure but do not throw exception
                                            $error .= "Salary processed, but failed to send notification to '$username'.<br>";
                                        }
                                        $stmt_notif->close();
                                    } else {
                                        $error .= "Salary processed, but failed to prepare notification for '$username'.<br>";
                                    }

                                    // Log the action
                                    log_action($conn, $admin_id, 'Processed Salary', "Salary of $" . number_format($total_salary, 2) . " processed for user $username.");

                                    // Commit transaction
                                    $conn->commit();

                                    // Success message
                                    $success = "Salary processed successfully for " . htmlspecialchars($username) . ".";
                                } catch (Exception $e) {
                                    $conn->rollback(); // Rollback on error
                                    $error = "Failed to process salary: " . $e->getMessage();
                                }
                            }
                        }
                    }
                }
            }
        }
    }
?>
<!DOCTYPE html>
<html>
<head>
    <title>Upload Attendance and Manage Salaries</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { 
            background-color: #f0f2f5; 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .navbar {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .navbar-brand {
            font-weight: 600;
            font-size: 1.4rem;
        }

        .container { 
            margin-top: 2rem;
            margin-bottom: 2rem;
        }

        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 1.5rem;
            transition: transform 0.2s;
        }

        .card:hover {
            transform: translateY(-5px);
        }

        .card-header {
            border-radius: 12px 12px 0 0 !important;
            padding: 1rem 1.5rem;
        }

        .card-header.bg-secondary {
            background: linear-gradient(135deg, #4b6cb7 0%, #182848 100%) !important;
        }

        .card-header.bg-info {
            background: linear-gradient(135deg, #36D1DC 0%, #5B86E5 100%) !important;
        }

        .btn {
            border-radius: 8px;
            padding: 0.5rem 1.2rem;
            font-weight: 500;
            transition: all 0.3s;
        }

        .btn-primary {
            background: linear-gradient(135deg, #4b6cb7 0%, #182848 100%);
            border: none;
        }

        .btn-success {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            border: none;
        }

        .btn-warning {
            background: linear-gradient(135deg, #f2994a 0%, #f2c94c 100%);
            border: none;
            color: white;
        }

        .table {
            border-radius: 8px;
            overflow: hidden;
        }

        .table thead th {
            background-color: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
            font-weight: 600;
        }

        .alert {
            border-radius: 10px;
            border: none;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .form-control {
            border-radius: 8px;
            padding: 0.7rem 1rem;
            border: 1px solid #dee2e6;
        }

        .form-control:focus {
            box-shadow: 0 0 0 3px rgba(75, 108, 183, 0.1);
            border-color: #4b6cb7;
        }

        .badge {
            padding: 0.5em 1em;
            border-radius: 6px;
        }

        /* Custom animation for alerts */
        .alert {
            animation: slideIn 0.5s ease-out;
        }

        @keyframes slideIn {
            from {
                transform: translateY(-20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        /* Hover effects for table rows */
        .table tbody tr:hover {
            background-color: rgba(75, 108, 183, 0.05);
            transition: background-color 0.3s;
        }

        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        ::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #555;
        }

        .nav-link {
            padding: 0.8rem 1rem !important;
            transition: all 0.3s ease;
        }

        .nav-link:hover {
            background-color: rgba(255, 255, 255, 0.1);
            transform: translateX(5px);
        }

        .nav-link.active {
            background-color: rgba(255, 255, 255, 0.2);
            border-radius: 8px;
        }

        /* Make icons slightly larger */
        .bi {
            font-size: 1.1em;
        }

        /* Add subtle glow effect to brand icon */
        .navbar-brand .bi {
            filter: drop-shadow(0 0 2px rgba(255, 255, 255, 0.3));
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#"><i class="bi bi-bank2 me-2"></i>Financial Management System</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item"><a class="nav-link" href="admin_dashboard.php"><i class="bi bi-speedometer2 me-1"></i>Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="manage_users.php"><i class="bi bi-people-fill me-1"></i>Users</a></li>
                    <li class="nav-item"><a class="nav-link" href="manage_transactions.php"><i class="bi bi-currency-exchange me-1"></i>Transactions</a></li>
                    <li class="nav-item"><a class="nav-link" href="manage_categories.php"><i class="bi bi-tags-fill me-1"></i>Categories</a></li>
                    <li class="nav-item"><a class="nav-link" href="manage_salaries.php"><i class="bi bi-cash-stack me-1"></i>Salaries</a></li>
                    <li class="nav-item"><a class="nav-link active" href="upload_attendance.php"><i class="bi bi-calendar-check-fill me-1"></i>Attendance</a></li>
                    <li class="nav-item"><a class="nav-link" href="financial_reports.php"><i class="bi bi-graph-up me-1"></i>Reports</a></li>
                    <li class="nav-item"><a class="nav-link" href="view_audit_logs.php"><i class="bi bi-journal-text me-1"></i>Logs</a></li>
                    <li class="nav-item"><a class="nav-link" href="logout.php"><i class="bi bi-box-arrow-right me-1"></i>Logout (<?php echo htmlspecialchars($_SESSION['username']); ?>)</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Attendance and Salary Management Content -->
    <div class="container">
        <h2 class="mb-4"><i class="bi bi-calendar-check-fill me-2"></i>Manage Attendance & Salary</h2>

        <!-- Display Success or Error Messages -->
        <?php if (!empty($success)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($success); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Quick Actions Section -->
        <div class="row mb-4">
            <div class="col">
                <h3 class="section-title">
                    <i class="bi bi-lightning-fill me-2"></i>Quick Actions
                </h3>
                <div class="d-flex gap-3 flex-wrap">
                    <button type="button" class="quick-action-btn btn btn-primary" data-bs-toggle="modal" data-bs-target="#uploadAttendanceModal">
                        <i class="bi bi-upload me-2"></i>Upload Attendance
                    </button>
                    <button type="submit" name="export_salary" class="quick-action-btn btn btn-success">
                        <i class="bi bi-calculator me-2"></i>Export Salary
                    </button>
                    <?php if ($_SESSION['role'] === 'admin'): ?>
                        <button type="submit" name="process_salary" class="quick-action-btn btn btn-warning">
                            <i class="bi bi-cash-stack me-2"></i>Process All Salaries
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Upload Attendance Modal -->
        <div class="modal fade" id="uploadAttendanceModal" tabindex="-1" aria-labelledby="uploadAttendanceModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title" id="uploadAttendanceModalLabel">
                                <i class="bi bi-upload me-2"></i>Upload Attendance Sheet
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <form method="POST" action="upload_attendance.php" enctype="multipart/form-data">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                <div class="mb-3">
                                    <label class="form-label">Select CSV File</label>
                                    <input type="file" name="attendance_csv" class="form-control" accept=".csv" required>
                                    <div class="form-text">CSV format: user_id,present_days,absent_days</div>
                                </div>
                                <div class="d-grid">
                                    <button type="submit" name="upload_csv" class="btn btn-primary">
                                        <i class="bi bi-cloud-upload-fill me-2"></i>Upload Attendance
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Display Salary Report if Available -->
        <?php if (!empty($salary_report)): ?>
            <div class="card mt-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="bi bi-file-earmark-text me-2"></i> Salary Calculation Report for <?php echo date('F Y', strtotime($current_month . '-01')); ?></h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Employee ID</th>
                                    <th>Employee Name</th>
                                    <th>Monthly Salary ($)</th>
                                    <th>Present Days</th>
                                    <th>Absent Days</th>
                                    <th>Per Day Salary ($)</th>
                                    <th>Total Salary ($)</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($salary_report as $report): ?>
                                    <tr>
                                        <td>#<?php echo htmlspecialchars($report['user_id']); ?></td>
                                        <td><?php echo htmlspecialchars($report['username']); ?></td>
                                        <td class="text-end"><?php echo number_format($report['monthly_salary'], 2); ?></td>
                                        <td class="text-end"><?php echo htmlspecialchars($report['present_days']); ?></td>
                                        <td class="text-end"><?php echo htmlspecialchars($report['absent_days']); ?></td>
                                        <td class="text-end"><?php echo number_format($report['per_day_salary'], 2); ?></td>
                                        <td class="text-end"><?php echo number_format($report['total_salary'], 2); ?></td>
                                        <td>
                                            <?php
                                                // Check if the salary has already been processed
                                                // Assuming that a processed salary has a total_salary > 0
                                                // Adjust this condition based on your actual criteria
                                                $processed = $report['total_salary'] > 0;
                                            ?>
                                            <?php if (!$processed): ?>
                                                <?php if ($_SESSION['role'] === 'admin'): ?>
                                                    <form method="POST" action="upload_attendance.php" class="d-inline">
                                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                        <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($report['user_id']); ?>">
                                                        <button type="submit" name="process_salary_individual" class="btn btn-sm btn-outline-primary" onclick="return confirm('Are you sure you want to process the salary for <?php echo htmlspecialchars($report['username']); ?>?');">
                                                            <i class="bi bi-cash me-1"></i>Process Salary
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <span class="text-muted">N/A</span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="badge bg-success">Processed</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Include Bootstrap JS and dependencies -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
