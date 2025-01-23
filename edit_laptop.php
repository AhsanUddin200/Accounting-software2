<?php
require_once 'session.php';
require_once 'db.php';

// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Ensure user has appropriate permissions
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$success = $error = "";
$laptop = null;

// Get laptop ID from URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: laptop_report.php");
    exit();
}

$laptop_id = (int)$_GET['id'];

// Fetch all users (potential custodians)
$users = [];
$user_query = "SELECT id, username FROM users ORDER BY username";
$user_result = $conn->query($user_query);
if ($user_result) {
    while ($row = $user_result->fetch_assoc()) {
        $users[] = $row;
    }
}

// Fetch existing laptop data
try {
    $query = "SELECT * FROM laptops WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $laptop_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $laptop = $result->fetch_assoc();

    if (!$laptop) {
        header("Location: laptop_report.php");
        exit();
    }
} catch (Exception $e) {
    $error = "Error fetching laptop details: " . $e->getMessage();
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
        $specifications = trim($_POST['specifications']);
        $location = trim($_POST['location']);
        $notes = trim($_POST['notes']);

        // Update database
        $query = "UPDATE laptops SET 
            asset_id = ?, 
            model = ?, 
            serial_number = ?, 
            status = ?, 
            custodian_id = ?,
            purchase_date = ?, 
            purchase_price = ?, 
            current_value = ?, 
            specifications = ?, 
            location = ?, 
            notes = ?
            WHERE id = ?";

        $stmt = $conn->prepare($query);
        $stmt->bind_param(
            "ssssssddsssi",
            $asset_id,
            $model,
            $serial_number,
            $status,
            $custodian_id,
            $purchase_date,
            $purchase_price,
            $current_value,
            $specifications,
            $location,
            $notes,
            $laptop_id
        );

        if ($stmt->execute()) {
            $success = "Laptop updated successfully!";
            
            // Refresh laptop data
            $query = "SELECT * FROM laptops WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $laptop_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $laptop = $result->fetch_assoc();
        } else {
            throw new Exception("Error executing update: " . $stmt->error);
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
        error_log("Database Error: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Laptop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .navbar {
            background: linear-gradient(135deg, #1e40af, #3b82f6);
        }
        .form-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <i class="fas fa-laptop me-2"></i>Edit Laptop
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

        <div class="form-card">
            <form method="POST" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Asset ID</label>
                    <input type="text" class="form-control" name="asset_id" 
                           value="<?php echo htmlspecialchars($laptop['asset_id']); ?>" required>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Model</label>
                    <input type="text" class="form-control" name="model" 
                           value="<?php echo htmlspecialchars($laptop['model']); ?>" required>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Serial Number</label>
                    <input type="text" class="form-control" name="serial_number" 
                           value="<?php echo htmlspecialchars($laptop['serial_number']); ?>" required>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status" required>
                        <?php
                        $statuses = ['active', 'maintenance', 'sold'];
                        foreach ($statuses as $status) {
                            $selected = ($laptop['status'] === $status) ? 'selected' : '';
                            echo "<option value=\"$status\" $selected>" . ucfirst($status) . "</option>";
                        }
                        ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Custodian</label>
                    <select class="form-select" name="custodian_id">
                        <option value="">Select Custodian...</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?php echo $user['id']; ?>" 
                                    <?php echo ($laptop['custodian_id'] == $user['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($user['username']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Purchase Date</label>
                    <input type="date" class="form-control" name="purchase_date" 
                           value="<?php echo htmlspecialchars($laptop['purchase_date']); ?>" required>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Purchase Price</label>
                    <div class="input-group">
                        <span class="input-group-text">PKR</span>
                        <input type="number" step="0.01" class="form-control" name="purchase_price" 
                               value="<?php echo htmlspecialchars($laptop['purchase_price']); ?>" required>
                    </div>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Current Value</label>
                    <div class="input-group">
                        <span class="input-group-text">PKR</span>
                        <input type="number" step="0.01" class="form-control" name="current_value" 
                               value="<?php echo htmlspecialchars($laptop['current_value']); ?>" required>
                    </div>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Location</label>
                    <input type="text" class="form-control" name="location" 
                           value="<?php echo htmlspecialchars($laptop['location']); ?>">
                </div>

                <div class="col-md-12">
                    <label class="form-label">Specifications</label>
                    <textarea class="form-control" name="specifications" rows="3"
                    ><?php echo htmlspecialchars($laptop['specifications']); ?></textarea>
                </div>

                <div class="col-md-12">
                    <label class="form-label">Notes</label>
                    <textarea class="form-control" name="notes" rows="3"
                    ><?php echo htmlspecialchars($laptop['notes']); ?></textarea>
                </div>

                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Update Laptop
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 