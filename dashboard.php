// dashboard.php
<?php
require 'session.php';
require 'db.php';

// Fetch total income
$income_result = $conn->query("SELECT SUM(amount) as total_income FROM transactions WHERE user_id = {$_SESSION['user_id']} AND type = 'income'");
$income = $income_result->fetch_assoc()['total_income'] ?? 0;

// Fetch total expenses
$expense_result = $conn->query("SELECT SUM(amount) as total_expenses FROM transactions WHERE user_id = {$_SESSION['user_id']} AND type = 'expense'");
$expenses = $expense_result->fetch_assoc()['total_expenses'] ?? 0;

// Calculate balance
$balance = $income - $expenses;

// Fetch recent transactions
$recent_transactions = [];
$result = $conn->query("SELECT * FROM transactions WHERE user_id = {$_SESSION['user_id']} ORDER BY date DESC LIMIT 5");
while ($row = $result->fetch_assoc()) {
    $recent_transactions[] = $row;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Dashboard</title>
    <!-- Include Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* Basic styling */
        .widget { display: inline-block; width: 30%; padding: 20px; margin: 10px; background: #f9f9f9; border-radius: 5px; text-align: center; }
        .chart-container { width: 100%; height: 300px; }
    </style>
</head>
<body>
    <h2>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></h2>
    <div class="widget">
        <h3>Total Income</h3>
        <p>$<?php echo number_format($income, 2); ?></p>
    </div>
    <div class="widget">
        <h3>Total Expenses</h3>
        <p>$<?php echo number_format($expenses, 2); ?></p>
    </div>
    <div class="widget">
        <h3>Net Balance</h3>
        <p>$<?php echo number_format($balance, 2); ?></p>
    </div>

    <div class="chart-container">
        <canvas id="incomeExpenseChart"></canvas>
    </div>

    <script>
        const ctx = document.getElementById('incomeExpenseChart').getContext('2d');
        const incomeExpenseChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Income', 'Expenses'],
                datasets: [{
                    label: 'Amount',
                    data: [<?php echo $income; ?>, <?php echo $expenses; ?>],
                    backgroundColor: [
                        'rgba(75, 192, 192, 0.6)',
                        'rgba(255, 99, 132, 0.6)'
                    ],
                    borderColor: [
                        'rgba(75, 192, 192, 1)',
                        'rgba(255,99,132,1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                scales: { y: { beginAtZero: true } }
            }
        });
    </script>

    <h3>Recent Transactions</h3>
    <table border="1" cellpadding="10">
        <tr>
            <th>Amount</th>
            <th>Type</th>
            <th>Category</th>
            <th>Date</th>
            <th>Description</th>
        </tr>
        <?php foreach ($recent_transactions as $transaction): ?>
            <tr>
                <td><?php echo number_format($transaction['amount'], 2); ?></td>
                <td><?php echo ucfirst($transaction['type']); ?></td>
                <td>
                    <?php
                        // Fetch category name
                        $cat_stmt = $conn->prepare("SELECT name FROM categories WHERE id = ?");
                        $cat_stmt->bind_param("i", $transaction['category_id']);
                        $cat_stmt->execute();
                        $cat_stmt->bind_result($category_name);
                        $cat_stmt->fetch();
                        echo htmlspecialchars($category_name);
                        $cat_stmt->close();
                    ?>
                </td>
                <td><?php echo htmlspecialchars($transaction['date']); ?></td>
                <td><?php echo htmlspecialchars($transaction['description']); ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
</body>
</html>
