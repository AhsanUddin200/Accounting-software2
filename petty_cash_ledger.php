<?php
require_once 'session.php';
require_once 'db.php';

// Check admin access
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Get date range filters
$from_date = $_GET['from_date'] ?? date('Y-m-01'); // First day of current month
$to_date = $_GET['to_date'] ?? date('Y-m-t');      // Last day of current month

// Query to get petty cash transactions
$query = "
    SELECT 
        t.date,
        t.voucher_number,
        t.description,
        l.debit,
        l.credit,
        ah.name as head_name,
        ac.name as category_name,
        u.username as added_by
    FROM transactions t
    JOIN ledgers l ON t.id = l.transaction_id
    JOIN accounting_heads ah ON t.head_id = ah.id
    JOIN account_categories ac ON t.category_id = ac.id
    JOIN users u ON t.user_id = u.id
    WHERE (ah.name LIKE '%petty cash%' OR ac.name LIKE '%petty cash%')
    AND t.date BETWEEN ? AND ?
    ORDER BY t.date ASC, t.created_at ASC";

$stmt = $conn->prepare($query);
$stmt->bind_param("ss", $from_date, $to_date);
$stmt->execute();
$result = $stmt->get_result();

// Calculate opening balance
$opening_balance_query = "
    SELECT 
        COALESCE(SUM(l.debit), 0) as total_debit,
        COALESCE(SUM(l.credit), 0) as total_credit
    FROM transactions t
    JOIN ledgers l ON t.id = l.transaction_id
    JOIN accounting_heads ah ON t.head_id = ah.id
    JOIN account_categories ac ON t.category_id = ac.id
    WHERE (ah.name LIKE '%petty cash%' OR ac.name LIKE '%petty cash%')
    AND t.date < ?";

$stmt = $conn->prepare($opening_balance_query);
$stmt->bind_param("s", $from_date);
$stmt->execute();
$opening_balance_result = $stmt->get_result()->fetch_assoc();
$opening_balance = $opening_balance_result['total_debit'] - $opening_balance_result['total_credit'];
?>

<!DOCTYPE html>
<html>
<head>
    <title>Petty Cash Ledger</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .running-balance-positive { color: #28a745; }
        .running-balance-negative { color: #dc3545; }
        .table th { background-color: #f8f9fa; }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <div class="row mb-4">
            <div class="col-md-6">
                <h2><i class="fas fa-money-bill-wave me-2"></i>Petty Cash Ledger</h2>
            </div>
            <div class="col-md-6 text-end">
                <a href="admin_dashboard.php" class="btn btn-secondary me-2">
                    <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                </a>
                <button onclick="window.print()" class="btn btn-secondary">
                    <i class="fas fa-print me-2"></i>Print Ledger
                </button>
            </div>
        </div>

        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">From Date</label>
                        <input type="date" name="from_date" class="form-control" 
                               value="<?php echo $from_date; ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">To Date</label>
                        <input type="date" name="to_date" class="form-control" 
                               value="<?php echo $to_date; ?>">
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary me-2">Apply Filter</button>
                        <a href="petty_cash_ledger.php" class="btn btn-secondary">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Ledger Table -->
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Voucher No.</th>
                                <th>Description</th>
                                <th>Category</th>
                                <th class="text-end">Debit (PKR)</th>
                                <th class="text-end">Credit (PKR)</th>
                                <th class="text-end">Balance (PKR)</th>
                                <th>Added By</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Opening Balance Row -->
                            <tr class="table-light">
                                <td colspan="4"><strong>Opening Balance</strong></td>
                                <td class="text-end">-</td>
                                <td class="text-end">-</td>
                                <td class="text-end">
                                    <strong><?php echo number_format($opening_balance, 2); ?></strong>
                                </td>
                                <td>-</td>
                                <td>-</td>
                            </tr>

                            <?php 
                            $running_balance = $opening_balance;
                            while ($row = $result->fetch_assoc()):
                                $running_balance += ($row['debit'] - $row['credit']);
                            ?>
                            <tr>
                                <td><?php echo date('d M Y', strtotime($row['date'])); ?></td>
                                <td>
                                    <a href="generate_voucher.php?voucher_number=<?php echo urlencode($row['voucher_number']); ?>" 
                                       class="btn btn-sm btn-link" target="_blank">
                                        <?php echo htmlspecialchars($row['voucher_number']); ?>
                                    </a>
                                </td>
                                <td><?php echo htmlspecialchars($row['description']); ?></td>
                                <td><?php echo htmlspecialchars($row['category_name']); ?></td>
                                <td class="text-end">
                                    <?php echo $row['debit'] > 0 ? number_format($row['debit'], 2) : '-'; ?>
                                </td>
                                <td class="text-end">
                                    <?php echo $row['credit'] > 0 ? number_format($row['credit'], 2) : '-'; ?>
                                </td>
                                <td class="text-end <?php echo $running_balance >= 0 ? 'running-balance-positive' : 'running-balance-negative'; ?>">
                                    <?php echo number_format($running_balance, 2); ?>
                                </td>
                                <td><?php echo htmlspecialchars($row['added_by']); ?></td>
                                <td>
                                    <a href="generate_voucher.php?voucher_number=<?php echo urlencode($row['voucher_number']); ?>" 
                                       class="btn btn-sm btn-primary" target="_blank">
                                        <i class="fas fa-file-alt"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 