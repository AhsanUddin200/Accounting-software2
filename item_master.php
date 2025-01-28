<?php
require_once 'session.php';
require_once 'db.php';

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Ensure user has appropriate permissions
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

try {
    // Fetch all items from the master list
    $query = "SELECT id, item_code, name, category, unit, minimum_quantity, location, description, created_at FROM items ORDER BY category, name";
    $result = $conn->query($query);
    $items = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $items[] = $row;
        }
    }
} catch (Exception $e) {
    // Log the error
    error_log("Database Error: " . $e->getMessage());
    // Show a user-friendly message
    die("An error occurred while fetching items. Please try again later.");
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Item Master List</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .navbar {
            background: linear-gradient(135deg, #1e40af, #3b82f6);
        }
        .card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .table th {
            background-color: #f8f9fa;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container mt-4">
        <!-- Back Button -->
        <div class="mb-3">
            <a href="stock_report.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back to Stock Report
            </a>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Item Master List</h5>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addItemModal">
                    <i class="fas fa-plus me-2"></i>Add New Item
                </button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Item Code</th>
                                <th>Name</th>
                                <th>Category</th>
                                <th>Unit</th>
                                <th>Min. Quantity</th>
                                <th>Location</th>
                                <th>Description</th>
                                <th>Created At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($items) > 0): ?>
                                <?php foreach ($items as $item): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($item['item_code']); ?></td>
                                        <td><?php echo htmlspecialchars($item['name']); ?></td>
                                        <td><?php echo htmlspecialchars($item['category']); ?></td>
                                        <td><?php echo htmlspecialchars($item['unit']); ?></td>
                                        <td><?php echo htmlspecialchars($item['minimum_quantity']); ?></td>
                                        <td><?php echo htmlspecialchars($item['location'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($item['description']); ?></td>
                                        <td><?php echo date('Y-m-d', strtotime($item['created_at'])); ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-primary" onclick="editItem(<?php echo $item['id']; ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-danger" onclick="deleteItem(<?php echo $item['id']; ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" class="text-center">No items found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Item Modal -->
    <div class="modal fade" id="addItemModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Item</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="addItemForm">
                        <div class="mb-3">
                            <label for="name" class="form-label">Item Name</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="category" class="form-label">Category</label>
                            <select class="form-select" id="category" name="category" required>
                                <option value="">Select Category</option>
                                <option value="MR">Material Requisition</option>
                                <option value="GRN">Goods Receipt Note</option>
                                <option value="PO">Purchase Order</option>
                                <option value="Raw Material">Raw Material</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="unit" class="form-label">Unit</label>
                            <select class="form-select" id="unit" name="unit" required>
                                <option value="">Select Unit</option>
                                <option value="PCS">Pieces (PCS)</option>
                                <option value="KG">Kilograms (KG)</option>
                                <option value="MTR">Meters (MTR)</option>
                                <option value="LTR">Liters (LTR)</option>
                                <option value="BOX">Box (BOX)</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="minimum_quantity" class="form-label">Minimum Quantity</label>
                            <input type="number" class="form-control" id="minimum_quantity" name="minimum_quantity" min="0" required>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" onclick="submitItemForm()">Add Item</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function submitItemForm() {
            const form = document.getElementById('addItemForm');
            const formData = new FormData(form);

            fetch('add_master_item.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Item added successfully!');
                    location.reload();
                } else {
                    alert(data.message || 'Error adding item');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error adding item');
            });
        }

        function editItem(itemId) {
            // Implement edit functionality
            alert('Edit functionality coming soon!');
        }

        function deleteItem(itemId) {
            if (confirm('Are you sure you want to delete this item?')) {
                fetch('delete_item.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `id=${itemId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Item deleted successfully!');
                        location.reload();
                    } else {
                        alert(data.message || 'Error deleting item');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error deleting item');
                });
            }
        }
    </script>
</body>
</html> 