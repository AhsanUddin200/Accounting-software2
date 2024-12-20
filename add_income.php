<?php
// add_income.php
require_once 'session.php';
require_once 'db.php'; // Ensure you have a db.php file that connects to your database
require_once 'functions.php'; // Ensure you have functions.php for logging

// Sirf admin hi income add kar sakta hai
if ($_SESSION['role'] != 'admin') {
    header("Location: admin_dashboard.php");
    exit();
}

// Initialize variables
$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // CSRF Token Validation
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("Invalid CSRF token.");
    }

    // Sanitize and validate inputs
    $description = trim($_POST['description']);
    $amount = trim($_POST['amount']);
    $date = trim($_POST['date']);
    $admin_id = $_SESSION['user_id']; // Admin adding the income

    // Basic validation
    if (empty($description) || empty($amount) || empty($date)) {
        $error = "All fields are required.";
    } elseif (!is_numeric($amount) || $amount <= 0) {
        $error = "Amount must be a positive number.";
    } else {
        // Insert into transactions table with type 'income'
        $stmt = $conn->prepare("INSERT INTO transactions (type, description, amount, date, added_by_admin_id) VALUES ('income', ?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("sdsi", $description, $amount, $date, $admin_id);
            if ($stmt->execute()) {
                // Log the action
                log_action($conn, $admin_id, 'Added Income', "Description: $description, Amount: $amount, Date: $date");
                $success = "Income added successfully.";
            } else {
                $error = "Failed to add income: (" . $stmt->errno . ") " . $stmt->error;
            }
            $stmt->close();
        } else {
            $error = "Prepare failed: (" . $conn->errno . ") " . $conn->error;
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Add Income</title>
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
                    <?php if ($_SESSION['role'] == 'admin'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="admin_dashboard.php">Admin Dashboard</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="view_audit_logs.php">View Audit Logs</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="add_income.php">Add Income</a>
                        </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">Logout (<?php echo htmlspecialchars($_SESSION['username']); ?>)</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Add Income Content -->
    <div class="container mt-4">
        <h2>Add Income</h2>
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <form method="POST" action="add_income.php">
            <!-- CSRF Token -->
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            
            <div class="mb-3">
                <label for="description" class="form-label">Description:</label>
                <input type="text" class="form-control" id="description" name="description" required value="<?php echo htmlspecialchars($_POST['description'] ?? ''); ?>">
            </div>
            
            <div class="mb-3">
                <label for="amount" class="form-label">Amount ($):</label>
                <input type="number" step="0.01" class="form-control" id="amount" name="amount" required value="<?php echo htmlspecialchars($_POST['amount'] ?? ''); ?>">
            </div>
            
            <div class="mb-3">
                <label for="date" class="form-label">Date:</label>
                <input type="date" class="form-control" id="date" name="date" required value="<?php echo htmlspecialchars($_POST['date'] ?? date('Y-m-d')); ?>">
            </div>
            
            <button type="submit" class="btn btn-primary">Add Income</button>
        </form>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
