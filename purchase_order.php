<?php
require_once 'session.php';
require_once 'db.php';

// Fetch MR numbers from your existing table
$mr_query = "SELECT id, mr_number, department FROM material_requisitions WHERE status = 'pending' ORDER BY mr_number ASC";
$mr_result = $conn->query($mr_query);
$material_requisitions = [];
if ($mr_result) {
    while ($row = $mr_result->fetch_assoc()) {
        $material_requisitions[] = $row;
    }
}

// Generate PO number
$date = date("Ymd");
$query = "SELECT MAX(SUBSTRING(po_number, -4)) as max_num 
         FROM purchase_orders 
         WHERE po_number LIKE 'PO$date%'";
$result = $conn->query($query);
$row = $result->fetch_assoc();
$next_num = $row['max_num'] ? intval($row['max_num']) + 1 : 1;
$po_number = "PO" . $date . sprintf("%04d", $next_num);

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        $conn->begin_transaction();

        // Insert into purchase_orders
        $stmt = $conn->prepare("INSERT INTO purchase_orders 
            (po_number, mr_id, supplier, delivery_date, status, terms, total_amount) 
            VALUES (?, ?, ?, ?, 'pending', ?, ?)");
        
        $stmt->bind_param("sisssd", 
            $po_number,
            $_POST['mr_id'],
            $_POST['supplier'],
            $_POST['delivery_date'],
            $_POST['terms'],
            $_POST['total_amount']
        );

        if (!$stmt->execute()) {
            throw new Exception("Error saving PO: " . $stmt->error);
        }

        $po_id = $conn->insert_id;

        // Insert items
        if (isset($_POST['items']) && is_array($_POST['items'])) {
            $stmt = $conn->prepare("INSERT INTO po_items 
                (po_id, item_code, description, quantity, unit, unit_price, total) 
                VALUES (?, ?, ?, ?, ?, ?, ?)");

            foreach ($_POST['items'] as $item) {
                $total = $item['quantity'] * $item['unit_price'];
                $stmt->bind_param("ississd",
                    $po_id,
                    $item['item_code'],
                    $item['description'],
                    $item['quantity'],
                    $item['unit'],
                    $item['unit_price'],
                    $total
                );
                
                if (!$stmt->execute()) {
                    throw new Exception("Error saving item: " . $stmt->error);
                }
            }
        }

        $conn->commit();
        header("Location: po_list.php?success=1");
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        $error_message = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Purchase Order</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            padding: 20px;
        }

        .document-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 30px;
            margin-bottom: 20px;
        }

        .header-section {
            border-bottom: 2px solid #e9ecef;
            margin-bottom: 25px;
            padding-bottom: 15px;
        }

        .po-number {
            background: #f8f9fa;
            padding: 10px 15px;
            border-radius: 5px;
            font-weight: bold;
        }

        .form-label {
            font-weight: 600;
            color: #495057;
        }

        .form-control, .form-select {
            border: 1px solid #ced4da;
            padding: 8px 12px;
            border-radius: 5px;
            transition: border-color 0.15s ease-in-out;
        }

        .form-control:focus, .form-select:focus {
            border-color: #80bdff;
            box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25);
        }

        .table {
            border: 1px solid #dee2e6;
            margin-top: 20px;
        }

        .table th {
            background-color: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
            color: #495057;
            font-weight: 600;
        }

        .table td {
            vertical-align: middle;
        }

        .total-row {
            background-color: #f8f9fa;
            font-weight: bold;
        }

        .btn-primary {
            background-color: #0d6efd;
            border: none;
            padding: 10px 20px;
            transition: background-color 0.15s ease-in-out;
        }

        .btn-primary:hover {
            background-color: #0b5ed7;
        }

        .btn-success {
            margin-top: 10px;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
        }

        .currency-label {
            font-weight: bold;
            color: #495057;
        }

        .terms-section {
            margin-top: 20px;
        }

        .submit-section {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .document-card {
                padding: 15px;
            }
            
            .table-responsive {
                border: 0;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="mb-3">
            <a href="po_list.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back to List
            </a>
        </div>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <div class="document-card">
            <div class="header-section d-flex justify-content-between align-items-center">
                <h2 class="mb-0">Purchase Order</h2>
                <div class="po-number">
                    PO No: <?php echo htmlspecialchars($po_number); ?>
                </div>
            </div>

            <form method="POST" id="poForm">
                <div class="row g-3">
                    <!-- MR Selection -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Material Requisition</label>
                            <select class="form-select" name="mr_id" required>
                                <option value="">Select MR Number</option>
                                <?php foreach ($material_requisitions as $mr): ?>
                                    <option value="<?php echo $mr['id']; ?>">
                                        <?php echo htmlspecialchars($mr['mr_number']); ?> - <?php echo htmlspecialchars($mr['department']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Supplier Section -->
                    <div class="col-md-6">
                        <label class="form-label">Supplier</label>
                        <input type="text" class="form-control" name="supplier" required>
                    </div>

                    <!-- Delivery Date Section -->
                    <div class="col-md-6">
                        <label class="form-label">Delivery Date</label>
                        <input type="date" class="form-control" name="delivery_date" required>
                    </div>

                    <!-- Items Table -->
                    <div class="col-12">
                        <div class="table-responsive">
                            <table class="table" id="items_table">
                                <thead>
                                    <tr>
                                        <th>Item Code</th>
                                        <th>Description</th>
                                        <th>Quantity</th>
                                        <th>Unit</th>
                                        <th>Unit Price</th>
                                        <th>Total</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                </tbody>
                                <tfoot>
                                    <tr class="total-row">
                                        <td colspan="5" class="text-end">
                                            <span class="currency-label">Total Amount:</span>
                                        </td>
                                        <td colspan="2">
                                            <div class="input-group">
                                                <span class="input-group-text">PKR</span>
                                                <input type="number" class="form-control" name="total_amount" id="total_amount" readonly>
                                            </div>
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        <button type="button" class="btn btn-success" onclick="addRow()">
                            <i class="fas fa-plus me-2"></i>Add Item
                        </button>
                    </div>

                    <!-- Terms Section -->
                    <div class="col-12 terms-section">
                        <label class="form-label">Terms & Conditions</label>
                        <textarea class="form-control" name="terms" rows="3"></textarea>
                    </div>

                    <!-- Submit Section -->
                    <div class="col-12 submit-section">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Create Purchase Order
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function loadMRItems(mrId) {
        if (!mrId) return;
        
        fetch(`get_mr_items.php?mr_id=${mrId}`)
            .then(response => response.json())
            .then(items => {
                const tbody = document.querySelector('#items_table tbody');
                tbody.innerHTML = ''; // Clear existing items
                
                items.forEach((item, index) => {
                    addRow(item);
                });
            })
            .catch(error => console.error('Error:', error));
    }

    function addRow(item = null) {
        const tbody = document.querySelector('#items_table tbody');
        const rowCount = tbody.children.length;
        const newRow = document.createElement('tr');
        
        newRow.innerHTML = `
            <td>
                <input type="text" class="form-control" name="items[${rowCount}][item_code]" 
                    value="${item ? item.item_code : ''}" ${item ? 'readonly' : ''} required>
            </td>
            <td>
                <input type="text" class="form-control" name="items[${rowCount}][description]" 
                    value="${item ? item.description : ''}" ${item ? 'readonly' : ''} required>
            </td>
            <td>
                <input type="number" class="form-control quantity" name="items[${rowCount}][quantity]" 
                    value="${item ? item.quantity : ''}" min="1" required onchange="calculateTotal(this)">
            </td>
            <td>
                <input type="text" class="form-control" name="items[${rowCount}][unit]" 
                    value="${item ? item.unit : ''}" ${item ? 'readonly' : ''} required>
            </td>
            <td>
                <input type="number" class="form-control unit-price" name="items[${rowCount}][unit_price]" 
                    min="0" step="0.01" required onchange="calculateTotal(this)">
            </td>
            <td>
                <input type="number" class="form-control row-total" readonly>
            </td>
            <td>
                <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('tr').remove(); updateGrandTotal();">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        `;
        tbody.appendChild(newRow);
    }

    function calculateTotal(input) {
        const row = input.closest('tr');
        const quantity = parseFloat(row.querySelector('.quantity').value) || 0;
        const unitPrice = parseFloat(row.querySelector('.unit-price').value) || 0;
        const total = quantity * unitPrice;
        row.querySelector('.row-total').value = total.toFixed(2);
        updateGrandTotal();
    }

    function updateGrandTotal() {
        const totals = [...document.querySelectorAll('.row-total')];
        const grandTotal = totals.reduce((sum, input) => sum + (parseFloat(input.value) || 0), 0);
        document.getElementById('total_amount').value = grandTotal.toFixed(2);
    }

    // Add initial row
    document.addEventListener('DOMContentLoaded', function() {
        addRow();
    });
    </script>
</body>
</html> 