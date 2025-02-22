<?php
require_once 'session.php';
require_once 'db.php';

session_start();

// Ensure user has appropriate permissions
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        // Validate CSRF token
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception("CSRF token validation failed.");
        }

        // Sanitize inputs
        $department = htmlspecialchars($_POST['department']);
        $date_required = htmlspecialchars($_POST['date_required']);
        $remarks = htmlspecialchars($_POST['remarks']);

        // Validate date format
        if (!DateTime::createFromFormat('Y-m-d', $date_required)) {
            throw new Exception("Invalid date format for 'date_required'.");
        }

        // Generate MR number
        $date = date('Ymd');
        $query = "SELECT MAX(SUBSTRING(mr_number, -4)) as max_num 
                 FROM material_requisitions 
                 WHERE mr_number LIKE 'MR$date%'";
        $result = $conn->query($query);
        $row = $result->fetch_assoc();
        $next_num = $row['max_num'] ? intval($row['max_num']) + 1 : 1;
        $mr_number = "MR" . $date . sprintf("%04d", $next_num);

        // Start transaction
        $conn->begin_transaction();

        // Insert MR
        $stmt = $conn->prepare("INSERT INTO material_requisitions 
            (mr_number, department, requested_by, date_required, status, remarks) 
            VALUES (?, ?, ?, ?, 'pending', ?)");
        $stmt->bind_param("ssiss", $mr_number, $department, $_SESSION['user_id'], $date_required, $remarks);
        $stmt->execute();
        $mr_id = $conn->insert_id;

        // Insert items
        if (!isset($_POST['items']) || empty($_POST['items'])) {
            throw new Exception("At least one item is required.");
        }

        $item_stmt = $conn->prepare("INSERT INTO mr_items 
            (mr_id, item_code, description, quantity, unit, purpose) 
            VALUES (?, ?, ?, ?, ?, ?)");

        foreach ($_POST['items'] as $item) {
            $item_stmt->bind_param("ississ", $mr_id, $item['item_code'], $item['description'], $item['quantity'], $item['unit'], $item['purpose']);
            $item_stmt->execute();
        }

        // Commit transaction
        $conn->commit();

        // Redirect on success
        $_SESSION['success_message'] = "Material Requisition saved successfully!";
        header("Location: mr_list.php");
        exit();

    } catch (Exception $e) {
        $conn->rollback(); // Rollback on error
        $_SESSION['error_message'] = "Error: " . $e->getMessage();
        header("Location: mr_form.php");
        exit();
    }
}

// Generate initial MR number for display
$mr_number = "MR" . date("Ymd") . sprintf("%04d", 1);

// Retrieve success/error messages from session
$success_message = '';
$error_message = '';

if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Material Requisition</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .navbar { background: linear-gradient(135deg, #1e40af, #3b82f6); }
        .document-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <!-- Back Button -->
        <div class="mb-3">
            <a href="stock_report.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back to Stock Report
            </a>
        </div>

        <!-- Success Message -->
        <?php if ($success_message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

        <!-- Error Message -->
        <?php if ($error_message): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

        <div class="document-card">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3>Material Requisition</h3>
                <div>
                    <strong>MR No: </strong><?php echo $mr_number; ?>
                </div>
            </div>
            <form method="POST" class="row g-3" id="mrForm">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <div class="col-md-6">
                    <label class="form-label">Department</label>
                    <input type="text" class="form-control" name="department" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Date Required</label>
                    <input type="date" class="form-control" name="date_required" required>
                </div>
                
                <!-- Items Table -->
                <div class="col-12">
                    <table class="table table-bordered" id="items_table">
                        <thead>
                            <tr>
                                <th>Item Code</th>
                                <th>Description</th>
                                <th>Quantity</th>
                                <th>Unit</th>
                                <th>Purpose</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Dynamic rows will be added here -->
                        </tbody>
                    </table>
                    <button type="button" class="btn btn-secondary" onclick="addRow()">
                        <i class="fas fa-plus me-2"></i>Add Item
                    </button>
                </div>

                <div class="col-12">
                    <label class="form-label">Remarks</label>
                    <textarea class="form-control" name="remarks" rows="3"></textarea>
                </div>

                <div class="col-12 mt-4">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Submit Requisition
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function addRow() {
        const tbody = document.querySelector('#items_table tbody');
        const rowCount = tbody.children.length;
        const newRow = document.createElement('tr');
        newRow.innerHTML = `
            <td>
                <input type="text" class="form-control" name="items[${rowCount}][item_code]" required>
            </td>
            <td>
                <input type="text" class="form-control" name="items[${rowCount}][description]" required>
            </td>
            <td>
                <input type="number" class="form-control" name="items[${rowCount}][quantity]" required min="1">
            </td>
            <td>
                <input type="text" class="form-control" name="items[${rowCount}][unit]" required>
            </td>
            <td>
                <input type="text" class="form-control" name="items[${rowCount}][purpose]" required>
            </td>
            <td>
                <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('tr').remove()">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        `;
        tbody.appendChild(newRow);
    }

    // Add initial row
    document.addEventListener('DOMContentLoaded', function() {
        addRow();
    });

    // Disable submit button after form submission
    document.getElementById('mrForm').addEventListener('submit', function() {
        const submitButton = this.querySelector('button[type="submit"]');
        submitButton.disabled = true;
        submitButton.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Submitting...';
    });
    </script>
</body>
</html>