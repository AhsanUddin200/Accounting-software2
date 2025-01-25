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

// Fetch all material requisitions with user information
$query = "SELECT mr.*, u.username as requester_name 
          FROM material_requisitions mr 
          LEFT JOIN users u ON mr.requested_by = u.id 
          ORDER BY mr.created_at DESC";
$result = $conn->query($query);
$requisitions = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $requisitions[] = $row;
    }
}

// Get success message if any
$success_message = '';
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $success_message = "Material Requisition saved successfully!";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Material Requisitions List</title>
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

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="mb-0">Material Requisitions</h4>
                <a href="material_requisition.php" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i>Create New MR
                </a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>MR Number</th>
                                <th>Department</th>
                                <th>Requested By</th>
                                <th>Date Required</th>
                                <th>Status</th>
                                <th>Created At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($requisitions) > 0): ?>
                                <?php foreach ($requisitions as $mr): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($mr['mr_number']); ?></td>
                                        <td><?php echo htmlspecialchars($mr['department']); ?></td>
                                        <td><?php echo htmlspecialchars($mr['requester_name']); ?></td>
                                        <td><?php echo date('Y-m-d', strtotime($mr['date_required'])); ?></td>
                                        <td>
                                            <span class="badge status-<?php echo $mr['status']; ?>">
                                                <?php echo ucfirst($mr['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('Y-m-d H:i', strtotime($mr['created_at'])); ?></td>
                                        <td>
                                            <button type="button" 
                                                    class="btn btn-sm btn-info me-1" 
                                                    onclick="viewMRDetails(<?php echo $mr['id']; ?>)"
                                                    title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <?php if ($mr['status'] === 'pending'): ?>
                                            <a href="edit_mr.php?id=<?php echo $mr['id']; ?>" 
                                               class="btn btn-sm btn-primary me-1" 
                                               title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button type="button" 
                                                    class="btn btn-sm btn-danger" 
                                                    onclick="deleteMR(<?php echo $mr['id']; ?>)"
                                                    title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center">No material requisitions found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="mrDetailModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Material Requisition Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="mrDetailsContent">
                    <!-- Content will be loaded here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <?php if ($_SESSION['role'] === 'admin'): ?>
                    <button type="button" class="btn btn-primary" onclick="applyChanges()">Apply</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function deleteMR(id) {
        if (confirm('Are you sure you want to delete this Material Requisition?')) {
            window.location.href = `delete_mr.php?id=${id}`;
        }
    }

    function viewMRDetails(mrId) {
        fetch(`get_mr_details.php?id=${mrId}`)
            .then(response => response.text())
            .then(data => {
                document.getElementById('mrDetailsContent').innerHTML = data;
                new bootstrap.Modal(document.getElementById('mrDetailModal')).show();
            })
            .catch(error => console.error('Error:', error));
    }

    function applyChanges() {
        const status = document.getElementById('mrStatus').value;
        const remarks = document.getElementById('mrRemarks').value;
        const mrId = document.getElementById('mrId').value;

        fetch('update_mr_status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `id=${mrId}&status=${status}&remarks=${remarks}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Status updated successfully');
                location.reload();
            } else {
                alert('Error updating status');
            }
        })
        .catch(error => console.error('Error:', error));
    }
    </script>
</body>
</html> 