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
                    <h5>Pending MRs</h5>
                    <h3><?php echo getPendingMRCount(); ?></h3>
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
                    <h5>Open POs</h5>
                    <h3><?php 
                        $query = "SELECT COUNT(*) as count FROM purchase_orders WHERE status = 'pending'";
                        $result = $conn->query($query);
                        $count = 0;
                        if ($result && $row = $result->fetch_assoc()) {
                            $count = $row['count'];
                        }
                        echo $count;
                    ?></h3>
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
                    <h5>Pending GRNs</h5>
                    <h3><?php 
                        $query = "SELECT COUNT(*) as count FROM goods_receipt_notes WHERE status = 'pending'";
                        $result = $conn->query($query);
                        $count = 0;
                        if ($result && $row = $result->fetch_assoc()) {
                            $count = $row['count'];
                        }
                        echo $count;
                    ?></h3>
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

        <!-- Stock List -->
        <div class="report-card">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4>Stock Inventory</h4>
                <div>
                    <a href="add_stock.php" class="btn btn-success me-2">
                        <i class="fas fa-plus me-2"></i>Add New Item
                    </a>
                    <button class="btn btn-primary" onclick="exportToCSV()">
                        <i class="fas fa-download me-2"></i>Export to CSV
                    </button>
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
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
    </script>
</body>
</html> 