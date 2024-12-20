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
    <style>
        /* Basic styling */
        body { font-family: Arial, sans-serif; background-color: #f4f4f4; }
        .container { width: 90%; margin: 20px auto; padding: 20px; background: #fff; border-radius: 5px; }
        h2 { text-align: center; }
        .report-section { margin-bottom: 40px; }
        .report-section h3 { margin-bottom: 10px; }
        .report-details { padding: 15px; background-color: #f9f9f9; border-radius: 4px; }
        .report-details p { font-size: 18px; margin: 5px 0; }
        form { margin-bottom: 20px; }
        input, select { padding: 8px; margin: 5px 0; border: 1px solid #ccc; border-radius: 4px; }
        input[type="submit"] { background: #5bc0de; border: none; color: #fff; cursor: pointer; padding: 10px 20px; }
        input[type="submit"]:hover { background: #31b0d5; }
        .message { padding: 10px; margin-bottom: 20px; border-radius: 4px; }
        .error { background-color: #f2dede; color: #a94442; }
        .back-button { text-align: center; margin-top: 20px; }
        .back-button a { 
            padding: 10px 20px; 
            background-color: #5bc0de; 
            color: #fff; 
            text-decoration: none; 
            border-radius: 4px; 
        }
        .back-button a:hover { background-color: #31b0d5; }
        .export-buttons { margin-top: 20px; }
        .export-buttons form { display: inline-block; margin-right: 10px; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Financial Reports</h2>

        <!-- Display Error Messages -->
        <?php if (!empty($error)): ?>
            <div class="message error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- User Financial Report -->
        <div class="report-section">
            <h3>Your Financial Report</h3>
            <form method="GET" action="financial_reports.php">
                <label for="start_date">From:</label>
                <input type="date" id="start_date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">

                <label for="end_date">To:</label>
                <input type="date" id="end_date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">

                <input type="submit" value="Filter">
                <a href="financial_reports.php"><input type="button" value="Reset"></a>
            </form>

            <div class="report-details">
                <p><strong>Total Income:</strong> <?php echo "$" . number_format($income, 2); ?></p>
                <p><strong>Total Expenses:</strong> <?php echo "$" . number_format($expenses, 2); ?></p>
                <p><strong>Net Balance:</strong> <?php echo "$" . number_format($balance, 2); ?></p>
            </div>

            <!-- Export Button -->
            <div class="export-buttons">
                <form method="POST" action="export_report.php">
                    <input type="hidden" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
                    <input type="hidden" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
                    <input type="submit" name="export_user_report" value="Export Your Report as CSV">
                </form>
            </div>
        </div>

        <!-- Admin Financial Report -->
        <?php if ($_SESSION['role'] == 'admin'): ?>
            <div class="report-section">
                <h3>Admin Financial Report</h3>
                <form method="GET" action="financial_reports.php">
                    <input type="hidden" name="admin_export" value="1">

                    <label for="user_id">User:</label>
                    <select id="user_id" name="user_id" required>
                        <option value="">Select User</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?php echo htmlspecialchars($user['id']); ?>" <?php echo (isset($_GET['user_id']) && $_GET['user_id'] == $user['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($user['username']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <label for="start_date_admin">From:</label>
                    <input type="date" id="start_date_admin" name="start_date_admin" value="<?php echo isset($_GET['start_date_admin']) ? htmlspecialchars($_GET['start_date_admin']) : ''; ?>">

                    <label for="end_date_admin">To:</label>
                    <input type="date" id="end_date_admin" name="end_date_admin" value="<?php echo isset($_GET['end_date_admin']) ? htmlspecialchars($_GET['end_date_admin']) : ''; ?>">

                    <input type="submit" value="Generate Report">
                    <a href="financial_reports.php"><input type="button" value="Reset"></a>
                </form>

                <?php if (isset($_GET['admin_export'])): ?>
                    <div class="report-details">
                        <p><strong>Total Income:</strong> <?php echo "$" . number_format($admin_income, 2); ?></p>
                        <p><strong>Total Expenses:</strong> <?php echo "$" . number_format($admin_expenses, 2); ?></p>
                        <p><strong>Net Balance:</strong> <?php echo "$" . number_format($admin_balance, 2); ?></p>
                    </div>

                    <!-- Export Button for Admin -->
                    <div class="export-buttons">
                        <form method="POST" action="export_report.php">
                            <input type="hidden" name="admin_export" value="1">
                            <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($selected_user); ?>">
                            <input type="hidden" name="start_date_admin" value="<?php echo htmlspecialchars($start_date_admin); ?>">
                            <input type="hidden" name="end_date_admin" value="<?php echo htmlspecialchars($end_date_admin); ?>">
                            <input type="submit" name="export_admin_report" value="Export Admin Report as CSV">
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Back Button -->
        <div class="back-button">
            <a href="<?php echo ($_SESSION['role'] == 'admin') ? 'admin_dashboard.php' : 'user_dashboard.php'; ?>">Back to Dashboard</a>
        </div>
    </div>
</body>
</html>
