<?php
require_once 'session.php';
require_once 'db.php';

// Ensure user has appropriate permissions
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Fetch all purchase orders with MR numbers from your existing table
$query = "SELECT po.*, mr.mr_number, mr.department 
          FROM purchase_orders po 
          LEFT JOIN material_requisitions mr ON po.mr_id = mr.id 
          WHERE mr.status = 'pending'  -- or you might want to show all statuses
          ORDER BY po.created_at DESC";

$result = $conn->query($query);
$purchase_orders = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $purchase_orders[] = $row;
    }
}

// Get success message if any
$success_message = '';
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $success_message = "Purchase Order saved successfully!";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Purchase Orders List</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }
        .status-approved {
            background-color: #d4edda;
            color: #155724;
        }
        .status-rejected {
            background-color: #f8d7da;
            color: #721c24;
        }
        .table-container {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
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

        <!-- Success Message -->
        <?php if ($success_message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

        <div class="table-container">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3>Purchase Orders</h3>
                <a href="purchase_order.php" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i>Create New PO
                </a>
            </div>

            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead>
                        <tr>
                            <th>MR Number</th>
                            <th>PO Number</th>
                            <th>Supplier</th>
                            <th>Delivery Date</th>
                            <th>Status</th>
                            <th>Total Amount</th>
                            <th>Created At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($purchase_orders) > 0): ?>
                            <?php foreach ($purchase_orders as $po): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($po['mr_number']); ?></td>
                                    <td><?php echo htmlspecialchars($po['po_number']); ?></td>
                                    <td><?php echo htmlspecialchars($po['supplier']); ?></td>
                                    <td><?php echo date('Y-m-d', strtotime($po['delivery_date'])); ?></td>
                                    <td>
                                        <span class="badge status-<?php echo $po['status']; ?>">
                                            <?php echo ucfirst($po['status']); ?>
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <?php echo number_format($po['total_amount'], 2); ?>
                                    </td>
                                    <td><?php echo date('Y-m-d H:i', strtotime($po['created_at'])); ?></td>
                                    <td>
                                        <button type="button" 
                                                class="btn btn-sm btn-info me-1" 
                                                onclick="viewPODetails(<?php echo $po['id']; ?>)"
                                                title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <?php if ($po['status'] === 'pending'): ?>
                                        <a href="edit_po.php?id=<?php echo $po['id']; ?>" 
                                           class="btn btn-sm btn-primary me-1" 
                                           title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" 
                                                class="btn btn-sm btn-danger" 
                                                onclick="deletePO(<?php echo $po['id']; ?>)"
                                                title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center">No purchase orders found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="modal fade" id="poDetailModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Purchase Order Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="poDetailsContent">
                        <!-- Content will be loaded here -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function deletePO(id) {
        if (confirm('Are you sure you want to delete this Purchase Order?')) {
            window.location.href = `delete_po.php?id=${id}`;
        }
    }

    function viewPODetails(poId) {
        // Show loading state
        document.getElementById('poDetailsContent').innerHTML = `
            <div class="text-center">
                <i class="fas fa-spinner fa-spin"></i> Loading...
            </div>`;
        
        // Show modal
        const modal = new bootstrap.Modal(document.getElementById('poDetailModal'));
        modal.show();
        
        // Fetch PO details
        fetch(`get_po_details.php?id=${poId}`)
            .then(response => response.text())
            .then(data => {
                document.getElementById('poDetailsContent').innerHTML = data;
            })
            .catch(error => {
                document.getElementById('poDetailsContent').innerHTML = `
                    <div class="alert alert-danger">
                        Error loading details. Please try again.
                    </div>`;
            });
    }
    </script>
</body>
</html> 