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

$error = "";
$laptop = null;

// Get laptop ID from URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: laptop_report.php");
    exit();
}

$laptop_id = (int)$_GET['id'];

// Fetch laptop data with custodian information
try {
    $query = "SELECT l.*, u.username as custodian_name 
              FROM laptops l 
              LEFT JOIN users u ON l.custodian_id = u.id 
              WHERE l.id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $laptop_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $laptop = $result->fetch_assoc();

    if (!$laptop) {
        header("Location: laptop_report.php");
        exit();
    }
} catch (Exception $e) {
    $error = "Error fetching laptop details: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>View Laptop Details</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .navbar {
            background: linear-gradient(135deg, #1e40af, #3b82f6);
        }
        .detail-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .info-label {
            font-weight: 600;
            color: #4B5563;
        }
        .status-indicator {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 500;
        }
        .status-active {
            background-color: #D1FAE5;
            color: #059669;
        }
        .status-maintenance {
            background-color: #FEF3C7;
            color: #D97706;
        }
        .status-sold {
            background-color: #FEE2E2;
            color: #DC2626;
        }
        .timeline {
            position: relative;
            padding: 1rem 0;
        }
        .timeline-item {
            padding: 1rem;
            border-left: 2px solid #E5E7EB;
            margin-left: 1rem;
        }
        .timeline-item::before {
            content: '';
            position: absolute;
            left: 0;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #3B82F6;
            margin-top: 6px;
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <i class="fas fa-laptop me-2"></i>View Laptop Details
            </a>
            <div class="ms-auto">
                <a href="laptop_report.php" class="nav-link text-white">
                    <i class="fas fa-arrow-left me-1"></i> Back to Laptop Report
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="detail-card">
            <div class="row mb-4">
                <div class="col-md-6">
                    <h3><?php echo htmlspecialchars($laptop['model']); ?></h3>
                    <p class="text-muted">Asset ID: <?php echo htmlspecialchars($laptop['asset_id']); ?></p>
                </div>
                <div class="col-md-6 text-md-end">
                    <a href="edit_laptop.php?id=<?php echo $laptop['id']; ?>" class="btn btn-primary">
                        <i class="fas fa-edit me-2"></i>Edit Laptop
                    </a>
                </div>
            </div>

            <div class="row g-4">
                <!-- Basic Information -->
                <div class="col-md-6">
                    <h5 class="mb-3">Basic Information</h5>
                    <div class="mb-3">
                        <div class="info-label">Serial Number</div>
                        <div><?php echo htmlspecialchars($laptop['serial_number']); ?></div>
                    </div>
                    <div class="mb-3">
                        <div class="info-label">Status</div>
                        <div>
                            <span class="status-indicator status-<?php echo $laptop['status']; ?>">
                                <i class="fas fa-circle me-1"></i>
                                <?php echo ucfirst($laptop['status']); ?>
                            </span>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="info-label">Location</div>
                        <div><?php echo htmlspecialchars($laptop['location'] ?? 'Not Specified'); ?></div>
                    </div>
                    <div class="mb-3">
                        <div class="info-label">Custodian</div>
                        <div><?php echo htmlspecialchars($laptop['custodian_name'] ?? 'Not Assigned'); ?></div>
                    </div>
                </div>

                <!-- Financial Information -->
                <div class="col-md-6">
                    <h5 class="mb-3">Financial Information</h5>
                    <div class="mb-3">
                        <div class="info-label">Purchase Date</div>
                        <div><?php echo date('F j, Y', strtotime($laptop['purchase_date'])); ?></div>
                    </div>
                    <div class="mb-3">
                        <div class="info-label">Purchase Price</div>
                        <div>PKR <?php echo number_format($laptop['purchase_price'], 2); ?></div>
                    </div>
                    <div class="mb-3">
                        <div class="info-label">Current Value</div>
                        <div>PKR <?php echo number_format($laptop['current_value'], 2); ?></div>
                    </div>
                    <div class="mb-3">
                        <div class="info-label">Depreciation</div>
                        <div class="text-<?php echo ($laptop['current_value'] < $laptop['purchase_price']) ? 'danger' : 'success'; ?>">
                            <?php
                            $depreciation = $laptop['purchase_price'] - $laptop['current_value'];
                            $depreciation_percentage = ($laptop['purchase_price'] > 0) 
                                ? ($depreciation / $laptop['purchase_price'] * 100) 
                                : 0;
                            echo "PKR " . number_format($depreciation, 2) . 
                                 " (" . number_format($depreciation_percentage, 1) . "%)";
                            ?>
                        </div>
                    </div>
                </div>

                <!-- Technical Specifications -->
                <div class="col-md-12">
                    <h5 class="mb-3">Technical Specifications</h5>
                    <div class="mb-3">
                        <div class="info-label">Specifications</div>
                        <div><?php echo nl2br(htmlspecialchars($laptop['specifications'] ?? 'No specifications available')); ?></div>
                    </div>
                </div>

                <!-- Notes -->
                <div class="col-md-12">
                    <h5 class="mb-3">Additional Notes</h5>
                    <div class="mb-3">
                        <div><?php echo nl2br(htmlspecialchars($laptop['notes'] ?? 'No notes available')); ?></div>
                    </div>
                </div>

                <!-- Timeline -->
                <div class="col-12">
                    <h5 class="mb-3">Timeline</h5>
                    <div class="timeline">
                        <div class="timeline-item">
                            <div class="text-muted mb-1">Last Updated</div>
                            <div><?php echo date('F j, Y H:i:s', strtotime($laptop['updated_at'])); ?></div>
                        </div>
                        <div class="timeline-item">
                            <div class="text-muted mb-1">Created</div>
                            <div><?php echo date('F j, Y H:i:s', strtotime($laptop['created_at'])); ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 