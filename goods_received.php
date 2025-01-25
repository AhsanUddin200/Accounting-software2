<?php
require_once 'session.php';
require_once 'db.php';

// Generate GRN number
$query = "SELECT COUNT(*) as count FROM goods_receipt_notes WHERE DATE(created_at) = CURDATE()";
$result = $conn->query($query);
$count = ($result && $row = $result->fetch_assoc()) ? $row['count'] + 1 : 1;
$grn_number = "GRN" . date("Ymd") . sprintf("%04d", $count);

// Fetch pending POs for dropdown
$po_query = "SELECT po.id, po.po_number, po.supplier 
             FROM purchase_orders po 
             WHERE po.status = 'pending'
             ORDER BY po.created_at DESC";
$po_result = $conn->query($po_query);
$purchase_orders = [];
if ($po_result) {
    while ($row = $po_result->fetch_assoc()) {
        $purchase_orders[] = $row;
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn->begin_transaction();

        // Insert GRN header
        $stmt = $conn->prepare("INSERT INTO goods_receipt_notes (grn_number, po_id, supplier, delivery_date, qc_remarks, status) 
                               VALUES (?, ?, ?, ?, ?, 'pending')");
        $stmt->bind_param("sisss", 
            $grn_number,
            $_POST['po_id'],
            $_POST['supplier'],
            $_POST['delivery_date'],
            $_POST['qc_remarks']
        );
        $stmt->execute();
        $grn_id = $conn->insert_id;

        // Insert GRN items
        foreach ($_POST['items'] as $item) {
            $stmt = $conn->prepare("INSERT INTO grn_items (grn_id, item_code, description, ordered_qty, 
                                  received_qty, unit, condition_status, remarks) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("issddsss",
                $grn_id,
                $item['item_code'],
                $item['description'],
                $item['ordered_qty'],
                $item['received_qty'],
                $item['unit'],
                $item['condition'],
                $item['remarks']
            );
            $stmt->execute();
        }

        $conn->commit();
        header("Location: grn_list.php?success=1");
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        $error = "Error creating GRN: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Goods Received Note</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .document-card {
            background: white;
            border-radius: 8px;
            padding: 2rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
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

        <div class="document-card">
            <div class="d-flex justify-content-between mb-4">
                <h3>Goods Received Note</h3>
                <div>
                    <strong>GRN No: </strong><?php echo $grn_number; ?>
                </div>
            </div>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <form method="POST" id="grnForm" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">PO Reference</label>
                    <select class="form-select" name="po_id" required onchange="loadPOItems(this.value)">
                        <option value="">Select PO</option>
                        <?php foreach ($purchase_orders as $po): ?>
                            <option value="<?php echo $po['id']; ?>" 
                                    data-supplier="<?php echo htmlspecialchars($po['supplier']); ?>">
                                <?php echo htmlspecialchars($po['po_number']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Supplier</label>
                    <input type="text" class="form-control" name="supplier" id="supplier" readonly required>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Delivery Date</label>
                    <input type="date" class="form-control" name="delivery_date" required>
                </div>

                <!-- Items Table -->
                <div class="col-12">
                    <table class="table table-bordered" id="itemsTable">
                        <thead>
                            <tr>
                                <th>Item Code</th>
                                <th>Ordered Qty</th>
                                <th>Received Qty</th>
                                <th>Unit</th>
                                <th>Condition</th>
                                <th>Remarks</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="itemsBody">
                            <!-- Items will be loaded here -->
                        </tbody>
                    </table>
                </div>

                <div class="col-12">
                    <label class="form-label">Quality Check Remarks</label>
                    <textarea class="form-control" name="qc_remarks" rows="3"></textarea>
                </div>

                <div class="col-12 mt-4">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Create GRN
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function loadPOItems(poId) {
        if (!poId) return;
        
        // Set supplier
        const select = document.querySelector('select[name="po_id"]');
        const option = select.options[select.selectedIndex];
        document.getElementById('supplier').value = option.dataset.supplier;

        // Load PO items via AJAX
        fetch(`get_po_items.php?po_id=${poId}`)
            .then(response => response.json())
            .then(items => {
                const tbody = document.getElementById('itemsBody');
                tbody.innerHTML = items.map((item, index) => `
                    <tr>
                        <td>
                            <input type="text" name="items[${index}][item_code]" class="form-control" value="${item.item_code}" readonly required>
                        </td>
                        <td>
                            <input type="number" name="items[${index}][ordered_qty]" class="form-control" step="0.01" min="0" value="${item.quantity}" readonly required>
                        </td>
                        <td>
                            <input type="number" name="items[${index}][received_qty]" class="form-control" step="0.01" min="0" value="${item.quantity}" required>
                        </td>
                        <td>
                            <input type="text" name="items[${index}][unit]" class="form-control" value="${item.unit}" readonly required>
                        </td>
                        <td>
                            <select class="form-select" name="items[${index}][condition]" required>
                                <option value="good">Good</option>
                                <option value="damaged">Damaged</option>
                                <option value="partial">Partial</option>
                            </select>
                        </td>
                        <td>
                            <input type="text" class="form-control" name="items[${index}][remarks]">
                        </td>
                        <td>
                            <button type="button" class="btn btn-danger btn-sm delete-row" disabled>
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                `).join('');
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error loading PO items');
            });
    }
    </script>
</body>
</html> 