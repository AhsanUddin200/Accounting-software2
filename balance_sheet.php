<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include session and database connection
require_once 'session.php';
require_once 'db.php';
require_once 'functions.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Get date from URL parameters or set default to current date
$as_of_date = $_GET['as_of_date'] ?? date('Y-m-d');

// Query to get assets
$assets_query = "
    SELECT 
        ah.name as head_name,
        ac.name as category_name,
        SUM(l.debit) - SUM(l.credit) as balance
    FROM ledgers l
    JOIN transactions t ON l.transaction_id = t.id
    JOIN accounting_heads ah ON t.head_id = ah.id
    JOIN account_categories ac ON t.category_id = ac.id
    WHERE t.type = 'asset' 
    AND t.date <= ?
    GROUP BY ah.name, ac.name
    HAVING balance != 0
    ORDER BY ah.name, ac.name";

// Query to get liabilities
$liabilities_query = "
    SELECT 
        ah.name as head_name,
        ac.name as category_name,
        SUM(l.credit) - SUM(l.debit) as balance
    FROM ledgers l
    JOIN transactions t ON l.transaction_id = t.id
    JOIN accounting_heads ah ON t.head_id = ah.id
    JOIN account_categories ac ON t.category_id = ac.id
    WHERE t.type = 'liability'
    AND t.date <= ?
    GROUP BY ah.name, ac.name
    HAVING balance != 0
    ORDER BY ah.name, ac.name";

// Query to get equity
$equity_query = "
    SELECT 
        ah.name as head_name,
        ac.name as category_name,
        SUM(l.credit) - SUM(l.debit) as balance
    FROM ledgers l
    JOIN transactions t ON l.transaction_id = t.id
    JOIN accounting_heads ah ON t.head_id = ah.id
    JOIN account_categories ac ON t.category_id = ac.id
    WHERE t.type = 'equity'
    AND t.date <= ?
    GROUP BY ah.name, ac.name
    HAVING balance != 0
    ORDER BY ah.name, ac.name";

// Prepare and execute queries
$stmt = $conn->prepare($assets_query);
$stmt->bind_param("s", $as_of_date);
$stmt->execute();
$assets = $stmt->get_result();

$stmt = $conn->prepare($liabilities_query);
$stmt->bind_param("s", $as_of_date);
$stmt->execute();
$liabilities = $stmt->get_result();

$stmt = $conn->prepare($equity_query);
$stmt->bind_param("s", $as_of_date);
$stmt->execute();
$equity = $stmt->get_result();

// Calculate totals
$total_assets = 0;
$total_liabilities = 0;
$total_equity = 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Balance Sheet | Financial Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f5f6fa;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        }

        .nav-bar {
            background: linear-gradient(135deg, #1e40af, #3b82f6);
            padding: 1rem;
            margin-bottom: 2rem;
        }

        .back-btn {
            color: white;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            border: 1px solid rgba(255, 255, 255, 0.3);
            transition: all 0.3s ease;
        }

        .back-btn:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white;
        }

        .print-btn {
            background: white;
            color: #1e40af;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            transition: all 0.3s ease;
        }

        .print-btn:hover {
            background: #f8f9fa;
            transform: translateY(-1px);
        }

        .main-content {
            padding: 2rem;
        }

        .statement-card {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .card-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e5e7eb;
        }

        .table {
            margin-bottom: 0;
        }

        .table th {
            background-color: #f8f9fa;
            font-weight: 600;
            padding: 1rem;
        }

        .table td {
            padding: 1rem;
        }

        .amount-column {
            text-align: right;
            width: 200px;
        }

        .total-row {
            font-weight: bold;
            background-color: #f8f9fa;
        }

        .section-total {
            font-weight: bold;
            background-color: #e9ecef;
        }

        @media print {
            .no-print {
                display: none;
            }
            body {
                background: white;
            }
            .statement-card {
                box-shadow: none;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <div class="nav-bar no-print d-flex justify-content-between align-items-center">
        <a href="admin_dashboard.php" class="back-btn">
            <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
        </a>
        <button onclick="window.print()" class="print-btn">
            <i class="fas fa-print me-2"></i>Print
        </button>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="statement-card">
            <div class="card-header">
                <h2 class="mb-0">Balance Sheet</h2>
                <p class="text-muted mb-0">As of <?php echo date('d F Y', strtotime($as_of_date)); ?></p>
            </div>

            <!-- Filter Section -->
            <div class="p-3 bg-light border-bottom no-print">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">As of Date:</label>
                        <input type="date" name="as_of_date" class="form-control" 
                               value="<?php echo htmlspecialchars($as_of_date); ?>">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary">Apply</button>
                    </div>
                </form>
            </div>

            <div class="table-responsive">
                <table class="table table-bordered">
                    <!-- Assets Section -->
                    <thead>
                        <tr>
                            <th colspan="2" class="bg-primary text-white">ASSETS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $current_head = '';
                        while ($row = $assets->fetch_assoc()):
                            $total_assets += $row['balance'];
                            if ($current_head != $row['head_name']):
                                $current_head = $row['head_name'];
                        ?>
                            <tr>
                                <td colspan="2" class="fw-bold"><?php echo htmlspecialchars($row['head_name']); ?></td>
                            </tr>
                        <?php endif; ?>
                            <tr>
                                <td class="ps-4"><?php echo htmlspecialchars($row['category_name']); ?></td>
                                <td class="amount-column">
                                    <?php echo number_format(abs($row['balance']), 2); ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                        <tr class="section-total">
                            <td>Total Assets</td>
                            <td class="amount-column"><?php echo number_format($total_assets, 2); ?></td>
                        </tr>
                    </tbody>

                    <!-- Liabilities Section -->
                    <thead>
                        <tr>
                            <th colspan="2" class="bg-primary text-white">LIABILITIES</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $current_head = '';
                        while ($row = $liabilities->fetch_assoc()):
                            $total_liabilities += $row['balance'];
                            if ($current_head != $row['head_name']):
                                $current_head = $row['head_name'];
                        ?>
                            <tr>
                                <td colspan="2" class="fw-bold"><?php echo htmlspecialchars($row['head_name']); ?></td>
                            </tr>
                        <?php endif; ?>
                            <tr>
                                <td class="ps-4"><?php echo htmlspecialchars($row['category_name']); ?></td>
                                <td class="amount-column">
                                    <?php echo number_format(abs($row['balance']), 2); ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                        <tr class="section-total">
                            <td>Total Liabilities</td>
                            <td class="amount-column"><?php echo number_format($total_liabilities, 2); ?></td>
                        </tr>
                    </tbody>

                    <!-- Equity Section -->
                    <thead>
                        <tr>
                            <th colspan="2" class="bg-primary text-white">EQUITY</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $current_head = '';
                        while ($row = $equity->fetch_assoc()):
                            $total_equity += $row['balance'];
                            if ($current_head != $row['head_name']):
                                $current_head = $row['head_name'];
                        ?>
                            <tr>
                                <td colspan="2" class="fw-bold"><?php echo htmlspecialchars($row['head_name']); ?></td>
                            </tr>
                        <?php endif; ?>
                            <tr>
                                <td class="ps-4"><?php echo htmlspecialchars($row['category_name']); ?></td>
                                <td class="amount-column">
                                    <?php echo number_format(abs($row['balance']), 2); ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                        <tr class="section-total">
                            <td>Total Equity</td>
                            <td class="amount-column"><?php echo number_format($total_equity, 2); ?></td>
                        </tr>
                    </tbody>

                    <!-- Final Total -->
                    <tfoot>
                        <tr class="total-row bg-light">
                            <td class="fw-bold text-primary">Total Liabilities and Equity</td>
                            <td class="amount-column fw-bold text-primary">
                                <?php 
                                $total_liab_equity = $total_liabilities + $total_equity;
                                echo number_format($total_liab_equity, 2); 
                                ?>
                            </td>
                        </tr>
                        <tr class="total-row">
                            <td colspan="2" class="text-center bg-light">
                                <div class="my-3">
                                    <h5 class="mb-3">Balance Sheet Summary</h5>
                                    <div class="row justify-content-center">
                                        <div class="col-md-4 text-center">
                                            <div class="card shadow-sm">
                                                <div class="card-body">
                                                    <h6 class="text-primary mb-2">Total Assets</h6>
                                                    <h5><?php echo number_format($total_assets, 2); ?></h5>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4 text-center">
                                            <div class="card shadow-sm">
                                                <div class="card-body">
                                                    <h6 class="text-primary mb-2">Total Liabilities & Equity</h6>
                                                    <h5><?php echo number_format($total_liab_equity, 2); ?></h5>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mt-3 p-3 <?php echo ($total_assets == $total_liab_equity) ? 'bg-success' : 'bg-warning'; ?> text-white rounded">
                                        <strong>Difference:</strong> 
                                        <?php 
                                        $difference = $total_assets - $total_liab_equity;
                                        echo number_format(abs($difference), 2); 
                                        if ($difference != 0) {
                                            echo $difference > 0 ? ' (Excess Assets)' : ' (Deficit)';
                                        } else {
                                            echo ' (Balanced)';
                                        }
                                        ?>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
