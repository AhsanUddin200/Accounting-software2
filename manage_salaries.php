<?php
// manage_salaries.php

require_once __DIR__ . '/session.php';        // Include session management
require_once __DIR__ . '/db.php';             // Include database connection
require_once __DIR__ . '/functions.php';      // Include common functions

// Check if the user is an admin
if ($_SESSION['role'] != 'admin') {
    header("Location: user_dashboard.php");
    exit();
}

// Handle form submission to update salaries
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    $successes = [];

    if (!isset($_POST['salaries']) || !is_array($_POST['salaries'])) {
        $errors[] = "Invalid form submission.";
    } else {
        foreach ($_POST['salaries'] as $user_id => $salary) {
            // Validate salary input
            $salary = floatval($salary);
            if ($salary < 0) {
                $errors[] = "Invalid salary amount for User ID: $user_id.";
                continue;
            }

            // Update salary in database
            $stmt = $conn->prepare("UPDATE users SET fixed_salary = ? WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("di", $salary, $user_id);
                if ($stmt->execute()) {
                    log_action($conn, $_SESSION['user_id'], 'Updated Salary', "Updated salary to $$salary for User ID: $user_id.");
                    $successes[] = "Salary updated for User ID: $user_id.";
                } else {
                    $errors[] = "Failed to update salary for User ID: $user_id.";
                }
                $stmt->close();
            } else {
                $errors[] = "Database error: Unable to prepare statement.";
            }
        }
    }
}

// Fetch all users with their current salaries
$users_result = $conn->query("SELECT id, username, fixed_salary FROM users ORDER BY id ASC");
if (!$users_result) {
    die("Query failed (Fetch Users): (" . $conn->errno . ") " . $conn->error);
}

$users = [];
while ($user = $users_result->fetch_assoc()) {
    $users[] = $user;
}
$users_result->close();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Manage Salaries</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .card { margin: 15px 0; }
        .table-responsive { max-height: 400px; }
        .btn-custom {
            width: 200px;
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
                        <a class="nav-link" href="process_salaries.php">Process Salaries</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="financial_reports.php">Financial Reports</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="admin_dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">Logout (<?php echo htmlspecialchars($_SESSION['username']); ?>)</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Manage Salaries Content -->
    <div class="container mt-4">
        <h2>Manage Salaries</h2>

        <!-- Display Success Messages -->
        <?php if (!empty($successes)): ?>
            <div class="alert alert-success">
                <?php foreach ($successes as $msg): ?>
                    <p><?php echo htmlspecialchars($msg); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Display Error Messages -->
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <?php foreach ($errors as $err): ?>
                    <p><?php echo htmlspecialchars($err); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Salaries Form -->
        <form method="POST" action="manage_salaries.php">
            <table class="table table-bordered">
                <thead class="table-dark">
                    <tr>
                        <th>User ID</th>
                        <th>Username</th>
                        <th>Current Salary ($)</th>
                        <th>New Salary ($)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($users) > 0): ?>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['id']); ?></td>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td><?php echo number_format($user['fixed_salary'], 2); ?></td>
                                <td>
                                    <input type="number" step="0.01" min="0" name="salaries[<?php echo $user['id']; ?>]" 
                                           class="form-control" value="<?php echo number_format($user['fixed_salary'], 2); ?>" required>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="text-center">No users found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            <button type="submit" class="btn btn-primary">Update Salaries</button>
        </form>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
