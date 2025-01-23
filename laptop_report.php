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

// Fetch all laptop records
$query = "SELECT 
    l.*,
    u.username as custodian_name
    FROM laptops l
    LEFT JOIN users u ON l.custodian_id = u.id
    ORDER BY l.purchase_date DESC";

$result = $conn->query($query);
$laptops = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $laptops[] = $row;
    }
}

// Calculate statistics
$total_laptops = count($laptops);
$active_laptops = 0;
$maintenance_laptops = 0;
$sold_laptops = 0;

foreach ($laptops as $laptop) {
    switch ($laptop['status']) {
        case 'active':
            $active_laptops++;
            break;
        case 'maintenance':
            $maintenance_laptops++;
            break;
        case 'sold':
            $sold_laptops++;
            break;
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Laptop Report</title>
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

        .status-active {
            color: #10B981;
        }

        .status-maintenance {
            color: #F59E0B;
        }

        .status-sold {
            color: #EF4444;
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

        .summary-icon {
            font-size: 2rem;
            margin-bottom: 1rem;
        }

        .table th {
            background-color: #f8fafc;
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <i class="fas fa-laptop me-2"></i>Laptop Report
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
                        <i class="fas fa-laptop"></i>
                    </div>
                    <h5>Total Laptops</h5>
                    <h3><?php echo $total_laptops; ?></h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="summary-card">
                    <div class="summary-icon status-active">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <h5>Active</h5>
                    <h3><?php echo $active_laptops; ?></h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="summary-card">
                    <div class="summary-icon status-maintenance">
                        <i class="fas fa-tools"></i>
                    </div>
                    <h5>In Maintenance</h5>
                    <h3><?php echo $maintenance_laptops; ?></h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="summary-card">
                    <div class="summary-icon status-sold">
                        <i class="fas fa-tags"></i>
                    </div>
                    <h5>Sold/Disposed</h5>
                    <h3><?php echo $sold_laptops; ?></h3>
                </div>
            </div>
        </div>

        <!-- Laptop List -->
        <div class="report-card">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4>Laptop Inventory</h4>
                <div>
                    <a href="add_laptop.php" class="btn btn-success me-2">
                        <i class="fas fa-plus me-2"></i>Add New Laptop
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
                            <th>Asset ID</th>
                            <th>Model</th>
                            <th>Serial Number</th>
                            <th>Status</th>
                            <th>Custodian</th>
                            <th>Location</th>
                            <th>Purchase Date</th>
                            <th>Purchase Price</th>
                            <th>Current Value</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($laptops) > 0): ?>
                            <?php foreach ($laptops as $laptop): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($laptop['asset_id']); ?></td>
                                    <td><?php echo htmlspecialchars($laptop['model']); ?></td>
                                    <td><?php echo htmlspecialchars($laptop['serial_number']); ?></td>
                                    <td>
                                        <span class="status-<?php echo $laptop['status']; ?>">
                                            <i class="fas fa-circle me-1"></i>
                                            <?php echo ucfirst($laptop['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($laptop['custodian_name'] ?? 'Not Assigned'); ?></td>
                                    <td><?php echo htmlspecialchars($laptop['location'] ?? 'Not Specified'); ?></td>
                                    <td><?php echo date('Y-m-d', strtotime($laptop['purchase_date'])); ?></td>
                                    <td>PKR <?php echo number_format($laptop['purchase_price'], 2); ?></td>
                                    <td>PKR <?php echo number_format($laptop['current_value'], 2); ?></td>
                                    <td>
                                        <a href="edit_laptop.php?id=<?php echo $laptop['id']; ?>" 
                                           class="btn btn-sm btn-primary me-1" 
                                           title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="view_laptop.php?id=<?php echo $laptop['id']; ?>" 
                                           class="btn btn-sm btn-info me-1" 
                                           title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="10" class="text-center">No laptops found</td>
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
            window.location.href = 'export_laptop_report.php';
        }
    </script>
</body>
</html> 