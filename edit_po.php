<?php
require_once 'session.php';
require_once 'db.php';

// Ensure user has appropriate permissions
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$error_message = '';
$success_message = '';

// Get PO details if ID is provided
if (!isset($_GET['id'])) {
    header("Location: po_list.php");
    exit();
}

$po_id = intval($_GET['id']);

// Fetch PO details
$query = "SELECT po.*, mr.mr_number 
          FROM purchase_orders po 
          LEFT JOIN material_requisitions mr ON po.mr_id = mr.id 
          WHERE po.id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $po_id);
$stmt->execute();
$result = $stmt->get_result();
$po = $result->fetch_assoc();

if (!$po) {
    header("Location: po_list.php");
    exit();
}

// Fetch PO items
$items_query = "SELECT * FROM po_items WHERE po_id = ?";
$stmt = $conn->prepare($items_query);
$stmt->bind_param("i", $po_id);
$stmt->execute();
$items_result = $stmt->get_result();
$po_items = [];
while ($item = $items_result->fetch_assoc()) {
    $po_items[] = $item;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn->begin_transaction();

        // Update PO details - removed 'notes' field
        $update_query = "UPDATE purchase_orders SET 
                        supplier = ?,
                        delivery_date = ?,
                        terms = ?,
                        total_amount = ?
                        WHERE id = ?";
        
        $supplier = $_POST['supplier'];
        $delivery_date = $_POST['delivery_date'];
        $terms = $_POST['terms'];
        $total_amount = 0;

        // Calculate total amount from items
        foreach ($_POST['items'] as $item) {
            $total_amount += floatval($item['quantity']) * floatval($item['unit_price']);
        }

        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("sssdi", $supplier, $delivery_date, $terms, $total_amount, $po_id);
        $stmt->execute();

        // Delete existing items
        $delete_items = "DELETE FROM po_items WHERE po_id = ?";
        $stmt = $conn->prepare($delete_items);
        $stmt->bind_param("i", $po_id);
        $stmt->execute();

        // Insert updated items
        $insert_item = "INSERT INTO po_items (po_id, item_code, description, quantity, unit, unit_price) 
                       VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($insert_item);

        foreach ($_POST['items'] as $item) {
            $stmt->bind_param("issdsd", 
                $po_id,
                $item['item_code'],
                $item['description'],
                $item['quantity'],
                $item['unit'],
                $item['unit_price']
            );
            $stmt->execute();
        }

        $conn->commit();
        header("Location: po_list.php?success=1");
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        $error_message = "Error updating PO: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Purchase Order</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-light">
    <div class="container mt-4">
        <div class="mb-3">
            <a href="po_list.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back to PO List
            </a>
        </div>

        <?php if ($error_message): ?>
        <div class="alert alert-danger">
            <?php echo $error_message; ?>
        </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Edit Purchase Order</h5>
            </div>
            <div class="card-body">
                <form method="POST" id="editPOForm">
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label">PO Number</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($po['po_number']); ?>" readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">MR Number</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($po['mr_number']); ?>" readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Supplier</label>
                            <input type="text" name="supplier" class="form-control" value="<?php echo htmlspecialchars($po['supplier']); ?>" required>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Delivery Date</label>
                            <input type="date" name="delivery_date" class="form-control" 
                                   value="<?php echo date('Y-m-d', strtotime($po['delivery_date'])); ?>" required>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Terms</label>
                            <input type="text" name="terms" class="form-control" 
                                   value="<?php echo htmlspecialchars($po['terms']); ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Items</label>
                        <div class="table-responsive">
                            <table class="table table-bordered" id="itemsTable">
                                <thead>
                                    <tr>
                                        <th>Item Code</th>
                                        <th>Description</th>
                                        <th>Quantity</th>
                                        <th>Unit</th>
                                        <th>Unit Price</th>
                                        <th>Total</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($po_items as $index => $item): ?>
                                    <tr>
                                        <td>
                                            <input type="text" name="items[<?php echo $index; ?>][item_code]" 
                                                   class="form-control" value="<?php echo htmlspecialchars($item['item_code']); ?>" required>
                                        </td>
                                        <td>
                                            <input type="text" name="items[<?php echo $index; ?>][description]" 
                                                   class="form-control" value="<?php echo htmlspecialchars($item['description']); ?>" required>
                                        </td>
                                        <td>
                                            <input type="number" name="items[<?php echo $index; ?>][quantity]" 
                                                   class="form-control quantity" value="<?php echo htmlspecialchars($item['quantity']); ?>" 
                                                   step="0.01" required>
                                        </td>
                                        <td>
                                            <input type="text" name="items[<?php echo $index; ?>][unit]" 
                                                   class="form-control" value="<?php echo htmlspecialchars($item['unit']); ?>" required>
                                        </td>
                                        <td>
                                            <input type="number" name="items[<?php echo $index; ?>][unit_price]" 
                                                   class="form-control unit-price" value="<?php echo htmlspecialchars($item['unit_price']); ?>" 
                                                   step="0.01" required>
                                        </td>
                                        <td>
                                            <input type="text" class="form-control item-total" readonly>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-danger btn-sm delete-row">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <button type="button" class="btn btn-success btn-sm" id="addRow">
                            <i class="fas fa-plus me-2"></i>Add Item
                        </button>
                    </div>

                    <div class="text-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const itemsTable = document.getElementById('itemsTable');
        const addRowBtn = document.getElementById('addRow');
        
        // Calculate totals on load
        calculateAllTotals();

        // Add new row
        addRowBtn.addEventListener('click', function() {
            const newRow = document.createElement('tr');
            const rowCount = itemsTable.querySelector('tbody').children.length;
            
            newRow.innerHTML = `
                <td>
                    <input type="text" name="items[${rowCount}][item_code]" class="form-control" required>
                </td>
                <td>
                    <input type="text" name="items[${rowCount}][description]" class="form-control" required>
                </td>
                <td>
                    <input type="number" name="items[${rowCount}][quantity]" class="form-control quantity" step="0.01" required>
                </td>
                <td>
                    <input type="text" name="items[${rowCount}][unit]" class="form-control" required>
                </td>
                <td>
                    <input type="number" name="items[${rowCount}][unit_price]" class="form-control unit-price" step="0.01" required>
                </td>
                <td>
                    <input type="text" class="form-control item-total" readonly>
                </td>
                <td>
                    <button type="button" class="btn btn-danger btn-sm delete-row">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            `;
            
            itemsTable.querySelector('tbody').appendChild(newRow);
            attachRowEvents(newRow);
        });

        // Delete row
        itemsTable.addEventListener('click', function(e) {
            if (e.target.closest('.delete-row')) {
                const row = e.target.closest('tr');
                if (itemsTable.querySelector('tbody').children.length > 1) {
                    row.remove();
                    renumberRows();
                    calculateAllTotals();
                }
            }
        });

        // Calculate total on input change
        itemsTable.addEventListener('input', function(e) {
            if (e.target.classList.contains('quantity') || e.target.classList.contains('unit-price')) {
                const row = e.target.closest('tr');
                calculateRowTotal(row);
            }
        });

        // Attach events to existing rows
        document.querySelectorAll('#itemsTable tbody tr').forEach(row => {
            attachRowEvents(row);
        });

        function attachRowEvents(row) {
            calculateRowTotal(row);
        }

        function calculateRowTotal(row) {
            const quantity = parseFloat(row.querySelector('.quantity').value) || 0;
            const unitPrice = parseFloat(row.querySelector('.unit-price').value) || 0;
            const total = quantity * unitPrice;
            row.querySelector('.item-total').value = total.toFixed(2);
        }

        function calculateAllTotals() {
            document.querySelectorAll('#itemsTable tbody tr').forEach(row => {
                calculateRowTotal(row);
            });
        }

        function renumberRows() {
            document.querySelectorAll('#itemsTable tbody tr').forEach((row, index) => {
                row.querySelectorAll('input[name*="items"]').forEach(input => {
                    const name = input.getAttribute('name');
                    input.setAttribute('name', name.replace(/items\[\d+\]/, `items[${index}]`));
                });
            });
        }
    });
    </script>
</body>
</html> 