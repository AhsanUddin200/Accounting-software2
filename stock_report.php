<?php
require_once 'session.php';
require_once 'db.php';

// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Check if user is logged in and has proper access
if (!isset($_SESSION['username']) || 
    ($_SESSION['username'] !== 'saim' && $_SESSION['username'] !== 'admin')) {
    header("Location: unauthorized.php");
    exit();
}

// Fetch all stock records
$query = "SELECT * FROM stock_items ORDER BY name ASC";
$result = $conn->query($query);
$items = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }
}

// Calculate statistics
$total_items = count($items);
$total_value = 0;
$low_stock_items = 0;
$out_of_stock = 0;

foreach ($items as $item) {
    $total_value += $item['quantity'] * $item['unit_price'];
    if ($item['quantity'] <= $item['minimum_quantity'] && $item['quantity'] > 0) {
        $low_stock_items++;
    }
    if ($item['quantity'] == 0) {
        $out_of_stock++;
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Stock Report</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .navbar {
            background: linear-gradient(135deg, #1e40af, #3b82f6);
        }
        
        .report-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .summary-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            transition: transform 0.2s;
        }

        .summary-card:hover {
            transform: translateY(-5px);
        }

        .low-stock {
            color: #F59E0B;
        }

        .out-of-stock {
            color: #EF4444;
        }

        .in-stock {
            color: #10B981;
        }

        .summary-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }

        .summary-card h5 {
            color: #6B7280;
            margin-bottom: 0.5rem;
        }

        .summary-card h3 {
            color: #111827;
            margin-bottom: 1rem;
            font-size: 2rem;
        }

        .summary-card .btn {
            margin: 0 0.25rem;
        }

        .text-primary {
            color: #3B82F6 !important;
        }

        .text-success {
            color: #10B981 !important;
        }

        .text-warning {
            color: #F59E0B !important;
        }

        .btn-sm {
            padding: 0.25rem 0.75rem;
            font-size: 0.875rem;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
            <img src="https://images.crunchbase.com/image/upload/c_pad,h_170,w_170,f_auto,b_white,q_auto:eco,dpr_1/v1436326579/fv5juvmpaq9zxgnkueof.png" alt="FMS Logo" height="40" class="me-2">
                <i class="fas fa-boxes me-2"></i>Stock Report
            </a>
            <div class="ms-auto">
                <a href="admin_dashboard.php" class="nav-link text-white">
                    <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Summary Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="summary-card">
                    <div class="summary-icon">
                        <i class="fas fa-boxes"></i>
                    </div>
                    <h5>Total Items</h5>
                    <h3><?php echo $total_items; ?></h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="summary-card">
                    <div class="summary-icon in-stock">
                        <i class="fas fa-money-bill"></i>
                    </div>
                    <h5>Total Value</h5>
                    <h3>PKR <?php echo number_format($total_value, 2); ?></h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="summary-card">
                    <div class="summary-icon low-stock">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <h5>Low Stock Items</h5>
                    <h3><?php echo $low_stock_items; ?></h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="summary-card">
                    <div class="summary-icon out-of-stock">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <h5>Out of Stock</h5>
                    <h3><?php echo $out_of_stock; ?></h3>
                </div>
            </div>
        </div>

        <!-- Update the summary cards section with buttons -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="summary-card">
                    <div class="summary-icon text-primary">
                        <i class="fas fa-file-invoice"></i>
                    </div>
                    <h5>Material Requisition (MR)</h5>
                    <div class="text-center">
                        <div class="d-flex justify-content-center gap-4">
                            <div>
                                <small class="text-muted">Pending</small>
                                <h3 class="text-warning mb-0"><?php 
                                    $query = "SELECT COUNT(*) as count FROM material_requisitions WHERE status = 'pending'";
                                    $result = $conn->query($query);
                                    echo ($result && $row = $result->fetch_assoc()) ? $row['count'] : 0;
                                ?></h3>
                            </div>
                            <div>
                            <small style="font-size: 13px; color: gray;">Completed</small> <small style="font-size: 8px; color: green; font-weight: bold;">Approved</small>
                                <h3 class="text-primary mb-0"><?php 
                                    $query = "SELECT COUNT(*) as count FROM material_requisitions WHERE status = 'approved'";
                                    $result = $conn->query($query);
                                    echo ($result && $row = $result->fetch_assoc()) ? $row['count'] : 0;
                                ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="mt-3">
                        <a href="material_requisition.php" class="btn btn-sm btn-primary">
                            <i class="fas fa-plus me-1"></i>Create MR
                        </a>
                        <a href="mr_list.php" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-list me-1"></i>View All
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="summary-card">
                    <div class="summary-icon text-success">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <h5>Purchase Orders (PO)</h5>
                    <div class="text-center">
                        <div class="d-flex justify-content-center gap-4">
                            <div>
                                <small class="text-muted">Pending</small>
                                <h3 class="text-warning mb-0"><?php 
                                    $query = "SELECT COUNT(*) as count FROM purchase_orders WHERE status = 'pending'";
                                    $result = $conn->query($query);
                                    echo ($result && $row = $result->fetch_assoc()) ? $row['count'] : 0;
                                ?></h3>
                            </div>
                            <div>
                            <small style="font-size: 13px; color: gray;">Completed</small> <small style="font-size: 8px; color: green; font-weight: bold;">Approved</small>

                                <h3 class="text-primary mb-0"><?php 
                                    $query = "SELECT COUNT(*) as count FROM purchase_orders WHERE status = 'approved'";
                                    $result = $conn->query($query);
                                    echo ($result && $row = $result->fetch_assoc()) ? $row['count'] : 0;
                                ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="mt-3">
                        <a href="purchase_order.php" class="btn btn-sm btn-success">
                            <i class="fas fa-plus me-1"></i>Create PO
                        </a>
                        <a href="po_list.php" class="btn btn-sm btn-outline-success">
                            <i class="fas fa-list me-1"></i>View All
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="summary-card">
                    <div class="summary-icon text-warning">
                        <i class="fas fa-truck"></i>
                    </div>
                    <h5>Goods Receipt Notes (GRN)</h5>
                    <div class="text-center">
                        <div class="d-flex justify-content-center gap-4">
                            <div>
                                <small class="text-muted">Pending</small>
                                <h3 class="text-warning mb-0"><?php 
                                    $query = "SELECT COUNT(*) as count FROM goods_receipt_notes WHERE status = 'pending'";
                                    $result = $conn->query($query);
                                    echo ($result && $row = $result->fetch_assoc()) ? $row['count'] : 0;
                                ?></h3>
                            </div>
                            <div>
                            <small style="font-size: 13px; color: gray;">Completed</small> <small style="font-size: 8px; color: green; font-weight: bold;">Approved</small>
                                <h3 class="text-primary mb-0"><?php 
                                    $query = "SELECT COUNT(*) as count FROM goods_receipt_notes WHERE status = 'approved'";
                                    $result = $conn->query($query);
                                    echo ($result && $row = $result->fetch_assoc()) ? $row['count'] : 0;
                                ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="mt-3">
                        <a href="goods_received.php" class="btn btn-sm btn-warning">
                            <i class="fas fa-plus me-1"></i>Create GRN
                        </a>
                        <a href="grn_list.php" class="btn btn-sm btn-outline-warning">
                            <i class="fas fa-list me-1"></i>View All
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add Item Modal -->
        <div class="modal fade" id="addItemModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Add New Item to Master List</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <form id="addItemForm">
                            <div class="mb-3">
                                <label for="name" class="form-label">Item Name</label>
                                <input type="text" class="form-control" id="name" name="name" required>
                            </div>
                            <div class="mb-3">
                                <label for="category" class="form-label">Document Type</label>
                                <select class="form-select" id="category" name="category" required>
                                    <option value="">Select Where to Use</option>
                                    <option value="MR">Material Requisition Items</option>
                                    <option value="GRN">Goods Receipt Note Items</option>
                                    <option value="PO">Purchase Order Items</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="unit" class="form-label">Unit of Measurement</label>
                                <select class="form-select" id="unit" name="unit" required>
                                    <option value="">Select Unit</option>
                                    <option value="PCS">Pieces (PCS)</option>
                                    <option value="KG">Kilograms (KG)</option>
                                    <option value="MTR">Meters (MTR)</option>
                                    <option value="LTR">Liters (LTR)</option>
                                    <option value="BOX">Box (BOX)</option>
                                    <option value="PKT">Packet (PKT)</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="minimum_quantity" class="form-label">Minimum Stock Level</label>
                                <input type="number" class="form-control" id="minimum_quantity" name="minimum_quantity" min="0" required>
                            </div>
                            <div class="mb-3">
                                <label for="description" class="form-label">Item Description</label>
                                <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="button" class="btn btn-primary" onclick="submitItemForm()">Add to Master List</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stock List -->
        <div class="report-card">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4>Stock Inventory</h4>
                <div>
                    <a href="item_master.php" class="btn btn-info me-2">
                        <i class="fas fa-list me-2"></i>Item Master List
                    </a>
                    <a href="add_stock.php" class="btn btn-success me-2">
                        <i class="fas fa-plus me-2"></i>Add New Item
                    </a>
                    <button type="button" class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#addItemModal">
                        <i class="fas fa-plus me-2"></i>Add to Master List
                    </button>
                    <button class="btn btn-secondary" onclick="exportToCSV()">
                        <i class="fas fa-download me-2"></i>Export to CSV
                    </button>
                </div>
            </div>
            
            <div class="mb-3">
                <button type="button" class="btn btn-secondary" onclick="printSelectedLabels()">
                    <i class="fas fa-print me-2"></i>Print Selected Labels
                </button>
            </div>

            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>
                                <input type="checkbox" id="selectAll" onchange="toggleAllCheckboxes(this)">
                            </th>
                            <th>Item Code</th>
                            <th>Name</th>
                            <th>Category</th>
                            <th>Quantity</th>
                            <th>Unit</th>
                            <th>Unit Price</th>
                            <th>Total Value</th>
                            <th>Location</th>
                            <th>Last Restock</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($items) > 0): ?>
                            <?php foreach ($items as $item): ?>
                                <tr class="<?php echo $item['quantity'] == 0 ? 'table-danger' : 
                                    ($item['quantity'] <= $item['minimum_quantity'] ? 'table-warning' : ''); ?>">
                                    <td>
                                        <input type="checkbox" name="print_items[]" value="<?php echo $item['id']; ?>">
                                    </td>
                                    <td><?php echo htmlspecialchars($item['item_code']); ?></td>
                                    <td><?php echo htmlspecialchars($item['name']); ?></td>
                                    <td><?php echo htmlspecialchars($item['category']); ?></td>
                                    <td><?php echo htmlspecialchars($item['quantity']); ?></td>
                                    <td><?php echo htmlspecialchars($item['unit']); ?></td>
                                    <td>PKR <?php echo number_format($item['unit_price'], 2); ?></td>
                                    <td>PKR <?php echo number_format($item['quantity'] * $item['unit_price'], 2); ?></td>
                                    <td><?php echo htmlspecialchars($item['location']); ?></td>
                                    <td><?php echo $item['last_restock_date'] ? date('Y-m-d', strtotime($item['last_restock_date'])) : 'Never'; ?></td>
                                    <td>
                                        <a href="edit_stock.php?id=<?php echo $item['id']; ?>" 
                                           class="btn btn-sm btn-primary me-1" 
                                           title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="view_stock.php?id=<?php echo $item['id']; ?>" 
                                           class="btn btn-sm btn-info me-1" 
                                           title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="10" class="text-center">No stock items found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function exportToCSV() {
            window.location.href = 'export_stock_report.php';
        }

        function submitItemForm() {
            const form = document.getElementById('addItemForm');
            const formData = new FormData(form);

            // Show loading state
            const submitButton = document.querySelector('button[onclick="submitItemForm()"]');
            const originalText = submitButton.innerHTML;
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
            submitButton.disabled = true;

            fetch('add_item.php', {
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
            })
            .finally(() => {
                // Restore button state
                submitButton.innerHTML = originalText;
                submitButton.disabled = false;
            });
        }

        function printSelectedLabels() {
            const selectedItems = document.querySelectorAll('input[name="print_items[]"]:checked');
            if (selectedItems.length === 0) {
                alert('Please select items to print labels for');
                return;
            }
            
            const ids = Array.from(selectedItems).map(cb => cb.value).join(',');
            window.open(`print_item_labels.php?ids=${ids}`, '_blank');
        }

        function toggleAllCheckboxes(source) {
            const checkboxes = document.querySelectorAll('input[name="print_items[]"]');
            checkboxes.forEach(checkbox => {
                checkbox.checked = source.checked;
            });
        }
    </script>
</body>
</html>