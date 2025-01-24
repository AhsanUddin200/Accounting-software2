<?php
require_once 'session.php';
require_once 'db.php';

// Ensure user has appropriate permissions
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Fetch all GRNs with related PO information
$query = "SELECT grn.*, po.po_number 
          FROM goods_receipt_notes grn
          LEFT JOIN purchase_orders po ON grn.po_id = po.id
          ORDER BY grn.created_at DESC";
$result = $conn->query($query);
$grns = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $grns[] = $row;
    }
}

// Get success message if any
$success_message = '';
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $success_message = "GRN saved successfully!";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>GRN List</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .navbar {
            background: linear-gradient(135deg, #1e40af, #3b82f6);
        }
        .status-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.875rem;
        }
        .status-pending {
            background-color: #FEF3C7;
            color: #92400E;
        }
        .status-approved {
            background-color: #D1FAE5;
            color: #065F46;
        }
        .status-rejected {
            background-color: #FEE2E2;
            color: #991B1B;
        }
    </style>
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <i class="fas fa-truck me-2"></i>GRN List
            </a>
            <div class="ms-auto">
                <a href="stock_report.php" class="nav-link text-white">
                    <i class="fas fa-arrow-left me-1"></i> Back to Stock Report
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Goods Received Notes</h5>
                <a href="goods_received.php" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i>Create New GRN
                </a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>GRN Number</th>
                                <th>PO Reference</th>
                                <th>Supplier</th>
                                <th>Delivery Date</th>
                                <th>Status</th>
                                <th>Created At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($grns) > 0): ?>
                                <?php foreach ($grns as $grn): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($grn['grn_number']); ?></td>
                                        <td><?php echo htmlspecialchars($grn['po_number']); ?></td>
                                        <td><?php echo htmlspecialchars($grn['supplier']); ?></td>
                                        <td><?php echo date('Y-m-d', strtotime($grn['delivery_date'])); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo strtolower($grn['status']); ?>">
                                                <?php echo ucfirst($grn['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('Y-m-d H:i', strtotime($grn['created_at'])); ?></td>
                                        <td>
                                            <a href="view_grn.php?id=<?php echo $grn['id']; ?>" 
                                               class="btn btn-sm btn-info me-1" 
                                               title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php if ($grn['status'] === 'pending'): ?>
                                                <a href="edit_grn.php?id=<?php echo $grn['id']; ?>" 
                                                   class="btn btn-sm btn-primary me-1" 
                                                   title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <button type="button" 
                                                        class="btn btn-sm btn-danger" 
                                                        onclick="deleteGRN(<?php echo $grn['id']; ?>)"
                                                        title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center">No GRNs found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function deleteGRN(id) {
        if (confirm('Are you sure you want to delete this GRN?')) {
            window.location.href = `delete_grn.php?id=${id}`;
        }
    }
    </script>
</body>
</html> 