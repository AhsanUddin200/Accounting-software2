<?php
require_once 'session.php';
require_once 'db.php';
require_once 'functions.php';

// Get date range from URL parameters or set defaults
$from_date = $_GET['from_date'] ?? date('Y-m-01');
$to_date = $_GET['to_date'] ?? date('Y-m-t');

class IncomeStatement {
    private $conn;
    private $from_date;
    private $to_date;

    public function __construct($conn, $from_date, $to_date) {
        $this->conn = $conn;
        $this->from_date = $from_date;
        $this->to_date = $to_date;
    }

    private function getRevenueData() {
        $query = "SELECT 
            ac.name as category_name,
            SUM(l.debit) as debit,
            SUM(l.credit) as credit
            FROM transactions t
            JOIN ledgers l ON t.id = l.transaction_id
            JOIN account_categories ac ON t.category_id = ac.id
            WHERE t.type = 'income'
            AND t.date BETWEEN ? AND ?
            GROUP BY ac.name
            ORDER BY ac.name";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("ss", $this->from_date, $this->to_date);
        $stmt->execute();
        return $stmt->get_result();
    }

    private function getOperatingExpensesData() {
        $query = "SELECT 
            ac.name as category_name,
            SUM(l.debit) as debit,
            SUM(l.credit) as credit
            FROM transactions t
            JOIN ledgers l ON t.id = l.transaction_id
            JOIN account_categories ac ON t.category_id = ac.id
            WHERE t.type = 'expense' 
            AND ac.name NOT IN ('Interest Expense', 'Depreciation', 'Tax Expense')
            AND t.date BETWEEN ? AND ?
            GROUP BY ac.name
            ORDER BY ac.name";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("ss", $this->from_date, $this->to_date);
        $stmt->execute();
        return $stmt->get_result();
    }

    private function getNonOperatingExpensesData() {
        $query = "SELECT 
            ac.name as category_name,
            SUM(l.debit) as debit,
            SUM(l.credit) as credit
            FROM transactions t
            JOIN ledgers l ON t.id = l.transaction_id
            JOIN account_categories ac ON t.category_id = ac.id
            WHERE t.type = 'expense'
            AND ac.name IN ('Interest Expense', 'Depreciation', 'Tax Expense')
            AND t.date BETWEEN ? AND ?
            GROUP BY ac.name
            ORDER BY ac.name";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("ss", $this->from_date, $this->to_date);
        $stmt->execute();
        return $stmt->get_result();
    }

    public function getData() {
        try {
            return [
                'revenue' => $this->getRevenueData(),
                'operating_expenses' => $this->getOperatingExpensesData(),
                'non_operating_expenses' => $this->getNonOperatingExpensesData(),
                'from_date' => $this->from_date,
                'to_date' => $this->to_date
            ];
        } catch (Exception $e) {
            throw new Exception("Error fetching data: " . $e->getMessage());
        }
    }
}

try {
    $statement = new IncomeStatement($conn, $from_date, $to_date);
    $data = $statement->getData();
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
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
            <a href="reports.php" class="btn btn-secondary">
                <i class="fas fa-file-alt mr-2"></i>
                Back to Reports
            </a>
        </div>

        <!-- Header Section -->
        <div class="header-section">
            <h1 class="page-title">Income Statement</h1>
            <p class="date-range">
                <?php echo date('F d, Y', strtotime($data['from_date'])); ?> - 
                <?php echo date('F d, Y', strtotime($data['to_date'])); ?>
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
                            value="<?php echo $data['from_date']; ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">To Date</label>
                        <input type="date" name="to_date" 
                            class="form-control"
                            value="<?php echo $data['to_date']; ?>">
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
            <!-- Revenue Section -->
            <div class="table-section">
                <div class="card-header">
                    <h2 class="text-xl font-semibold">Revenue</h2>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Category</th>
                                    <th class="text-right">Amount (PKR)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $total_revenue = 0;
                                while ($row = $data['revenue']->fetch_assoc()): 
                                    $balance = $row['credit'] - $row['debit'];
                                    $total_revenue += $balance;
                                ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['category_name']); ?></td>
                                        <td class="amount">
                                            <?php echo number_format(abs($balance), 2); ?>
                                            <span class="text-sm ml-1">
                                                <?php echo ($balance < 0) ? 'Dr' : 'Cr'; ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <tr class="total-row">
                                    <td>Total Revenue</td>
                                    <td class="amount <?php echo $total_revenue >= 0 ? 'profit' : 'loss'; ?>">
                                        <?php echo number_format(abs($total_revenue), 2); ?>
                                        <span class="text-sm ml-1">
                                            <?php echo ($total_revenue < 0) ? 'Dr' : 'Cr'; ?>
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Operating Expenses Section -->
            <div class="table-section">
                <div class="card-header">
                    <h2 class="text-xl font-semibold">Operating Expenses</h2>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Category</th>
                                    <th class="text-right">Amount (PKR)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $total_operating_expenses = 0;
                                while ($row = $data['operating_expenses']->fetch_assoc()): 
                                    $balance = $row['debit'] - $row['credit'];
                                    $total_operating_expenses += $balance;
                                ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['category_name']); ?></td>
                                        <td class="amount">
                                            <?php echo number_format(abs($balance), 2); ?>
                                            <span class="text-sm ml-1">
                                                <?php echo ($balance < 0) ? 'Cr' : 'Dr'; ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                                <tr class="total-row">
                                    <td>Total Operating Expenses</td>
                                    <td class="amount">
                                        <?php echo number_format(abs($total_operating_expenses), 2); ?>
                                        <span class="text-sm ml-1">
                                            <?php echo ($total_operating_expenses < 0) ? 'Cr' : 'Dr'; ?>
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Non-Operating Expenses Section -->
            <div class="table-section">
                <div class="card-header">
                    <h2 class="text-xl font-semibold">Non-Operating Expenses</h2>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Category</th>
                                    <th class="text-right">Amount (PKR)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $total_non_operating_expenses = 0;
                                while ($row = $data['non_operating_expenses']->fetch_assoc()): 
                                    $balance = $row['debit'] - $row['credit'];
                                    $total_non_operating_expenses += $balance;
                                ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['category_name']); ?></td>
                                        <td class="amount">
                                            <?php echo number_format(abs($balance), 2); ?>
                                            <span class="text-sm ml-1">
                                                <?php echo ($balance < 0) ? 'Cr' : 'Dr'; ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                                <tr class="total-row">
                                    <td>Total Non-Operating Expenses</td>
                                    <td class="amount">
                                        <?php echo number_format(abs($total_non_operating_expenses), 2); ?>
                                        <span class="text-sm ml-1">
                                            <?php echo ($total_non_operating_expenses < 0) ? 'Cr' : 'Dr'; ?>
                                        </span>
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
                    <!-- Gross Profit -->
                    <div class="summary-card">
                        <h3 class="summary-title">Gross Profit</h3>
                        <?php $gross_profit = $total_revenue - $total_operating_expenses; ?>
                        <p class="summary-value <?php echo $gross_profit >= 0 ? 'profit' : 'loss'; ?>">
                            <?php echo number_format(abs($gross_profit), 2); ?>
                            <span class="text-sm ml-1">
                                <?php echo ($gross_profit < 0) ? 'Dr' : 'Cr'; ?>
                            </span>
                        </p>
                    </div>

                    <!-- Total Expenses -->
                    <div class="summary-card">
                        <h3 class="summary-title">Total Expenses</h3>
                        <?php $total_expenses = $total_operating_expenses + $total_non_operating_expenses; ?>
                        <p class="summary-value">
                            <?php echo number_format(abs($total_expenses), 2); ?>
                            <span class="text-sm ml-1">Dr</span>
                        </p>
                    </div>

                    <!-- Net Income -->
                    <div class="summary-card">
                        <h3 class="summary-title">Net Income</h3>
                        <?php $net_income = $gross_profit - $total_non_operating_expenses; ?>
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
        window.location.href = `export_income_statement.php?from_date=<?php echo $data['from_date']; ?>&to_date=<?php echo $data['to_date']; ?>`;
    }
    </script>
</body>
</html>