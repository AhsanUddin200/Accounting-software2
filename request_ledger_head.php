<?php
require_once 'session.php';
require_once 'db.php';

// For debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Handle both new request submission and status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['submit_request'])) {
        // Handle new request submission
        $head_name = $_POST['head_name'] ?? '';
        $description = $_POST['description'] ?? '';
        $user_id = $_SESSION['user_id'];
        
        if (empty($head_name) || empty($description)) {
            $_SESSION['error'] = "Please fill all fields!";
        } else {
            $sql = "INSERT INTO ledger_head_requests 
                    (user_id, requested_head_name, description, status, requested_date) 
                    VALUES (?, ?, ?, 'pending', NOW())";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iss", $user_id, $head_name, $description);
            
            if ($stmt->execute()) {
                $_SESSION['success'] = "Request submitted successfully!";
            } else {
                $_SESSION['error'] = "Failed to save request!";
            }
        }
    } 
    elseif (isset($_POST['action']) && isset($_POST['request_id'])) {
        // Handle status update
        $request_id = $_POST['request_id'];
        $action = $_POST['action'];
        $new_status = ($action === 'approve') ? 'approved' : 'rejected';
        
        $sql = "UPDATE ledger_head_requests 
                SET status = ?, 
                    processed_by = ?, 
                    processed_date = NOW() 
                WHERE id = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sii", $new_status, $_SESSION['user_id'], $request_id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Request " . ucfirst($new_status) . " successfully!";
        } else {
            $_SESSION['error'] = "Failed to update status!";
        }
    }
    
    // Redirect after any POST action
    header("Location: request_ledger_head.php");
    exit();
}

// Get all requests for admin view
$requests_query = "SELECT r.*, u.username as requester, 
                         p.username as processor
                  FROM ledger_head_requests r
                  JOIN users u ON r.user_id = u.id
                  LEFT JOIN users p ON r.processed_by = p.id
                  ORDER BY r.requested_date DESC";
$requests_result = $conn->query($requests_query);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Ledger Head Requests</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        
        .card {
            border: none;
            box-shadow: 0 0 20px rgba(66, 86, 228, 0.1);
            border-radius: 15px;
        }
        
        .card-header {
            background: linear-gradient(45deg, #4256e4, #5e6fe4);
            color: white;
            border-radius: 15px 15px 0 0 !important;
            padding: 1.5rem;
            border-bottom: none;
        }
        
        .nav-tabs {
            border-bottom: none;
            margin-top: -10px;
        }
        
        .nav-tabs .nav-link {
            color: rgba(255, 255, 255, 0.8);
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            margin-right: 10px;
            transition: all 0.3s;
        }
        
        .nav-tabs .nav-link:hover {
            color: white;
            background: rgba(255, 255, 255, 0.1);
        }
        
        .nav-tabs .nav-link.active {
            background: white;
            color: #4256e4;
            font-weight: 600;
        }
        
        .form-control {
            border-radius: 8px;
            padding: 0.75rem;
            border: 1px solid #e0e0e0;
            background-color: #f8f9fa;
        }
        
        .form-control:focus {
            border-color: #4256e4;
            box-shadow: 0 0 0 0.2rem rgba(66, 86, 228, 0.25);
            background-color: white;
        }
        
        .input-group-text {
            background-color: #f8f9fa;
            border: 1px solid #e0e0e0;
            border-radius: 8px 0 0 8px;
        }
        
        .btn-submit {
            background: linear-gradient(45deg, #4256e4, #5e6fe4);
            color: white;
            padding: 0.75rem 2rem;
            border-radius: 8px;
            border: none;
            transition: all 0.3s;
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(66, 86, 228, 0.3);
        }
        
        .table {
            border-radius: 8px;
            overflow: hidden;
        }
        
        .table thead th {
            background-color: #f8f9fa;
            border-bottom: 2px solid #e0e0e0;
            color: #495057;
            font-weight: 600;
        }
        
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
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
        
        .action-btn {
            padding: 5px 10px;
            border-radius: 6px;
            transition: all 0.3s;
        }
        
        .action-btn:hover {
            transform: translateY(-2px);
        }
        
        .alert {
            border-radius: 8px;
            border: none;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .help-text {
            color: #6c757d;
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php 
                            echo $_SESSION['success'];
                            unset($_SESSION['success']);
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header">
                        <ul class="nav nav-tabs card-header-tabs">
                            <li class="nav-item">
                                <a class="nav-link active" data-bs-toggle="tab" href="#new-request">
                                    <i class="fas fa-plus-circle me-2"></i>New Request
                                </a>
                            </li>
                            <?php if (in_array($_SESSION['role'], ['admin', 'accountant'])): ?>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#manage-requests">
                                    <i class="fas fa-tasks me-2"></i>Manage Requests
                                </a>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                    
                    <div class="card-body p-4">
                        <div class="tab-content">
                            <!-- New Request Form -->
                            <div class="tab-pane fade show active" id="new-request">
                                <form method="POST">
                                    <input type="hidden" name="action" value="new_request">
                                    
                                    <div class="mb-4">
                                        <label class="form-label fw-bold">Ledger Head Name</label>
                                        <div class="input-group">
                                            <span class="input-group-text">
                                                <i class="fas fa-book"></i>
                                            </span>
                                            <input type="text" 
                                                   name="head_name" 
                                                   class="form-control" 
                                                   placeholder="Enter ledger head name"
                                                   required>
                                        </div>
                                        <div class="help-text">
                                            <i class="fas fa-info-circle me-1"></i>
                                            Enter a clear and descriptive name for the ledger head
                                        </div>
                                    </div>

                                    <div class="mb-4">
                                        <label class="form-label fw-bold">Description/Reason</label>
                                        <div class="input-group">
                                            <span class="input-group-text">
                                                <i class="fas fa-align-left"></i>
                                            </span>
                                            <textarea name="description" 
                                                      class="form-control" 
                                                      rows="4" 
                                                      placeholder="Explain why this ledger head is needed"
                                                      required></textarea>
                                        </div>
                                        <div class="help-text">
                                            <i class="fas fa-info-circle me-1"></i>
                                            Provide a detailed explanation for requesting this ledger head
                                        </div>
                                    </div>

                                    <div class="d-flex justify-content-between align-items-center">
                                        <a href="admin_dashboard.php" class="btn btn-light">
                                            <i class="fas fa-arrow-left me-2"></i>Back
                                        </a>
                                        <button type="submit" name="submit_request" class="btn btn-submit">
                                            <i class="fas fa-paper-plane me-2"></i>Submit Request
                                        </button>
                                    </div>
                                </form>
                            </div>

                            <!-- Manage Requests Table -->
                            <?php if (in_array($_SESSION['role'], ['admin', 'accountant'])): ?>
                            <div class="tab-pane fade" id="manage-requests">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Requested By</th>
                                                <th>Head Name</th>
                                                <th>Description</th>
                                                <th>Status</th>
                                                <th>Date</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($row = $requests_result->fetch_assoc()): ?>
                                            <tr>
                                                <td>
                                                    <i class="fas fa-user me-2 text-muted"></i>
                                                    <?php echo htmlspecialchars($row['requester']); ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($row['requested_head_name']); ?></td>
                                                <td><?php echo htmlspecialchars($row['description']); ?></td>
                                                <td>
                                                    <span class="status-badge status-<?php echo $row['status']; ?>">
                                                        <?php echo ucfirst($row['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <i class="far fa-calendar-alt me-2 text-muted"></i>
                                                    <?php echo date('d M Y', strtotime($row['requested_date'])); ?>
                                                </td>
                                                <td>
                                                    <?php if ($row['status'] === 'pending'): ?>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="action" value="approve">
                                                        <input type="hidden" name="request_id" value="<?php echo $row['id']; ?>">
                                                        <button type="submit" name="status" value="approved" 
                                                                class="btn btn-success btn-sm action-btn" 
                                                                title="Approve">
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                        <button type="submit" name="status" value="reject" 
                                                                class="btn btn-danger btn-sm action-btn" 
                                                                title="Reject">
                                                            <i class="fas fa-times"></i>
                                                        </button>
                                                    </form>
                                                    <?php else: ?>
                                                    <small class="text-muted">
                                                        <i class="fas fa-user-check me-1"></i>
                                                        <?php echo htmlspecialchars($row['processor']); ?>
                                                    </small>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-dismiss alerts after 5 seconds
        window.setTimeout(function() {
            document.querySelectorAll('.alert').forEach(function(alert) {
                var bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>
</html> 