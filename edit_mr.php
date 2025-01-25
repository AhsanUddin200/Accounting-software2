<?php
require_once 'session.php';
require_once 'db.php';

// Ensure user has appropriate permissions
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Get MR ID from query string
$mr_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch MR details
$query = "SELECT * FROM material_requisitions WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $mr_id);
$stmt->execute();
$result = $stmt->get_result();
$mr = $result->fetch_assoc();

// Fetch MR items
$item_query = "SELECT * FROM mr_items WHERE mr_id = ?";
$item_stmt = $conn->prepare($item_query);
$item_stmt->bind_param("i", $mr_id);
$item_stmt->execute();
$item_result = $item_stmt->get_result();
$items = $item_result->fetch_all(MYSQLI_ASSOC);

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        // Update MR details
        $update_query = "UPDATE material_requisitions SET department = ?, date_required = ?, remarks = ? WHERE id = ?";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param("sssi", $_POST['department'], $_POST['date_required'], $_POST['remarks'], $mr_id);
        $update_stmt->execute();

        // Delete existing items
        $delete_items_query = "DELETE FROM mr_items WHERE mr_id = ?";
        $delete_items_stmt = $conn->prepare($delete_items_query);
        $delete_items_stmt->bind_param("i", $mr_id);
        $delete_items_stmt->execute();

        // Insert updated items
        if (isset($_POST['items']) && is_array($_POST['items'])) {
            $item_stmt = $conn->prepare("INSERT INTO mr_items 
                (mr_id, item_code, description, quantity, unit, purpose) 
                VALUES (?, ?, ?, ?, ?, ?)");

            foreach ($_POST['items'] as $item) {
                $item_stmt->bind_param("ississ",
                    $mr_id,
                    $item['item_code'],
                    $item['description'],
                    $item['quantity'],
                    $item['unit'],
                    $item['purpose']
                );
                $item_stmt->execute();
            }
        }

        // Redirect on success
        header("Location: mr_list.php?success=1");
        exit();

    } catch (Exception $e) {
        $error_message = "Error: " . $e->getMessage();
        error_log($error_message);
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Material Requisition</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <h3>Edit Material Requisition</h3>
        <form method="POST" class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Department</label>
                <input type="text" class="form-control" name="department" value="<?php echo htmlspecialchars($mr['department']); ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Date Required</label>
                <input type="date" class="form-control" name="date_required" value="<?php echo htmlspecialchars($mr['date_required']); ?>" required>
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
                        <?php foreach ($items as $index => $item): ?>
                        <tr>
                            <td>
                                <input type="text" class="form-control" name="items[<?php echo $index; ?>][item_code]" value="<?php echo htmlspecialchars($item['item_code']); ?>" required>
                            </td>
                            <td>
                                <input type="text" class="form-control" name="items[<?php echo $index; ?>][description]" value="<?php echo htmlspecialchars($item['description']); ?>" required>
                            </td>
                            <td>
                                <input type="number" class="form-control" name="items[<?php echo $index; ?>][quantity]" value="<?php echo htmlspecialchars($item['quantity']); ?>" required min="1">
                            </td>
                            <td>
                                <input type="text" class="form-control" name="items[<?php echo $index; ?>][unit]" value="<?php echo htmlspecialchars($item['unit']); ?>" required>
                            </td>
                            <td>
                                <input type="text" class="form-control" name="items[<?php echo $index; ?>][purpose]" value="<?php echo htmlspecialchars($item['purpose']); ?>" required>
                            </td>
                            <td>
                                <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('tr').remove()">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <button type="button" class="btn btn-secondary" onclick="addRow()">
                    <i class="fas fa-plus me-2"></i>Add Item
                </button>
            </div>

            <div class="col-12">
                <label class="form-label">Remarks</label>
                <textarea class="form-control" name="remarks" rows="3"><?php echo htmlspecialchars($mr['remarks']); ?></textarea>
            </div>

            <div class="col-12 mt-4">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-2"></i>Save Changes
                </button>
            </div>
        </form>
    </div>

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
    </script>
</body>
</html> 