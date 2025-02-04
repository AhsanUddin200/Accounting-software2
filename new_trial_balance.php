<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include session and database connection
require_once 'session.php';
require_once 'db.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Get date range from URL parameters or set defaults
$from_date = $_GET['from_date'] ?? date('Y-m-01'); // First day of current month
$to_date = $_GET['to_date'] ?? date('Y-m-t');      // Last day of current month

// Update the query to get last closing balance for each account
$query = "WITH ClosingBalances AS (
    SELECT 
        ah.name as head_name,
        ac.name as category_name,
        l.date,
        -- Calculate running balance for each entry
        SUM(l.debit - l.credit) OVER (
            PARTITION BY ac.id 
            ORDER BY l.date ASC, l.id ASC
        ) as running_balance,
        -- Get row number to identify latest entry
        ROW_NUMBER() OVER (
            PARTITION BY ac.id 
            ORDER BY l.date DESC, l.id DESC
        ) as rn
    FROM ledgers l
    JOIN transactions t ON l.transaction_id = t.id
    JOIN accounting_heads ah ON t.head_id = ah.id
    JOIN account_categories ac ON t.category_id = ac.id
    WHERE l.date <= ?
)
SELECT 
    head_name,
    category_name,
    CASE 
        WHEN running_balance > 0 THEN ABS(running_balance)
        ELSE 0 
    END as debit,
    CASE 
        WHEN running_balance < 0 THEN ABS(running_balance)
        ELSE 0 
    END as credit
FROM ClosingBalances 
WHERE rn = 1
    AND running_balance != 0
ORDER BY head_name, category_name";

$stmt = $conn->prepare($query);
$stmt->bind_param("s", $to_date);
$stmt->execute();
$result = $stmt->get_result();

// Initialize arrays to store results
$trial_balance = [];
$total_debit = 0;
$total_credit = 0;

// Process results
while ($row = $result->fetch_assoc()) {
    $head_name = $row['head_name'];
    if (!isset($trial_balance[$head_name])) {
        $trial_balance[$head_name] = [];
    }
    $trial_balance[$head_name][] = $row;
    $total_debit += $row['debit'];
    $total_credit += $row['credit'];
}

// Check if debits and credits are balanced
$is_balanced = abs($total_debit - $total_credit) < 0.01; // Using 0.01 to handle floating point precision
$difference = abs($total_debit - $total_credit);

// Calculate net balance
$net_balance = $total_debit - $total_credit;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trial Balance</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .nav-bar {
            background-color: #0052cc;
            padding: 10px 20px;
        }
        .back-btn {
            color: white;
            text-decoration: none;
            border: 1px solid rgba(255, 255, 255, 0.5);
            padding: 6px 15px;
            border-radius: 4px;
            display: inline-block;
            margin-right: 10px;
        }
        .back-btn:last-child {
            margin-right: 0;
        }
        .back-btn:hover {
            color: white;
            background: rgba(255, 255, 255, 0.1);
            text-decoration: none;
        }
        .print-btn {
            background: white;
            border: none;
            padding: 6px 15px;
            border-radius: 4px;
        }
        .main-content {
            padding: 20px;
        }
        .page-title {
            font-size: 24px;
            margin-bottom: 20px;
            color: #333;
        }
        .filter-section {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .table-section {
            background: white;
            border-radius: 4px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .table {
            margin-bottom: 0;
        }
        .head-row {
            background: #f8f9fa;
        }
        .amount-column {
            text-align: right;
            width: 180px;
        }
        .total-row {
            font-weight: bold;
            background: #f8f9fa;
        }
        @media print {
            .no-print {
                display: none;
            }
            .table-section {
                box-shadow: none;
            }
        }
        .balance-alert {
            margin: 20px 0;
            padding: 15px;
            border-radius: 4px;
        }
        .balance-alert.balanced {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        .balance-alert.unbalanced {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <div class="nav-bar no-print d-flex justify-content-between align-items-center">
        <div>
            <a href="admin_dashboard.php" class="back-btn">
                <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
            </a>
            <a href="reports.php" class="back-btn">
                <i class="fas fa-file-alt me-2"></i>Back to Reports
            </a>
        </div>
        <div>
            <button onclick="exportToCSV()" class="print-btn me-2">
                <i class="fas fa-file-csv me-2"></i>Export CSV
            </button>
            <button onclick="window.print()" class="print-btn">
                <i class="fas fa-print me-2"></i>Print
            </button>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <h1 class="page-title">Trial Balance</h1>
        
        <!-- Balance Status Alert -->
        <div class="balance-alert <?php echo $is_balanced ? 'balanced' : 'unbalanced'; ?>">
            <?php if ($is_balanced): ?>
                <i class="fas fa-check-circle me-2"></i>Trial Balance is properly balanced. Total Debits and Credits: PKR <?php echo number_format($total_debit, 2); ?>
            <?php else: ?>
                <i class="fas fa-exclamation-triangle me-2"></i>Warning: Trial Balance is not balanced!<br>
                Total Debits: PKR <?php echo number_format($total_debit, 2); ?><br>
                Total Credits: PKR <?php echo number_format($total_credit, 2); ?><br>
                Net Difference: PKR <?php echo number_format($net_balance, 2); ?>
            <?php endif; ?>
        </div>

        <!-- Filter Section -->
        <div class="filter-section no-print">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">From Date:</label>
                    <input type="date" name="from_date" class="form-control" 
                           value="<?php echo htmlspecialchars($from_date); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">To Date:</label>
                    <input type="date" name="to_date" class="form-control" 
                           value="<?php echo htmlspecialchars($to_date); ?>">
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary">Apply Filter</button>
                </div>
            </form>
        </div>

        <!-- Table Section -->
        <div class="table-section">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Account Head / Category</th>
                        <th class="amount-column">Debit (PKR)</th>
                        <th class="amount-column">Credit (PKR)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($trial_balance as $head_name => $categories): ?>
                        <tr class="head-row">
                            <td colspan="3"><strong><?php echo htmlspecialchars($head_name); ?></strong></td>
                        </tr>
                        <?php foreach ($categories as $row): ?>
                            <tr>
                                <td class="ps-4">
                                    <?php echo htmlspecialchars($row['category_name']); ?>
                                </td>
                                <td class="amount-column">
                                    <?php echo $row['debit'] > 0 ? number_format($row['debit'], 2) : '-'; ?>
                                </td>
                                <td class="amount-column">
                                    <?php echo $row['credit'] > 0 ? number_format($row['credit'], 2) : '-'; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="total-row">
                        <td>Total</td>
                        <td class="amount-column"><?php echo number_format($total_debit, 2); ?></td>
                        <td class="amount-column"><?php echo number_format($total_credit, 2); ?></td>
                    </tr>
                    <?php if (!$is_balanced): ?>
                    <tr class="total-row text-danger">
                        <td>Net Difference</td>
                        <td colspan="2" class="text-center">PKR <?php echo number_format($net_balance, 2); ?></td>
                    </tr>
                    <?php endif; ?>
                </tfoot>
            </table>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function exportToCSV() {
        window.location.href = `export_trial_balance.php?from_date=<?php echo $from_date; ?>&to_date=<?php echo $to_date; ?>`;
    }
    </script>
</body>
</html>