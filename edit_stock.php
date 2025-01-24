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
$item = null;

// Get item ID from URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: stock_report.php");
    exit();
}

$item_id = (int)$_GET['id'];

// Fetch existing item data
try {
    $query = "SELECT * FROM stock_items WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $item_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $item = $result->fetch_assoc();

    if (!$item) {
        header("Location: stock_report.php");
        exit();
    }
} catch (Exception $e) {
    $error = "Error fetching item details: " . $e->getMessage();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Check if item code already exists for other items
        $check_query = "SELECT id FROM stock_items WHERE item_code = ? AND id != ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param("si", $_POST['item_code'], $item_id);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        
        if ($result->num_rows > 0) {
            throw new Exception("Item code already exists. Please use a unique item code.");
        }

        // Validate and sanitize input
        $item_code = trim($_POST['item_code']);
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $category = trim($_POST['category']);
        $quantity = (int)$_POST['quantity'];
        $unit = trim($_POST['unit']);
        $unit_price = (float)$_POST['unit_price'];
        $location = trim($_POST['location']);
        $minimum_quantity = (int)$_POST['minimum_quantity'];
        $last_restock_date = $_POST['last_restock_date'];

        // Update database
        $query = "UPDATE stock_items SET 
            item_code = ?, 
            name = ?, 
            description = ?, 
            category = ?, 
            quantity = ?, 
            unit = ?,
            unit_price = ?, 
            location = ?, 
            minimum_quantity = ?, 
            last_restock_date = ?
            WHERE id = ?";

        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }

        $stmt->bind_param(
            "ssssisdsiis",
            $item_code,
            $name,
            $description,
            $category,
            $quantity,
            $unit,
            $unit_price,
            $location,
            $minimum_quantity,
            $last_restock_date,
            $item_id
        );

        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }

        $success = "Stock item updated successfully!";
        
        // Refresh item data
        $query = "SELECT * FROM stock_items WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $item_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $item = $result->fetch_assoc();
        
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
        error_log("Database Error: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Stock Item</title>
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
                <i class="fas fa-edit me-2"></i>Edit Stock Item
            </a>
            <div class="ms-auto">
                <a href="stock_report.php" class="nav-link text-white">
                    <i class="fas fa-arrow-left me-1"></i> Back to Stock Report
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
                    <label class="form-label">Item Code</label>
                    <input type="text" class="form-control" name="item_code" 
                           value="<?php echo htmlspecialchars($item['item_code']); ?>" required>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Name</label>
                    <input type="text" class="form-control" name="name" 
                           value="<?php echo htmlspecialchars($item['name']); ?>" required>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Category</label>
                    <input type="text" class="form-control" name="category" 
                           value="<?php echo htmlspecialchars($item['category']); ?>" required>
                </div>

                <div class="col-md-12">
                    <label class="form-label">Description</label>
                    <textarea class="form-control" name="description" rows="3"
                    ><?php echo htmlspecialchars($item['description']); ?></textarea>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Quantity</label>
                    <input type="number" class="form-control" name="quantity" 
                           value="<?php echo htmlspecialchars($item['quantity']); ?>" required min="0">
                </div>

                <div class="col-md-4">
                    <label class="form-label">Unit</label>
                    <select class="form-select" name="unit" required>
                        <option value="">Select Unit...</option>
                        <?php
                        $units = ['pieces', 'kg', 'meters', 'liters', 'boxes', 'pairs'];
                        foreach ($units as $unit) {
                            $selected = ($item['unit'] === $unit) ? 'selected' : '';
                            echo "<option value=\"$unit\" $selected>" . ucfirst($unit) . "</option>";
                        }
                        ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Unit Price</label>
                    <div class="input-group">
                        <span class="input-group-text">PKR</span>
                        <input type="number" step="0.01" class="form-control" name="unit_price" 
                               value="<?php echo htmlspecialchars($item['unit_price']); ?>" required min="0">
                    </div>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Minimum Quantity</label>
                    <input type="number" class="form-control" name="minimum_quantity" 
                           value="<?php echo htmlspecialchars($item['minimum_quantity']); ?>" required min="0">
                </div>

                <div class="col-md-4">
                    <label class="form-label">Location</label>
                    <input type="text" class="form-control" name="location" 
                           value="<?php echo htmlspecialchars($item['location']); ?>">
                </div>

                <div class="col-md-4">
                    <label class="form-label">Last Restock Date</label>
                    <input type="date" class="form-control" name="last_restock_date" 
                           value="<?php echo htmlspecialchars($item['last_restock_date']); ?>" required>
                </div>

                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Update Stock Item
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>