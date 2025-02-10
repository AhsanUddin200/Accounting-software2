<?php
require_once 'session.php';
require_once 'db.php';

// Initialize variables
$transactions = [];
$error = "";
$success = "";

try {
    $query = "SELECT 
        t1.id,
        t1.date,
        t1.voucher_number,
        t1.description,
        t1.amount,
        t1.subcategory_id,
        ac1.name as category_name,
        acs.name as subcategory_name,
        CASE 
            WHEN ac1.name != 'Cash in Hand' THEN t1.amount 
            ELSE NULL 
        END as debit_amount,
        CASE 
            WHEN ac1.name = 'Cash in Hand' THEN t1.amount 
            ELSE NULL 
        END as credit_amount
    FROM transactions t1
    LEFT JOIN account_categories ac1 ON t1.category_id = ac1.id
    LEFT JOIN account_subcategories acs ON t1.subcategory_id = acs.id
    WHERE t1.voucher_number IN (
        SELECT DISTINCT voucher_number 
        FROM transactions 
        WHERE category_id = 23 OR 
        voucher_number IN (
            SELECT voucher_number 
            FROM transactions 
            WHERE category_id = 23
        )
    )
    ORDER BY t1.date DESC, t1.voucher_number";

    $result = $conn->query($query);

    if (!$result) {
        throw new Exception("Database query failed: " . $conn->error);
    }

    while ($row = $result->fetch_assoc()) {
        $transactions[] = $row;
    }

} catch (Exception $e) {
    $error = $e->getMessage();
    error_log($e->getMessage());
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>View Petty Cash Transactions</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3f37c9;
            --success-color: #27AE60;
            --danger-color: #C0392B;
            --warning-color: #f72585;
            --info-color: #4895ef;
            --light-color: #f8f9fa;
            --dark-color: #212529;
        }

        body {
            background-color: #f5f6fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .navbar {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 0.5rem 0;
        }

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .card-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border-radius: 15px 15px 0 0 !important;
            padding: 1rem;
        }

        .table {
            margin-bottom: 0;
        }

        .table th {
            background-color: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
            font-weight: 600;
            color: var(--dark-color);
        }

        .table td {
            vertical-align: middle;
            padding: 0.75rem;
        }

        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
            border-radius: 0.2rem;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .btn-primary:hover {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
            transform: translateY(-1px);
        }

        .text-danger {
            color: var(--danger-color) !important;
        }

        .text-success {
            color: var(--success-color) !important;
        }

        .alert {
            border-radius: 10px;
            border: none;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .table-responsive {
            border-radius: 0 0 15px 15px;
            overflow: hidden;
        }

        .table-hover tbody tr:hover {
            background-color: rgba(67, 97, 238, 0.05);
            transition: background-color 0.3s ease;
        }

        .fa-edit {
            color: white;
        }

        .btn-light {
            background-color: rgba(255, 255, 255, 0.9);
            border: none;
            font-weight: 500;
        }

        .btn-light:hover {
            background-color: white;
            transform: translateY(-1px);
        }

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }
            
            .card-header {
                padding: 0.75rem;
            }
            
            .table th, .table td {
                padding: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container mt-4">
        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-money-bill-wave me-2"></i>Petty Cash Transactions
                </h5>
                <a href="petty_cash_expense.php" class="btn btn-light btn-sm">
                    <i class="fas fa-plus me-1"></i>New Transaction
                </a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Voucher No</th>
                                <th>Description</th>
                                <th>Category</th>
                                <th>Subcategory</th>
                                <th>Debit</th>
                                <th>Credit</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($transactions) > 0): ?>
                                <?php foreach ($transactions as $transaction): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y', strtotime($transaction['date'])); ?></td>
                                        <td><?php echo htmlspecialchars($transaction['voucher_number']); ?></td>
                                        <td><?php echo htmlspecialchars($transaction['description']); ?></td>
                                        <td><?php echo htmlspecialchars($transaction['category_name']); ?></td>
                                        <td><?php echo htmlspecialchars($transaction['subcategory_name'] ?? '-'); ?></td>
                                        <td class="text-danger">
                                            <?php echo $transaction['debit_amount'] ? number_format($transaction['debit_amount'], 2) : '-'; ?>
                                        </td>
                                        <td class="text-success">
                                            <?php echo $transaction['credit_amount'] ? number_format($transaction['credit_amount'], 2) : '-'; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4">
                                        <i class="fas fa-info-circle me-2"></i>No petty cash transactions found
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 	    