<?php
require_once 'session.php';
require_once 'db.php';

// Check admin access
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// First check if user is super admin
$is_super_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'super_admin' && empty($_SESSION['cost_center_id']);

// Fetch heads for filter
$heads_query = "SELECT * FROM accounting_heads ORDER BY display_order";
$heads = $conn->query($heads_query);

// Fetch categories for filter
$categories_query = "SELECT * FROM account_categories ORDER BY name";
$categories = $conn->query($categories_query);

// Simplified query to get recent transactions
$query = "SELECT 
    t.date,
    t.voucher_number,
    ah.name as head_name,
    ac.name as category_name,
    acs.name as subcategory_name,
    t.type,
    t.amount,
    t.description,
    u.username as added_by,
    cc.name as cost_center_name
FROM transactions t
LEFT JOIN accounting_heads ah ON t.head_id = ah.id
LEFT JOIN account_categories ac ON t.category_id = ac.id
LEFT JOIN account_subcategories acs ON t.subcategory_id = acs.id
LEFT JOIN users u ON t.user_id = u.id
LEFT JOIN cost_centers cc ON t.cost_center_id = cc.id
WHERE 1=1 ";

// If user is not saim or admin, only show their own transactions
if ($_SESSION['username'] !== 'saim' && $_SESSION['username'] !== 'admin') {
    $query .= " AND t.user_id = " . intval($_SESSION['user_id']);
}

// Order by most recent first and limit to last 10 transactions
$query .= " ORDER BY t.date DESC, t.id DESC LIMIT 10";

$result = $conn->query($query);

// Function to safely escape output
function safe_echo($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Accounting Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container py-4">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <?php 
                echo safe_echo($_SESSION['success']); 
                unset($_SESSION['success']);
                ?>
            </div>
        <?php endif; ?>

        <!-- Recent Transactions Table -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Recent Transactions</h5>
                <a href="add_transaction.php" class="btn btn-primary btn-sm">
                    <i class="fas fa-plus"></i> Add Transaction
                </a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Voucher No.</th>
                                <th>Head</th>
                                <th>Category</th>
                                <th>Sub Category</th>
                                <th>Type</th>
                                <th>Amount</th>
                                <th>Description</th>
                                <th>Added By</th>
                                <?php if ($is_super_admin): ?>
                                    <th>Cost Center</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result && $result->num_rows > 0): ?>
                                <?php while ($row = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo date('Y-m-d', strtotime($row['date'])); ?></td>
                                        <td><?php echo safe_echo($row['voucher_number']); ?></td>
                                        <td><?php echo safe_echo($row['head_name']); ?></td>
                                        <td><?php echo safe_echo($row['category_name']); ?></td>
                                        <td><?php echo safe_echo($row['subcategory_name']); ?></td>
                                        <td>
                                            <span class="badge <?php echo $row['type'] == 'income' ? 'bg-success' : 'bg-danger'; ?>">
                                                <?php echo safe_echo(ucfirst($row['type'])); ?>
                                            </span>
                                        </td>
                                        <td><?php echo number_format($row['amount'], 2); ?></td>
                                        <td><?php echo safe_echo($row['description']); ?></td>
                                        <td><?php echo safe_echo($row['added_by']); ?></td>
                                        <?php if ($is_super_admin): ?>
                                            <td><?php echo safe_echo($row['cost_center_name']); ?></td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="<?php echo $is_super_admin ? '10' : '9'; ?>" class="text-center">
                                        No transactions found
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>