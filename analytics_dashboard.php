<?php
session_start();
require_once 'db.php';

// Add error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Get monthly trends
$monthly_query = "
    SELECT 
        DATE_FORMAT(date, '%Y-%m') as month,
        SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as income,
        SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as expense,
        SUM(CASE WHEN type = 'asset' THEN amount ELSE 0 END) as asset,
        SUM(CASE WHEN type = 'liability' THEN amount ELSE 0 END) as liability,
        SUM(CASE WHEN type = 'equity' THEN amount ELSE 0 END) as equity
    FROM transactions 
    WHERE date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(date, '%Y-%m')
    ORDER BY month ASC";

$result = mysqli_query($conn, $monthly_query);
if (!$result) {
    die("Error in monthly query: " . mysqli_error($conn));
}
$monthly_data = [];
while ($row = mysqli_fetch_assoc($result)) {
    $monthly_data[] = $row;
}

// Get transaction summary by type
$type_query = "
    SELECT 
        type,
        COUNT(*) as transaction_count,
        SUM(amount) as total_amount,
        AVG(amount) as avg_amount
    FROM transactions
    GROUP BY type
    ORDER BY total_amount DESC";

$result = mysqli_query($conn, $type_query);
if (!$result) {
    die("Error in type query: " . mysqli_error($conn));
}
$type_data = [];
while ($row = mysqli_fetch_assoc($result)) {
    $type_data[] = $row;
}

// Get summary statistics
$summary_query = "
    SELECT 
        COALESCE(COUNT(*), 0) as total_transactions,
        COALESCE(AVG(amount), 0) as avg_amount,
        COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END), 0) as total_income,
        COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0) as total_expense
    FROM transactions
    WHERE date >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)";

$result = mysqli_query($conn, $summary_query);
if (!$result) {
    die("Error in summary query: " . mysqli_error($conn));
}
$summary = mysqli_fetch_assoc($result);

// Add this PHP function at the top
function getChartData($period = '6') {
    global $conn;   
    
    $monthly_data_query = "
        SELECT 
            DATE_FORMAT(date, '%Y-%m') as month,
            SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as income,
            SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as expense,
            SUM(CASE WHEN type = 'asset' THEN amount ELSE 0 END) as asset,
            SUM(CASE WHEN type = 'liability' THEN amount ELSE 0 END) as liability,
            SUM(CASE WHEN type = 'equity' THEN amount ELSE 0 END) as equity
        FROM transactions 
        WHERE date >= DATE_SUB(CURRENT_DATE(), INTERVAL ? MONTH)
        GROUP BY DATE_FORMAT(date, '%Y-%m')
        ORDER BY month ASC";
        
    $stmt = mysqli_prepare($conn, $monthly_data_query);
    mysqli_stmt_bind_param($stmt, 's', $period);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $data = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $data[] = $row;
    }
    return $data;
}

// Add these new queries
// Daily transactions for last 30 days
$daily_query = "
    SELECT 
        DATE_FORMAT(date, '%d %b') as day,
        COUNT(*) as transaction_count,
        SUM(amount) as total_amount,
        type
    FROM transactions 
    WHERE date >= DATE_SUB(CURRENT_DATE(), INTERVAL 30 DAY)
    GROUP BY date, type
    ORDER BY date ASC";

$result = mysqli_query($conn, $daily_query);
$daily_data = [];
while ($row = mysqli_fetch_assoc($result)) {
    $daily_data[] = $row;
}

// Category-wise analysis
$category_query = "
    SELECT 
        type,
        SUM(amount) as total_amount,
        COUNT(*) as transaction_count
    FROM transactions 
    WHERE date >= DATE_SUB(CURRENT_DATE(), INTERVAL 30 DAY)
    GROUP BY type
    ORDER BY total_amount DESC";

$result = mysqli_query($conn, $category_query);
$category_data = [];
while ($row = mysqli_fetch_assoc($result)) {
    $category_data[] = $row;
}

// Add this new query after other queries
$balance_query = "
    SELECT 
        DATE_FORMAT(date, '%Y-%m') as month,
        SUM(CASE 
            WHEN type IN ('income', 'asset') THEN amount 
            WHEN type IN ('expense', 'liability') THEN -amount 
            ELSE 0 
        END) as net_balance
    FROM transactions 
    WHERE date >= DATE_SUB(CURRENT_DATE(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(date, '%Y-%m')
    ORDER BY month ASC";

$result = mysqli_query($conn, $balance_query);
$balance_data = [];
while ($row = mysqli_fetch_assoc($result)) {
    $balance_data[] = $row;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Advanced Analytics</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .analytics-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .chart-container {
            height: 400px;
            margin: 20px 0;
        }
        .period-btn {
            padding: 6px 12px;
            border: 1px solid #e2e8f0;
            background: white;
            border-radius: 20px;
            cursor: pointer;
            font-size: 13px;
            color: #64748b;
            transition: all 0.2s ease;
        }
        .period-btn.active {
            background: #4361ee;
            color: white;
            border-color: #4361ee;
        }
        .period-btn:hover:not(.active) {
            background: #f8fafc;
            border-color: #cbd5e1;
        }
        .chart-container {
            margin: 20px 0;
            min-height: 300px;
        }
        .btn-group .btn {
            padding: 8px 20px;
            font-size: 14px;
        }
        .btn-group .btn.active {
            background-color: #4361ee;
            color: white;
            border-color: #4361ee;
        }
        .table td {
            vertical-align: middle;
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <div class="container mt-4">
        <h2>Advanced Analytics Dashboard</h2>

        <!-- Summary Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="analytics-card">
                    <h4>Total Transactions</h4>
                    <p class="h3"><?= number_format((float)$summary['total_transactions']) ?></p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="analytics-card">
                    <h4>Average Amount</h4>
                    <p class="h3">PKR <?= number_format((float)$summary['avg_amount'], 2) ?></p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="analytics-card">
                    <h4>Monthly Income</h4>
                    <p class="h3">PKR <?= number_format((float)$summary['total_income'], 2) ?></p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="analytics-card">
                    <h4>Monthly Expenses</h4>
                    <p class="h3">PKR <?= number_format((float)$summary['total_expense'], 2) ?></p>
                </div>
            </div>
        </div>

        <!-- Charts -->
        <div class="row">
            <div class="col-md-8">
                <div class="analytics-card">
                    <div class="chart-header">
                        <div class="chart-title">Monthly Overview</div>
                        <!-- <div class="chart-period">
                            <button class="period-btn active" data-period="6">6 Months</button>
                            <button class="period-btn" data-period="12">1 Year</button>
                        </div> -->
                    </div>
                    <div class="chart-container">   
                        <canvas id="monthlyChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="analytics-card">
                    <h4>Transaction Types Distribution</h4>
                    <canvas id="typeChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Transaction Types Table -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="analytics-card">
                    <h4>Transaction Types Summary</h4>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Total Amount</th>
                                    <th>Transaction Count</th>
                                    <th>Average Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($type_data as $type): ?>
                                    <tr>
                                        <td><?= ucfirst($type['type']) ?></td>
                                        <td>PKR <?= number_format($type['total_amount'], 2) ?></td>
                                        <td><?= number_format($type['transaction_count']) ?></td>
                                        <td>PKR <?= number_format($type['avg_amount'], 2) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add these new chart containers after existing charts -->
        <div class="row mt-4">
            <div class="col-md-6">
                <div class="analytics-card">
                    <div class="chart-header">
                        <div class="chart-title">Daily Transaction Volume</div>
                        <div class="chart-period">Last 3 Days</div>
                    </div>
                    <div class="chart-container">
                        <canvas id="dailyChart"></canvas>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="analytics-card">
                    <div class="chart-header">
                        <div class="chart-title">Category Performance</div>
                        <div class="chart-period">Last 3 Days</div>
                    </div>
                    <div class="chart-container">
                        <canvas id="categoryChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add this new chart container before the closing div.container -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="analytics-card">
                    <div class="chart-header">
                        <div class="chart-title">Balance Trend</div>
                        <div class="chart-period">Last 6 Months</div>
                    </div>
                    <div class="chart-container">
                        <canvas id="balanceChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    let monthlyChart; // Declare chart variable globally

    function initializeChart(data) {
        if (monthlyChart) {
            monthlyChart.destroy(); // Destroy existing chart if any
        }
        
        monthlyChart = new Chart(document.getElementById('monthlyChart'), {
            type: 'line',
            data: {
                labels: data.map(d => d.month),
                datasets: [
                    {
                        label: 'Income',
                        data: data.map(d => d.income),
                        borderColor: '#2ecc71',
                        backgroundColor: 'rgba(46, 204, 113, 0.1)',
                        borderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        fill: true
                    },
                    {
                        label: 'Expense',
                        data: data.map(d => d.expense),
                        borderColor: '#e74c3c',
                        backgroundColor: 'rgba(231, 76, 60, 0.1)',
                        borderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        fill: true
                    },
                    {
                        label: 'Asset',
                        data: data.map(d => d.asset),
                        borderColor: '#3498db',
                        backgroundColor: 'rgba(52, 152, 219, 0.1)',
                        borderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        fill: true
                    },
                    {
                        label: 'Liability',
                        data: data.map(d => d.liability),
                        borderColor: '#f1c40f',
                        backgroundColor: 'rgba(241, 196, 15, 0.1)',
                        borderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        fill: true
                    },
                    {
                        label: 'Equity',
                        data: data.map(d => d.equity),
                        borderColor: '#9b59b6',
                        backgroundColor: 'rgba(155, 89, 182, 0.1)',
                        borderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        fill: true
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            padding: 20,
                            usePointStyle: true,
                            font: {
                                size: 12
                            }
                        }
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        padding: 10,
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                label += 'PKR ' + new Intl.NumberFormat().format(context.raw);
                                return label;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            drawBorder: false,
                            color: 'rgba(0, 0, 0, 0.05)'
                        },
                        ticks: {
                            callback: function(value) {
                                return 'PKR ' + new Intl.NumberFormat().format(value);
                            }
                        }
                    },
                    x: {
                        grid: {
                            drawBorder: false,
                            display: false
                        }
                    }
                },
                interaction: {
                    intersect: false,
                    mode: 'index'
                }
            }
        });
    }

    // Initialize with 6 months data
    initializeChart(<?= json_encode(getChartData(6)) ?>);

    // Add click handlers for period buttons
    document.querySelectorAll('.period-btn').forEach(button => {
        button.addEventListener('click', function() {
            // Remove active class from all buttons
            document.querySelectorAll('.period-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Add active class to clicked button
            this.classList.add('active');
            
            // Get period from button's data attribute
            const period = this.dataset.period;
            
            // Fetch new data and update chart
            fetch(`get_chart_data.php?period=${period}`)
                .then(response => response.json())
                .then(data => {
                    initializeChart(data);
                });
        });
    });

    // Type Distribution Chart
    const typeData = <?= json_encode($type_data) ?>;
    new Chart(document.getElementById('typeChart'), {
        type: 'pie',
        data: {
            labels: typeData.map(d => d.type),
            datasets: [{
                data: typeData.map(d => d.total_amount),
                backgroundColor: [
                    '#2ecc71', '#e74c3c', '#3498db', 
                    '#f1c40f', '#9b59b6'
                ]
            }]
        },
        options: {
            responsive: true
        }
    });

    // Daily Transactions Chart
    const dailyData = <?= json_encode($daily_data) ?>;
    new Chart(document.getElementById('dailyChart'), {
        type: 'bar',
        data: {
            labels: [...new Set(dailyData.map(d => d.day))],
            datasets: [
                {
                    label: 'Transaction Count',
                    data: dailyData.map(d => d.transaction_count),
                    backgroundColor: 'rgba(52, 152, 219, 0.8)',
                    borderColor: 'rgba(52, 152, 219, 1)',
                    borderWidth: 1,
                    yAxisID: 'y1'
                },
                {
                    label: 'Total Amount',
                    data: dailyData.map(d => d.total_amount),
                    type: 'line',
                    borderColor: '#e74c3c',
                    backgroundColor: 'rgba(231, 76, 60, 0.1)',
                    fill: true,
                    yAxisID: 'y2'
                }
            ]
        },
        options: {
            responsive: true,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            plugins: {
                legend: {
                    position: 'top',
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            if (context.datasetIndex === 0) {
                                return `Transactions: ${context.raw}`;
                            }
                            return `Amount: PKR ${new Intl.NumberFormat().format(context.raw)}`;
                        }
                    }
                }
            },
            scales: {
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    title: {
                        display: true,
                        text: 'Transaction Count'
                    }
                },
                y2: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    title: {
                        display: true,
                        text: 'Amount (PKR)'
                    },
                    grid: {
                        drawOnChartArea: false
                    },
                    ticks: {
                        callback: function(value) {
                            return 'PKR ' + new Intl.NumberFormat().format(value);
                        }
                    }
                }
            }
        }
    });

    // Category Performance Chart
    const categoryData = <?= json_encode($category_data) ?>;
    new Chart(document.getElementById('categoryChart'), {
        type: 'radar',
        data: {
            labels: categoryData.map(d => d.type.toUpperCase()),
            datasets: [{
                label: 'Total Amount',
                data: categoryData.map(d => d.total_amount),
                backgroundColor: 'rgba(46, 204, 113, 0.2)',
                borderColor: '#2ecc71',
                pointBackgroundColor: '#2ecc71',
                pointBorderColor: '#fff',
                pointHoverBackgroundColor: '#fff',
                pointHoverBorderColor: '#2ecc71'
            }, {
                label: 'Transaction Count',
                data: categoryData.map(d => d.transaction_count * 10000), // Scaling for better visualization
                backgroundColor: 'rgba(52, 152, 219, 0.2)',
                borderColor: '#3498db',
                pointBackgroundColor: '#3498db',
                pointBorderColor: '#fff',
                pointHoverBackgroundColor: '#fff',
                pointHoverBorderColor: '#3498db'
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'top',
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            if (context.datasetIndex === 0) {
                                return `Amount: PKR ${new Intl.NumberFormat().format(context.raw)}`;
                            }
                            return `Transactions: ${context.raw / 10000}`; // Reverse scaling
                        }
                    }
                }
            },
            scales: {
                r: {
                    angleLines: {
                        display: true
                    },
                    suggestedMin: 0
                }
            }
        }
    });

    // Balance Trend Chart
    const balanceData = <?= json_encode($balance_data) ?>;
    new Chart(document.getElementById('balanceChart'), {
        type: 'line',
        data: {
            labels: balanceData.map(d => d.month),
            datasets: [{
                label: 'Net Balance',
                data: balanceData.map(d => d.net_balance),
                borderColor: '#8e44ad',
                backgroundColor: 'rgba(142, 68, 173, 0.1)',
                borderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return 'Balance: PKR ' + new Intl.NumberFormat().format(context.raw);
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        drawBorder: false,
                        color: 'rgba(0, 0, 0, 0.05)'
                    },
                    ticks: {
                        callback: function(value) {
                            return 'PKR ' + new Intl.NumberFormat().format(value);
                        }
                    }
                },
                x: {
                    grid: {
                        drawBorder: false,
                        display: false
                    }
                }
            }
        }
    });

    // Add this function at the end of your script section
    function applyFilter() {
        const filterType = document.getElementById('filterType').value;
        const filterPeriod = document.getElementById('filterPeriod').value;
        
        // Update URL with new filter parameters
        window.location.href = `analytics_dashboard.php?filter=${filterType}&period=${filterPeriod}`;
    }

    // Update period text based on filter type
    document.getElementById('filterType').addEventListener('change', function() {
        const periodSelect = document.getElementById('filterPeriod');
        const filterType = this.value;
        const options = periodSelect.options;
        
        for(let option of options) {
            if(filterType === 'daily') {
                option.text = option.text.replace(/Months|Years/g, 'Days');
            } else if(filterType === 'monthly') {
                option.text = option.text.replace(/Days|Years/g, 'Months');
            } else {
                option.text = option.text.replace(/Days|Months/g, 'Years');
            }
        }
    });
    </script>
</body>
</html> 