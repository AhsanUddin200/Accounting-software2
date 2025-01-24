<?php
// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'session.php';
require_once 'db.php';

// Ensure user has appropriate permissions
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$success = $error = "";

// Fetch all users (potential custodians)
$users = [];
$user_query = "SELECT id, username FROM users ORDER BY username";
$user_result = $conn->query($user_query);
if ($user_result) {
    while ($row = $user_result->fetch_assoc()) {
        $users[] = $row;
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate and sanitize input
        $asset_id = trim($_POST['asset_id']);
        $model = trim($_POST['model']);
        $serial_number = trim($_POST['serial_number']);
        $status = $_POST['status'];
        $custodian_id = !empty($_POST['custodian_id']) ? $_POST['custodian_id'] : null;
        $purchase_date = $_POST['purchase_date'];
        $purchase_price = $_POST['purchase_price'];
        $current_value = $_POST['current_value'];
        $sale_value = !empty($_POST['sale_value']) ? $_POST['sale_value'] : null;
        $specifications = trim($_POST['specifications']);
        $location = trim($_POST['location']);
        $notes = trim($_POST['notes']);

        // Insert into database
        $query = "INSERT INTO laptops (
            asset_id, model, serial_number, status, custodian_id,
            purchase_date, purchase_price, current_value, sale_value, specifications, location, notes
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }

        $stmt->bind_param(
            "sssssdddssss",
            $asset_id,
            $model,
            $serial_number,
            $status,
            $custodian_id,
            $purchase_date,
            $purchase_price,
            $current_value,
            $sale_value,
            $specifications,
            $location,
            $notes
        );

        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }

        $success = "Laptop added successfully!";
        
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
        error_log("Database Error: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Add New Laptop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <i class="fas fa-laptop me-2"></i>Add New Laptop
            </a>
            <div class="ms-auto">
                <a href="laptop_report.php" class="nav-link text-white">
                    <i class="fas fa-arrow-left me-1"></i> Back to Laptop Report
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="card-body">
                <form method="POST" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Asset ID</label>
                        <input type="text" class="form-control" name="asset_id" required>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Model</label>
                        <input type="text" class="form-control" name="model" required>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Serial Number</label>
                        <input type="text" class="form-control" name="serial_number" required>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status" required>
                            <option value="active">Active</option>
                            <option value="maintenance">In Maintenance</option>
                            <option value="sold">Sold/Disposed</option>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Custodian</label>
                        <select class="form-select" name="custodian_id">
                            <option value="">Select Custodian...</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['id']; ?>">
                                    <?php echo htmlspecialchars($user['username']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Purchase Date</label>
                        <input type="date" class="form-control" name="purchase_date" required>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Purchase Price</label>
                        <input type="number" step="0.01" class="form-control" name="purchase_price" required>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Current Value</label>
                        <input type="number" step="0.01" class="form-control" name="current_value" required>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Sale Value</label>
                        <input type="number" step="0.01" class="form-control" name="sale_value">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Location</label>
                        <input type="text" class="form-control" name="location">
                    </div>

                    <div class="col-md-12">
                        <label class="form-label">Specifications</label>
                        <textarea class="form-control" name="specifications" rows="3"></textarea>
                    </div>

                    <div class="col-md-12">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" name="notes" rows="3"></textarea>
                    </div>

                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>Add Laptop
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 