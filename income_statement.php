<?php
require_once 'session.php';
require_once 'db.php';
require_once 'functions.php';

// Check admin access
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Get filter values
$from_date = $_GET['from_date'] ?? date('Y-m-01');
$to_date = $_GET['to_date'] ?? date('Y-m-d');
$cost_center_id = $_GET['cost_center'] ?? '';

// Query for income
$income_query = "SELECT 
    ah.name as head_name,
    ac.name as category_name,
    cc.code as cost_center_code,
    cc.name as cost_center_name,
    SUM(l.credit) - SUM(l.debit) as amount
    FROM ledgers l
    JOIN transactions t ON l.transaction_id = t.id
    JOIN accounting_heads ah ON t.head_id = ah.id
    JOIN account_categories ac ON t.category_id = ac.id
    LEFT JOIN cost_centers cc ON t.cost_center_id = cc.id
    WHERE t.type = 'income' 
    AND t.date BETWEEN ? AND ?";

// Add cost center filter if selected
if (!empty($cost_center_id)) {
    $income_query .= " AND t.cost_center_id = ?";
}

$income_query .= " GROUP BY ah.name, ac.name, cc.code, cc.name
                   ORDER BY ah.name, ac.name";

// Query for expenses
$expense_query = "SELECT 
    ah.name as head_name,
    ac.name as category_name,
    cc.code as cost_center_code,
    cc.name as cost_center_name,
    SUM(l.debit) - SUM(l.credit) as amount
    FROM ledgers l
    JOIN transactions t ON l.transaction_id = t.id
    JOIN accounting_heads ah ON t.head_id = ah.id
    JOIN account_categories ac ON t.category_id = ac.id
    LEFT JOIN cost_centers cc ON t.cost_center_id = cc.id
    WHERE t.type = 'expense' 
    AND t.date BETWEEN ? AND ?";

// Add cost center filter if selected
if (!empty($cost_center_id)) {
    $expense_query .= " AND t.cost_center_id = ?";
}

$expense_query .= " GROUP BY ah.name, ac.name, cc.code, cc.name
                    ORDER BY ah.name, ac.name";

// Prepare and execute income query
$stmt = $conn->prepare($income_query);
if (!empty($cost_center_id)) {
    $stmt->bind_param("ssi", $from_date, $to_date, $cost_center_id);
} else {
    $stmt->bind_param("ss", $from_date, $to_date);
}
$stmt->execute();
$income_result = $stmt->get_result();

// Prepare and execute expense query
$stmt = $conn->prepare($expense_query);
if (!empty($cost_center_id)) {
    $stmt->bind_param("ssi", $from_date, $to_date, $cost_center_id);
} else {
    $stmt->bind_param("ss", $from_date, $to_date);
}
$stmt->execute();
$expense_result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Income Statement | Financial Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');
        
        :root {
            --primary-color: #3b82f6;
            --secondary-color: #64748b;
            --success-color: #059669;
            --danger-color: #dc2626;
            --background-color: #f8fafc;
            --card-background: #ffffff;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--background-color);
            color: var(--text-primary);
            line-height: 1.6;
        }

        .container {
            width: 100%;
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 1rem;
        }

        .statement-card {
            background: var(--card-background);
            border-radius: 16px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 
                       0 10px 15px -3px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            margin-bottom: 2rem;
            overflow: hidden;
        }

        .statement-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 
                       0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        .card-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e2e8f0;
        }

        .card-body {
            padding: 1.5rem;
        }

        .header-section {
            text-align: center;
            margin-bottom: 3rem;
        }

        .page-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .date-range {
            color: var(--text-secondary);
            font-size: 1.1rem;
        }

        .filter-section {
            background: #f1f5f9;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            align-items: end;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--text-primary);
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            transition: all 0.2s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.2s ease;
            cursor: pointer;
            border: none;
            gap: 0.5rem;
        }

        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background-color: #2563eb;
        }

        .btn-secondary {
            background-color: var(--secondary-color);
            color: white;
        }

        .btn-secondary:hover {
            background-color: #475569;
        }

        .table-section {
            margin-bottom: 2rem;
        }

        .table-responsive {
            overflow-x: auto;
        }

        .table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .table th {
            background-color: #f8fafc;
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: var(--text-primary);
        }

        .table td {
            padding: 1rem;
            border-bottom: 1px solid #e2e8f0;
        }

        .table tr:hover {
            background-color: #f8fafc;
        }

        .amount {
            font-family: 'JetBrains Mono', monospace;
            text-align: right;
        }

        .total-row {
            font-weight: 600;
            background-color: #f8fafc;
        }

        .profit {
            color: var(--success-color);
        }

        .loss {
            color: var(--danger-color);
        }

        .summary-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-top: 2rem;
        }

        .summary-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .summary-title {
            font-size: 1.1rem;
            color: var(--text-secondary);
            margin-bottom: 0.5rem;
        }

        .summary-value {
            font-size: 1.5rem;
            font-weight: 700;
        }

        @media print {
            body {
                background: white;
            }

            .no-print {
                display: none !important;
            }

            .statement-card {
                box-shadow: none;
                margin: 0;
                padding: 0;
            }

            .table {
                page-break-inside: auto;
            }

            tr {
                page-break-inside: avoid;
                page-break-after: auto;
            }

            .summary-section {
                page-break-inside: avoid;
            }
        }

        @media (max-width: 768px) {
            .page-title {
                font-size: 2rem;
            }

            .filter-grid {
                grid-template-columns: 1fr;
            }

            .btn {
                width: 100%;
            }

            .summary-section {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body class="min-h-screen py-12">
    <div class="container">
        <!-- Back Buttons -->
        <div class="mb-8 no-print">
            <a href="admin_dashboard.php" class="btn btn-secondary mr-2">
                <i class="fas fa-arrow-left mr-2"></i>
                Back to Dashboard
            </a>
            <a href="financial_reports.php" class="btn btn-secondary">
                <i class="fas fa-file-alt mr-2"></i>
                Back to Reports
            </a>
        </div>

        <!-- Header Section -->
        <div class="header-section">
            <h1 class="page-title">Income Statement</h1>
            <p class="date-range">
                <?php echo date('F d, Y', strtotime($from_date)); ?> - 
                <?php echo date('F d, Y', strtotime($to_date)); ?>
            </p>
        </div>

        <!-- Filter Section -->
        <div class="statement-card no-print">
            <div class="card-header">
                <h2 class="text-xl font-semibold">Filter Options</h2>
            </div>
            <div class="card-body">
                <form method="GET" class="filter-grid">
                    <div class="form-group">
                        <label class="form-label">From Date</label>
                        <input type="date" name="from_date" 
                            class="form-control"
                            value="<?php echo $from_date; ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">To Date</label>
                        <input type="date" name="to_date" 
                            class="form-control"
                            value="<?php echo $to_date; ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Cost Center</label>
                        <select name="cost_center" class="form-control">
                            <option value="">All Cost Centers</option>
                            <?php
                            $cost_centers_query = "SELECT id, code, name FROM cost_centers ORDER BY code";
                            $cost_centers = $conn->query($cost_centers_query);
                            while ($center = $cost_centers->fetch_assoc()):
                                $selected = (isset($_GET['cost_center']) && $_GET['cost_center'] == $center['id']) ? 'selected' : '';
                            ?>
                                <option value="<?php echo $center['id']; ?>" <?php echo $selected; ?>>
                                    <?php echo htmlspecialchars($center['code'] . ' - ' . $center['name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary w-full mb-2">
                            <i class="fas fa-filter"></i>
                            <span>Apply Filter</span>
                        </button>
                        <button type="button" onclick="exportToCSV()" class="btn btn-secondary w-full mb-2">
                            <i class="fas fa-file-csv"></i>
                            <span>Export CSV</span>
                        </button>
                        <button type="button" onclick="window.print()" class="btn btn-secondary w-full">
                            <i class="fas fa-print"></i>
                            <span>Print Report</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Main Content -->
        <div class="statement-card">
            <!-- Income Section -->
            <div class="table-section">
                <div class="card-header">
                    <h2 class="text-xl font-semibold">Income</h2>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Category</th>
                                    <th>Cost Center</th>
                                    <th class="text-right">Amount (PKR)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $total_income = 0;
                                while ($row = $income_result->fetch_assoc()):
                                    $total_income += $row['amount'];
                                ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['category_name']); ?></td>
                                        <td>
                                            <?php 
                                            if (!empty($row['cost_center_code']) && !empty($row['cost_center_name'])) {
                                                echo htmlspecialchars($row['cost_center_code'] . ' - ' . $row['cost_center_name']);
                                            } else {
                                                echo '-';
                                            }
                                            ?>
                                        </td>
                                        <td class="amount">
                                            <?php echo number_format(abs($row['amount']), 2); ?>
                                            <span class="text-sm ml-1">
                                                <?php echo ($row['amount'] < 0) ? 'Dr' : 'Cr'; ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                                <tr class="total-row">
                                    <td>Total Income</td>
                                    <td></td>
                                    <td class="amount <?php echo $total_income >= 0 ? 'profit' : 'loss'; ?>">
                                        <?php echo number_format(abs($total_income), 2); ?>
                                        <span class="text-sm ml-1">
                                            <?php echo ($total_income < 0) ? 'Dr' : 'Cr'; ?>
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Expense Section -->
            <div class="table-section">
                <div class="card-header">
                    <h2 class="text-xl font-semibold">Expenses</h2>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Category</th>
                                    <th>Cost Center</th>
                                    <th class="text-right">Amount (PKR)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $total_expense = 0;
                                while ($row = $expense_result->fetch_assoc()):
                                    $total_expense += $row['amount'];
                                ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['category_name']); ?></td>
                                        <td>
                                            <?php 
                                            if (!empty($row['cost_center_code']) && !empty($row['cost_center_name'])) {
                                                echo htmlspecialchars($row['cost_center_code'] . ' - ' . $row['cost_center_name']);
                                            } else {
                                                echo '-';
                                            }
                                            ?>
                                        </td>
                                        <td class="amount">
                                            <?php echo number_format(abs($row['amount']), 2); ?>
                                            <span class="text-sm ml-1">
                                                <?php echo ($row['amount'] < 0) ? 'Cr' : 'Dr'; ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                                <tr class="total-row">
                                    <td>Total Expenses</td>
                                    <td></td>
                                    <td class="amount">
                                        <?php echo number_format(abs($total_expense), 2); ?>
                                        <span class="text-sm ml-1">Dr</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Summary Section -->
            <div class="card-body">
                <div class="summary-section">
                    <!-- Net Income -->
                    <div class="summary-card">
                        <h3 class="summary-title">Net Income</h3>
                        <?php $net_income = $total_income - $total_expense; ?>
                        <p class="summary-value <?php echo $net_income >= 0 ? 'profit' : 'loss'; ?>">
                            <?php echo number_format(abs($net_income), 2); ?>
                            <span class="text-sm ml-1">
                                <?php echo ($net_income < 0) ? 'Dr' : 'Cr'; ?>
                            </span>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Format numbers with animations
        document.querySelectorAll('.amount').forEach(el => {
            const value = parseFloat(el.textContent.replace(/,/g, ''));
            el.textContent = value.toLocaleString('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        });

        // Add hover effects for table rows
        document.querySelectorAll('tr').forEach(row => {
            row.addEventListener('mouseenter', function() {
                this.style.backgroundColor = '#f8fafc';
            });
            row.addEventListener('mouseleave', function() {
                this.style.backgroundColor = '';
            });
        });
    });

    function exportToCSV() {
        window.location.href = `export_income_statement.php?from_date=${encodeURIComponent(from_date)}&to_date=${encodeURIComponent(to_date)}&cost_center=${encodeURIComponent(cost_center_id)}`;
    }
    </script>
</body>
</html>