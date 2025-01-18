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

    private function getIncomeData() {
        $query = "SELECT 
            ac.name as category_name,
            SUM(CASE 
                WHEN t.type IN ('income', 'liability', 'equity') THEN l.credit - l.debit
                ELSE 0 
            END) as balance
            FROM transactions t
            JOIN ledgers l ON t.id = l.transaction_id
            JOIN account_categories ac ON t.category_id = ac.id
            WHERE t.type IN ('income', 'liability', 'equity')
            AND t.date BETWEEN ? AND ?
            GROUP BY ac.name
            ORDER BY ac.name";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("ss", $this->from_date, $this->to_date);
        $stmt->execute();
        return $stmt->get_result();
    }

    private function getExpenseData() {
        $query = "SELECT 
            ac.name as category_name,
            SUM(CASE 
                WHEN t.type IN ('expense', 'asset') THEN l.debit - l.credit
                ELSE 0 
            END) as balance
            FROM transactions t
            JOIN ledgers l ON t.id = l.transaction_id
            JOIN account_categories ac ON t.category_id = ac.id
            WHERE t.type IN ('expense', 'asset')
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
            $income_data = $this->getIncomeData();
            $expense_data = $this->getExpenseData();
            
            return [
                'income' => $income_data,
                'expenses' => $expense_data,
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
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
        
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f3f4f6;
        }

        .statement-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .amount {
            font-family: 'Monaco', monospace;
        }

        @media print {
            .no-print { display: none; }
            body { background-color: white; }
            .statement-card {
                box-shadow: none;
                border: 1px solid #e5e7eb;
            }
        }

        .hover-row:hover {
            background-color: #f9fafb;
        }

        .animate-fade-in {
            animation: fadeIn 0.3s ease-in;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .back-button {
            transition: transform 0.2s ease;
        }

        .back-button:hover {
            transform: translateX(-3px);
        }
    </style>
</head>
<body class="min-h-screen py-8">
    <div class="container mx-auto px-4 max-w-5xl">
        <!-- Back Button -->
        <div class="mb-6 no-print">
            <a href="financial_reports.php" class="inline-flex items-center text-gray-700 hover:text-gray-900 back-button">
                <i class="fas fa-arrow-left mr-2"></i>
                <span class="font-medium">Back to Reports</span>
            </a>
        </div>

        <!-- Header -->
        <div class="text-center mb-8 animate-fade-in">
            <h1 class="text-3xl font-bold text-gray-800 mb-2">Income Statement</h1>
            <p class="text-gray-600">
                <?php echo date('F d, Y', strtotime($data['from_date'])); ?> - 
                <?php echo date('F d, Y', strtotime($data['to_date'])); ?>
            </p>
        </div>

        <!-- Filter Form -->
        <div class="statement-card p-6 mb-6 no-print animate-fade-in">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">From Date</label>
                    <input type="date" name="from_date" 
                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                        value="<?php echo $data['from_date']; ?>">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">To Date</label>
                    <input type="date" name="to_date" 
                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                        value="<?php echo $data['to_date']; ?>">
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="flex-1 bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                        <i class="fas fa-filter mr-2"></i>Apply Filter
                    </button>
                    <button type="button" onclick="window.print()" 
                        class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition-colors">
                        <i class="fas fa-print mr-2"></i>Print
                    </button>
                </div>
            </form>
        </div>

        <!-- Statement Content -->
        <div class="statement-card p-6 animate-fade-in">
            <!-- Income Section -->
            <div class="mb-8">
                <h2 class="text-xl font-semibold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-arrow-down text-green-500 mr-2"></i>Income, Liabilities & Equity 
                </h2>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr>
                                <th>Particulars</th>
                                <th class="text-end">Amount (PKR)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Income Section -->
                            <tr class="table-light">
                                <td colspan="2"><strong>Income, Liabilities & Equity</strong></td>
                            </tr>
                            <?php 
                            $total_income = 0;
                            while ($row = $data['income']->fetch_assoc()): 
                                if ($row['balance'] != 0) {
                                    $total_income += $row['balance'];
                            ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['category_name']); ?></td>
                                    <td class="text-end">
                                        <?php echo number_format(abs($row['balance']), 2); ?>
                                        <?php echo ($row['balance'] < 0) ? ' debit' : ' credit'; ?>
                                    </td>
                                </tr>
                            <?php 
                                }
                            endwhile; 
                            ?>
                            <tr class="fw-bold">
                                <td>Total Income</td>
                                <td class="text-end">
                                    <?php echo number_format(abs($total_income), 2); ?>
                                    <?php echo ($total_income < 0) ? ' debit' : ' credit'; ?>
                                </td>
                            </tr>

                            <!-- Expenses Section -->
                            <tr class="table-light">
                                <td colspan="2"><strong>Expenses & Assets</strong></td>
                            </tr>
                            <?php 
                            $total_expenses = 0;
                            while ($row = $data['expenses']->fetch_assoc()): 
                                if ($row['balance'] != 0) {
                                    $total_expenses += $row['balance'];
                            ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['category_name']); ?></td>
                                    <td class="text-end">
                                        <?php echo number_format(abs($row['balance']), 2); ?>
                                        <?php echo ($row['balance'] < 0) ? ' credit' : ' debit'; ?>
                                    </td>
                                </tr>
                            <?php 
                                }
                            endwhile; 
                            ?>
                            <tr class="fw-bold">
                                <td>Total Expenses</td>
                                <td class="text-end">
                                    <?php echo number_format(abs($total_expenses), 2); ?>
                                    <?php echo ($total_expenses < 0) ? ' credit' : ' debit'; ?>
                                </td>
                            </tr>

                            <!-- Net Profit/Loss -->
                            <tr class="table-success fw-bold">
                                <td>Net Profit</td>
                                <td class="text-end">
                                    <?php 
                                    $net_profit = $total_income - $total_expenses;
                                    echo number_format(abs($net_profit), 2);
                                    echo ($net_profit < 0) ? ' debit' : ' credit';
                                    ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Add subtle animation when switching between date ranges
        const form = document.querySelector('form');
        form.addEventListener('submit', function() {
            document.querySelectorAll('.animate-fade-in').forEach(el => {
                el.style.opacity = 0;
            });
        });

        // Format numbers with commas as they're typed
        document.querySelectorAll('.amount').forEach(el => {
            const value = parseFloat(el.textContent.replace(/,/g, ''));
            el.textContent = value.toLocaleString('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        });
    });
    </script>
</body>
</html>