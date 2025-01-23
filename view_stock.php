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
$item = null;

// Get item ID from URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: stock_report.php");
    exit();
}

$item_id = (int)$_GET['id'];

// Fetch item data
try {
    $query = "SELECT * FROM stock_items WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $item_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $item = $result->fetch_assoc();

    if (!$item) {
        header("Location: stock_report.php");
        exit();
    }
} catch (Exception $e) {
    $error = "Error fetching item details: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>View Stock Item</title>
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
        .status-low {
            background-color: #FEF3C7;
            color: #D97706;
        }
        .status-out {
            background-color: #FEE2E2;
            color: #DC2626;
        }
        .status-good {
            background-color: #D1FAE5;
            color: #059669;
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
                <i class="fas fa-box me-2"></i>View Stock Item
            </a>
            <div class="ms-auto">
                <a href="stock_report.php" class="nav-link text-white">
                    <i class="fas fa-arrow-left me-1"></i> Back to Stock Report
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
                    <h3><?php echo htmlspecialchars($item['name']); ?></h3>
                    <p class="text-muted"><?php echo htmlspecialchars($item['item_code']); ?></p>
                </div>
                <div class="col-md-6 text-md-end">
                    <a href="edit_stock.php?id=<?php echo $item['id']; ?>" class="btn btn-primary">
                        <i class="fas fa-edit me-2"></i>Edit Item
                    </a>
                </div>
            </div>

            <div class="row g-4">
                <!-- Basic Information -->
                <div class="col-md-6">
                    <h5 class="mb-3">Basic Information</h5>
                    <div class="mb-3">
                        <div class="info-label">Category</div>
                        <div><?php echo htmlspecialchars($item['category']); ?></div>
                    </div>
                    <div class="mb-3">
                        <div class="info-label">Description</div>
                        <div><?php echo nl2br(htmlspecialchars($item['description'])); ?></div>
                    </div>
                    <div class="mb-3">
                        <div class="info-label">Location</div>
                        <div><?php echo htmlspecialchars($item['location']); ?></div>
                    </div>
                </div>

                <!-- Stock Status -->
                <div class="col-md-6">
                    <h5 class="mb-3">Stock Status</h5>
                    <div class="mb-3">
                        <div class="info-label">Current Quantity</div>
                        <div class="d-flex align-items-center">
                            <span class="me-2"><?php echo number_format($item['quantity']) . ' ' . $item['unit']; ?></span>
                            <?php
                            $status_class = 'status-good';
                            $status_text = 'Good Stock Level';
                            if ($item['quantity'] == 0) {
                                $status_class = 'status-out';
                                $status_text = 'Out of Stock';
                            } elseif ($item['quantity'] <= $item['minimum_quantity']) {
                                $status_class = 'status-low';
                                $status_text = 'Low Stock';
                            }
                            ?>
                            <span class="status-indicator <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="info-label">Minimum Quantity</div>
                        <div><?php echo number_format($item['minimum_quantity']) . ' ' . $item['unit']; ?></div>
                    </div>
                    <div class="mb-3">
                        <div class="info-label">Unit Price</div>
                        <div>PKR <?php echo number_format($item['unit_price'], 2); ?></div>
                    </div>
                    <div class="mb-3">
                        <div class="info-label">Total Value</div>
                        <div>PKR <?php echo number_format($item['quantity'] * $item['unit_price'], 2); ?></div>
                    </div>
                </div>

                <!-- Timeline -->
                <div class="col-12">
                    <h5 class="mb-3">Timeline</h5>
                    <div class="timeline">
                        <div class="timeline-item">
                            <div class="text-muted mb-1">Last Restock</div>
                            <div><?php echo date('F j, Y', strtotime($item['last_restock_date'])); ?></div>
                        </div>
                        <div class="timeline-item">
                            <div class="text-muted mb-1">Last Updated</div>
                            <div><?php echo date('F j, Y H:i:s', strtotime($item['updated_at'])); ?></div>
                        </div>
                        <div class="timeline-item">
                            <div class="text-muted mb-1">Created</div>
                            <div><?php echo date('F j, Y H:i:s', strtotime($item['created_at'])); ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 