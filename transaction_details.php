<?php
require_once 'db.php';
require_once 'session.php';

$type = $_GET['type'] ?? '';
$period_start = $_GET['start_date'] ?? date('Y-m-01');
$period_end = $_GET['end_date'] ?? date('Y-m-t');

// Validate type
$allowed_types = ['income', 'expense', 'asset', 'liability', 'equity'];
if (!in_array($type, $allowed_types)) {
    header("Location: admin_dashboard.php");
    exit();
}

// Get transactions if View Transactions is clicked
if (isset($_GET['view'])) {
    $query = "SELECT 
        t.*,
        ah.name as head_name,
        ac.name as category_name,
        u.username as added_by
        FROM transactions t
        LEFT JOIN accounting_heads ah ON t.head_id = ah.id
        LEFT JOIN account_categories ac ON t.category_id = ac.id
        LEFT JOIN users u ON t.user_id = u.id
        WHERE t.type = ?
        AND t.date BETWEEN ? AND ?
        ORDER BY t.date DESC";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("sss", $type, $period_start, $period_end);
    $stmt->execute();
    $transactions = $stmt->get_result();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title><?php echo ucfirst($type); ?> Details</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .status-badge {
            padding: 5px 10px;
            border-radius: 4px;
            color: white;
        }
        .status-original {
            background-color: #27ae60;
        }
    </style>
</head>
<body>
    <!-- Header with new color -->
  
<div class="header text-white p-3 d-flex justify-content-between align-items-center" style="background-color: #4256e5;">
        <div>
            <i class="fas fa-chart-line me-2"></i>
            Financial Management System
        </div>
        <a href="admin_dashboard.php" class="btn btn-outline-light">
            <i class="fas fa-arrow-left me-2"></i>
            Back to Dashboard
        </a>
    </div>

    <div class="container mt-4">
        <!-- Header with Date Range -->
        <h5 class="mb-4">
            <i class="fas fa-list me-2"></i>
            <?php echo ucfirst($type); ?> Details (<?php echo date('d M Y', strtotime($period_start)); ?> - <?php echo date('d M Y', strtotime($period_end)); ?>)
        </h5>

        <!-- Date Range Selector -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-end">
                    <input type="hidden" name="type" value="<?php echo $type; ?>">
                    <div class="col-md-4">
                        <label>From Date</label>
                        <input type="date" name="start_date" class="form-control" value="<?php echo $period_start; ?>">
                    </div>
                    <div class="col-md-4">
                        <label>To Date</label>
                        <input type="date" name="end_date" class="form-control" value="<?php echo $period_end; ?>">
                    </div>
                    <div class="col-md-4">
                        <button type="submit" name="view" class="btn btn-primary">
                            <i class="fas fa-search me-2"></i>View Transactions
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <?php if (isset($_GET['view'])): ?>
        <!-- Transactions Table -->
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Voucher No.</th>
                                <th>Head</th>
                                <th>Category</th>
                                <th>Amount</th>
                                <th>Description</th>
                                <th>Status</th>
                                <th>Added By</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($row = $transactions->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo date('d M Y', strtotime($row['date'])); ?></td>
                                <td>
                                    <a href="generate_voucher.php?voucher_number=<?php echo urlencode($row['voucher_number']); ?>" 
                                       class="btn btn-sm btn-link" title="View Voucher">
                                        <?php echo htmlspecialchars($row['voucher_number']); ?>
                                    </a>
                                </td>
                                <td><?php echo htmlspecialchars($row['head_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['category_name']); ?></td>
                                <td>PKR <?php echo number_format($row['amount'], 2); ?></td>
                                <td><?php echo htmlspecialchars($row['description']); ?></td>
                                <td><span class="status-badge status-original">Original</span></td>
                                <td><?php echo htmlspecialchars($row['added_by']); ?></td>
                                <td>
                                    <button class="btn btn-sm btn-primary"><i class="fas fa-edit"></i></button>
                                    <button class="btn btn-sm btn-warning"><i class="fas fa-exchange-alt"></i></button>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="mt-3">
            <a href="admin_dashboard.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
            </a>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>
</html> 